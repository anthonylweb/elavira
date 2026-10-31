<?php
/**
 * Explicit scan completion and failure transitions.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Application;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, Generic.Commenting.DocComment.MissingShort, Generic.Files.LineLength -- Compact orchestration mirrors the existing scanner application service.

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Audit\Values\AuditStatus;
use Elavira\Audit\Values\AuditUrlStatus;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Findings\Persistence\ReviewedExceptionRepository;
use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\Rules\Grouping\SupersessionResolver;
use Elavira\Scanner\Persistence\ScanSessionRepository;
use Elavira\Scanner\Persistence\StagedObservationRepository;
use Elavira\Scanner\Security\SessionGuard;
use Elavira\Scanner\Values\ScanSessionState;
use RuntimeException;

/** Owns terminal lifecycle transitions; silence is never treated as success. */
final class CompleteScan {
	/** WordPress database object.
	 *
	 * @var object
	 */
	private $database;
	/** Session repository.
	 *
	 * @var ScanSessionRepository
	 */
	private $sessions;
	/** Session guard.
	 *
	 * @var SessionGuard
	 */
	private $guard;
	/** Audit repository.
	 *
	 * @var AuditRepository
	 */
	private $audits;
	/** Audit URL repository.
	 *
	 * @var AuditUrlRepository
	 */
	private $urls;
	/** Finding repository.
	 *
	 * @var FindingRepository
	 */
	private $findings;
	/** @var OccurrenceRepository */ private $occurrences;
	/** @var ReviewedExceptionRepository|null */ private $exceptions;
	/** @var ReviewedConditionIdentity */ private $identity;
	/** @var SupersessionResolver|null */ private $supersession;
	/** @var StagedObservationRepository */ private $staged;

	/**
	 * Construct completion service.
	 *
	 * @param object                $database WordPress database object.
	 * @param ScanSessionRepository $sessions Session repository.
	 * @param SessionGuard          $guard    Session guard.
	 * @param AuditRepository       $audits   Audit repository.
	 * @param AuditUrlRepository    $urls     Audit URL repository.
	 * @param FindingRepository     $findings Finding repository.
	 */
	public function __construct( $database, ScanSessionRepository $sessions, SessionGuard $guard, AuditRepository $audits, AuditUrlRepository $urls, FindingRepository $findings, ?OccurrenceRepository $occurrences = null, ?ReviewedExceptionRepository $exceptions = null, ?ReviewedConditionIdentity $identity = null, ?SupersessionResolver $supersession = null, ?StagedObservationRepository $staged = null ) {
		$this->database     = $database;
		$this->sessions     = $sessions;
		$this->guard        = $guard;
		$this->audits       = $audits;
		$this->urls         = $urls;
		$this->findings     = $findings;
		$this->occurrences  = $occurrences ? $occurrences : new OccurrenceRepository( $database );
		$this->exceptions   = $exceptions;
		$this->identity     = $identity ? $identity : new ReviewedConditionIdentity();
		$this->supersession = $supersession;
		$this->staged       = $staged ? $staged : new StagedObservationRepository( $database );
	}

