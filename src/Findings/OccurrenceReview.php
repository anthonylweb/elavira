<?php
/**
 * Occurrence workflow policy and persistence.
 *
 * @package Elavira
 */

namespace Elavira\Findings;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Generic.Files.LineLength

use Elavira\Core\Database\Repository;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Findings\Persistence\ReviewedExceptionRepository;
use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\Audit\Persistence\AuditUrlRepository;

/** A review is always attached to one captured occurrence, never a row position. */
final class OccurrenceReview extends Repository {
	public static function status( array $occurrence, array $finding ) {
		if ( ! empty( $occurrence['reviewed_exception_id'] ) && isset( $occurrence['exception_active'] ) && ! $occurrence['exception_active'] ) {
			return 'needs_review'; }
		if ( ! empty( $occurrence['current_status'] ) ) {
			return $occurrence['current_status'];
		}
		if ( ! empty( $occurrence['reviewed_exception_id'] ) ) {
			return 'reviewed_not_issue';
		}
		// A single-occurrence legacy decision maps exactly. Group decisions do not.
		return 1 === (int) $finding['occurrence_count'] ? $finding['current_status'] : 'needs_review';
	}

	public function history( $occurrence_id ) {
		return $this->database->get_results( $this->database->prepare( "SELECT * FROM {$this->tables->finding_status_history()} WHERE occurrence_id = %d ORDER BY id ASC", absint( $occurrence_id ) ), ARRAY_A );
	}

	/** Fetch at most one non-empty note per requested occurrence in one query. */
	public function latest_notes( array $occurrence_ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $occurrence_ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$table        = $this->tables->finding_status_history();
		// Only explicitly associated occurrence history qualifies; legacy grouped notes stay in history.
		$rows = $this->database->get_results(
			$this->database->prepare(
				"SELECT h.occurrence_id, h.reason FROM {$table} h WHERE h.occurrence_id IN ({$placeholders}) AND h.reason REGEXP '[^[:space:]]'
				AND NOT EXISTS (SELECT 1 FROM {$table} newer WHERE newer.occurrence_id = h.occurrence_id AND newer.reason REGEXP '[^[:space:]]'
				AND (newer.created_at > h.created_at OR (newer.created_at = h.created_at AND newer.id > h.id)))",
				$ids
			),
			ARRAY_A
		);
		return array_column( $rows, 'reason', 'occurrence_id' );
	}

	/** Refresh group summaries without applying one occurrence decision to another. */
	public function refresh( $finding_id ) {
		$finding  = ( new FindingRepository( $this->database ) )->find( $finding_id );
		$rows     = ( new OccurrenceRepository( $this->database ) )->find_by_finding( $finding_id );
		$active   = 0;
		$accepted = 0;
		$statuses = array();
		foreach ( $rows as $row ) {
			$status     = self::status( $row, $finding );
			$statuses[] = $status;
			$accepted  += 'reviewed_not_issue' === $status ? 1 : 0;
			$active    += in_array( $status, array( 'addressed', 'reviewed_not_issue', 'verified' ), true ) ? 0 : 1;
		}
		$statuses = array_unique( $statuses );
		if ( false === $this->database->update(
			$this->tables->findings(),
			array(
				'active_occurrence_count'   => $active,
				'reviewed_occurrence_count' => $accepted,
				'current_status'            => 1 === count( $statuses ) ? reset( $statuses ) : 'needs_review',
			),
			array( 'id' => absint( $finding_id ) )
		) ) {
			throw new \RuntimeException( 'Could not update occurrence totals.' );
		}
	}

