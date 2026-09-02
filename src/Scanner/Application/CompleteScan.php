<?php
/**
 * Explicit scan completion and failure transitions.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Application;

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Audit\Values\AuditStatus;
use Elavira\Audit\Values\AuditUrlStatus;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Scanner\Persistence\ScanSessionRepository;
use Elavira\Scanner\Security\SessionGuard;
use Elavira\Scanner\Values\ScanSessionState;
use RuntimeException;

/** Owns terminal lifecycle transitions; silence is never treated as success. */
final class CompleteScan {
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
	/** Session guard.
	 *
	 * @var SessionGuard
	 */
	private $guard;
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
	/** Finding repository.
	 *
	 * @var FindingRepository
	 */
	private $findings;

	/**
	 * Construct completion service.
	 *
	 * @param object                $database WordPress database object.
	 * @param ScanSessionRepository $sessions Session repository.
	 * @param SessionGuard          $guard    Session guard.
	 * @param AuditRepository       $audits   Audit repository.
	 * @param AuditUrlRepository    $urls     Audit URL repository.
	 * @param FindingRepository     $findings Finding repository.
	 */
	public function __construct( $database, ScanSessionRepository $sessions, SessionGuard $guard, AuditRepository $audits, AuditUrlRepository $urls, FindingRepository $findings ) {
		$this->database = $database;
		$this->sessions = $sessions;
		$this->guard    = $guard;
		$this->audits   = $audits;
		$this->urls     = $urls;
		$this->findings = $findings;
	}

	/**
	 * Complete idempotently.
	 *
	 * @param string $token   Credential.
	 * @param int    $user_id User ID.
	 * @return array
	 * @throws RuntimeException When the session state cannot complete.
	 * @throws \Throwable When authorization or persistence fails.
	 */
	public function complete( $token, $user_id ) {
		$session  = $this->guard->authorize( $token, $user_id, null, array( ScanSessionState::CREATED, ScanSessionState::ACTIVE, ScanSessionState::COMPLETED ) );
		$findings = $this->findings->find_by_audit( $session['audit_id'] );
		if ( ScanSessionState::COMPLETED === $session['state'] ) {
			return array(
				'audit_id'      => (int) $session['audit_id'],
				'finding_count' => count( $findings ),
				'duplicate'     => true,
			);
		}

		$now = current_time( 'mysql', true );
		$this->database->query( 'START TRANSACTION' );
		try {
			$session = $this->sessions->lock( $session['id'] );
			if ( ScanSessionState::COMPLETED === $session['state'] ) {
				$this->database->query( 'COMMIT' );
				return array(
					'audit_id'      => (int) $session['audit_id'],
					'finding_count' => count( $findings ),
					'duplicate'     => true,
				);
			}
			if ( ! in_array( $session['state'], array( ScanSessionState::CREATED, ScanSessionState::ACTIVE ), true ) ) {
				throw new RuntimeException( 'This scan session cannot be completed.' );
			}
			$this->sessions->update( $session['id'], array( 'state' => ScanSessionState::COMPLETING ) );
			$this->urls->update_lifecycle(
				$session['audit_url_id'],
				array(
					'status'       => AuditUrlStatus::COMPLETED,
					'completed_at' => $now,
				)
			);
			$this->audits->update_lifecycle(
				$session['audit_id'],
				array(
					'status'       => AuditStatus::COMPLETED,
					'completed_at' => $now,
				)
			);
			$this->sessions->update( $session['id'], array( 'state' => ScanSessionState::COMPLETED ) );
			$this->database->query( 'COMMIT' );
		} catch ( \Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			throw $error;
		}
		return array(
			'audit_id'      => (int) $session['audit_id'],
			'finding_count' => count( $findings ),
			'duplicate'     => false,
		);
	}

	/**
	 * Mark a safe failure.
	 *
	 * @param string $token   Credential.
	 * @param int    $user_id User ID.
	 * @param string $code    Safe code.
	 * @return void
	 * @throws \Throwable When authorization or persistence fails.
	 */
	public function fail( $token, $user_id, $code ) {
		$session = $this->guard->authorize( $token, $user_id, null, array( ScanSessionState::CREATED, ScanSessionState::ACTIVE ) );
		$allowed = array( 'scanner_error', 'readiness_timeout', 'transport_error', 'validation_rejected', 'result_limit_exceeded' );
		if ( ! in_array( $code, $allowed, true ) ) {
			$code = 'scanner_error'; }
		$this->database->query( 'START TRANSACTION' );
		try {
			$session = $this->sessions->lock( $session['id'] );
			$this->sessions->update(
				$session['id'],
				array(
					'state'        => ScanSessionState::FAILED,
					'failure_code' => $code,
				)
			);
			$this->urls->update_lifecycle(
				$session['audit_url_id'],
				array(
					'status'          => AuditUrlStatus::FAILED,
					'failure_code'    => $code,
					'failure_message' => 'The browser scan did not complete. Start a new scan and keep the page open.',
				)
			);
			$this->audits->update_lifecycle( $session['audit_id'], array( 'status' => AuditStatus::FAILED ) );
			$this->database->query( 'COMMIT' );
		} catch ( \Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			throw $error;
		}
	}
}
