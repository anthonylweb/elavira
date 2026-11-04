<?php
/**
 * Submission remediation regressions.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Scanner\Security\CanonicalUrl;
use Elavira\Core\Lifecycle\UpgradeManager;
use Elavira\Core\Version\VersionManager;
use Elavira\Statement\Admin\StatementPage;
use Elavira\Tests\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

final class SubmissionRemediationTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['elavira_test_home_url']       = 'https://example.com/blog';
		$GLOBALS['elavira_test_site_url']       = 'https://example.com/blog/wordpress';
		$GLOBALS['elavira_test_url_objects']    = array();
		$GLOBALS['elavira_test_capabilities']   = array();
		$GLOBALS['elavira_test_inserted_posts'] = array();
	}
	protected function tearDown(): void {
		unset( $GLOBALS['elavira_test_home_url'], $GLOBALS['elavira_test_site_url'], $GLOBALS['elavira_test_rest_url'] );
	}
	public function test_subdirectory_request_preserves_page_and_public_query_identity() {
		$policy = new CanonicalUrl();
		$this->assertSame( 'https://example.com/blog/?lang=fr&p=42', $policy->current_request( '/blog/?p=42&lang=fr&elavira_scan=temporary' ) );
		$this->assertSame( 'https://example.com/blog/shop/?color=blue&paged=2', $policy->authorize( 'https://example.com/blog/shop/?paged=2&color=blue#heading' ) );
	}
	public function test_plain_permalinks_rest_root_does_not_block_public_pages() {
		$GLOBALS['elavira_test_rest_url'] = 'https://example.com/blog/?rest_route=/';
		$policy                           = new CanonicalUrl();
		$this->assertSame( 'https://example.com/blog/?p=42', $policy->current_request( '/blog/?p=42' ) );
		$this->expectException( \InvalidArgumentException::class );
		$policy->current_request( '/blog/?rest_route=/wp/v2' );
	}

	public function test_internal_and_ambiguous_paths_are_rejected_under_both_install_paths() {
		$policy = new CanonicalUrl();
		foreach ( array( '/blog/wp-admin/', '/blog/wordpress/wp-login.php', '/blog/wp-json/wp/v2', '/blog/wordpress/xmlrpc.php', '/blog/wp-cron.php', '/blog/wp-comments-post.php', '/blog/%77p-admin/', '/blog/public/../wp-admin/', '/blog/%2577p-admin/', '/outside/' ) as $path ) {
			try {
				$policy->current_request( $path );
				$this->fail( $path ); } catch ( \InvalidArgumentException $error ) {
							$this->assertNotEmpty( $error->getMessage() ); }
		}
		$GLOBALS['elavira_test_rest_url'] = 'https://example.com/blog/api/';
		$this->expectException( \InvalidArgumentException::class );
		$policy->current_request( '/blog/api/wp/v2' );
	}
	public function test_sensitive_encoded_nested_and_internal_query_parameters_are_rejected() {
		foreach ( array( 'access_token=abc', '_wpnonce=abc', 'filter[apiKey]=abc', 'e%6Dail=person%40example.com', 'q=person%40example.com', 'rest_route=/wp/v2', 'preview=true', 'sessionId=abc', 'password=abc', 'signature=abc' ) as $query ) {
			try {
				( new CanonicalUrl() )->current_request( '/blog/?' . $query );
				$this->fail( $query ); } catch ( \InvalidArgumentException $error ) {
							$this->assertSame( 'sensitive_query', $error->getMessage() ); }
		}
	}
	/**
	 * Exercise recovery against the complete release schema in an isolated process.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_failed_upgrade_retains_versions_and_data_then_retries() {
		if ( ! defined( 'ELAVIRA_VERSION' ) ) {
			define( 'ELAVIRA_VERSION', '0.2.6' ); }
		if ( ! defined( 'ELAVIRA_SCHEMA_VERSION' ) ) {
			define( 'ELAVIRA_SCHEMA_VERSION', '0.8.0' ); }
		$database                              = new FakeWpdb();
		$database->tables['wp_elavira_audits'] = array(
			array(
				'id'    => 99,
				'label' => 'Retain me',
			),
		);
		$before                                = $database->tables;
		$database->fail_table_creation         = true;
		$GLOBALS['wpdb']                       = $database;
		$GLOBALS['elavira_test_options']       = array(
			VersionManager::PLUGIN_VERSION_OPTION => '0.1.0',
			VersionManager::SCHEMA_VERSION_OPTION => '0.0.0',
		);
		$manager                               = new UpgradeManager();
		$manager->maybe_upgrade();
		$this->assertTrue( UpgradeManager::has_failed() );
		$this->assertSame( '0.1.0', get_option( VersionManager::PLUGIN_VERSION_OPTION ) );
		$this->assertSame( '0.0.0', get_option( VersionManager::SCHEMA_VERSION_OPTION ) );
		$this->assertSame( $before, $database->tables );
		$frontend = new \Elavira\Statement\FrontendStatement( new \Elavira\Statement\StatementRepository( $database ), new \Elavira\Statement\StatementRenderer() );
		$this->assertSame( 'Original page content', $frontend->filter_content( 'Original page content' ) );
		$start                                 = new \Elavira\Scanner\Application\StartSinglePageScan( $database, new \Elavira\Audit\Persistence\AuditRepository( $database ), new \Elavira\Audit\Persistence\AuditUrlRepository( $database ), new \Elavira\Scanner\Persistence\ScanSessionRepository( $database ), \Elavira\Rules\CoreRuleRegistry::create(), new CanonicalUrl() );
		$GLOBALS['elavira_test_http_requests'] = array();
		try {
			$start->start( 'https://example.com/blog/' );
			$this->fail(); } catch ( \RuntimeException $error ) {
			$this->assertSame( 'database_update_failed', $error->getMessage() ); }
			$this->assertSame( array(), $GLOBALS['elavira_test_http_requests'] );
			$this->assertSame( $before, $database->tables );
			ob_start();
			$manager->notice();
			$this->assertSame( '', ob_get_clean() );
			$GLOBALS['elavira_test_capabilities']['manage_options'] = true;
			ob_start();
			$manager->notice();
			$notice = ob_get_clean();
			$this->assertStringContainsString( 'reload to retry', $notice );
			$database->fail_table_creation = false;
			$manager->maybe_upgrade();
			$this->assertFalse( UpgradeManager::has_failed() );
			$this->assertSame( ELAVIRA_VERSION, get_option( VersionManager::PLUGIN_VERSION_OPTION ) );
			$this->assertSame( '0.8.0', get_option( VersionManager::SCHEMA_VERSION_OPTION ) );
			$this->assertSame( $before['wp_elavira_audits'], $database->tables['wp_elavira_audits'] );
	}
	public function test_statement_publishing_requires_page_capabilities_and_object_access() {
		$page   = new StatementPage( array() );
		$method = new \ReflectionMethod( $page, 'publish_page' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		foreach ( array(
			array(),
			array( 'publish_pages' => true ),
			array( 'edit_pages' => true ),
			array(
				'edit_pages'    => true,
				'publish_pages' => true,
				'edit_post:17'  => false,
			),
		) as $caps ) {
			$GLOBALS['elavira_test_capabilities'] = $caps;
			try {
				$method->invoke( $page, array( 'post_id' => 17 ), array( 'title' => 'Statement' ) );
				$this->fail(); } catch ( \RuntimeException $error ) {
							$this->assertSame( 'publish_forbidden', $error->getMessage() ); }
		}
		$this->assertSame( array(), $GLOBALS['elavira_test_inserted_posts'] );
		$GLOBALS['elavira_test_capabilities'] = array(
			'edit_pages'    => true,
			'publish_pages' => true,
			'edit_post:17'  => true,
		);
		$this->assertSame( 17, $method->invoke( $page, array( 'post_id' => 17 ), array( 'title' => 'Statement' ) ) );
		$this->assertSame( 123, $method->invoke( $page, array( 'post_id' => 0 ), array( 'title' => 'Statement' ) ) );
	}
	public function test_early_state_uses_head_script_and_before_configuration_only_when_enabled() {
		if ( ! defined( 'ELAVIRA_URL' ) ) {
			define( 'ELAVIRA_URL', 'https://example.com/plugins/elavira/' ); }
		if ( ! defined( 'ELAVIRA_VERSION' ) ) {
			define( 'ELAVIRA_VERSION', '0.2.6' ); }
		$GLOBALS['elavira_test_scripts'] = array();
		$GLOBALS['elavira_test_options'] = array();
		$frontend                        = new \Elavira\Toolbar\FrontendToolbar( new \Elavira\Statement\StatementRepository( new FakeWpdb() ) );
		$frontend->enqueue();
		$this->assertSame( array(), $GLOBALS['elavira_test_scripts'] );
		$settings                          = \Elavira\Toolbar\ToolbarSettings::defaults();
		$settings['enabled']               = true;
		$settings['controls']['grayscale'] = false;
		update_option( \Elavira\Toolbar\ToolbarSettings::OPTION, $settings );
		$frontend->enqueue();
		$this->assertFalse( $GLOBALS['elavira_test_scripts']['elavira-toolbar-state']['footer'] );
		$this->assertTrue( $GLOBALS['elavira_test_scripts']['elavira-toolbar']['footer'] );
		$this->assertSame( 'before', $GLOBALS['elavira_test_inline']['elavira-toolbar-state']['position'] );
		$this->assertStringNotContainsString( 'grayscale', $GLOBALS['elavira_test_inline']['elavira-toolbar-state']['data'] );
	}
}
