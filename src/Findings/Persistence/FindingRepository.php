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
	/** Summarize grouped findings without loading individual rows.
	 *
	 * @param int $audit_id Audit ID.
	 * @return array
	 */
	public function summarize_by_audit( $audit_id ) {
		$query = "SELECT priority, current_status, review_state, COUNT(*) AS finding_count, SUM(occurrence_count) AS occurrence_count FROM {$this->tables->findings()} WHERE audit_id = %d GROUP BY priority, current_status, review_state";
		return $this->database->get_results( $this->database->prepare( $query, absint( $audit_id ) ), ARRAY_A );
	}

	/** Find a finding by ID.
	 *
	 * @param int $finding_id Finding ID.
	 * @return array|null
	 */
	public function find( $finding_id ) {
		return $this->database->get_row( $this->database->prepare( "SELECT * FROM {$this->tables->findings()} WHERE id = %d", absint( $finding_id ) ), ARRAY_A );
	}

	/** Return a bounded filtered page of grouped findings.
	 *
	 * @param int   $audit_id Audit ID.
	 * @param array $filters Finding filters.
	 * @param int   $page Page number.
	 * @param int   $per_page Page size.
	 * @return array{items:array,total:int,pages:int}
	 */
	public function paginate_by_audit( $audit_id, array $filters, $page = 1, $per_page = 20 ) {
		$page     = max( 1, absint( $page ) );
		$per_page = min( 50, max( 1, absint( $per_page ) ) );
		$where    = array( 'audit_id = %d' );
		$args     = array( absint( $audit_id ) );
		$allowed  = array(
			'priority'       => Priority::all(),
			'current_status' => FindingStatus::all(),
			'review_state'   => ReviewState::all(),
			'wcag_level'     => array( 'A', 'AA', 'AAA' ),
		);
		foreach ( $allowed as $key => $values ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$where[] = "{$key} = %s";
				$args[]  = PersistenceValidator::one_of( $filters[ $key ], $values, $key );
			}
		}
		if ( ! empty( $filters['search'] ) ) {
			$where[] = '(summary_snapshot LIKE %s OR rule_id LIKE %s)';
			$like    = '%' . $this->database->esc_like( substr( (string) $filters['search'], 0, 100 ) ) . '%';
			$args[]  = $like;
			$args[]  = $like;
		}
		$where_sql = implode( ' AND ', $where );
		$total     = (int) $this->database->get_var( $this->database->prepare( "SELECT COUNT(*) FROM {$this->tables->findings()} WHERE {$where_sql}", $args ) );
		$items     = $this->database->get_results( $this->database->prepare( "SELECT * FROM {$this->tables->findings()} WHERE {$where_sql} ORDER BY FIELD(priority, 'high', 'medium', 'low'), id ASC LIMIT %d OFFSET %d", array_merge( $args, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A );
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => max( 1, (int) ceil( $total / $per_page ) ),
		);
	}
	/**
	 * Create a grouped finding and its initial Open history record.
	 *
	 * @param array $data Validated finding attributes.
	 * @return int
	 * @throws Throwable When validation or persistence fails.
	 */
	public function create( array $data ) {
		$this->database->query( 'START TRANSACTION' );

		try {
			$finding_id = $this->create_in_transaction( $data );

			$this->database->query( 'COMMIT' );

			return $finding_id;
		} catch ( Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			throw $error;
		}
	}

	/**
	 * Create a finding while the caller owns the transaction.
	 *
	 * @param array $data Finding data.
	 * @return int
	 */
	public function create_in_transaction( array $data ) {
		$audit_id   = $this->require_parent( $this->tables->audits(), $data['audit_id'] ?? 0, 'audit' );
		$now        = $this->now();
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
		return $finding_id;
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

	/**
	 * Find a grouped finding.
	 *
	 * @param int    $audit_id  Audit ID.
	 * @param string $rule_id   Rule ID.
	 * @param string $group_key Group key.
	 * @return array|null
	 */
	public function find_group( $audit_id, $rule_id, $group_key ) {
		return $this->database->get_row(
			$this->database->prepare( "SELECT * FROM {$this->tables->findings()} WHERE audit_id = %d AND rule_id = %s AND group_key = %s", absint( $audit_id ), $rule_id, $group_key ),
			ARRAY_A
		);
	}

	/**
	 * Increment occurrence count and set the single-page affected count.
	 *
	 * @param int $finding_id  Finding ID.
	 * @param int $occurrences Added occurrences.
	 * @return void
	 * @throws RuntimeException When the grouped finding or update is unavailable.
	 */
	public function increment_counts( $finding_id, $occurrences = 1 ) {
		$finding = $this->database->get_row( $this->database->prepare( "SELECT * FROM {$this->tables->findings()} WHERE id = %d", absint( $finding_id ) ), ARRAY_A );
		if ( ! $finding ) {
			throw new RuntimeException( 'Elavira could not find the grouped finding.' );
		}
		$updated = $this->database->update(
			$this->tables->findings(),
			array(
				'affected_page_count' => 1,
				'occurrence_count'    => (int) $finding['occurrence_count'] + absint( $occurrences ),
				'updated_at'          => $this->now(),
			),
			array( 'id' => absint( $finding_id ) )
		);
		if ( false === $updated ) {
			throw new RuntimeException( 'Elavira could not update finding counts.' );
		}
	}
}
