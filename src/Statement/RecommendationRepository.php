<?php
/**
 * Statement recommendation persistence.
 *
 * @package Elavira
 */

namespace Elavira\Statement;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag -- Concise repository comments are intentional.

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Core\Database\Repository;
use RuntimeException;

/** Owns traceable, deduplicated Statement recommendations. */
final class RecommendationRepository extends Repository {
	/** Create once for a stable trusted-source fingerprint. @param array $data Recommendation. @return int */
	public function create_once( array $data ) {
		$existing = $this->database->get_var( $this->database->prepare( "SELECT id FROM {$this->tables->statement_recommendations()} WHERE source_fingerprint = %s", $data['source_fingerprint'] ) );
		if ( null !== $existing ) {
			return (int) $existing;
		}
		return $this->insert(
			$this->tables->statement_recommendations(),
			array(
				'statement_id'       => absint( $data['statement_id'] ?? 0 ),
				'audit_id'           => $this->require_parent( $this->tables->audits(), $data['audit_id'], 'audit' ),
				'finding_id'         => $this->require_parent( $this->tables->findings(), $data['finding_id'], 'finding' ),
				'guided_decision_id' => absint( $data['guided_decision_id'] ?? 0 ),
				'source_fingerprint' => PersistenceValidator::identifier( $data['source_fingerprint'], 'recommendation fingerprint' ),
				'kind'               => PersistenceValidator::one_of( $data['kind'], array( 'known_limitation', 'human_review' ), 'recommendation kind' ),
				'state'              => 'pending',
				'suggested_text'     => PersistenceValidator::text( $data['suggested_text'], 4096, 'suggested text' ),
				'approved_text'      => '',
				'dismissal_reason'   => '',
				'created_at'         => $this->now(),
				'reviewed_at'        => null,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/** Return bounded recommendations. @param int $statement_id Statement ID. @return array */
	public function find_for_statement( $statement_id ) {
		return $this->database->get_results( $this->database->prepare( "SELECT r.*, f.summary_snapshot, f.rule_id, f.rule_version, f.affected_page_count, f.occurrence_count FROM {$this->tables->statement_recommendations()} r JOIN {$this->tables->findings()} f ON f.id = r.finding_id WHERE r.statement_id IN (0, %d) ORDER BY FIELD(r.state, 'pending', 'accepted', 'dismissed'), r.id DESC LIMIT 200", absint( $statement_id ) ), ARRAY_A );
	}

	/** Find one recommendation. @param int $id ID. @return array|null */
	public function find( $id ) {
		return $this->database->get_row( $this->database->prepare( "SELECT * FROM {$this->tables->statement_recommendations()} WHERE id = %d", absint( $id ) ), ARRAY_A );
	}

	/** Review a pending recommendation. @param int $id ID. @param int $statement_id Statement. @param string $state State. @param string $text Approved text. @param string $reason Dismissal reason. @return void */
	public function review( $id, $statement_id, $state, $text, $reason ) {
		$state  = PersistenceValidator::one_of( $state, array( 'accepted', 'dismissed' ), 'recommendation state' );
		$reason = 'dismissed' === $state ? PersistenceValidator::one_of( $reason, array( 'not_a_limitation', 'already_addressed', 'already_covered', 'do_not_include', 'other' ), 'dismissal reason' ) : '';
		$text   = 'accepted' === $state ? PersistenceValidator::text( sanitize_textarea_field( $text ), 4096, 'approved recommendation text' ) : '';
		if ( false === $this->database->update(
			$this->tables->statement_recommendations(),
			array(
				'statement_id'     => absint( $statement_id ),
				'state'            => $state,
				'approved_text'    => $text,
				'dismissal_reason' => $reason,
				'reviewed_at'      => $this->now(),
			),
			array(
				'id'    => absint( $id ),
				'state' => 'pending',
			)
		) ) {
			throw new RuntimeException( 'Elavira could not review the Statement recommendation.' );
		}
	}

	/** Count pending recommendations for an audit. @param int $audit_id Audit. @return int */
	public function pending_for_audit( $audit_id ) {
		return (int) $this->database->get_var( $this->database->prepare( "SELECT COUNT(*) FROM {$this->tables->statement_recommendations()} WHERE audit_id = %d AND state = 'pending'", absint( $audit_id ) ) );
	}
}
