<?php
/**
 * Scanner module wiring tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Scanner\Modules\ScannerModule;
use Elavira\Tests\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

final class ScannerModuleTest extends TestCase {
	public function test_registers_all_rest_routes_with_initialized_dependencies() {
		$GLOBALS['wpdb']                     = new FakeWpdb();
		$GLOBALS['elavira_test_rest_routes'] = array();

		( new ScannerModule() )->register_routes();

		$this->assertSame(
			array(
				'elavira/v1/scans',
				'elavira/v1/scans/batches',
				'elavira/v1/scans/complete',
				'elavira/v1/scans/fail',
			),
			array_keys( $GLOBALS['elavira_test_rest_routes'] )
		);
	}
}
