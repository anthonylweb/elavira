<?php
/**
 * Reviewed exception persistence.
 *
 * @package Elavira
 */

namespace Elavira\Findings\Persistence;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Generic.Commenting.DocComment.MissingShort, Generic.Files.LineLength

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Core\Database\Repository;
use RuntimeException;

/** Owns the reversible future-suppression layer. */
final class ReviewedExceptionRepository extends Repository {
	/** Find an exact active exception. @return array|null */
	public function match( $rule_id, $rule_version, $canonical_url_hash, $target_fingerprint, $evidence_fingerprint ) {
		$query = "SELECT * FROM {$this->tables->reviewed_exceptions()} WHERE rule_id = %s AND rule_version = %s AND canonical_url_hash = %s AND target_fingerprint = %s AND material_evidence_fingerprint = %s AND active = %d ORDER BY id DESC LIMIT 1";
		return $this->database->get_row( $this->database->prepare( $query, $rule_id, $rule_version, $canonical_url_hash, $target_fingerprint, $evidence_fingerprint, 1 ), ARRAY_A );
	}

	/** Persist one explicit decision per source occurrence. @return int */
	public function create( array $data ) {
		$existing = $this->match( $data['rule_id'], $data['rule_version'], $data['canonical_url_hash'], $data['target_fingerprint'], $data['material_evidence_fingerprint'] );
		if ( $existing ) {
			return (int) $existing['id'];
		}
		return $this->insert(
			$this->tables->reviewed_exceptions(),
			array(
				'source_finding_id'             => $this->require_parent( $this->tables->findings(), $data['source_finding_id'], 'finding' ),
				'source_occurrence_id'          => $this->require_parent( $this->tables->occurrences(), $data['source_occurrence_id'], 'occurrence' ),
				'rule_id'                       => PersistenceValidator::identifier( $data['rule_id'], 'rule ID' ),
				'rule_version'                  => PersistenceValidator::identifier( $data['rule_version'], 'rule version' ),
				'canonical_url_hash'            => $this->hash_value( $data['canonical_url_hash'] ),
				'target_fingerprint'            => $this->hash_value( $data['target_fingerprint'] ),
				'material_evidence_fingerprint' => $this->hash_value( $data['material_evidence_fingerprint'] ),
				'actor_user_id'                 => absint( $data['actor_user_id'] ),
				'active'                        => 1,
				'created_at'                    => $this->now(),
				'reset_at'                      => null,
				'reset_by_user_id'              => 0,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d' )
		);
	}

	/** Reconsider the linked historical exception, after occurrence ownership validation. @return int */
	public function reset_id( $id, $actor ) {
		return $this->reset_where( 'id = %d', array( absint( $id ) ), $actor );
	}

	/** Reconsider only the exact condition, preserving other decisions. @return int */
	public function reset_identity( $rule, $version, $page, $target, $evidence, $actor ) {
		return $this->reset_where( 'rule_id = %s AND rule_version = %s AND canonical_url_hash = %s AND target_fingerprint = %s AND material_evidence_fingerprint = %s', array( $rule, $version, $page, $target, $evidence ), $actor );
	}

	/** Deactivate exceptions for one canonical page without touching history. @return int */
	public function reset_page( $canonical_url_hash, $actor_user_id ) {
		return $this->reset_where( 'canonical_url_hash = %s', array( $this->hash_value( $canonical_url_hash ) ), $actor_user_id );
	}

	/** Deactivate all exceptions without touching source records. @return int */
	public function reset_all( $actor_user_id ) {
		return $this->reset_where( '1 = 1', array(), $actor_user_id );
	}

	/** @return array[] */
	public function find_by_source_finding( $finding_id ) {
		return $this->database->get_results( $this->database->prepare( "SELECT * FROM {$this->tables->reviewed_exceptions()} WHERE source_finding_id = %d ORDER BY id ASC", absint( $finding_id ) ), ARRAY_A );
	}

	/** @return int */
	private function reset_where( $where, array $args, $actor_user_id ) {
		$sql    = "UPDATE {$this->tables->reviewed_exceptions()} SET active = 0, reset_at = %s, reset_by_user_id = %d WHERE {$where} AND active = 1";
		$result = $this->database->query( $this->database->prepare( $sql, ...array_merge( array( $this->now(), absint( $actor_user_id ) ), $args ) ) );
		if ( false === $result ) {
			throw new RuntimeException( 'Elavira could not reset reviewed exceptions.' );
		}
		return (int) $result;
	}

	/** Validate a SHA-256 hex digest. @return string */
	private function hash_value( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^[a-f0-9]{64}$/', $value ) ) {
			throw new \InvalidArgumentException( 'A reviewed-exception fingerprint is invalid.' );
		}
		return $value;
	}
}
