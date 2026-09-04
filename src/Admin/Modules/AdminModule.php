<?php
/**
 * Core administration integration.
 *
 * @package Elavira
 */

namespace Elavira\Admin\Modules;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.NonceVerification.Recommended -- Delegation methods and read-only page routing are intentionally concise.

use Elavira\Admin\DashboardPage;
use Elavira\Core\Capabilities\Capability;
use Elavira\Core\Contracts\Module;
use Elavira\Settings\Admin\SettingsPage;
use Elavira\Settings\CoreSettings;
use Elavira\Settings\CoreHealth;

/** Registers the Core Dashboard, Settings, and navigation helpers. */
final class AdminModule implements Module {
	/** Register hooks. @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'dashboard_menu' ), 5 );
		add_action( 'admin_menu', array( $this, 'settings_menu' ), 99 );
		add_action( 'admin_post_elavira_settings_save', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 90 );
		add_action( 'admin_notices', array( $this, 'health_notices' ) );
	}

	/** Register pages. @return void */
	public function dashboard_menu() {
		( new DashboardPage( $GLOBALS['wpdb'] ) )->register();
	}
	/** Register Settings after feature destinations. @return void */
	public function settings_menu() {
		( new SettingsPage( $GLOBALS['wpdb'] ) )->register(); }
	/** Save settings. @return void */
	public function save() {
		( new SettingsPage( $GLOBALS['wpdb'] ) )->save(); }
	/** Load scoped responsive admin CSS. @return void */
	public function enqueue() { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( in_array( $page, array( 'elavira-dashboard', 'elavira-settings' ), true ) ) {
			wp_enqueue_style( 'elavira-admin', ELAVIRA_URL . 'assets/css/admin.css', array(), ELAVIRA_VERSION ); }
	}

	/** Add an optional admin-bar shortcut. @param object $bar Admin bar. @return void */
	public function admin_bar( $bar ) {
		if ( CoreSettings::get()['admin_bar_visibility'] && current_user_can( Capability::VIEW_REPORTS ) ) {
			$bar->add_node(
				array(
					'id'    => 'elavira-dashboard',
					'title' => __( 'Elavira', 'elavira' ),
					'href'  => admin_url( 'admin.php?page=elavira-dashboard' ),
				)
			); }
	}

	/** Show only actionable Core health failures when enabled. @return void */
	public function health_notices() {
		if ( ! CoreSettings::get()['admin_notifications'] || ! current_user_can( Capability::MANAGE_SETTINGS ) ) {
			return; }
		foreach ( ( new CoreHealth( $GLOBALS['wpdb'] ) )->checks() as $check ) {
			if ( ! $check['healthy'] ) {
				echo '<div class="notice notice-error"><p><strong>' . esc_html( $check['label'] ) . ':</strong> ' . esc_html( $check['message'] ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=elavira-settings' ) ) . '">' . esc_html__( 'Review Elavira system status', 'elavira' ) . '</a></p></div>'; }
		}
	}
}