	public function update( $finding_id, $occurrence_id, $status, $reason, $actor ) {
		$allowed = array( 'open', 'needs_review', 'needs_correction', 'addressed', 'accepted_limitation', 'reviewed_not_issue' );
		if ( ! in_array( $status, $allowed, true ) ) {
			throw new \InvalidArgumentException( 'Choose a manual review status.' );
		}
		if ( 'needs_correction' === $status && '' === trim( sanitize_textarea_field( $reason ) ) ) {
			throw new \InvalidArgumentException( 'Explain what needs correction.' );
		}
		$this->database->query( 'START TRANSACTION' );
		try {
			$finding    = $this->database->get_row( $this->database->prepare( "SELECT * FROM {$this->tables->findings()} WHERE id = %d FOR UPDATE", absint( $finding_id ) ), ARRAY_A );
			$occurrence = $this->database->get_row( $this->database->prepare( "SELECT * FROM {$this->tables->occurrences()} WHERE id = %d FOR UPDATE", absint( $occurrence_id ) ), ARRAY_A );
			if ( ! $occurrence || ! $finding || (int) $occurrence['finding_id'] !== (int) $finding_id ) {
				throw new \InvalidArgumentException( 'The occurrence does not belong to this finding.' );
			}
			$occurrence = ( new OccurrenceRepository( $this->database ) )->find( $occurrence_id );
			$old        = self::status( $occurrence, $finding );
			$reason     = substr( sanitize_textarea_field( $reason ), 0, 1000 );
			if ( $old === $status && '' === $reason && ! empty( $occurrence['current_status'] ) ) {
				$this->database->query( 'COMMIT' );
				return 0; }
			$url              = ( new AuditUrlRepository( $this->database ) )->find( $occurrence['audit_url_id'] );
			$exceptions       = new ReviewedExceptionRepository( $this->database );
			$identity         = ( new ReviewedConditionIdentity() )->fingerprints( (array) json_decode( $occurrence['target_json'], true ), (array) json_decode( $occurrence['evidence_json'], true ), (array) json_decode( $occurrence['element_json'], true ) );
			$exception_id     = 0;
			$identity_service = new ReviewedConditionIdentity();
			$matchable        = $identity_service->matchable( (array) json_decode( $occurrence['target_json'], true ), (array) json_decode( $occurrence['element_json'], true ) );
			$matches          = 0;
			foreach ( ( new OccurrenceRepository( $this->database ) )->find_by_finding( $finding_id ) as $candidate ) {
				$candidate_identity = $identity_service->fingerprints( (array) json_decode( $candidate['target_json'], true ), (array) json_decode( $candidate['evidence_json'], true ), (array) json_decode( $candidate['element_json'], true ) );
				if ( $candidate_identity === $identity ) {
					++$matches; }
			}
			$matchable = $matchable && 1 === $matches;

			if ( 'reviewed_not_issue' === $status ) {
				if ( ! in_array( $finding['category'], array( 'potential_issue', 'human_review' ), true ) ) {
					throw new \InvalidArgumentException( 'This scanner result requires remediation review.' );
				}
				$exception_id = $matchable ? $exceptions->create(
					array(
						'source_finding_id'             => $finding_id,
						'source_occurrence_id'          => $occurrence_id,
						'rule_id'                       => $finding['rule_id'],
						'rule_version'                  => $finding['rule_version'],
						'canonical_url_hash'            => $url['canonical_url_hash'],
						'target_fingerprint'            => $identity['target'],
						'material_evidence_fingerprint' => $identity['evidence'],
						'actor_user_id'                 => $actor,
					)
				) : 0;
			} elseif ( 'reviewed_not_issue' === $old ) {
				if ( ! empty( $occurrence['reviewed_exception_id'] ) ) {
					$exceptions->reset_id( $occurrence['reviewed_exception_id'], $actor ); }
				$exceptions->reset_identity( $finding['rule_id'], $finding['rule_version'], $url['canonical_url_hash'], $identity['target'], $identity['evidence'], $actor );
			}
			if ( false === $this->database->update(
				$this->tables->occurrences(),
				array(
					'current_status'        => $status,
					'reviewed_exception_id' => $exception_id,
				),
				array( 'id' => absint( $occurrence_id ) )
			) ) {
				throw new \RuntimeException( 'Could not save occurrence status.' );
			}
			$id = $this->insert(
				$this->tables->finding_status_history(),
				array(
					'finding_id'      => $finding_id,
					'occurrence_id'   => $occurrence_id,
					'status'          => $status,
					'reason'          => $reason,
					'actor_user_id'   => $actor,
					'source_audit_id' => 0,
					'created_at'      => $this->now(),
				),
				array( '%d', '%d', '%s', '%s', '%d', '%d', '%s' )
			);
			$this->refresh( $finding_id );
			$this->database->query( 'COMMIT' );
			do_action( 'elavira_finding_status_changed', (int) $finding['audit_id'], (int) $finding_id, $status );
			return $id;
		} catch ( \Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			throw $error;
		}
	}
}
