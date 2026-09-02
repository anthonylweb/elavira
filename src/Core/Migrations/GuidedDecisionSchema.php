<?php
/**
 * Completed Guided Decision schema.
 *
 * @package Elavira
 */

namespace Elavira\Core\Migrations;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment -- Migration comments follow the concise contract style.

use Elavira\Core\Database\TableNames;
use RuntimeException;

/** Adds bounded audit history for completed human decisions. */
final class GuidedDecisionSchema implements Migration {
	/** Database connection. @var object */
	private $database;

	/** Create the migration. @param object $database Database connection. */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/** Return the migration version. @return string */
	public function version() {
		return '0.4.0';
	}

	/** Create and verify the decision history table. @return void */
	public function up() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		$table   = ( new TableNames( $this->database->prefix ) )->guided_decisions();
		$charset = $this->database->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		finding_id bigint(20) unsigned NOT NULL,
		decision_id varchar(191) NOT NULL,
		decision_version varchar(64) NOT NULL,
		answers_json text NOT NULL,
		outcome_id varchar(64) NOT NULL,
		recommendation text NOT NULL,
		actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		completed_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY finding_completed (finding_id, completed_at),
		KEY decision_version (decision_id, decision_version)
	) {$charset};"
		);
		if ( $table !== $this->database->get_var( $this->database->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			throw new RuntimeException( 'Elavira could not verify its Guided Decision table.' );
		}
	}
}
