<?php
/**
 * Regression coverage for occurrence report updates.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Scanner\Security\PageResponse;
use PHPUnit\Framework\TestCase;

final class PageResponseTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['elavira_test_http_requests']  = array();
		$GLOBALS['elavira_test_http_responses'] = array();
		$GLOBALS['elavira_test_url_objects']    = array();
	}
	protected function tearDown(): void {
		$GLOBALS['elavira_test_http_responses'] = array();
	}
	/**
	 * Distinguishes response categories.
	 *
	 * @dataProvider responses
	 * @param int    $code HTTP code.
	 * @param string $expected Failure code.
	 */
	public function test_failures_are_distinct( $code, $expected ) {
		$GLOBALS['elavira_test_http_responses'][] = array( 'response' => array( 'code' => $code ) );
		$this->expectExceptionMessage( $expected );
		( new PageResponse() )->check( 'https://example.com/missing' );
	}
	public function responses() {
		return array( array( 404, 'page_missing' ), array( 401, 'page_blocked' ), array( 403, 'page_blocked' ), array( 500, 'response_failed' ), array( 503, 'response_failed' ), array( 302, 'redirect_failed' ) );
	}
	public function test_404_requires_explicit_intent_and_does_not_accept_success() {
		$GLOBALS['elavira_test_http_responses'][] = array( 'response' => array( 'code' => 404 ) );
		$this->assertSame( 'https://example.com/missing', ( new PageResponse() )->check( 'https://example.com/missing', true ) );
		$this->expectExceptionMessage( 'expected_404' );
		( new PageResponse() )->check( 'https://example.com/ok', true );
	}
	public function test_redirects_are_revalidated_without_cookies_or_automatic_redirects() {
		$GLOBALS['elavira_test_http_responses'][] = array(
			'response' => array( 'code' => 301 ),
			'headers'  => array( 'location' => '/final' ),
		);
		$this->assertSame( 'https://example.com/final', ( new PageResponse() )->check( 'https://example.com/start' ) );
		$this->assertCount( 2, $GLOBALS['elavira_test_http_requests'] );
		$this->assertSame( 0, $GLOBALS['elavira_test_http_requests'][0][1]['redirection'] );
		$this->assertSame( array(), $GLOBALS['elavira_test_http_requests'][0][1]['cookies'] );
		$GLOBALS['elavira_test_http_responses'][] = array(
			'response' => array( 'code' => 302 ),
			'headers'  => array( 'location' => 'https://external.example/private' ),
		);
		$this->expectException( \InvalidArgumentException::class );
		( new PageResponse() )->check( 'https://example.com/start' );
	}
	public function test_transport_error_is_not_a_missing_page() {
		$GLOBALS['elavira_test_http_responses'][] = new \WP_Error( 'http_request_failed', 'Timed out' );
		$this->expectExceptionMessage( 'request_failed' );
		( new PageResponse() )->check( 'https://example.com/slow' );
	}
	public function test_redirect_loop_is_bounded() {
		$GLOBALS['elavira_test_http_responses'] = array_fill(
			0,
			6,
			array(
				'response' => array( 'code' => 302 ),
				'headers'  => array( 'location' => '/loop' ),
			)
		);
		$this->expectExceptionMessage( 'redirect_failed' );
		( new PageResponse() )->check( 'https://example.com/loop' );
	}
}
