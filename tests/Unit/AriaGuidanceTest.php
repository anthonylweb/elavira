<?php
/**
 * Contextual ARIA rule guidance tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Learn\AriaGuidance;
use Elavira\Rules\CoreRuleRegistry;
use PHPUnit\Framework\TestCase;

final class AriaGuidanceTest extends TestCase {
	public function test_every_aria_rule_has_specific_authoritative_guidance() {
		$count = 0;
		foreach ( CoreRuleRegistry::create()->all() as $rule ) {
			if ( 0 !== strpos( $rule->id(), 'elavira.aria.' ) ) {
				continue; }
			++$count;
			$this->assertStringStartsWith( 'https://www.w3.org/TR/wai-aria-1.2/#', AriaGuidance::reference( $rule->id() ) );
			ob_start();
			AriaGuidance::render( $rule );
			$html = ob_get_clean();
			$this->assertStringContainsString( esc_html( $rule->editorial()['summary'] ), $html );
			$this->assertStringContainsString( 'presentation and none roles are valid', $html );
			$this->assertStringContainsString( 'href="' . AriaGuidance::reference( $rule->id() ) . '"', $html );
		}
		$this->assertGreaterThanOrEqual( 8, $count );
		$this->assertSame( '', AriaGuidance::reference( 'unknown' ) );
	}
	public function test_trigger_identifies_the_specific_role_and_attribute() {
		$text = AriaGuidance::trigger(
			'elavira.aria.prohibited_attribute',
			array(
				'attribute_name' => 'aria-label',
				'role'           => 'presentation',
			)
		);
		$this->assertStringContainsString( 'aria-label', $text );
		$this->assertStringContainsString( 'role presentation', $text );
		$this->assertStringContainsString( 'check whether the browser ignores the role', $text );
	}
}
