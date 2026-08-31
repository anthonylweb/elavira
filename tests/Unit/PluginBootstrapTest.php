<?php
/**
 * Plugin bootstrap tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PluginBootstrapTest extends TestCase {
	/**
	 * Verify the real entry file boots against WordPress hook contracts.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_entry_file_registers_wordpress_lifecycle_hooks() {
		define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );

		require dirname( __DIR__, 2 ) . '/elavira.php';

		$this->assertArrayHasKey( ELAVIRA_FILE, $GLOBALS['elavira_test_activation_hooks'] );
		$this->assertArrayHasKey( ELAVIRA_FILE, $GLOBALS['elavira_test_deactivation_hooks'] );
		$this->assertArrayHasKey( 'plugins_loaded', $GLOBALS['elavira_test_actions'] );
	}

	public function test_entry_file_contains_discoverable_plugin_header() {
		$contents = file_get_contents( dirname( __DIR__, 2 ) . '/elavira.php' );

		$this->assertStringContainsString( 'Plugin Name:       Elavira', $contents );
		$this->assertStringContainsString( 'Text Domain:       elavira', $contents );
	}

	public function test_composer_maps_the_elavira_namespace() {
		$composer = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/composer.json' ), true );

		$this->assertSame( 'src/', $composer['autoload']['psr-4']['Elavira\\'] );
	}
}