	/**
	 * Complete idempotently.
	 *
	 * @param string $token   Credential.
	 * @param int    $user_id User ID.
	 * @return array
	 * @throws RuntimeException When the session state cannot complete.
	 * @throws \Throwable When authorization or persistence fails.
	 */
	public function complete( $token, $user_id ) {
		$session  = $this->guard->authorize( $token, $user_id, null, array( ScanSessionState::CREATED, ScanSessionState::ACTIVE, ScanSessionState::COMPLETED ) );
		$findings = $this->findings->find_by_audit( $session['audit_id'] );
		if ( ScanSessionState::COMPLETED === $session['state'] ) {
			return array(
				'audit_id'      => (int) $session['audit_id'],
				'finding_count' => count( $findings ),
				'duplicate'     => true,
			);
		}

		$now = current_time( 'mysql', true );
		$this->database->query( 'START TRANSACTION' );
		try {
			$session = $this->sessions->lock( $session['id'] );
			if ( ScanSessionState::COMPLETED === $session['state'] ) {
				$this->database->query( 'COMMIT' );
				return array(
					'audit_id'      => (int) $session['audit_id'],
					'finding_count' => count( $findings ),
					'duplicate'     => true,
				);
			}
			if ( ! in_array( $session['state'], array( ScanSessionState::CREATED, ScanSessionState::ACTIVE ), true ) ) {
				throw new RuntimeException( 'This scan session cannot be completed.' );
			}
			$this->sessions->update( $session['id'], array( 'state' => ScanSessionState::COMPLETING ) );
			$normalized = $this->staged->all_for_session( $session['id'] );
			if ( $this->supersession ) {
				$normalized = $this->supersession->resolve( $normalized );
			}
			$identities = array();
			foreach ( $normalized as $result ) {
				$key                = $this->occurrence_key( $result );
				$identities[ $key ] = ( $identities[ $key ] ?? 0 ) + 1;
			}
			foreach ( $normalized as $result ) {
				$this->persist_result( $session, $result, 1 === $identities[ $this->occurrence_key( $result ) ] );
			}
			foreach ( $this->findings->find_by_audit( $session['audit_id'] ) as $finding ) {
				$this->occurrences->reviews()->refresh( $finding['id'] ); }
			$this->urls->update_lifecycle(
				$session['audit_url_id'],
				array(
					'status'       => AuditUrlStatus::COMPLETED,
					'completed_at' => $now,
				)
			);
			$this->audits->update_lifecycle(
				$session['audit_id'],
				array(
					'status'       => AuditStatus::COMPLETED,
					'completed_at' => $now,
				)
			);
			$this->sessions->update( $session['id'], array( 'state' => ScanSessionState::COMPLETED ) );
			$this->staged->cleanup( $session['id'] );
			$this->database->query( 'COMMIT' );
		} catch ( \Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			throw $error;
		}
		do_action( 'elavira_audit_completed', (int) $session['audit_id'] );
		$findings = $this->findings->find_by_audit( $session['audit_id'] );
		return array(
			'audit_id'      => (int) $session['audit_id'],
			'finding_count' => count( $findings ),
			'duplicate'     => false,
		);
	}

	/** Count matching identities before applying any previous decision. @return string */
	private function occurrence_key( array $result ) {
		$identity = $this->identity->fingerprints( $result['target'], $result['evidence'], $result['element'] );
		return $result['rule_id'] . ':' . $result['rule_version'] . ':' . $identity['target'] . ':' . $identity['evidence'];
	}

