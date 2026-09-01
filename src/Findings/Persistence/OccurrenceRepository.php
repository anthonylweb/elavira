<?php
/**
 * Finding occurrence repository.
 *
 * @package Elavira
 */

namespace Elavira\Findings\Persistence;

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Core\Database\Repository;
use InvalidArgumentException;

/**
 * Persists bounded structured evidence for individual finding instances.
 */
final class OccurrenceRepository extends Repository {
	/**
	 * Create one occurrence.
	 *
	 * @param array $data Structured occurrence attributes.
	 * @return int
	 * @throws InvalidArgumentException When evidence or relational references are invalid.
	 */
	public function create( array $data ) {
		$finding_id   = $this->require_parent( $this->tables->findings(), $data['finding_id'] ?? 0, 'finding' );
		$audit_url_id = $this->require_parent( $this->tables->audit_urls(), $data['audit_url_id'] ?? 0, 'audit URL' );
		$finding      = $this->database->get_row(
			$this->database->prepare( "SELECT audit_id FROM {$this->tables->findings()} WHERE id = %d", $finding_id ),
			ARRAY_A
		);
		$audit_url    = $this->database->get_row(
			$this->database->prepare( "SELECT audit_id FROM {$this->tables->audit_urls()} WHERE id = %d", $audit_url_id ),
			ARRAY_A
		);

		if ( (int) $finding['audit_id'] !== (int) $audit_url['audit_id'] ) {
			throw new InvalidArgumentException( 'The finding and audit URL must belong to the same audit.' );
		}

		return $this->insert(
			$this->tables->occurrences(),
			array(
				'finding_id'    => $finding_id,
				'audit_url_id'  => $audit_url_id,
				'target_json'   => PersistenceValidator::json( $data['target'] ?? array(), PersistenceValidator::TARGET_MAX_BYTES, 'target' ),
				'evidence_json' => PersistenceValidator::json( $data['evidence'] ?? array(), PersistenceValidator::EVIDENCE_MAX_BYTES, 'evidence' ),
				'element_json'  => PersistenceValidator::json( $data['element'] ?? array(), PersistenceValidator::ELEMENT_MAX_BYTES, 'element metadata' ),
				'created_at'    => $this->now(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Return occurrences belonging to a finding.
	 *
	 * @param int $finding_id Finding ID.
	 * @return array[]
	 */
	public function find_by_finding( $finding_id ) {
		return $this->database->get_results(
			$this->database->prepare( "SELECT * FROM {$this->tables->occurrences()} WHERE finding_id = %d ORDER BY id ASC", absint( $finding_id ) ),
			ARRAY_A
		);
	}

	/**
	 * Return occurrences belonging to an audited URL.
	 *
	 * @param int $audit_url_id Audit URL ID.
	 * @return array[]
	 */
	public function find_by_audit_url( $audit_url_id ) {
		return $this->database->get_results(
			$this->database->prepare( "SELECT * FROM {$this->tables->occurrences()} WHERE audit_url_id = %d ORDER BY id ASC", absint( $audit_url_id ) ),
			ARRAY_A
		);
	}
}
