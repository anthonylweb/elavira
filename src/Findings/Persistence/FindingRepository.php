<?php
/**
 * Finding repository.
 *
 * @package Elavira
 */

namespace Elavira\Findings\Persistence;

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Core\Database\Repository;
use Elavira\Findings\Values\FindingStatus;
use Elavira\Findings\Values\Priority;
use Elavira\Findings\Values\ReviewState;
use RuntimeException;
use Throwable;

/**
 * Persists grouped findings rather than individual DOM occurrences.
 */
final class FindingRepository extends Repository {
	/**
	 * Create a grouped finding and its initial Open history record.
	 *
	 * @param array $data Validated finding attributes.
	 * @return int
	 * @throws Throwable When validation or persistence fails.
	 */
	public function create( array $data ) {
		$audit_id = $this->require_parent( $this->tables->audits(), $data['audit_id'] ?? 0, 'audit' );
		$now      = $this->now();

		$this->database->query( 'START TRANSACTION' );

		try {
			$finding_id = $this->insert(
				$this->tables->findings(),
				array(
					'audit_id'            => $audit_id,
					'rule_id'             => PersistenceValidator::identifier( $data['rule_id'] ?? '', 'rule ID' ),
					'rule_version'        => PersistenceValidator::identifier( $data['rule_version'] ?? '', 'rule version' ),
					'group_key'           => PersistenceValidator::identifier( $data['group_key'] ?? '', 'group key' ),
					'category'            => PersistenceValidator::one_of( $data['category'] ?? '', array( 'detected_failure', 'potential_issue', 'human_review' ), 'finding category' ),
					'priority'            => PersistenceValidator::one_of( $data['priority'] ?? '', Priority::all(), 'priority' ),
					'review_state'        => PersistenceValidator::one_of( $data['review_state'] ?? '', ReviewState::all(), 'review state' ),
					'current_status'      => FindingStatus::OPEN,
					'wcag_criterion'      => PersistenceValidator::text( $data['wcag_criterion'] ?? '', 32, 'WCAG criterion' ),
					'wcag_level'          => PersistenceValidator::one_of( $data['wcag_level'] ?? '', array( 'A', 'AA', 'AAA' ), 'WCAG level' ),
					'wcag_principle'      => PersistenceValidator::text( $data['wcag_principle'] ?? '', 32, 'WCAG principle' ),
					'editorial_version'   => PersistenceValidator::identifier( $data['editorial_version'] ?? '', 'editorial version' ),
					'summary_snapshot'    => PersistenceValidator::text( $data['summary_snapshot'] ?? '', 4096, 'summary snapshot', true ),
					'affected_page_count' => absint( $data['affected_page_count'] ?? 0 ),
					'occurrence_count'    => absint( $data['occurrence_count'] ?? 0 ),
					'created_at'          => $now,
					'updated_at'          => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
			);

			$this->insert(
				$this->tables->finding_status_history(),
				array(
					'finding_id'      => $finding_id,
					'status'          => FindingStatus::OPEN,
					'actor_user_id'   => 0,
					'reason'          => '',
					'source_audit_id' => 0,
					'created_at'      => $now,
				),
				array( '%d', '%s', '%d', '%s', '%d', '%s' )
			);

			$this->database->query( 'COMMIT' );

			return $finding_id;
		} catch ( Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			throw $error;
		}
	}

	/**
	 * Return grouped findings belonging to an audit.
	 *
	 * @param int $audit_id Audit ID.
	 * @return array[]
	 */
	public function find_by_audit( $audit_id ) {
		return $this->database->get_results(
			$this->database->prepare( "SELECT * FROM {$this->tables->findings()} WHERE audit_id = %d ORDER BY id ASC", absint( $audit_id ) ),
			ARRAY_A
		);
	}
}
