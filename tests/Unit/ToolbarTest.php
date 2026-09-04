<?php
/**
 * Visitor Toolbar tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Bootstrap;
use Elavira\Toolbar\ToolbarSettings;
use PHPUnit\Framework\TestCase;

final class ToolbarTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['elavira_test_options']      = array();
		$GLOBALS['elavira_test_actions']      = array();
		$GLOBALS['elavira_test_capabilities'] = array();
	}

	public function test_default_is_disabled_and_all_ten_optional_controls_are_available() {
		$settings = ToolbarSettings::get();
		$this->assertFalse( $settings['enabled'] );
		$this->assertSame( 'bottom_right', $settings['position'] );
		$this->assertCount( 10, $settings['controls'] );
		$this->assertNotContains( false, $settings['controls'], true );
	}

	public function test_invalid_settings_are_bounded_to_known_values() {
		$settings = ToolbarSettings::sanitize(
			array(
				'enabled'  => 'yes',
				'position' => 'center',
				'controls' => array(
					'text_size' => 1,
					'unknown'   => 1,
				),
			)
		);
		$this->assertTrue( $settings['enabled'] );
		$this->assertSame( 'bottom_right', $settings['position'] );
		$this->assertTrue( $settings['controls']['text_size'] );
		$this->assertFalse( $settings['controls']['grayscale'] );
		$this->assertArrayNotHasKey( 'unknown', $settings['controls'] );
	}

	public function test_module_registers_scoped_frontend_and_protected_admin_hooks() {
		$GLOBALS['wpdb'] = new \Elavira\Tests\Support\FakeWpdb();
		Bootstrap::boot();
		$this->assertArrayHasKey( 'wp_head', $GLOBALS['elavira_test_actions'] );
		$this->assertArrayHasKey( 'wp_footer', $GLOBALS['elavira_test_actions'] );
		$this->assertArrayHasKey( 'admin_post_elavira_toolbar_save', $GLOBALS['elavira_test_actions'] );
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Toolbar/Admin/ToolbarPage.php' );
		$this->assertStringContainsString( 'Capability::MANAGE_TOOLBAR', $source );
		$this->assertStringContainsString( "check_admin_referer( 'elavira_toolbar_save' )", $source );
	}

	public function test_frontend_contains_modal_contract_all_controls_and_statement_link_support() {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Toolbar/FrontendToolbar.php' );
		foreach ( array( 'aria-haspopup="dialog"', 'aria-modal="true"', 'Accessibility Preferences', 'Text Size', 'Appearance', 'Reset Preferences', 'Accessibility Statement' ) as $expected ) {
			$this->assertStringContainsString( $expected, $source );
		}
	}
}
