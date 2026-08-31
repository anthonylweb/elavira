<?php
/**
 * Migration runner tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Migrations\Migration;
use Elavira\Core\Migrations\MigrationRunner;
use Elavira\Core\Version\VersionManager;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['elavira_test_options'] = array();
	}

	public function test_runs_pending_migrations_in_version_order() {
		$history    = array();
		$migrations = array(
			new RecordingMigration( '0.2.0', $history ),
			new RecordingMigration( '0.1.0', $history ),
		);

		( new MigrationRunner( new VersionManager(), $migrations ) )->run( '0.2.0' );

		$this->assertSame( array( '0.1.0', '0.2.0' ), $history );
		$this->assertSame( '0.2.0', get_option( VersionManager::SCHEMA_VERSION_OPTION ) );
	}

	public function test_does_not_repeat_applied_migrations() {
		$history   = array();
		$migration = new RecordingMigration( '0.1.0', $history );
		$runner    = new MigrationRunner( new VersionManager(), array( $migration ) );

		$runner->run( '0.1.0' );
		$runner->run( '0.1.0' );

		$this->assertSame( array( '0.1.0' ), $history );
	}

	public function test_initializes_schema_version_without_feature_tables() {
		( new MigrationRunner( new VersionManager() ) )->run( '0.1.0' );

		$this->assertSame( '0.1.0', get_option( VersionManager::SCHEMA_VERSION_OPTION ) );
	}
}

final class RecordingMigration implements Migration {
	private $migration_version;
	private $history;

	public function __construct( $version, array &$history ) {
		$this->migration_version = $version;
		$this->history           = &$history;
	}

	public function version() {
		return $this->migration_version;
	}

	public function up() {
		$this->history[] = $this->migration_version;
	}
}
