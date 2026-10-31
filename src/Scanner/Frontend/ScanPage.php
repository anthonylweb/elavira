<?php
/**
 * Scoped public scan-page integration.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Frontend;

use Elavira\Scanner\ScanLimits;
use Elavira\Scanner\Security\CanonicalUrl;
use Elavira\Scanner\Security\SessionGuard;
use Elavira\Scanner\Values\ScanSessionState;
use Throwable;

/** Loads the scanner only for its owner on the bound canonical page. */
final class ScanPage {
	/** Session guard.
	 *
	 * @var SessionGuard
	 */
	private $guard;
	/** Canonical URL policy.
	 *
	 * @var CanonicalUrl
	 */
	private $canonical;

	/**
	 * Construct the page integration.
	 *
	 * @param SessionGuard $guard     Session guard.
	 * @param CanonicalUrl $canonical URL policy.
	 */
	public function __construct( SessionGuard $guard, CanonicalUrl $canonical ) {
		$this->guard     = $guard;
		$this->canonical = $canonical; }

	/** Conditionally enqueue scanner assets. @return void */
	public function enqueue() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only session credential; REST mutations require a separate nonce.
		$token = isset( $_GET['elavira_scan'] ) ? sanitize_text_field( wp_unslash( $_GET['elavira_scan'] ) ) : '';
		if ( '' === $token || ! is_user_logged_in() ) {
			return; }
		try {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) );
			$current     = $this->canonical->authorize( home_url( $request_uri ) );
			$session     = $this->guard->authorize( $token, get_current_user_id(), $current, array( ScanSessionState::CREATED, ScanSessionState::ACTIVE ) );
		} catch ( Throwable $error ) {
			return; }

		if ( function_exists( 'is_404' ) && is_404() ) {
			$audit         = ( new \Elavira\Audit\Persistence\AuditRepository( $GLOBALS['wpdb'] ) )->find( $session['audit_id'] );
			$configuration = (array) json_decode( $audit['configuration_json'], true );
			if ( empty( $configuration['audit_404'] ) ) {
				return; }
		}

		wp_enqueue_style( 'elavira-scanner', ELAVIRA_URL . 'assets/css/scanner.css', array(), ELAVIRA_VERSION );
		wp_enqueue_script( 'elavira-language-primary-subtags', ELAVIRA_URL . 'assets/js/language-primary-subtags.js', array(), ELAVIRA_VERSION, true );
		wp_enqueue_script( 'elavira-aria-vocabulary', ELAVIRA_URL . 'assets/js/aria-vocabulary.js', array(), ELAVIRA_VERSION, true );
		wp_enqueue_script( 'elavira-scanner', ELAVIRA_URL . 'assets/js/scanner.js', array( 'elavira-language-primary-subtags', 'elavira-aria-vocabulary' ), \Elavira\Scanner\Versions::SCANNER, true );
		wp_add_inline_script(
			'elavira-scanner',
			'window.ElaviraScanConfig=' . wp_json_encode(
				array(
					'session'  => $token,
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'restRoot' => esc_url_raw( rest_url( 'elavira/v1/scans/' ) ),
					'limits'   => array(
						'batch'       => ScanLimits::MAX_RESULTS_BATCH,
						'quietMs'     => ScanLimits::QUIET_TIME_MS,
						'stabilizeMs' => ScanLimits::STABILIZE_MAX_MS,
						'readyMaxMs'  => ScanLimits::READY_MAX_MS,
					),
					'messages' => array(
						'ariaLabel' => __( 'Elavira accessibility scan', 'elavira' ),
						'preparing' => __( 'Preparing accessibility scan. Keep this page open.', 'elavira' ),
						'scanning'  => __( 'Scanning the rendered page.', 'elavira' ),
						'saving'    => __( 'Saving accessibility findings.', 'elavira' ),
						'completed' => __( 'Scan completed.', 'elavira' ),
						'failed'    => __( 'The scan could not be completed. Start a new scan and keep the page open.', 'elavira' ),
						'view'      => __( 'View audit result', 'elavira' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/** Disable shared caching only after the credential and page binding validate. @return void */
	public function protect_cache() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only session credential; REST mutations require a separate nonce.
		$token = isset( $_GET['elavira_scan'] ) ? sanitize_text_field( wp_unslash( $_GET['elavira_scan'] ) ) : '';
		if ( '' === $token || ! is_user_logged_in() ) {
			return; }
		try {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) );
			$current     = $this->canonical->authorize( home_url( $request_uri ) );
			$session     = $this->guard->authorize( $token, get_current_user_id(), $current, array( ScanSessionState::CREATED, ScanSessionState::ACTIVE ) );
			if ( function_exists( 'is_404' ) && is_404() ) {
				$database      = $GLOBALS['wpdb'];
				$audits        = new \Elavira\Audit\Persistence\AuditRepository( $database );
				$audit         = $audits->find( $session['audit_id'] );
				$configuration = (array) json_decode( $audit['configuration_json'], true );
				if ( empty( $configuration['audit_404'] ) ) {
					$complete = new \Elavira\Scanner\Application\CompleteScan( $database, new \Elavira\Scanner\Persistence\ScanSessionRepository( $database ), $this->guard, $audits, new \Elavira\Audit\Persistence\AuditUrlRepository( $database ), new \Elavira\Findings\Persistence\FindingRepository( $database ) );
					$complete->fail( $token, get_current_user_id(), 'page_missing' );
					wp_die( esc_html__( 'The requested page could not be found. Return to Run Audit to correct the URL or explicitly choose Audit 404 page.', 'elavira' ), '', array( 'response' => 404 ) );
				}
			}
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true ); }
			nocache_headers();
		} catch ( Throwable $error ) {
			return; }
	}
}
