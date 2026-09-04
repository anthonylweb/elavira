<?php
/**
 * Run Audit integration contract tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the launcher wiring and deliberately narrow Core UI. */
final class RunAuditPageTest extends TestCase {
	public function test_launcher_is_capability_and_nonce_protected() {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Scanner/Admin/RunAuditPage.php' );
		$this->assertStringContainsString( 'Capability::RUN_AUDITS', $source );
		$this->assertStringContainsString( "check_admin_referer( 'elavira_run_audit' )", $source );
		$this->assertStringContainsString( "wp_nonce_field( 'elavira_run_audit' )", $source );
		$this->assertStringContainsString( 'StartSinglePageScan', $source );
	}

	public function test_launcher_exposes_only_single_page_scope_and_wcag_a_or_aa() {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Scanner/Admin/RunAuditPage.php' );
		$this->assertStringContainsString( 'CoreSettings::get()', $source );
		$this->assertStringContainsString( 'value="2.2_A"', $source );
		$this->assertStringContainsString( 'value="2.2_AA"', $source );
		$this->assertStringContainsString( "'Single page'", $source );
		$this->assertStringNotContainsString( 'Site-wide', $source );
		$this->assertStringNotContainsString( 'Selected URLs', $source );
		$this->assertStringNotContainsString( 'Custom audit name', $source );
	}

	public function test_dashboard_and_audits_link_to_the_canonical_launcher() {
		$dashboard = file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/DashboardPage.php' );
		$reports   = file_get_contents( dirname( __DIR__, 2 ) . '/src/Reports/Admin/ReportsPage.php' );
		$this->assertStringContainsString( 'Run Your First Audit', $dashboard );
		$this->assertStringContainsString( 'Run New Audit', $dashboard );
		$this->assertStringContainsString( 'elavira-run-audit', $dashboard );
		$this->assertStringContainsString( 'elavira-run-audit', $reports );
		$this->assertStringContainsString( 'Capability::RUN_AUDITS', $dashboard );
		$this->assertStringContainsString( 'Capability::RUN_AUDITS', $reports );
	}

	public function test_contextual_links_are_capability_gated_and_credential_free() {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Scanner/Admin/ContextualAuditLinks.php' );
		$this->assertStringContainsString( 'Capability::RUN_AUDITS', $source );
		$this->assertStringContainsString( "'publish'", $source );
		$this->assertStringContainsString( 'is_post_type_viewable', $source );
		$this->assertStringContainsString( 'elavira-run-audit', $source );
		$this->assertStringNotContainsString( 'elavira_scan', $source );
	}
}
