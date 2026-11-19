<?php
/**
 * Visitor Toolbar administration.
 *
 * @package Elavira
 */

namespace Elavira\Toolbar\Admin;

// phpcs:disable WordPress.Security.NonceVerification.Missing -- Save verifies its action before reading input.

use Elavira\Core\Capabilities\Capability;
use Elavira\Toolbar\ToolbarSettings;

/** Provides the minimum Phase 1 Toolbar configuration screen. */
final class ToolbarPage {
	/** Labels keyed by control ID. @return array */
	private function labels() {
		return array(
			'text_size'       => __( 'Text Size', 'elavira' ),
			'text_spacing'    => __( 'Text Spacing', 'elavira' ),
			'line_height'     => __( 'Line Height', 'elavira' ),
			'high_contrast'   => __( 'High Contrast', 'elavira' ),
			'appearance'      => __( 'Appearance', 'elavira' ),
			'grayscale'       => __( 'Grayscale', 'elavira' ),
			'highlight_links' => __( 'Highlight Links', 'elavira' ),
			'enhanced_focus'  => __( 'Enhanced Focus', 'elavira' ),
			'reading_guide'   => __( 'Reading Guide', 'elavira' ),
			'readable_font'   => __( 'Dyslexia-Friendly Font', 'elavira' ),
		);
	}

	/** Register page. @return void */
	public function register() {
		add_submenu_page( 'elavira-dashboard', __( 'Visitor Toolbar', 'elavira' ), __( 'Toolbar', 'elavira' ), Capability::MANAGE_TOOLBAR, 'elavira-toolbar', array( $this, 'render' ), 30 );
	}

	/** Render page. @return void */
	public function render() {
		$this->authorize();
		$settings = ToolbarSettings::get();
		?>
		<div class="wrap elavira-toolbar-admin"><h1><?php echo esc_html__( 'Visitor Accessibility Toolbar', 'elavira' ); ?></h1>
		<p><?php echo esc_html__( 'Offer optional browsing preferences. The toolbar does not fix accessibility defects or establish conformance.', 'elavira' ); ?></p>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect notice marker.
		if ( isset( $_GET['updated'] ) ) :
			?>
			<div class="notice notice-success inline"><p><?php echo esc_html__( 'Toolbar settings saved.', 'elavira' ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="elavira_toolbar_save"><?php wp_nonce_field( 'elavira_toolbar_save' ); ?>
		<p><label><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>> <?php echo esc_html__( 'Enable the visitor toolbar', 'elavira' ); ?></label></p>
		<fieldset><legend><strong><?php echo esc_html__( 'Available preferences', 'elavira' ); ?></strong></legend>
		<?php
		foreach ( $this->labels() as $id => $label ) :
			?>
			<p><label><input type="checkbox" name="controls[<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( $settings['controls'][ $id ] ); ?>> <?php echo esc_html( $label ); ?></label></p><?php endforeach; ?>
		<p><?php echo esc_html__( 'Reset Preferences is always available.', 'elavira' ); ?></p></fieldset>
		<p><label for="elavira-toolbar-position"><strong><?php echo esc_html__( 'Position', 'elavira' ); ?></strong></label><br><select id="elavira-toolbar-position" name="position">
		<?php
		foreach ( array(
			'bottom_right' => __( 'Bottom Right', 'elavira' ),
			'bottom_left'  => __( 'Bottom Left', 'elavira' ),
			'top_right'    => __( 'Top Right', 'elavira' ),
			'top_left'     => __( 'Top Left', 'elavira' ),
		) as $value => $label ) :
			?>
																<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['position'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
		</select></p><?php submit_button( __( 'Save Toolbar Settings', 'elavira' ) ); ?> <a class="button" href="<?php echo esc_url( home_url( '/?elavira_toolbar_preview=1' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Preview on Site', 'elavira' ); ?></a></form></div>
		<?php
	}

	/** Save settings. @return void */
	public function save() {
		$this->authorize();
		check_admin_referer( 'elavira_toolbar_save' );
		$raw_controls = isset( $_POST['controls'] ) && is_array( $_POST['controls'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['controls'] ) ) : array();
		$raw          = array(
			'enabled'  => isset( $_POST['enabled'] ) ? sanitize_key( wp_unslash( $_POST['enabled'] ) ) : '',
			'position' => isset( $_POST['position'] ) ? sanitize_key( wp_unslash( $_POST['position'] ) ) : '',
			'controls' => $raw_controls,
		);
		update_option( ToolbarSettings::OPTION, ToolbarSettings::sanitize( $raw ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=elavira-toolbar&updated=1' ) );
		exit;
	}

	/** Require Toolbar management. @return void */
	private function authorize() {
		if ( ! current_user_can( Capability::MANAGE_TOOLBAR ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the visitor toolbar.', 'elavira' ), '', array( 'response' => 403 ) );
		}
	}
}
