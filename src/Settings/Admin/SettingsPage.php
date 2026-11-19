<?php
/**
 * Core Settings administration.
 *
 * @package Elavira
 */

namespace Elavira\Settings\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.WP.I18n.MissingTranslatorsComment -- Save verifies its nonce before bounded input; routing and notices are read-only.

use Elavira\Core\Capabilities\Capability;
use Elavira\Core\Version\VersionManager;
use Elavira\Settings\CoreHealth;
use Elavira\Settings\CoreSettings;

/** Server-rendered Core Settings tabs. */
final class SettingsPage {
	/** Database. @var object */
	private $database;
	/** Construct. @param object $database Database. */
	public function __construct( $database ) {
		$this->database = $database; }

	/** Register page. @return void */
	public function register() {
		add_submenu_page( 'elavira-dashboard', __( 'Elavira Settings', 'elavira' ), __( 'Settings', 'elavira' ), Capability::MANAGE_SETTINGS, 'elavira-settings', array( $this, 'render' ), 50 );
	}

	/** Render selected tab. @return void */
	public function render() {
		$this->authorize();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab routing.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		if ( ! in_array( $tab, array( 'general', 'audit-defaults', 'data-privacy' ), true ) ) {
			$tab = 'general'; }
		?>
		<div class="wrap elavira-admin"><h1><?php echo esc_html__( 'Elavira Settings', 'elavira' ); ?></h1><?php $this->notice(); ?>
		<nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr__( 'Settings sections', 'elavira' ); ?>">
		<?php
		foreach ( array(
			'general'        => __( 'General', 'elavira' ),
			'audit-defaults' => __( 'Audit Defaults', 'elavira' ),
			'data-privacy'   => __( 'Data & Privacy', 'elavira' ),
		) as $id => $label ) :
			?>
	<a class="nav-tab <?php echo $tab === $id ? 'nav-tab-active' : ''; ?>" <?php echo $tab === $id ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-settings&tab=' . $id ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav>
		<?php
		if ( 'general' === $tab ) {
			$this->general();
		} elseif ( 'audit-defaults' === $tab ) {
			$this->audit_defaults();
		} else {
			$this->privacy(); }
		?>
		</div>
		<?php
	}

	/** General settings and health. @return void */
	private function general() {
		$settings = CoreSettings::get();
		?>
		<h2><?php echo esc_html__( 'General', 'elavira' ); ?></h2><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="elavira_settings_save"><?php wp_nonce_field( 'elavira_settings_save' ); ?>
		<fieldset><legend><strong><?php echo esc_html__( 'Default WCAG target for new audits', 'elavira' ); ?></strong></legend><label><input type="radio" name="default_wcag_target" value="2.2_A" <?php checked( $settings['default_wcag_target'], '2.2_A' ); ?>> <?php echo esc_html__( 'WCAG 2.2 Level A', 'elavira' ); ?></label><br><label><input type="radio" name="default_wcag_target" value="2.2_AA" <?php checked( $settings['default_wcag_target'], '2.2_AA' ); ?>> <?php echo esc_html__( 'WCAG 2.2 Level AA', 'elavira' ); ?></label></fieldset>
		<p><label><input type="checkbox" name="admin_notifications" value="1" <?php checked( $settings['admin_notifications'] ); ?>> <?php echo esc_html__( 'Show actionable Elavira administration notices', 'elavira' ); ?></label></p><p><label><input type="checkbox" name="admin_bar_visibility" value="1" <?php checked( $settings['admin_bar_visibility'] ); ?>> <?php echo esc_html__( 'Show an Elavira Dashboard link in the administration bar', 'elavira' ); ?></label></p><?php submit_button( __( 'Save General Settings', 'elavira' ) ); ?></form>
		<h2><?php echo esc_html__( 'Core system status', 'elavira' ); ?></h2><dl class="elavira-health">
		<?php
		foreach ( ( new CoreHealth( $this->database ) )->checks() as $check ) :
			?>
			<div><dt><?php echo esc_html( $check['label'] ); ?></dt><dd><strong><?php echo esc_html( $check['healthy'] ? __( 'Available', 'elavira' ) : __( 'Needs attention', 'elavira' ) ); ?>:</strong> <?php echo esc_html( $check['message'] ); ?></dd></div><?php endforeach; ?><div><dt><?php echo esc_html__( 'Environment', 'elavira' ); ?></dt><dd>
			<?php
			/* translators: 1: WordPress version, 2: PHP version, 3: Elavira version, 4: Database schema version. */
			echo esc_html( sprintf( __( 'WordPress %1$s; PHP %2$s; Elavira %3$s; schema %4$s.', 'elavira' ), get_bloginfo( 'version' ), PHP_VERSION, ELAVIRA_VERSION, ( new VersionManager() )->schema_version() ) );
			?>
		</dd></div></dl>
		<?php
	}

