<?php
/**
 * Core module tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Modules\CoreModule;
use PHPUnit\Framework\TestCase;

final class CoreModuleTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['elavira_test_actions'] = array();
	}

	public function test_registers_the_upgrade_check() {
		( new CoreModule() )->register();

		$this->assertArrayHasKey( 'plugins_loaded', $GLOBALS['elavira_test_actions'] );
		$this->assertCount( 1, $GLOBALS['elavira_test_actions']['plugins_loaded'][5] );
	}
}
