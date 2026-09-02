<?php
/**
 * Finding workflow updates initiated from Reports.
 *
 * @package Elavira
 */

namespace Elavira\Reports\Application;

use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\FindingStatusHistoryRepository;
use Elavira\Findings\Values\FindingStatus;
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

	/** Create the workflow service.
	 *
	 * @param FindingRepository              $findings Finding persistence.
	 * @param FindingStatusHistoryRepository $history Status-history persistence.
	 */
	public function __construct( FindingRepository $findings, FindingStatusHistoryRepository $history ) {
		$this->findings = $findings;
		$this->history  = $history;
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
	 * @return int
	 * @throws RuntimeException When the finding is unavailable.
	 * @throws InvalidArgumentException When the requested transition is invalid.
	 */
	public function update( $finding_id, $status, $reason, $actor_id ) {
		$finding = $this->findings->find( $finding_id );
		if ( ! $finding ) {
			throw new RuntimeException( 'The finding is not available.' );
		}
		$allowed = array( FindingStatus::OPEN, FindingStatus::ADDRESSED, FindingStatus::ACCEPTED_LIMITATION, FindingStatus::NEEDS_REVIEW );
		if ( ! in_array( $status, $allowed, true ) ) {
			throw new InvalidArgumentException( 'That manual finding status is not allowed.' );
		}
		if ( $status === $finding['current_status'] ) {
			throw new InvalidArgumentException( 'Choose a status different from the current status.' );
		}
		return $this->history->record(
			array(
				'finding_id'      => $finding_id,
				'status'          => $status,
				'actor_user_id'   => $actor_id,
				'reason'          => substr( trim( (string) $reason ), 0, 1000 ),
				'source_audit_id' => 0,
			)
		);
	}
}
