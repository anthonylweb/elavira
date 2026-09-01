<?php
/**
 * Minimal post-scan administrative destination.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Admin;

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Core\Capabilities\Capability;
use Elavira\Findings\Persistence\FindingRepository;

/** Confirms completion until the specified Reports UI is implemented. */
final class ScanResultPage {
	/** Audit repository.
	 *
	 * @var AuditRepository
	 */
	private $audits;
	/** Finding repository.
	 *
	 * @var FindingRepository
	 */
	private $findings;

	/**
	 * Construct the page.
	 *
	 * @param AuditRepository   $audits   Audit repository.
	 * @param FindingRepository $findings Finding repository.
	 */
	public function __construct( AuditRepository $audits, FindingRepository $findings ) {
		$this->audits   = $audits;
		$this->findings = $findings; }

	/** Register a non-menu admin destination. @return void */
	public function register() {
		add_submenu_page( null, __( 'Elavira audit result', 'elavira' ), __( 'Elavira audit result', 'elavira' ), Capability::VIEW_REPORTS, 'elavira-scan-result', array( $this, 'render' ) );
	}

	/** Render safe completion summary. @return void */
	public function render() {
		if ( ! current_user_can( Capability::VIEW_REPORTS ) ) {
			wp_die( esc_html__( 'You are not allowed to view audit results.', 'elavira' ) ); }
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This page performs a read-only lookup.
		$audit_id = isset( $_GET['audit'] ) ? absint( $_GET['audit'] ) : 0;
		$audit    = $this->audits->find( $audit_id );
		if ( ! $audit || get_current_user_id() !== (int) $audit['owner_user_id'] ) {
			wp_die( esc_html__( 'This audit result is not available.', 'elavira' ) ); }
		$findings = $this->findings->find_by_audit( $audit_id );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Accessibility scan completed', 'elavira' ); ?></h1>
			<p><?php echo esc_html( $audit['label'] ); ?></p>
			<?php /* translators: %d: number of grouped findings. */ ?>
			<p><?php echo esc_html( sprintf( _n( '%d grouped finding was recorded.', '%d grouped findings were recorded.', count( $findings ), 'elavira' ), count( $findings ) ) ); ?></p>
			<p><?php echo esc_html__( 'Automated testing is incomplete. Review findings and items requiring human judgment when the Reports interface becomes available.', 'elavira' ); ?></p>
		</div>
		<?php
	}
}