	/** Persist one whole-page-resolved primary result inside the completion transaction. @return void */
	private function persist_result( array $session, array $result, $unique = true ) {
		if ( empty( $result['creates_finding'] ) ) {
			return;
		}
		$identity  = $this->identity->fingerprints( $result['target'], $result['evidence'], $result['element'] );
		$audit_url = $this->urls->find( $session['audit_url_id'] );
		$matchable = $unique && $this->identity->matchable( $result['target'], $result['element'] );
		$exception = ( $matchable && $this->exceptions && $audit_url ) ? $this->exceptions->match( $result['rule_id'], $result['rule_version'], $audit_url['canonical_url_hash'], $identity['target'], $identity['evidence'] ) : null;
		if ( ! $exception && $matchable && $this->exceptions && $audit_url && 2 === ( $result['target']['identity_version'] ?? 0 ) ) {
			$legacy_target = array_intersect_key( $result['target'], array_flip( array( 'selector', 'tag_name' ) ) );
			$legacy        = $this->identity->fingerprints( $legacy_target, $result['evidence'], $result['element'] );
			$exception     = $this->exceptions->match( $result['rule_id'], $result['rule_version'], $audit_url['canonical_url_hash'], $legacy['target'], $legacy['evidence'] );
		}
		$finding    = $this->findings->find_group( $session['audit_id'], $result['rule_id'], $result['grouping_key'] );
		$finding_id = $finding ? $finding['id'] : $this->findings->create_in_transaction(
			array(
				'audit_id'                    => $session['audit_id'],
				'rule_id'                     => $result['rule_id'],
				'rule_version'                => $result['rule_version'],
				'group_key'                   => $result['grouping_key'],
				'result_type'                 => $result['outcome'],
				'category'                    => $result['classification'],
				'priority'                    => $result['priority'],
				'review_state'                => $result['review_state'],
				'wcag_criterion'              => $result['wcag_criterion'],
				'wcag_level'                  => $result['wcag_level'],
				'wcag_principle'              => $result['wcag_principle'],
				'editorial_version'           => $result['editorial_version'],
				'summary_snapshot'            => $result['editorial']['summary'],
				'affected_page_count'         => 0,
				'occurrence_count'            => 0,
				'active_occurrence_count'     => 0,
				'reviewed_occurrence_count'   => 0,
				'diagnostic_occurrence_count' => 0,
			)
		);
		$previous   = $matchable && $audit_url ? $this->occurrences->previous( $session['audit_id'], $result['rule_id'], $result['rule_version'], $audit_url['canonical_url_hash'], $identity['target'], $identity['evidence'] ) : null;
		$status     = $exception ? 'reviewed_not_issue' : ( $previous && in_array( $previous['current_status'] ?? '', array( 'addressed', 'accepted_limitation', 'needs_review' ), true ) ? $previous['current_status'] : 'open' );
		$this->occurrences->create(
			array(
				'current_status'                => $status,
				'previous_occurrence_id'        => $previous['id'] ?? $exception['source_occurrence_id'] ?? 0,
				'finding_id'                    => $finding_id,
				'audit_url_id'                  => $session['audit_url_id'],
				'target'                        => $result['target'],
				'evidence'                      => $result['evidence'],
				'element'                       => $result['element'],
				'target_fingerprint'            => $identity['target'],
				'material_evidence_fingerprint' => $identity['evidence'],
				'reviewed_exception_id'         => $exception['id'] ?? 0,
				'diagnostics'                   => $result['diagnostics'] ?? array(),
			)
		);
		$this->findings->increment_counts( $finding_id, 1, (bool) $exception, count( $result['diagnostics'] ?? array() ) );
	}

	/**
	 * Mark a safe failure.
	 *
	 * @param string $token   Credential.
	 * @param int    $user_id User ID.
	 * @param string $code    Safe code.
	 * @return void
	 * @throws \Throwable When authorization or persistence fails.
	 */
	public function fail( $token, $user_id, $code ) {
		$session = $this->guard->authorize( $token, $user_id, null, array( ScanSessionState::CREATED, ScanSessionState::ACTIVE ) );
		$allowed = array( 'page_missing', 'scanner_error', 'readiness_timeout', 'transport_error', 'validation_rejected', 'result_limit_exceeded' );
		if ( ! in_array( $code, $allowed, true ) ) {
			$code = 'scanner_error'; }
		$this->database->query( 'START TRANSACTION' );
		try {
			$session = $this->sessions->lock( $session['id'] );
			$this->sessions->update(
				$session['id'],
				array(
					'state'        => ScanSessionState::FAILED,
					'failure_code' => $code,
				)
			);
			$this->urls->update_lifecycle(
				$session['audit_url_id'],
				array(
					'status'          => AuditUrlStatus::FAILED,
					'failure_code'    => $code,
					'failure_message' => 'The browser scan did not complete. Start a new scan and keep the page open.',
				)
			);
			$this->audits->update_lifecycle( $session['audit_id'], array( 'status' => AuditStatus::FAILED ) );
			$this->staged->cleanup( $session['id'] );
			$this->database->query( 'COMMIT' );
		} catch ( \Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			throw $error;
		}
	}
}
