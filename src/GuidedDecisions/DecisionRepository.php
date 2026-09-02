<?php
/**
 * Guided Decision completion repository.
 *
 * @package Elavira
 */

namespace Elavira\GuidedDecisions;

// phpcs:disable Squiz.Commenting.FunctionComment -- Concise repository comments are intentional.

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Core\Database\Repository;

/** Persists completed decision records, never intermediate navigation state. */
final class DecisionRepository extends Repository {
	/** Record a completed decision. @param array $data Completion data. @return int */
	public function record( array $data ) {
		$finding_id = $this->require_parent( $this->tables->findings(), $data['finding_id'] ?? 0, 'finding' );
		$answers    = PersistenceValidator::json( $data['answers'] ?? array(), 16384, 'Guided Decision answers' );
		return $this->insert(
			$this->tables->guided_decisions(),
			array(
				'finding_id'       => $finding_id,
				'decision_id'      => PersistenceValidator::text( $data['decision_id'] ?? '', 191, 'decision ID' ),
				'decision_version' => PersistenceValidator::identifier( $data['decision_version'] ?? '', 'decision version' ),
				'answers_json'     => $answers,
				'outcome_id'       => PersistenceValidator::text( $data['outcome_id'] ?? '', 64, 'outcome ID' ),
				'recommendation'   => PersistenceValidator::text( $data['recommendation'] ?? '', 4096, 'recommendation' ),
				'actor_user_id'    => absint( $data['actor_user_id'] ?? 0 ),
				'completed_at'     => $this->now(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/** Return bounded completed decisions for a finding. @param int $finding_id Finding ID. @return array[] */
	public function find_by_finding( $finding_id ) {
		return $this->database->get_results( $this->database->prepare( "SELECT * FROM {$this->tables->guided_decisions()} WHERE finding_id = %d ORDER BY completed_at DESC, id DESC LIMIT 100", absint( $finding_id ) ), ARRAY_A );
	}
}
