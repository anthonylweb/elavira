<?php
/**
 * Core health checks.
 *
 * @package Elavira
 */

namespace Elavira\Settings;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment -- Compact value service comments are intentional.

use Elavira\Core\Database\TableNames;
use Elavira\Core\Version\VersionManager;

/** Runs bounded checks for dependencies Core actually uses. */
final class CoreHealth {
	/** Database. @var object */
	private $database;

	/** Construct. @param object $database WordPress database. */
	public function __construct( $database ) {
		$this->database = $database; }

	/** Return safe actionable checks. @return array */
	public function checks() {
		$tables  = new TableNames( $this->database->prefix );
		$root    = defined( 'ELAVIRA_DIR' ) ? ELAVIRA_DIR : dirname( __DIR__, 2 );
		$missing = array();
		foreach ( $tables->all() as $table ) {
			if ( $table !== $this->database->get_var( $this->database->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				$missing[] = $table;
			}
		}
		$schema_current = defined( 'ELAVIRA_SCHEMA_VERSION' ) && version_compare( ( new VersionManager() )->schema_version(), ELAVIRA_SCHEMA_VERSION, '>=' );
		return array(
			array(
				'label'   => __( 'Database persistence', 'elavira' ),
				'healthy' => empty( $missing ),
				'message' => empty( $missing ) ? __( 'All required Core data tables are available.', 'elavira' ) : __( 'Required data storage is incomplete. Deactivate and reactivate Elavira, then recheck.', 'elavira' ),
			),
			array(
				'label'   => __( 'Database schema', 'elavira' ),
				'healthy' => $schema_current,
				'message' => $schema_current ? __( 'The Elavira database schema is current.', 'elavira' ) : __( 'The database schema needs an update. Reload an administration page or reactivate Elavira.', 'elavira' ),
			),
			array(
				'label'   => __( 'Browser scanner assets', 'elavira' ),
				'healthy' => is_readable( $root . '/assets/js/scanner.js' ),
				'message' => is_readable( $root . '/assets/js/scanner.js' ) ? __( 'The Core browser scanner asset is available.', 'elavira' ) : __( 'The browser scanner asset is missing. Reinstall the complete Elavira package.', 'elavira' ),
			),
		);
	}
}
