<?php
/**
 * Core audit persistence schema.
 *
 * @package Elavira
 */

namespace Elavira\Core\Migrations;

use Elavira\Core\Database\TableNames;
use RuntimeException;

/**
 * Creates the relational tables used by Core single-page audits.
 */
final class CoreAuditSchema implements Migration {
	/**
	 * WordPress database object.
	 *
	 * @var object
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @param object $database WordPress database object.
	 */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/**
	 * Return the schema version introduced by this migration.
	 *
	 * @return string
	 */
	public function version() {
		return '0.2.0';
	}

	/**
	 * Create and verify the Core audit tables.
	 *
	 * @return void
	 * @throws RuntimeException When table creation cannot be completed or verified.
	 */
	public function up() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$tables  = new TableNames( $this->database->prefix );
		$charset = $this->database->get_charset_collate();
		$sql     = $this->schema_sql( $tables, $charset );

		$this->database->last_error = '';
		dbDelta( $sql );

		if ( '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Elavira could not create its Core audit tables.' );
		}

		foreach ( array( $tables->audits(), $tables->audit_urls(), $tables->findings(), $tables->occurrences(), $tables->finding_status_history() ) as $table ) {
			$found = $this->database->get_var(
				$this->database->prepare( 'SHOW TABLES LIKE %s', $table )
			);

			if ( $table !== $found ) {
				throw new RuntimeException( 'Elavira could not verify its Core audit tables.' );
			}
		}
	}

	/**
	 * Build dbDelta-compatible schema SQL.
	 *
	 * @param TableNames $tables  Table names.
	 * @param string     $charset Charset and collation clause.
	 * @return string
	 */
	private function schema_sql( TableNames $tables, $charset ) {
		return "CREATE TABLE {$tables->audits()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		label varchar(191) NOT NULL,
		scope varchar(32) NOT NULL,
		wcag_target varchar(16) NOT NULL,
		status varchar(32) NOT NULL,
		owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		configuration_json longtext NULL,
		scanner_version varchar(64) NOT NULL,
		ruleset_version varchar(64) NOT NULL,
		editorial_version varchar(64) NOT NULL,
		started_at datetime NULL,
		completed_at datetime NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY created_at (created_at),
		KEY status_created (status, created_at)
	) {$charset};

	CREATE TABLE {$tables->audit_urls()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		audit_id bigint(20) unsigned NOT NULL,
		canonical_url text NOT NULL,
		canonical_url_hash char(64) NOT NULL,
		object_id bigint(20) unsigned NOT NULL DEFAULT 0,
		object_type varchar(64) NOT NULL DEFAULT '',
		status varchar(32) NOT NULL,
		failure_code varchar(64) NOT NULL DEFAULT '',
		failure_message varchar(1000) NOT NULL DEFAULT '',
		started_at datetime NULL,
		completed_at datetime NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY audit_url (audit_id, canonical_url_hash),
		KEY audit_status (audit_id, status)
	) {$charset};

	CREATE TABLE {$tables->findings()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		audit_id bigint(20) unsigned NOT NULL,
		rule_id varchar(191) NOT NULL,
		rule_version varchar(64) NOT NULL,
		group_key varchar(191) NOT NULL,
		category varchar(32) NOT NULL,
		priority varchar(16) NOT NULL,
		review_state varchar(32) NOT NULL,
		current_status varchar(32) NOT NULL,
		wcag_criterion varchar(32) NOT NULL DEFAULT '',
		wcag_level varchar(8) NOT NULL DEFAULT '',
		wcag_principle varchar(32) NOT NULL DEFAULT '',
		editorial_version varchar(64) NOT NULL,
		summary_snapshot text NOT NULL,
		affected_page_count bigint(20) unsigned NOT NULL DEFAULT 0,
		occurrence_count bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY audit_rule_group (audit_id, rule_id, group_key),
		KEY audit_status (audit_id, current_status),
		KEY rule_group (rule_id, group_key)
	) {$charset};

	CREATE TABLE {$tables->occurrences()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		finding_id bigint(20) unsigned NOT NULL,
		audit_url_id bigint(20) unsigned NOT NULL,
		target_json text NOT NULL,
		evidence_json text NOT NULL,
		element_json text NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY finding_id (finding_id),
		KEY audit_url_id (audit_url_id),
		KEY finding_url (finding_id, audit_url_id)
	) {$charset};

	CREATE TABLE {$tables->finding_status_history()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		finding_id bigint(20) unsigned NOT NULL,
		status varchar(32) NOT NULL,
		actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		reason varchar(1000) NOT NULL DEFAULT '',
		source_audit_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY finding_created (finding_id, created_at),
		KEY source_audit_id (source_audit_id)
	) {$charset};";
	}
}
