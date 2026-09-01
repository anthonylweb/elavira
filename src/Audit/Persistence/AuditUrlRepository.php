<?php
/**
 * Audit URL repository.
 *
 * @package Elavira
 */

namespace Elavira\Audit\Persistence;

use Elavira\Audit\Values\AuditUrlStatus;
use Elavira\Core\Database\PersistenceValidator;
use Elavira\Core\Database\Repository;
use InvalidArgumentException;

/**
 * Persists canonical pages belonging to an Audit.
 */
final class AuditUrlRepository extends Repository {
	/**
	 * Attach one canonical URL to an audit.
	 *
	 * @param array $data Validated URL attributes.
	 * @return int
	 * @throws InvalidArgumentException When the parent or URL attributes are invalid.
	 */
	public function create( array $data ) {
		$audit_id = $this->require_parent( $this->tables->audits(), $data['audit_id'] ?? 0, 'audit' );
		$url      = is_string( $data['canonical_url'] ?? null ) ? esc_url_raw( $data['canonical_url'] ) : '';

		if ( '' === $url || false === wp_http_validate_url( $url ) || strlen( $url ) > 2048 ) {
			throw new InvalidArgumentException( 'Invalid canonical URL.' );
		}

		$now = $this->now();

		return $this->insert(
			$this->tables->audit_urls(),
			array(
				'audit_id'           => $audit_id,
				'canonical_url'      => $url,
				'canonical_url_hash' => hash( 'sha256', $url ),
				'object_id'          => absint( $data['object_id'] ?? 0 ),
				'object_type'        => PersistenceValidator::text( $data['object_type'] ?? '', 64, 'object type' ),
				'status'             => PersistenceValidator::one_of( $data['status'] ?? '', AuditUrlStatus::all(), 'audit URL status' ),
				'failure_code'       => PersistenceValidator::text( $data['failure_code'] ?? '', 64, 'failure code' ),
				'failure_message'    => PersistenceValidator::text( $data['failure_message'] ?? '', 1000, 'failure message', true ),
				'started_at'         => PersistenceValidator::datetime( $data['started_at'] ?? null, 'started timestamp' ),
				'completed_at'       => PersistenceValidator::datetime( $data['completed_at'] ?? null, 'completed timestamp' ),
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Return pages belonging to an audit.
	 *
	 * @param int $audit_id Audit ID.
	 * @return array[]
	 */
	public function find_by_audit( $audit_id ) {
		return $this->database->get_results(
			$this->database->prepare( "SELECT * FROM {$this->tables->audit_urls()} WHERE audit_id = %d ORDER BY id ASC", absint( $audit_id ) ),
			ARRAY_A
		);
	}
}
