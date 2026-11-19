<?php
/**
 * Finding workflow updates initiated from Reports.
 *
 * @package Elavira
 */

namespace Elavira\Reports\Application;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment, Generic.Commenting.DocComment.MissingShort, Generic.Files.LineLength

use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\FindingStatusHistoryRepository;
use Elavira\Findings\Values\FindingStatus;
use Elavira\Findings\Values\ReviewState;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Findings\Persistence\ReviewedExceptionRepository;
use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\Audit\Persistence\AuditUrlRepository;
use InvalidArgumentException;
use RuntimeException;

/** Applies only Phase 1 user-declared workflow states. */
final class UpdateFindingStatus {
	/** Finding persistence.
	 *
	 * @var FindingRepository
	 */
	private $findings;
	/** Status-history persistence.
	 *
	 * @var FindingStatusHistoryRepository
	 */
	private $history;
	/** @var object|null */ private $database;
	/** @var OccurrenceRepository|null */ private $occurrences;
	/** @var AuditUrlRepository|null */ private $urls;
	/** @var ReviewedExceptionRepository|null */ private $exceptions;
	/** @var ReviewedConditionIdentity */ private $identity;

	/** Create the workflow service.
	 *
	 * @param FindingRepository              $findings Finding persistence.
	 * @param FindingStatusHistoryRepository $history Status-history persistence.
	 */
	public function __construct( FindingRepository $findings, FindingStatusHistoryRepository $history, $database = null, ?OccurrenceRepository $occurrences = null, ?AuditUrlRepository $urls = null, ?ReviewedExceptionRepository $exceptions = null, ?ReviewedConditionIdentity $identity = null ) {
		$this->findings    = $findings;
		$this->history     = $history;
		$this->database    = $database;
		$this->occurrences = $occurrences;
		$this->urls        = $urls;
		$this->exceptions  = $exceptions;
		$this->identity    = $identity ? $identity : new ReviewedConditionIdentity();
	}

	/**
	 * Record an authorized user declaration.
	 *
	 * Verified is audit-owned and cannot be selected manually.
	 *
	 * @param int    $finding_id Finding ID.
	 * @param string $status     Requested status.
	 * @param string $reason     Optional bounded reason.
	 * @param int    $actor_id   Actor user ID.
	 * @return int History ID, or zero when there was no meaningful change.
	 * @throws RuntimeException When the finding is unavailable.
	 * @throws InvalidArgumentException When the requested transition is invalid.
	 */
	public function update( $finding_id, $status, $reason, $actor_id ) {
		if ( FindingStatus::NEEDS_CORRECTION === $status && '' === trim( sanitize_textarea_field( $reason ) ) ) {
			throw new InvalidArgumentException( 'Explain what needs correction.' );
		}
		$finding = $this->findings->find( $finding_id );
		if ( ! $finding ) {
			throw new RuntimeException( 'The finding is not available.' );
		}
		if ( $this->occurrences && count( $this->occurrences->find_by_finding( $finding_id ) ) > 1 && $status !== $finding['current_status'] ) {
			throw new InvalidArgumentException( 'Review each occurrence independently from its report entry.' );
		}
		if ( $this->occurrences && $this->database ) {
			$items = $this->occurrences->find_by_finding( $finding_id );
			if ( 1 === count( $items ) ) {
				return $this->occurrences->reviews()->update( $finding_id, $items[0]['id'], $status, $reason, $actor_id );
			}
		}
		$allowed     = array( FindingStatus::OPEN, FindingStatus::ADDRESSED, FindingStatus::ACCEPTED_LIMITATION, FindingStatus::NEEDS_REVIEW, FindingStatus::NEEDS_CORRECTION, FindingStatus::REVIEWED_NOT_ISSUE );
		$status_same = $status === $finding['current_status'];
		if ( ( ! $status_same && ! in_array( $status, $allowed, true ) ) || ! in_array( $status, FindingStatus::all(), true ) ) {
			throw new InvalidArgumentException( 'That manual finding status is not allowed.' );
		}
		$reason = substr( trim( (string) $reason ), 0, 1000 );
		if ( $status_same ) {
			if ( '' === $reason ) {
				return 0;
			}
			$history_id = $this->record( $finding_id, $status, $reason, $actor_id, false );
			do_action( 'elavira_finding_activity_recorded', (int) $finding['audit_id'], (int) $finding_id, $status );
			return $history_id;
		}
		if ( FindingStatus::REVIEWED_NOT_ISSUE === $status ) {
			if ( ! in_array( $finding['category'], array( 'potential_issue', 'human_review' ), true ) ) {
				throw new InvalidArgumentException( 'Only Potential Issue or Human Review findings can be reviewed as not an issue.' );
			}
			if ( ! $this->database || ! $this->occurrences || ! $this->urls || ! $this->exceptions ) {
				throw new RuntimeException( 'Reviewed-exception persistence is unavailable.' );
			}
			$this->database->query( 'START TRANSACTION' );
			try {
				$created = 0;
				foreach ( $this->occurrences->find_by_finding( $finding_id ) as $occurrence ) {
					$url = $this->urls->find( $occurrence['audit_url_id'] );
					if ( ! $url ) {
						throw new RuntimeException( 'The reviewed finding page is unavailable.' );
					}
					$target   = json_decode( $occurrence['target_json'], true );
					$evidence = json_decode( $occurrence['evidence_json'], true );
					$element  = json_decode( $occurrence['element_json'], true );
					$identity = $this->identity->fingerprints( is_array( $target ) ? $target : array(), is_array( $evidence ) ? $evidence : array(), is_array( $element ) ? $element : array() );
					$this->exceptions->create(
						array(
							'source_finding_id'    => $finding_id,
							'source_occurrence_id' => $occurrence['id'],
							'rule_id'              => $finding['rule_id'],
							'rule_version'         => $finding['rule_version'],
							'canonical_url_hash'   => $url['canonical_url_hash'],
							'target_fingerprint'   => $identity['target'],
							'material_evidence_fingerprint' => $identity['evidence'],
							'actor_user_id'        => $actor_id,
						)
					);
					++$created;
				}
				if ( 0 === $created ) {
					throw new RuntimeException( 'The finding has no reviewable occurrence evidence.' );
				}
				$history_id = $this->record( $finding_id, $status, $reason, $actor_id, true );
				$this->findings->update_review_state( $finding_id, ReviewState::REVIEWED );
				$this->database->query( 'COMMIT' );
			} catch ( \Throwable $error ) {
				$this->database->query( 'ROLLBACK' );
				throw $error;
			}
		} else {
			$history_id = $this->record( $finding_id, $status, $reason, $actor_id, false );
		}
		do_action( 'elavira_finding_status_changed', (int) $finding['audit_id'], (int) $finding_id, $status );
		do_action( 'elavira_finding_activity_recorded', (int) $finding['audit_id'], (int) $finding_id, $status );
		return $history_id;
	}

	/** Persist a history record using either the caller's or repository's transaction. @return int */
	private function record( $finding_id, $status, $reason, $actor_id, $in_transaction ) {
		$data =
			array(
				'finding_id'      => $finding_id,
				'status'          => $status,
				'actor_user_id'   => $actor_id,
				'reason'          => $reason,
				'source_audit_id' => 0,
			);
		return $in_transaction ? $this->history->record_in_transaction( $data ) : $this->history->record( $data );
	}
}
