<?php
/**
 * Lifecycle tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Capabilities\Capability;
use Elavira\Core\Lifecycle\Activator;
use Elavira\Core\Lifecycle\Deactivator;
use Elavira\Core\Version\VersionManager;
use PHPUnit\Framework\TestCase;

final class LifecycleTest extends TestCase {
	protected function setUp(): void {
		if ( ! defined( 'ELAVIRA_VERSION' ) ) {
			define( 'ELAVIRA_VERSION', '0.1.0' );
		}

		if ( ! defined( 'ELAVIRA_SCHEMA_VERSION' ) ) {
			define( 'ELAVIRA_SCHEMA_VERSION', '0.1.0' );
		}

		$GLOBALS['elavira_test_options']       = array();
		$GLOBALS['elavira_test_administrator'] = new RecordingRole();
		$GLOBALS['elavira_test_fired_actions'] = array();
	}

	public function test_activation_installs_versions_and_capabilities() {
		Activator::activate();

		$this->assertSame( '0.1.0', get_option( VersionManager::PLUGIN_VERSION_OPTION ) );
		$this->assertSame( '0.1.0', get_option( VersionManager::SCHEMA_VERSION_OPTION ) );
		$this->assertSame( Capability::all(), array_keys( $GLOBALS['elavira_test_administrator']->capabilities ) );
		$this->assertArrayHasKey( 'elavira_activated', $GLOBALS['elavira_test_fired_actions'] );
	}

	public function test_deactivation_preserves_persistent_data() {
		Activator::activate();
		$before = $GLOBALS['elavira_test_options'];

		Deactivator::deactivate();

		$this->assertSame( $before, $GLOBALS['elavira_test_options'] );
		$this->assertArrayHasKey( 'elavira_deactivated', $GLOBALS['elavira_test_fired_actions'] );
	}
}
