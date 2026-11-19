<?php
/**
 * Version option management.
 *
 * @package Elavira
 */

namespace Elavira\Core\Version;

/**
 * Owns the persisted plugin and schema versions.
 */
final class VersionManager {
	const PLUGIN_VERSION_OPTION = 'elavira_version';
	const SCHEMA_VERSION_OPTION = 'elavira_schema_version';

	/**
	 * Read the installed schema version.
	 *
	 * @return string
	 */
	public function schema_version() {
		return (string) get_option( self::SCHEMA_VERSION_OPTION, '0.0.0' );
	}

	/**
	 * Persist the installed plugin version as a non-autoloaded option.
	 *
	 * @param string $version Plugin version.
	 * @return void
	 */
	public function set_plugin_version( $version ) {
		update_option( self::PLUGIN_VERSION_OPTION, $version, false );
	}

	/**
	 * Persist the installed schema version as a non-autoloaded option.
	 *
	 * @param string $version Schema version.
	 * @return void
	 */
	public function set_schema_version( $version ) {
		update_option( self::SCHEMA_VERSION_OPTION, $version, false );
	}
}
