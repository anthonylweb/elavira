<?php
/**
 * Core browser scan-session schema.
 *
 * @package Elavira
 */

namespace Elavira\Core\Migrations;

use Elavira\Core\Database\TableNames;
use RuntimeException;

/** Creates coordination tables for one-page browser scans, not queue jobs. */
final class CoreScanSessionSchema implements Migration {
	/** WordPress database object.
	 *
	 * @var object
	 */
	private $database;

	/**
	 * Construct the migration.
	 *
	 * @param object $database WordPress database object.
	 */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/**
	 * Return the schema version.
	 *
	 * @return string
	 */
	public function version() {
		return '0.3.0';
	}

	/**
	 * Create and verify scan-session tables.
	 *
	 * @return void
	 * @throws RuntimeException When table verification fails.
	 */
	public function up() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$tables  = new TableNames( $this->database->prefix );
		$charset = $this->database->get_charset_collate();
		$sql     = "CREATE TABLE {$tables->scan_sessions()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		token_hash char(64) NOT NULL,
		audit_id bigint(20) unsigned NOT NULL,
		audit_url_id bigint(20) unsigned NOT NULL,
		owner_user_id bigint(20) unsigned NOT NULL,
		canonical_url text NOT NULL,
		canonical_url_hash char(64) NOT NULL,
		wcag_target varchar(16) NOT NULL,
		ruleset_version varchar(64) NOT NULL,
		scanner_version varchar(64) NOT NULL,
		editorial_version varchar(64) NOT NULL,
		allowed_rules_json text NOT NULL,
		state varchar(32) NOT NULL,
		total_results bigint(20) unsigned NOT NULL DEFAULT 0,
		failure_code varchar(64) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		expires_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY token_hash (token_hash),
		KEY owner_state (owner_user_id, state),
		KEY expires_at (expires_at)
	) {$charset};

	CREATE TABLE {$tables->scan_batches()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		session_id bigint(20) unsigned NOT NULL,
		batch_key varchar(64) NOT NULL,
		result_count int(10) unsigned NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY session_batch (session_id, batch_key),
		KEY session_id (session_id)
	) {$charset};";

		$this->database->last_error = '';
		dbDelta( $sql );

		foreach ( array( $tables->scan_sessions(), $tables->scan_batches() ) as $table ) {
			if ( $table !== $this->database->get_var( $this->database->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				throw new RuntimeException( 'Elavira could not verify its scan-session tables.' );
			}
		}
	}
}
