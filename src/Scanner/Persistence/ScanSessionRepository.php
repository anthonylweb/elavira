<?php
/**
 * Scan-session persistence.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Persistence;

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Core\Database\Repository;
use Elavira\Scanner\Values\ScanSessionState;
use InvalidArgumentException;
use RuntimeException;

/** Stores short-lived server-owned session metadata and batch receipts. */
final class ScanSessionRepository extends Repository {
	/**
	 * Create a session and return its browser credential.
	 *
	 * @param array $data Session data.
	 * @return string
	 */
	public function create( array $data ) {
		$token = bin2hex( random_bytes( 32 ) );
		$now   = $this->now();
		$this->insert(
			$this->tables->scan_sessions(),
			array(
				'token_hash'         => hash( 'sha256', $token ),
				'audit_id'           => $this->require_parent( $this->tables->audits(), $data['audit_id'] ?? 0, 'audit' ),
				'audit_url_id'       => $this->require_parent( $this->tables->audit_urls(), $data['audit_url_id'] ?? 0, 'audit URL' ),
				'owner_user_id'      => absint( $data['owner_user_id'] ?? 0 ),
				'canonical_url'      => PersistenceValidator::text( $data['canonical_url'] ?? '', 2048, 'canonical URL' ),
				'canonical_url_hash' => hash( 'sha256', (string) $data['canonical_url'] ),
				'wcag_target'        => PersistenceValidator::one_of( $data['wcag_target'] ?? '', array( '2.2_A', '2.2_AA' ), 'WCAG target' ),
				'ruleset_version'    => PersistenceValidator::identifier( $data['ruleset_version'] ?? '', 'ruleset version' ),
				'scanner_version'    => PersistenceValidator::identifier( $data['scanner_version'] ?? '', 'scanner version' ),
				'editorial_version'  => PersistenceValidator::identifier( $data['editorial_version'] ?? '', 'editorial version' ),
				'allowed_rules_json' => PersistenceValidator::json( $data['allowed_rules'] ?? array(), 8192, 'allowed rules' ),
				'state'              => ScanSessionState::CREATED,
				'total_results'      => 0,
				'failure_code'       => '',
				'created_at'         => $now,
				'expires_at'         => gmdate( 'Y-m-d H:i:s', time() + absint( $data['ttl'] ?? 900 ) ),
				'updated_at'         => $now,
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return $token;
	}

	/**
	 * Resolve a session credential.
	 *
	 * @param string $token Browser credential.
	 * @return array|null
	 */
	public function find_by_token( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return null;
		}

		return $this->database->get_row(
			$this->database->prepare( "SELECT * FROM {$this->tables->scan_sessions()} WHERE token_hash = %s", hash( 'sha256', $token ) ),
			ARRAY_A
		);
	}

	/**
	 * Update session state and safe counters.
	 *
	 * @param int   $id   Session ID.
	 * @param array $data Fields.
	 * @return void
	 * @throws RuntimeException When the update fails.
	 */
	public function update( $id, array $data ) {
		$allowed = array_intersect_key( $data, array_flip( array( 'state', 'total_results', 'failure_code' ) ) );
		if ( isset( $allowed['state'] ) ) {
			$allowed['state'] = PersistenceValidator::one_of( $allowed['state'], ScanSessionState::all(), 'session state' );
		}
		if ( isset( $allowed['failure_code'] ) ) {
			$allowed['failure_code'] = PersistenceValidator::text( $allowed['failure_code'], 64, 'failure code', true );
		}
		$allowed['updated_at'] = $this->now();
		if ( false === $this->database->update( $this->tables->scan_sessions(), $allowed, array( 'id' => absint( $id ) ) ) ) {
			throw new RuntimeException( 'Elavira could not update the scan session.' );
		}
	}

	/**
	 * Find a batch receipt.
	 *
	 * @param int    $session_id Session ID.
	 * @param string $batch_key  Batch key.
	 * @return array|null
	 */
	public function find_batch( $session_id, $batch_key ) {
		return $this->database->get_row(
			$this->database->prepare( "SELECT * FROM {$this->tables->scan_batches()} WHERE session_id = %d AND batch_key = %s", absint( $session_id ), $batch_key ),
			ARRAY_A
		);
	}

	/**
	 * Lock a session row inside the caller transaction.
	 *
	 * @param int $session_id Session ID.
	 * @return array|null
	 */
	public function lock( $session_id ) {
		return $this->database->get_row(
			$this->database->prepare( "SELECT * FROM {$this->tables->scan_sessions()} WHERE id = %d FOR UPDATE", absint( $session_id ) ),
			ARRAY_A
		);
	}

	/**
	 * Record an accepted batch inside the caller transaction.
	 *
	 * @param int    $session_id Session ID.
	 * @param string $batch_key  Batch key.
	 * @param int    $count      Result count.
	 * @return int
	 * @throws InvalidArgumentException When the batch identity is malformed.
	 */
	public function record_batch( $session_id, $batch_key, $count ) {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', (string) $batch_key ) ) {
			throw new InvalidArgumentException( 'Invalid batch identity.' );
		}
		return $this->insert(
			$this->tables->scan_batches(),
			array(
				'session_id'   => absint( $session_id ),
				'batch_key'    => $batch_key,
				'result_count' => absint( $count ),
				'created_at'   => $this->now(),
			),
			array( '%d', '%s', '%d', '%s' )
		);
	}
}
