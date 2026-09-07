<?php
/**
 * Durable page-scan observation staging.
 *
 * @package Elavira
 */

namespace Elavira\Core\Migrations;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment, Generic.Commenting.DocComment.MissingShort -- Concise migration contracts match existing migrations.

use Elavira\Core\Database\TableNames;
use RuntimeException;

/** Adds retry-safe staging used before whole-page supersession and persistence. */
final class ScanObservationSchema implements Migration {
	/** @var object */
	private $database;

	/** @param object $database WordPress database object. */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/** @return string */
	public function version() {
		return '0.7.0';
	}

	/** @return void */
	public function up() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		$tables                     = new TableNames( $this->database->prefix );
		$charset                    = $this->database->get_charset_collate();
		$sql                        = "CREATE TABLE {$tables->scan_observations()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		session_id bigint(20) unsigned NOT NULL,
		batch_key char(64) NOT NULL,
		result_index int(10) unsigned NOT NULL,
		normalized_json longtext NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY session_batch_result (session_id, batch_key, result_index),
		KEY session_id (session_id)
	) {$charset};";
		$this->database->last_error = '';
		dbDelta( $sql );
		if ( '' !== $this->database->last_error || $tables->scan_observations() !== $this->database->get_var( $this->database->prepare( 'SHOW TABLES LIKE %s', $tables->scan_observations() ) ) ) {
			throw new RuntimeException( 'Elavira could not install scan observation staging.' );
		}
	}
}
