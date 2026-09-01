<?php
/**
 * Audit repository.
 *
 * @package Elavira
 */

namespace Elavira\Audit\Persistence;

use Elavira\Audit\Values\AuditScope;
use Elavira\Audit\Values\AuditStatus;
use Elavira\Core\Database\PersistenceValidator;
use Elavira\Core\Database\Repository;
use RuntimeException;

/**
 * Persists Core Audit aggregate roots.
 */
final class AuditRepository extends Repository {
	/**
	 * Create an audit.
	 *
	 * @param array $data Validated audit attributes.
	 * @return int
	 */
	public function create( array $data ) {
		$now = $this->now();
		$row = array(
			'label'              => PersistenceValidator::text( $data['label'] ?? '', 191, 'audit label' ),
			'scope'              => PersistenceValidator::one_of( $data['scope'] ?? '', AuditScope::core(), 'audit scope' ),
			'wcag_target'        => PersistenceValidator::one_of( $data['wcag_target'] ?? '', array( '2.2_A', '2.2_AA' ), 'WCAG target' ),
			'status'             => PersistenceValidator::one_of( $data['status'] ?? '', AuditStatus::all(), 'audit status' ),
			'owner_user_id'      => absint( $data['owner_user_id'] ?? 0 ),
			'configuration_json' => PersistenceValidator::json( $data['configuration'] ?? array(), PersistenceValidator::CONFIGURATION_MAX_BYTES, 'configuration' ),
			'scanner_version'    => PersistenceValidator::identifier( $data['scanner_version'] ?? '', 'scanner version' ),
			'ruleset_version'    => PersistenceValidator::identifier( $data['ruleset_version'] ?? '', 'ruleset version' ),
			'editorial_version'  => PersistenceValidator::identifier( $data['editorial_version'] ?? '', 'editorial version' ),
			'started_at'         => PersistenceValidator::datetime( $data['started_at'] ?? null, 'started timestamp' ),
			'completed_at'       => PersistenceValidator::datetime( $data['completed_at'] ?? null, 'completed timestamp' ),
			'created_at'         => $now,
			'updated_at'         => $now,
		);

		return $this->insert(
			$this->tables->audits(),
			$row,
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Find an audit by ID.
	 *
	 * @param int $audit_id Audit ID.
	 * @return array|null
	 */
	public function find( $audit_id ) {
		return $this->database->get_row(
			$this->database->prepare( "SELECT * FROM {$this->tables->audits()} WHERE id = %d", absint( $audit_id ) ),
			ARRAY_A
		);
	}

	/**
	 * Update lifecycle state and completion counts.
	 *
	 * @param int   $audit_id Audit ID.
	 * @param array $data     Fields.
	 * @return void
	 * @throws RuntimeException When the update fails.
	 */
	public function update_lifecycle( $audit_id, array $data ) {
		$allowed = array_intersect_key( $data, array_flip( array( 'status', 'completed_at' ) ) );
		if ( isset( $allowed['status'] ) ) {
			$allowed['status'] = PersistenceValidator::one_of( $allowed['status'], AuditStatus::all(), 'audit status' );
		}
		if ( array_key_exists( 'completed_at', $allowed ) ) {
			$allowed['completed_at'] = PersistenceValidator::datetime( $allowed['completed_at'], 'completed timestamp' );
		}
		$allowed['updated_at'] = $this->now();
		if ( false === $this->database->update( $this->tables->audits(), $allowed, array( 'id' => absint( $audit_id ) ) ) ) {
			throw new RuntimeException( 'Elavira could not update the audit.' );
		}
	}
}
