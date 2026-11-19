<?php
/**
 * Plugin upgrade coordination.
 *
 * @package Elavira
 */

namespace Elavira\Core\Lifecycle;

use Elavira\Core\Version\VersionManager;

/**
 * Detects code/schema version changes and invokes repeatable installation.
 */
final class UpgradeManager {
	/**
	 * Whether schema initialization failed during this request.
	 *
	 * @var bool
	 */
	private static $failed = false;

	/**
	 * Report request-local schema availability without exposing database errors.
	 *
	 * @return bool
	 */
	public static function has_failed() {
		return self::$failed;
	}

	/**
	 * Run installation when persisted versions trail the running code.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		self::$failed     = false;
		$versions         = new VersionManager();
		$plugin_version   = (string) get_option( VersionManager::PLUGIN_VERSION_OPTION, '0.0.0' );
		$schema_version   = $versions->schema_version();
		$plugin_is_behind = version_compare( $plugin_version, ELAVIRA_VERSION, '<' );
		$schema_is_behind = version_compare( $schema_version, ELAVIRA_SCHEMA_VERSION, '<' );

		if ( $plugin_is_behind || $schema_is_behind ) {
			try {
				( new Installer() )->install();
			} catch ( \Throwable $error ) {
				self::$failed = true;
				add_action( 'admin_notices', array( $this, 'notice' ) );
			}
		}
	}

	/**
	 * Display a recoverable failure without raw SQL or exception details.
	 *
	 * @return void
	 */
	public function notice() {
		if ( self::$failed && current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Elavira could not finish updating its database. Existing audit data has been retained. Ask your site administrator to check database permissions and available storage, then reload to retry. Avoid running audits until the update succeeds.', 'elavira' ) . '</p></div>';
		}
	}
}
