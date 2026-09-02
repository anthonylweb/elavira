<?php
/**
 * Core single-page scan initiation.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Application;

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Audit\Values\AuditScope;
use Elavira\Audit\Values\AuditStatus;
use Elavira\Audit\Values\AuditUrlStatus;
use Elavira\Core\Capabilities\Capability;
use Elavira\Rules\RuleRegistry;
use Elavira\Scanner\Persistence\ScanSessionRepository;
use Elavira\Scanner\ScanLimits;
use Elavira\Scanner\Security\CanonicalUrl;
use Elavira\Scanner\Versions;
use RuntimeException;
use Throwable;

/** Creates the audit graph and short-lived browser credential. */
final class StartSinglePageScan {
	/** WordPress database object.
	 *
	 * @var object
	 */
	private $database;
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
	/** Session repository.
	 *
	 * @var ScanSessionRepository
	 */
	private $sessions;
	/** Trusted rules.
	 *
	 * @var RuleRegistry
	 */
	private $rules;
	/** URL policy.
	 *
	 * @var CanonicalUrl
	 */
	private $canonical;

	/**
	 * Construct the initiation service.
	 *
	 * @param object                $database  WordPress database object.
	 * @param AuditRepository       $audits    Audit repository.
	 * @param AuditUrlRepository    $urls      Audit URL repository.
	 * @param ScanSessionRepository $sessions Session repository.
	 * @param RuleRegistry          $rules     Trusted rules.
	 * @param CanonicalUrl          $canonical URL policy.
	 */
	public function __construct( $database, AuditRepository $audits, AuditUrlRepository $urls, ScanSessionRepository $sessions, RuleRegistry $rules, CanonicalUrl $canonical ) {
		$this->database  = $database;
		$this->audits    = $audits;
		$this->urls      = $urls;
		$this->sessions  = $sessions;
		$this->rules     = $rules;
		$this->canonical = $canonical;
	}

	/**
	 * Start a scan.
	 *
	 * @param string $requested_url Requested URL.
	 * @param string $wcag_target   WCAG target.
	 * @return array
	 * @throws RuntimeException When the user lacks authorization.
	 * @throws Throwable When authorization, validation, or persistence fails.
	 */
	public function start( $requested_url, $wcag_target = '2.2_AA' ) {
		if ( ! current_user_can( Capability::RUN_AUDITS ) ) {
			throw new RuntimeException( 'You are not allowed to run accessibility audits.' );
		}

		$user_id   = get_current_user_id();
		$canonical = $this->canonical->authorize( $requested_url );
		$versions  = array();
		foreach ( $this->rules->all() as $rule ) {
			$versions[ $rule->id() ] = $rule->version();
		}
		$this->database->query( 'START TRANSACTION' );
		try {
			$audit_id = $this->audits->create(
				array(
					/* translators: %s: localized audit start date and time. */
					'label'             => sprintf( __( 'Single-page audit — %s', 'elavira' ), wp_date( 'Y-m-d H:i' ) ),
					'scope'             => AuditScope::SINGLE_PAGE,
					'wcag_target'       => $wcag_target,
					'status'            => AuditStatus::RUNNING,
					'owner_user_id'     => $user_id,
					'configuration'     => array( 'source' => 'browser_session' ),
					'scanner_version'   => Versions::SCANNER,
					'ruleset_version'   => Versions::RULESET,
					'editorial_version' => Versions::EDITORIAL,
					'started_at'        => current_time( 'mysql', true ),
				)
			);
			$url_id   = $this->urls->create(
				array(
					'audit_id'      => $audit_id,
					'canonical_url' => $canonical,
					'status'        => AuditUrlStatus::SCANNING,
					'started_at'    => current_time( 'mysql', true ),
				)
			);
			$token    = $this->sessions->create(
				array(
					'audit_id'          => $audit_id,
					'audit_url_id'      => $url_id,
					'owner_user_id'     => $user_id,
					'canonical_url'     => $canonical,
					'wcag_target'       => $wcag_target,
					'ruleset_version'   => Versions::RULESET,
					'scanner_version'   => Versions::SCANNER,
					'editorial_version' => Versions::EDITORIAL,
					'allowed_rules'     => $versions,
					'ttl'               => ScanLimits::SESSION_TTL_SECONDS,
				)
			);

			$this->database->query( 'COMMIT' );
			return array(
				'scan_url'   => add_query_arg( 'elavira_scan', rawurlencode( $token ), $canonical ),
				'expires_in' => ScanLimits::SESSION_TTL_SECONDS,
			);
		} catch ( Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			throw $error;
		}
	}
}