	/** Audit defaults without duplicate mutation UI. @return void */
	private function audit_defaults() {
		$target = CoreSettings::get()['default_wcag_target'];
		echo '<h2>' . esc_html__( 'Audit Defaults', 'elavira' ) . '</h2><p>' . esc_html__( 'Core runs rendered single-page browser audits. Scanner readiness limits and evidence limits are fixed safety behavior, not configurable defaults.', 'elavira' ) . '</p><dl><div><dt>' . esc_html__( 'Default WCAG target', 'elavira' ) . '</dt><dd>' . esc_html( '2.2_AA' === $target ? __( 'WCAG 2.2 Level AA', 'elavira' ) : __( 'WCAG 2.2 Level A', 'elavira' ) ) . ' — <a href="' . esc_url( admin_url( 'admin.php?page=elavira-settings&tab=general' ) ) . '">' . esc_html__( 'Change in General settings', 'elavira' ) . '</a></dd></div></dl>';
	}

	/** Data disclosures. @return void */
	private function privacy() {
		echo '<p>' . esc_html( \Elavira\Core\Privacy\PrivacyPolicy::text() ) . '</p>';
		?>
		<h2><?php echo esc_html__( 'Data & Privacy', 'elavira' ); ?></h2><h3><?php echo esc_html__( 'Core audit data', 'elavira' ); ?></h3><p><?php echo esc_html__( 'Audit reports, grouped findings, bounded evidence, workflow history, completed Guided Decisions, and Statement history are stored in this WordPress database. Core reports are retained indefinitely; Core provides no scheduled deletion or retention control.', 'elavira' ); ?></p><h3><?php echo esc_html__( 'Visitor preferences', 'elavira' ); ?></h3><p><?php echo esc_html__( 'Toolbar presentation choices are stored only in the visitor’s browser. Elavira does not transmit them to WordPress or treat them as disability information.', 'elavira' ); ?></p><h3><?php echo esc_html__( 'Uninstall behavior', 'elavira' ); ?></h3><p><?php echo esc_html__( 'Deactivation preserves Elavira data. This build does not automatically delete Core records or published WordPress pages.', 'elavira' ); ?></p>
		<?php
	}

	/** Save supported settings only. @return void */
	public function save() {
		$this->authorize();
		check_admin_referer( 'elavira_settings_save' );
		$raw = array(
			'default_wcag_target'  => isset( $_POST['default_wcag_target'] ) ? sanitize_text_field( wp_unslash( $_POST['default_wcag_target'] ) ) : '',
			'admin_notifications'  => isset( $_POST['admin_notifications'] ) ? '1' : '',
			'admin_bar_visibility' => isset( $_POST['admin_bar_visibility'] ) ? '1' : '',
		);
		update_option( CoreSettings::OPTION, CoreSettings::sanitize( $raw ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=elavira-settings&tab=general&updated=1' ) );
		exit;
	}

	/** Save feedback. @return void */
	private function notice() { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect marker.
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Settings saved.', 'elavira' ) . '</p></div>'; }
	}

	/** Require settings capability. @return void */
	private function authorize() {
		if ( ! current_user_can( Capability::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Elavira settings.', 'elavira' ), '', array( 'response' => 403 ) ); } }
}
