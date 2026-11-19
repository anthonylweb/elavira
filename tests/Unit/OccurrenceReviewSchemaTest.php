<?php
/**
 * Retry-safe additive migration tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Migrations\OccurrenceReviewSchema;
use PHPUnit\Framework\TestCase;

final class OccurrenceReviewSchemaTest extends TestCase {
	public function test_columns_are_added_once_without_rewriting_history() {
		$db        = new OccurrenceSchemaDatabase();
		$migration = new OccurrenceReviewSchema( $db );
		$migration->up();
		$migration->up();
		$this->assertCount( 3, $db->queries );
		$this->assertSame( '0.8.0', $migration->version() );
		$this->assertStringContainsString( "DEFAULT ''", $db->queries[0] );
		foreach ( $db->queries as $query ) {
			$this->assertStringStartsWith( 'ALTER TABLE ', $query );
			$this->assertStringNotContainsString( 'DROP', $query );
			$this->assertStringNotContainsString( 'UPDATE', $query );
		}
	}
	public function test_failed_schema_verification_stops_migration() {
		$db                 = new OccurrenceSchemaDatabase();
		$db->ignore_changes = true;
		$this->expectException( \RuntimeException::class );
		( new OccurrenceReviewSchema( $db ) )->up();
	}
}

final class OccurrenceSchemaDatabase {
	public $prefix         = 'test_';
	public $queries        = array();
	public $columns        = array();
	public $ignore_changes = false;
	public function prepare( $query, $column ) {
		return array( $query, $column ); }
	public function get_var( $prepared ) {
		preg_match( '/FROM ([^ ]+)/', $prepared[0], $table );
		return isset( $this->columns[ $table[1] ][ $prepared[1] ] ) ? $prepared[1] : null;
	}
	public function query( $query ) {
		$this->queries[] = $query;
		if ( ! $this->ignore_changes ) {
			preg_match( '/ALTER TABLE ([^ ]+) ADD ([^ ]+)/', $query, $parts );
			$this->columns[ $parts[1] ][ $parts[2] ] = true;
		}
		return true;
	}
}
