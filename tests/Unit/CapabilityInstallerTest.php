<?php
/**
 * Capability installer tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Capabilities\Capability;
use Elavira\Core\Capabilities\CapabilityInstaller;
use PHPUnit\Framework\TestCase;

final class CapabilityInstallerTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['elavira_test_administrator'] = new RecordingRole();
	}

	public function test_installs_all_core_capabilities_for_administrators() {
		( new CapabilityInstaller() )->install();

		$this->assertSame( Capability::all(), array_keys( $GLOBALS['elavira_test_administrator']->capabilities ) );
	}

	public function test_installation_is_idempotent() {
		$installer = new CapabilityInstaller();
		$installer->install();
		$installer->install();

		$this->assertCount( count( Capability::all() ), $GLOBALS['elavira_test_administrator']->capabilities );
	}
}

final class RecordingRole {
	public $capabilities = array();

	public function add_cap( $capability ) {
		$this->capabilities[ $capability ] = true;
	}
}
