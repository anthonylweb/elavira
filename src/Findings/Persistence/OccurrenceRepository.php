<?php
/**
 * Finding occurrence repository.
 *
 * @package Elavira
 */

namespace Elavira\Findings\Persistence;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Generic.Files.LineLength

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Core\Database\Repository;
use InvalidArgumentException;

/**
 * Persists bounded structured evidence for individual finding instances.
 */
final class OccurrenceRepository extends Repository {
	/** Return export-bounded occurrences for one audit in one query.
	 *
	 * @param int $audit_id Audit ID.
	 * @return array[]
	 */
	public function find_by_audit( $audit_id ) {
		$query = "SELECT o.*, f.rule_id, f.summary_snapshot, u.canonical_url FROM {$this->tables->occurrences()} o INNER JOIN {$this->tables->findings()} f ON f.id = o.finding_id INNER JOIN {$this->tables->audit_urls()} u ON u.id = o.audit_url_id WHERE f.audit_id = %d ORDER BY f.id ASC, o.id ASC LIMIT 1000";
		return $this->database->get_results( $this->database->prepare( $query, absint( $audit_id ) ), ARRAY_A );
	}
	/** Return bounded occurrences with captured page URLs.
	 *
	 * @param int $finding_id Finding ID.
	 * @param int $page Page number.
	 * @param int $per_page Page size.
	 * @return array{items:array,total:int,pages:int}
	 */
	public function paginate_by_finding( $finding_id, $page = 1, $per_page = 25 ) {
		$page     = max( 1, absint( $page ) );
		$per_page = min( 100, max( 1, absint( $per_page ) ) );
		$total    = (int) $this->database->get_var( $this->database->prepare( "SELECT COUNT(*) FROM {$this->tables->occurrences()} WHERE finding_id = %d", absint( $finding_id ) ) );
		$query    = "SELECT o.*, u.canonical_url FROM {$this->tables->occurrences()} o INNER JOIN {$this->tables->audit_urls()} u ON u.id = o.audit_url_id WHERE o.finding_id = %d ORDER BY o.id ASC LIMIT %d OFFSET %d";
		$items    = $this->database->get_results( $this->database->prepare( $query, absint( $finding_id ), $per_page, ( $page - 1 ) * $per_page ), ARRAY_A );
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => max( 1, (int) ceil( $total / $per_page ) ),
		);
	}
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
				'finding_id'                    => $finding_id,
				'audit_url_id'                  => $audit_url_id,
				'target_json'                   => PersistenceValidator::json( $data['target'] ?? array(), PersistenceValidator::TARGET_MAX_BYTES, 'target' ),
				'evidence_json'                 => PersistenceValidator::json( $data['evidence'] ?? array(), PersistenceValidator::EVIDENCE_MAX_BYTES, 'evidence' ),
				'element_json'                  => PersistenceValidator::json( $data['element'] ?? array(), PersistenceValidator::ELEMENT_MAX_BYTES, 'element metadata' ),
				'target_fingerprint'            => $this->fingerprint( $data['target_fingerprint'] ?? '' ),
				'material_evidence_fingerprint' => $this->fingerprint( $data['material_evidence_fingerprint'] ?? '' ),
				'reviewed_exception_id'         => absint( $data['reviewed_exception_id'] ?? 0 ),
				'superseded_by_occurrence_id'   => absint( $data['superseded_by_occurrence_id'] ?? 0 ),
				'diagnostic_json'               => PersistenceValidator::json( $data['diagnostics'] ?? array(), PersistenceValidator::EVIDENCE_MAX_BYTES, 'diagnostics' ),
				'created_at'                    => $this->now(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
	}

	/** Find an occurrence by ID. @return array|null */
	public function find( $occurrence_id ) {
		return $this->database->get_row( $this->database->prepare( "SELECT * FROM {$this->tables->occurrences()} WHERE id = %d", absint( $occurrence_id ) ), ARRAY_A );
	}

	/** Validate a fingerprint, allowing empty only for pre-migration compatibility. @return string */
	private function fingerprint( $value ) {
		if ( '' !== $value && ( ! is_string( $value ) || ! preg_match( '/^[a-f0-9]{64}$/', $value ) ) ) {
			throw new InvalidArgumentException( 'An occurrence fingerprint is invalid.' );
		}
		return $value;
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
