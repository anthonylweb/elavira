<?php
/**
 * Core single-page audit launcher.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment -- Compact integration methods remain clear at the class boundary.

use Elavira\Core\Capabilities\Capability;
use Elavira\Scanner\Application\StartSinglePageScan;
use Elavira\Settings\CoreSettings;
use InvalidArgumentException;
use Throwable;

/** Presents a progressive, server-backed entry point into the browser scanner. */
final class RunAuditPage {
	/** Initiation service.
	 *
	 * @var StartSinglePageScan
	 */ private $start;
	/** Create the page. @param StartSinglePageScan $start Initiation service. */
	public function __construct( StartSinglePageScan $start ) {
		$this->start = $start; }
	/** Register hidden canonical launcher route. */
	public function register() {
		add_submenu_page( null, __( 'Run Audit', 'elavira' ), __( 'Run Audit', 'elavira' ), Capability::RUN_AUDITS, 'elavira-run-audit', array( $this, 'render' ) ); }
	/** Render launcher. */
	public function render() {
		$this->authorize();
		$settings = CoreSettings::get();
		$url      = home_url( '/' );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only convenience value, revalidated on submission.
		if ( isset( $_GET['url'] ) ) {
			$candidate = esc_url_raw( wp_unslash( $_GET['url'] ) );
			if ( $candidate ) {
				$url = $candidate; }
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap elavira-run-audit"><h1><?php echo esc_html__( 'Run Audit', 'elavira' ); ?></h1><?php $this->notice(); ?>
		<p><?php echo esc_html__( 'Elavira will open and scan one public page as it is rendered for visitors. JavaScript-driven page changes are included when present.', 'elavira' ); ?></p>
		<p><strong><?php echo esc_html__( 'Keep the scan tab open until Elavira completes the audit and opens the full report.', 'elavira' ); ?></strong></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="elavira_run_audit"><?php wp_nonce_field( 'elavira_run_audit' ); ?>
		<h2><?php echo esc_html__( 'Audit details', 'elavira' ); ?></h2><p><strong><?php echo esc_html__( 'Scope:', 'elavira' ); ?></strong> <?php echo esc_html__( 'Single page', 'elavira' ); ?></p>
		<p><label for="elavira-audit-url"><strong><?php echo esc_html__( 'Public page URL', 'elavira' ); ?></strong></label><br><input class="regular-text code" id="elavira-audit-url" name="audit_url" type="url" value="<?php echo esc_attr( $url ); ?>" required aria-describedby="elavira-audit-url-help"></p>
		<p class="description" id="elavira-audit-url-help"><?php echo esc_html__( 'Enter a public URL on this WordPress site. Administrative, login, internal, private, and external pages cannot be scanned.', 'elavira' ); ?></p>
		<p><label><input type="checkbox" name="audit_404" value="1"> <?php echo esc_html__( 'Audit 404 page — intentionally scan the not-found page at this URL', 'elavira' ); ?></label></p>
		<fieldset><legend><strong><?php echo esc_html__( 'WCAG target', 'elavira' ); ?></strong></legend><p>
		<label><input type="radio" name="wcag_target" value="2.2_A" <?php checked( '2.2_A', $settings['default_wcag_target'] ); ?>> <?php echo esc_html__( 'WCAG 2.2 Level A', 'elavira' ); ?></label><br>
		<label><input type="radio" name="wcag_target" value="2.2_AA" <?php checked( '2.2_AA', $settings['default_wcag_target'] ); ?>> <?php echo esc_html__( 'WCAG 2.2 Level AA', 'elavira' ); ?></label></p></fieldset>
		<?php submit_button( __( 'Open Page and Run Audit', 'elavira' ) ); ?></form></div>
		<?php
	}
	/** Launch through the existing application service. */
	public function launch() {
		$this->authorize();
		check_admin_referer( 'elavira_run_audit' );
		// phpcs:disable WordPress.Security.NonceVerification -- Verified immediately above.
		$url    = isset( $_POST['audit_url'] ) ? esc_url_raw( wp_unslash( $_POST['audit_url'] ) ) : '';
		$target = isset( $_POST['wcag_target'] ) ? sanitize_text_field( wp_unslash( $_POST['wcag_target'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification
		try {
			$result = $this->start->start( $url, $target, isset( $_POST['audit_404'] ) && '1' === $_POST['audit_404'] );
			wp_safe_redirect( $result['scan_url'] );
			exit; } catch ( InvalidArgumentException $error ) {
			$this->redirect_failure( 'invalid_target' ); } catch ( Throwable $error ) {
				$code = $error->getMessage();
				$this->redirect_failure( in_array( $code, array( 'page_missing', 'page_blocked', 'request_failed', 'response_failed', 'redirect_failed', 'expected_404' ), true ) ? $code : 'launch_failed' ); }
	}
	/** Require capability. */
	private function authorize() {
		if ( ! is_user_logged_in() || ! current_user_can( Capability::RUN_AUDITS ) ) {
			wp_die( esc_html__( 'You are not allowed to run accessibility audits.', 'elavira' ), '', array( 'response' => 403 ) ); } }
	/** Redirect safely. @param string $code Safe feedback code. */
	private function redirect_failure( $code ) {
		wp_safe_redirect( add_query_arg( 'elavira_notice', $code, admin_url( 'admin.php?page=elavira-run-audit' ) ) );
		exit; }
	/** Render safe feedback. */
	private function notice() {
		$notice   = sanitize_key( (string) filter_input( INPUT_GET, 'elavira_notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$messages = array(
			'page_missing'    => __( 'The requested page could not be found (HTTP 404). Check the URL, or explicitly choose Audit 404 page.', 'elavira' ),
			'page_blocked'    => __( 'The page requires authentication or blocked the request (HTTP 401/403). No audit was created.', 'elavira' ),
			'request_failed'  => __( 'The page request failed or timed out. Check connectivity and try again.', 'elavira' ),
			'response_failed' => __( 'The server returned an unsuccessful response. No audit was created.', 'elavira' ),
			'redirect_failed' => __( 'The page redirect could not be followed safely. Enter the final public URL.', 'elavira' ),
			'expected_404'    => __( 'Audit 404 page requires a URL that returns HTTP 404. This URL returned a successful response.', 'elavira' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice notice-error inline" role="alert"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
		if ( 'invalid_target' === $notice ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The audit could not start. Enter a public URL on this WordPress site and try again.', 'elavira' ) . '</p></div>'; } elseif ( 'launch_failed' === $notice ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The audit could not start or be saved. Check the page URL and try again.', 'elavira' ) . '</p></div>'; }
	}
}
