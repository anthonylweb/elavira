<?php
/**
 * Repeatable plugin installation operations.
 *
 * @package Elavira
 */

namespace Elavira\Core\Lifecycle;

use Elavira\Core\Capabilities\CapabilityInstaller;
use Elavira\Core\Migrations\CoreAuditSchema;
use Elavira\Core\Migrations\CoreScanSessionSchema;
use Elavira\Core\Migrations\GuidedDecisionSchema;
use Elavira\Core\Migrations\MigrationRunner;
use Elavira\Core\Migrations\StatementSchema;
use Elavira\Core\Version\VersionManager;

/**
 * Coordinates the idempotent work shared by activation and upgrades.
 */
final class Installer {
	/**
	 * Apply the current foundation version.
	 *
	 * @return void
	 */
	public function install() {
		$versions = new VersionManager();
		$runner   = new MigrationRunner(
			$versions,
			array(
				new CoreAuditSchema( $GLOBALS['wpdb'] ),
				new CoreScanSessionSchema( $GLOBALS['wpdb'] ),
				new GuidedDecisionSchema( $GLOBALS['wpdb'] ),
				new StatementSchema( $GLOBALS['wpdb'] ),
			)
		);

		$runner->run( ELAVIRA_SCHEMA_VERSION );
		( new CapabilityInstaller() )->install();
		$versions->set_plugin_version( ELAVIRA_VERSION );
	}
}
