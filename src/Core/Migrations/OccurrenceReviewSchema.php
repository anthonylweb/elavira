<?php
/**
 * Independent occurrence review storage, preserving grouped history.
 *
 * @package Elavira
 */

namespace Elavira\Core\Migrations;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, Squiz.Commenting.FunctionCommentThrowTag, Generic.Files.LineLength

/** Additive, retry-safe migration. Legacy decisions are resolved conservatively on read. */
final class OccurrenceReviewSchema implements Migration {
	private $database;
	public function __construct( $database ) {
		$this->database = $database;
	}
	public function version() {
		return '0.8.0';
	}
	public function up() {
		$tables = new \Elavira\Core\Database\TableNames( $this->database->prefix );
		foreach ( array( array( $tables->occurrences(), 'current_status', "varchar(32) NOT NULL DEFAULT ''" ), array( $tables->occurrences(), 'previous_occurrence_id', 'bigint(20) unsigned NOT NULL DEFAULT 0' ), array( $tables->finding_status_history(), 'occurrence_id', 'bigint(20) unsigned NOT NULL DEFAULT 0' ) ) as $column ) {
			if ( ! $this->database->get_var( $this->database->prepare( "SHOW COLUMNS FROM {$column[0]} LIKE %s", $column[1] ) ) ) {
				if ( false === $this->database->query( "ALTER TABLE {$column[0]} ADD {$column[1]} {$column[2]}" ) ) {
					throw new \RuntimeException( 'Could not install occurrence review storage.' );
				}
				if ( ! $this->database->get_var( $this->database->prepare( "SHOW COLUMNS FROM {$column[0]} LIKE %s", $column[1] ) ) ) {
					throw new \RuntimeException( 'Occurrence review storage verification failed.' );
				}
			}
		}
	}
}
