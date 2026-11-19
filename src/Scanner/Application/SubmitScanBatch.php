<?php
/**
 * Browser result batch persistence.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Application;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, Generic.Commenting.DocComment.MissingShort, Generic.Files.LineLength

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Audit\Values\AuditStatus;
use Elavira\Audit\Values\AuditUrlStatus;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Findings\Persistence\ReviewedExceptionRepository;
use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\Rules\Browser\BrowserEvidenceValidator;
use Elavira\Rules\Normalization\RuleResultNormalizer;
use Elavira\Rules\Grouping\SupersessionResolver;
use Elavira\Scanner\Persistence\ScanSessionRepository;
use Elavira\Scanner\Persistence\StagedObservationRepository;
use Elavira\Scanner\ScanLimits;
use Elavira\Scanner\Security\SessionGuard;
use Elavira\Scanner\Values\ScanSessionState;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Validates a complete batch before atomically persisting findings and receipt. */
final class SubmitScanBatch {
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
	/** Evidence validator.
	 *
	 * @var BrowserEvidenceValidator
	 */
	private $validator;
	/** Result normalizer.
	 *
	 * @var RuleResultNormalizer
	 */
	private $normalizer;
	/** Finding repository.
	 *
	 * @var FindingRepository
	 */
	private $findings;
	/** Occurrence repository.
	 *
	 * @var OccurrenceRepository
	 */
	private $occurrences;
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
	/** @var ReviewedExceptionRepository|null */
	private $exceptions;
	/** @var ReviewedConditionIdentity */
	private $identity;
	/** @var SupersessionResolver|null */
	private $supersession;
	/** @var StagedObservationRepository */
	private $staged;

	/**
	 * Construct the batch application service.
	 *
	 * @param object                   $database    WordPress database object.
	 * @param ScanSessionRepository    $sessions    Session repository.
	 * @param SessionGuard             $guard       Session guard.
	 * @param BrowserEvidenceValidator $validator   Evidence validator.
	 * @param RuleResultNormalizer     $normalizer  Result normalizer.
	 * @param FindingRepository        $findings    Finding repository.
	 * @param OccurrenceRepository     $occurrences Occurrence repository.
	 * @param AuditRepository          $audits      Audit repository.
	 * @param AuditUrlRepository       $urls        Audit URL repository.
	 */
	public function __construct( $database, ScanSessionRepository $sessions, SessionGuard $guard, BrowserEvidenceValidator $validator, RuleResultNormalizer $normalizer, FindingRepository $findings, OccurrenceRepository $occurrences, AuditRepository $audits, AuditUrlRepository $urls, ?ReviewedExceptionRepository $exceptions = null, ?ReviewedConditionIdentity $identity = null, ?SupersessionResolver $supersession = null, ?StagedObservationRepository $staged = null ) {
		$this->database     = $database;
		$this->sessions     = $sessions;
		$this->guard        = $guard;
		$this->validator    = $validator;
		$this->normalizer   = $normalizer;
		$this->findings     = $findings;
		$this->occurrences  = $occurrences;
		$this->audits       = $audits;
		$this->urls         = $urls;
		$this->exceptions   = $exceptions;
		$this->identity     = $identity ? $identity : new ReviewedConditionIdentity();
		$this->supersession = $supersession;
		$this->staged       = $staged ? $staged : new StagedObservationRepository( $database );
	}

	/**
	 * Submit one bounded batch.
	 *
	 * @param string $token   Session credential.
	 * @param int    $user_id User ID.
	 * @param array  $results Browser results.
	 * @return array
	 * @throws InvalidArgumentException When batch validation fails.
	 * @throws RuntimeException When limits or persistence reject the batch.
	 * @throws Throwable When validation or persistence fails.
	 */
	public function submit( $token, $user_id, array $results ) {
		$this->staged->cleanup_expired();
		$session = $this->guard->authorize( $token, $user_id, null, array( ScanSessionState::CREATED, ScanSessionState::ACTIVE ) );
		$count   = count( $results );
		if ( 0 === $count || $count > ScanLimits::MAX_RESULTS_BATCH ) {
			throw new InvalidArgumentException( 'Each scan batch must contain between 1 and 50 results.' );
		}
		if ( strlen( (string) wp_json_encode( $results ) ) > ScanLimits::MAX_REQUEST_BYTES ) {
			throw new InvalidArgumentException( 'The scan batch is too large.' );
		}
		$allowed    = json_decode( $session['allowed_rules_json'], true );
		$normalized = array();
		foreach ( $results as $result ) {
			if ( ! is_array( $result ) || ! isset( $allowed[ $result['rule_id'] ?? '' ] ) || ( $result['rule_version'] ?? null ) !== $allowed[ $result['rule_id'] ] ) {
				throw new InvalidArgumentException( 'A result does not belong to this session ruleset.' );
			}
			$normalized[] = $this->normalizer->normalize( $this->validator->validate( $result ) )->to_array();
		}
		$batch_key = hash( 'sha256', (string) wp_json_encode( $results ) );
		$this->database->query( 'START TRANSACTION' );
		try {
			$session = $this->sessions->lock( $session['id'] );
			if ( ! $session || ! in_array( $session['state'], array( ScanSessionState::CREATED, ScanSessionState::ACTIVE ), true ) ) {
				throw new RuntimeException( 'This scan session no longer accepts results.' );
			}
			if ( $this->sessions->find_batch( $session['id'], $batch_key ) ) {
				$this->database->query( 'COMMIT' );
				return array(
					'accepted'  => $count,
					'duplicate' => true,
					'total'     => (int) $session['total_results'],
				);
			}
			if ( (int) $session['total_results'] + $count > ScanLimits::MAX_TOTAL_RESULTS ) {
				$this->sessions->update(
					$session['id'],
					array(
						'state'        => ScanSessionState::FAILED,
						'failure_code' => 'result_limit_exceeded',
					)
				);
				$this->urls->update_lifecycle(
					$session['audit_url_id'],
					array(
						'status'          => AuditUrlStatus::FAILED,
						'failure_code'    => 'result_limit_exceeded',
						'failure_message' => 'The page produced more results than this Core scan can safely store.',
					)
				);
				$this->audits->update_lifecycle( $session['audit_id'], array( 'status' => AuditStatus::FAILED ) );
				$this->database->query( 'COMMIT' );
				throw new RuntimeException( 'The page produced more results than this Core scan can safely store.' );
			}
			$this->staged->stage_batch( $session['id'], $batch_key, $normalized );
			$this->sessions->record_batch( $session['id'], $batch_key, $count );
			$total = (int) $session['total_results'] + $count;
			$this->sessions->update(
				$session['id'],
				array(
					'state'         => ScanSessionState::ACTIVE,
					'total_results' => $total,
				)
			);
			$this->database->query( 'COMMIT' );
			return array(
				'accepted'  => $count,
				'duplicate' => false,
				'total'     => $total,
			);
		} catch ( Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			throw $error;
		}
	}
}
