<?php
/**
 * Scoped scanner cache signaling regressions.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Scanner\Frontend\ScanPage;
use Elavira\Scanner\Modules\ScannerModule;
use Elavira\Scanner\Persistence\ScanSessionRepository;
use Elavira\Scanner\Security\CanonicalUrl;
use Elavira\Scanner\Security\SessionGuard;
use Elavira\Tests\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

/**
 * Isolate the process-wide cache constant between request scenarios.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ScanCacheTest extends TestCase {
	private $database;
	private $page;

	protected function setUp(): void {
		$this->database                          = new FakeWpdb();
		$GLOBALS['wpdb']                         = $this->database;
		$GLOBALS['elavira_test_current_user_id'] = 7;
		$GLOBALS['elavira_test_is_admin']        = false;
		$GLOBALS['elavira_test_nocache_headers'] = 0;
		$GLOBALS['elavira_test_404_checks']      = 0;
		$GLOBALS['elavira_test_is_404']          = false;
		$token                                   = str_repeat( 'a', 64 );
		$_GET                                    = array( 'elavira_scan' => $token );
		$_SERVER['REQUEST_URI']                  = '/article/?elavira_scan=' . $token;
		$this->database->tables['wp_elavira_scan_sessions'] = array(
			1 => array(
				'id'                 => 1,
				'audit_id'           => 1,
				'audit_url_id'       => 1,
				'owner_user_id'      => 7,
				'token_hash'         => hash( 'sha256', $token ),
				'canonical_url_hash' => hash( 'sha256', 'https://example.com/article/' ),
				'expires_at'         => gmdate( 'Y-m-d H:i:s', time() + 900 ),
				'state'              => 'created',
			),
		);
		$this->database->tables['wp_elavira_audits']        = array(
			1 => array(
				'id'                 => 1,
				'configuration_json' => '{"audit_404":true}',
			),
		);
		$this->database->tables['wp_elavira_audit_urls']    = array( 1 => array( 'id' => 1 ) );
		$this->page = new ScanPage( new SessionGuard( $this->database, new ScanSessionRepository( $this->database ), new AuditRepository( $this->database ), new AuditUrlRepository( $this->database ) ), new CanonicalUrl() );
	}

	public function test_early_hook_authorizes_then_signals_without_reading_main_query() {
		$module = new ScannerModule();
		$module->register();
		$this->assertContains( array( $module, 'protect_cache_early' ), $GLOBALS['elavira_test_actions']['wp_loaded'][0] );
		$this->assertContains( array( $module, 'protect_cache' ), $GLOBALS['elavira_test_actions']['template_redirect'][1] );
		$module->protect_cache_early();
		$this->assertTrue( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );
		$this->assertSame( 1, $GLOBALS['elavira_test_nocache_headers'] );
		$this->assertSame( 0, $GLOBALS['elavira_test_404_checks'] );
		$module->protect_cache();
		$this->assertSame( 1, $GLOBALS['elavira_test_404_checks'] );
		$this->assertSame( 2, $GLOBALS['elavira_test_nocache_headers'] );
	}

	public function test_existing_constant_is_never_redefined_and_headers_are_still_sent() {
		define( 'DONOTCACHEPAGE', false );
		$this->page->protect_cache( false );
		$this->assertFalse( DONOTCACHEPAGE );
		$this->assertSame( 1, $GLOBALS['elavira_test_nocache_headers'] );
	}

	public function test_active_scan_and_explicit_404_scan_keep_their_behavior() {
		$this->database->tables['wp_elavira_scan_sessions'][1]['state'] = 'active';
		$GLOBALS['elavira_test_is_404']                                 = true;
		$this->page->protect_cache( false );
		$this->assertSame( 0, $GLOBALS['elavira_test_404_checks'] );
		$this->page->protect_cache();
		$this->assertTrue( DONOTCACHEPAGE );
		$this->assertSame( 'active', $this->database->tables['wp_elavira_scan_sessions'][1]['state'] );
	}

	/**
	 * Verify unrelated or unauthorized requests never opt out of caching.
	 *
	 * @dataProvider ignored_requests
	 * @param string $scenario Request scenario.
	 */
	public function test_unrelated_or_unauthorized_requests_do_not_signal_cache_bypass( $scenario ) {
		switch ( $scenario ) {
			case 'normal':
				$_GET = array();
				break;
			case 'toolbar':
				$_GET = array( 'elavira_toolbar_preview' => '1' );
				break;
			case 'guest':
				$GLOBALS['elavira_test_current_user_id'] = 0;
				break;
			case 'other_owner':
				$GLOBALS['elavira_test_current_user_id'] = 8;
				break;
			case 'invalid_token':
				$_GET['elavira_scan'] = 'invalid';
				break;
			case 'other_page':
				$_SERVER['REQUEST_URI'] = '/different/';
				break;
			case 'admin':
				$GLOBALS['elavira_test_is_admin'] = true;
				break;
			case 'rest':
				define( 'REST_REQUEST', true );
				break;
			case 'rest_before_dispatch':
				$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts?elavira_scan=' . str_repeat( 'a', 64 );
				break;
			case 'plain_rest_before_dispatch':
				$_SERVER['REQUEST_URI'] = '/?rest_route=/wp/v2/posts&elavira_scan=' . str_repeat( 'a', 64 );
				break;
			case 'cron':
				define( 'DOING_CRON', true );
				break;
			case 'expired':
				$this->database->tables['wp_elavira_scan_sessions'][1]['expires_at'] = '2000-01-01 00:00:00';
				break;
			default:
				$this->database->tables['wp_elavira_scan_sessions'][1]['state'] = $scenario;
		}
		$this->page->protect_cache( false );
		$this->page->protect_cache();
		$this->assertFalse( defined( 'DONOTCACHEPAGE' ), $scenario );
		$this->assertSame( 0, $GLOBALS['elavira_test_nocache_headers'], $scenario );
	}

	public function ignored_requests() {
		return array_map(
			static function ( $scenario ) {
				return array( $scenario );
			},
			array( 'normal', 'toolbar', 'guest', 'other_owner', 'invalid_token', 'other_page', 'admin', 'rest', 'rest_before_dispatch', 'plain_rest_before_dispatch', 'cron', 'expired', 'completed', 'failed', 'cancelled' )
		);
	}
}
