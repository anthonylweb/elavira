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
	 * Run installation when persisted versions trail the running code.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		$versions         = new VersionManager();
		$plugin_version   = (string) get_option( VersionManager::PLUGIN_VERSION_OPTION, '0.0.0' );
		$schema_version   = $versions->schema_version();
		$plugin_is_behind = version_compare( $plugin_version, ELAVIRA_VERSION, '<' );
		$schema_is_behind = version_compare( $schema_version, ELAVIRA_SCHEMA_VERSION, '<' );

		if ( $plugin_is_behind || $schema_is_behind ) {
			( new Installer() )->install();
		}
	}
}
