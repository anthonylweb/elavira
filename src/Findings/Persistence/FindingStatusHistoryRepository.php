<?php
/**
 * Finding status history repository.
 *
 * @package Elavira
 */

namespace Elavira\Findings\Persistence;

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Core\Database\Repository;
use Elavira\Findings\Values\FindingStatus;
use RuntimeException;
use Throwable;

/**
 * Records status transitions without erasing prior workflow history.
 */
final class FindingStatusHistoryRepository extends Repository {
	/** Return bounded status history for all findings in an audit.
	 *
	 * @param int $audit_id Audit ID.
	 * @return array[]
	 */
	public function find_by_audit( $audit_id ) {
		$query = "SELECT h.*, f.rule_id, f.summary_snapshot FROM {$this->tables->finding_status_history()} h INNER JOIN {$this->tables->findings()} f ON f.id = h.finding_id WHERE f.audit_id = %d ORDER BY h.created_at ASC, h.id ASC LIMIT 5000";
		return $this->database->get_results( $this->database->prepare( $query, absint( $audit_id ) ), ARRAY_A );
	}
	/**
	 * Record a status transition.
	 *
	 * Verified remains an explicit persisted state; verification automation is
	 * outside this repository and must supply its source audit deliberately.
	 *
	 * @param array $data Status transition attributes.
	 * @return int
	 * @throws RuntimeException When the status cannot be persisted.
	 * @throws Throwable When a transactional operation fails unexpectedly.
	 */
	public function record( array $data ) {
		$finding_id = $this->require_parent( $this->tables->findings(), $data['finding_id'] ?? 0, 'finding' );
		$status     = PersistenceValidator::one_of( $data['status'] ?? '', FindingStatus::all(), 'finding status' );
		$source_id  = absint( $data['source_audit_id'] ?? 0 );

		if ( 0 !== $source_id ) {
			$this->require_parent( $this->tables->audits(), $source_id, 'source audit' );
		}

		$now = $this->now();
		$this->database->query( 'START TRANSACTION' );

		try {
			$history_id = $this->insert(
				$this->tables->finding_status_history(),
				array(
					'finding_id'      => $finding_id,
					'status'          => $status,
					'actor_user_id'   => absint( $data['actor_user_id'] ?? 0 ),
					'reason'          => PersistenceValidator::text( $data['reason'] ?? '', 1000, 'status reason', true ),
					'source_audit_id' => $source_id,
					'created_at'      => $now,
				),
				array( '%d', '%s', '%d', '%s', '%d', '%s' )
			);

			$updated = $this->database->update(
				$this->tables->findings(),
				array(
					'current_status' => $status,
					'updated_at'     => $now,
				),
				array( 'id' => $finding_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				throw new RuntimeException( 'Elavira could not update the current finding status.' );
			}

			$this->database->query( 'COMMIT' );

			return $history_id;
		} catch ( Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			throw $error;
		}
	}

	/**
	 * Return status history in chronological order.
	 *
	 * @param int $finding_id Finding ID.
	 * @return array[]
	 */
	public function find_by_finding( $finding_id ) {
		return $this->database->get_results(
			$this->database->prepare( "SELECT * FROM {$this->tables->finding_status_history()} WHERE finding_id = %d ORDER BY created_at ASC, id ASC LIMIT 500", absint( $finding_id ) ),
			ARRAY_A
		);
	}
}
