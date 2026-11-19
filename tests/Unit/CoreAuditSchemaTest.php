<?php
/**
 * Core audit schema tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Database\TableNames;
use Elavira\Core\Migrations\CoreAuditSchema;
use Elavira\Core\Migrations\CoreScanSessionSchema;
use Elavira\Core\Migrations\MigrationRunner;
use Elavira\Core\Version\VersionManager;
use Elavira\Tests\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CoreAuditSchemaTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['elavira_test_options'] = array();
		$GLOBALS['wpdb']                 = new FakeWpdb();
	}

	public function test_initial_migration_creates_all_core_tables_and_indexes() {
		$migration = new CoreAuditSchema( $GLOBALS['wpdb'] );
		$migration->up();
		$tables = new TableNames( $GLOBALS['wpdb']->prefix );

		$this->assertSame( array( $tables->audits(), $tables->audit_urls(), $tables->findings(), $tables->occurrences(), $tables->finding_status_history() ), array_keys( $GLOBALS['wpdb']->tables ) );
		$this->assertStringContainsString( 'KEY created_at (created_at)', $GLOBALS['wpdb']->schema_sql );
		$this->assertStringContainsString( 'UNIQUE KEY audit_url (audit_id, canonical_url_hash)', $GLOBALS['wpdb']->schema_sql );
		$this->assertStringContainsString( 'UNIQUE KEY audit_rule_group (audit_id, rule_id, group_key)', $GLOBALS['wpdb']->schema_sql );
		$this->assertStringContainsString( 'KEY finding_url (finding_id, audit_url_id)', $GLOBALS['wpdb']->schema_sql );
		$this->assertStringContainsString( 'KEY finding_created (finding_id, created_at)', $GLOBALS['wpdb']->schema_sql );
	}

	public function test_migration_runner_is_safe_to_repeat() {
		$runner = new MigrationRunner(
			new VersionManager(),
			array( new CoreAuditSchema( $GLOBALS['wpdb'] ) )
		);

		$runner->run( '0.2.0' );
		$runner->run( '0.2.0' );

		$this->assertCount( 5, $GLOBALS['wpdb']->tables );
		$this->assertSame( '0.2.0', get_option( VersionManager::SCHEMA_VERSION_OPTION ) );
	}

	public function test_scan_session_migration_adds_session_and_unique_batch_receipt_tables() {
		( new CoreAuditSchema( $GLOBALS['wpdb'] ) )->up();
		( new CoreScanSessionSchema( $GLOBALS['wpdb'] ) )->up();
		$tables = new TableNames( $GLOBALS['wpdb']->prefix );

		$this->assertArrayHasKey( $tables->scan_sessions(), $GLOBALS['wpdb']->tables );
		$this->assertArrayHasKey( $tables->scan_batches(), $GLOBALS['wpdb']->tables );
		$this->assertStringContainsString( 'UNIQUE KEY token_hash (token_hash)', $GLOBALS['wpdb']->schema_sql );
		$this->assertStringContainsString( 'UNIQUE KEY session_batch (session_id, batch_key)', $GLOBALS['wpdb']->schema_sql );
	}

	public function test_schema_version_does_not_advance_when_creation_cannot_be_verified() {
		$GLOBALS['wpdb']->fail_table_creation = true;
		$runner                               = new MigrationRunner(
			new VersionManager(),
			array( new CoreAuditSchema( $GLOBALS['wpdb'] ) )
		);

		try {
			$runner->run( '0.2.0' );
			$this->fail( 'Expected schema verification to fail.' );
		} catch ( RuntimeException $error ) {
			$this->assertSame( '0.0.0', get_option( VersionManager::SCHEMA_VERSION_OPTION, '0.0.0' ) );
		}
	}
}
