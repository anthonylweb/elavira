<?php
/**
 * Scan-session authorization.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Security;

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Audit\Values\AuditStatus;
use Elavira\Audit\Values\AuditUrlStatus;
use Elavira\Scanner\Persistence\ScanSessionRepository;
use Elavira\Scanner\Values\ScanSessionState;
use RuntimeException;

/** Resolves credentials and enforces ownership, expiry, state, and page binding. */
final class SessionGuard {
	/** WordPress database object.
	 *
	 * @var object
	 */
	private $database;
	/** Session repository.
	 *
	 * @var ScanSessionRepository
	 */
	private $sessions;
	/** Audit repository.
	 *
	 * @var AuditRepository
	 */
	private $audits;
	/** Audit URL repository.
	 *
	 * @var AuditUrlRepository
	 */
	private $urls;

	/**
	 * Construct the guard.
	 *
	 * @param object                $database WordPress database object.
	 * @param ScanSessionRepository $sessions Session repository.
	 * @param AuditRepository       $audits   Audit repository.
	 * @param AuditUrlRepository    $urls     Audit URL repository.
	 */
	public function __construct( $database, ScanSessionRepository $sessions, AuditRepository $audits, AuditUrlRepository $urls ) {
		$this->database = $database;
		$this->sessions = $sessions;
		$this->audits   = $audits;
		$this->urls     = $urls;
	}

	/**
	 * Authorize a session.
	 *
	 * @param string      $token   Credential.
	 * @param int         $user_id User ID.
	 * @param string|null $url     Current page URL.
	 * @param string[]    $states  Allowed states.
	 * @return array
	 * @throws RuntimeException When the session is unavailable or invalid.
	 * @throws \Throwable When expiration persistence fails.
	 */
	public function authorize( $token, $user_id, $url = null, array $states = array() ) {
		$session = $this->sessions->find_by_token( $token );
		if ( ! $session || absint( $user_id ) !== (int) $session['owner_user_id'] ) {
			throw new RuntimeException( 'This scan session is not available to the current user.' );
		}

		if ( strtotime( $session['expires_at'] . ' UTC' ) <= time() ) {
			$this->database->query( 'START TRANSACTION' );
			try {
				$this->sessions->update(
					$session['id'],
					array(
						'state'        => ScanSessionState::EXPIRED,
						'failure_code' => 'session_expired',
					)
				);
				$this->urls->update_lifecycle(
					$session['audit_url_id'],
					array(
						'status'          => AuditUrlStatus::FAILED,
						'failure_code'    => 'session_expired',
						'failure_message' => 'The browser scan session expired before completion.',
					)
				);
				$this->audits->update_lifecycle( $session['audit_id'], array( 'status' => AuditStatus::FAILED ) );
				$this->database->query( 'COMMIT' );
			} catch ( \Throwable $error ) {
				$this->database->query( 'ROLLBACK' );
				throw $error;
			}
			throw new RuntimeException( 'This scan session has expired. Start a new scan.' );
		}

		if ( array() !== $states && ! in_array( $session['state'], $states, true ) ) {
			throw new RuntimeException( 'This scan session does not accept that action.' );
		}

		if ( null !== $url && ! hash_equals( $session['canonical_url_hash'], hash( 'sha256', $url ) ) ) {
			throw new RuntimeException( 'The scan session does not match this page.' );
		}

		return $session;
	}
}
