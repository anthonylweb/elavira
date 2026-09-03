<?php
/**
 * Accessibility Statement schema.
 *
 * @package Elavira
 */

namespace Elavira\Core\Migrations;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment -- Migration comments follow concise project conventions.

use Elavira\Core\Database\TableNames;
use RuntimeException;

/** Creates structured statements, recommendations, and published revisions. */
final class StatementSchema implements Migration {
	/** Database connection. @var object */
	private $database;

	/** Create the migration. @param object $database Database connection. */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/** Return the migration version. @return string */
	public function version() {
		return '0.5.0';
	}

	/** Create and verify Statement tables. @return void */
	public function up() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		$tables  = new TableNames( $this->database->prefix );
		$charset = $this->database->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$tables->statements()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		post_id bigint(20) unsigned NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'draft',
		fields_json longtext NOT NULL,
		published_fields_json longtext NOT NULL,
		owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		last_reviewed date NULL,
		published_at datetime NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id), KEY post_id (post_id), KEY status (status)
	) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$tables->statement_recommendations()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		statement_id bigint(20) unsigned NOT NULL DEFAULT 0,
		audit_id bigint(20) unsigned NOT NULL,
		finding_id bigint(20) unsigned NOT NULL,
		guided_decision_id bigint(20) unsigned NOT NULL DEFAULT 0,
		source_fingerprint char(64) NOT NULL,
		kind varchar(32) NOT NULL,
		state varchar(20) NOT NULL DEFAULT 'pending',
		suggested_text text NOT NULL,
		approved_text text NOT NULL,
		dismissal_reason varchar(64) NOT NULL,
		created_at datetime NOT NULL,
		reviewed_at datetime NULL,
		PRIMARY KEY  (id), UNIQUE KEY source_fingerprint (source_fingerprint), KEY statement_state (statement_id, state), KEY finding_id (finding_id)
	) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$tables->statement_revisions()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		statement_id bigint(20) unsigned NOT NULL,
		fields_json longtext NOT NULL,
		actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		published_at datetime NOT NULL,
		PRIMARY KEY  (id), KEY statement_published (statement_id, published_at)
	) {$charset};"
		);
		foreach ( array( $tables->statements(), $tables->statement_recommendations(), $tables->statement_revisions() ) as $table ) {
			if ( $table !== $this->database->get_var( $this->database->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				throw new RuntimeException( 'Elavira could not verify its Accessibility Statement tables.' );
			}
		}
	}
}
