<?php
/**
 * Dashboard and Core Settings integration tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Database\TableNames;
use Elavira\Settings\CoreHealth;
use Elavira\Settings\CoreSettings;
use Elavira\Tests\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

final class AdminIntegrationTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['elavira_test_options'] = array();
	}

	public function test_core_settings_have_safe_fresh_install_defaults() {
		$settings = CoreSettings::get();
		$this->assertSame( '2.2_AA', $settings['default_wcag_target'] );
		$this->assertTrue( $settings['admin_notifications'] );
		$this->assertTrue( $settings['admin_bar_visibility'] );
	}

	public function test_invalid_target_is_normalized_and_toolbar_option_is_untouched() {
		$GLOBALS['elavira_test_options']['elavira_toolbar_settings'] = array( 'enabled' => true );
		$settings = CoreSettings::sanitize(
			array(
				'default_wcag_target' => 'AAA',
				'admin_notifications' => 1,
			)
		);
		$this->assertSame( '2.2_AA', $settings['default_wcag_target'] );
		$this->assertSame( array( 'enabled' => true ), get_option( 'elavira_toolbar_settings' ) );
	}

	public function test_health_reports_current_tables_and_schema_without_forensic_details() {
		$GLOBALS['elavira_test_options']['elavira_schema_version'] = '0.5.0';
		$database = new FakeWpdb();
		foreach ( ( new TableNames( $database->prefix ) )->all() as $table ) {
			$database->create_table( $table ); }
		$checks = ( new CoreHealth( $database ) )->checks();
		$this->assertCount( 3, $checks );
		$this->assertTrue( $checks[0]['healthy'] );
		$this->assertTrue( $checks[2]['healthy'] );
		$this->assertStringNotContainsString( 'SELECT ', implode( ' ', array_column( $checks, 'message' ) ) );
	}

	public function test_dashboard_and_settings_preserve_required_boundaries() {
		$dashboard = file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/DashboardPage.php' );
		$settings  = file_get_contents( dirname( __DIR__, 2 ) . '/src/Settings/Admin/SettingsPage.php' );
		$this->assertStringContainsString( 'Capability::VIEW_REPORTS', $dashboard );
		$this->assertStringContainsString( 'Capability::MANAGE_SETTINGS', $settings );
		$this->assertStringContainsString( "check_admin_referer( 'elavira_settings_save' )", $settings );
		$this->assertStringNotContainsString( 'X% compliant', $dashboard );
		$this->assertStringNotContainsString( 'site grade', strtolower( $dashboard ) );
		foreach ( array( 'Content & URLs', 'report retention control', 'Developer diagnostics', 'permission-management' ) as $premium ) {
			$this->assertStringNotContainsString( $premium, $settings ); }
	}

	public function test_admin_navigation_uses_dashboard_root_and_stable_feature_slugs() {
		$sources = '';
		foreach ( array( 'src/Admin/DashboardPage.php', 'src/Reports/Admin/ReportsPage.php', 'src/Statement/Admin/StatementPage.php', 'src/Toolbar/Admin/ToolbarPage.php', 'src/Learn/Modules/LearnModule.php', 'src/Settings/Admin/SettingsPage.php' ) as $file ) {
			$sources .= file_get_contents( dirname( __DIR__, 2 ) . '/' . $file ); }
		foreach ( array( 'elavira-dashboard', 'elavira-audits', 'elavira-statement', 'elavira-toolbar', 'elavira-learn', 'elavira-settings' ) as $slug ) {
			$this->assertStringContainsString( $slug, $sources ); }
	}
}
