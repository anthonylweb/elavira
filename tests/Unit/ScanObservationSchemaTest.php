<?php
/**
 * Durable scan observation schema tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Database\TableNames;
use Elavira\Core\Migrations\ScanObservationSchema;
use Elavira\Tests\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

/** Verifies durable observation staging migration. */
final class ScanObservationSchemaTest extends TestCase {
	public function test_migration_is_idempotent_and_verifies_staging_table() {
		$database        = new FakeWpdb();
		$GLOBALS['wpdb'] = $database;
		$migration       = new ScanObservationSchema( $database );
		$migration->up();
		$migration->up();
		$this->assertSame( '0.7.0', $migration->version() );
		$this->assertArrayHasKey( ( new TableNames( $database->prefix ) )->scan_observations(), $database->tables );
		$this->assertStringContainsString( 'UNIQUE KEY session_batch_result', $database->schema_sql );
	}
}
