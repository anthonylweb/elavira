<?php
/**
 * Round 5 registry and calculation tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Rules\ContrastMath;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Rules\Values\EvaluationType;
use PHPUnit\Framework\TestCase;

final class Round5RuleRegistryTest extends TestCase {
	public function test_round_five_inventory_and_certainty_are_exact() {
		$rules    = CoreRuleRegistry::create();
		$expected = array(
			'elavira.contrast.text_insufficient'       => EvaluationType::AUTOMATIC_FAILURE,
			'elavira.contrast.large_text_insufficient' => EvaluationType::AUTOMATIC_FAILURE,
			'elavira.contrast.non_text_insufficient'   => EvaluationType::AUTOMATIC_FAILURE,
			'elavira.contrast.cannot_determine'        => EvaluationType::HUMAN_REVIEW,
			'elavira.color.color_only_review'          => EvaluationType::HUMAN_REVIEW,
		);
		foreach ( $expected as $id => $type ) {
			$this->assertSame( $type, $rules->get( $id, '1.0.0' )->evaluation_type() );
		}
		$this->assertSame( array( 'elavira.contrast.cannot_determine' ), $rules->get( 'elavira.contrast.text_insufficient', '1.0.0' )->supersedes() );
	}

	public function test_server_contrast_math_uses_wcag_relative_luminance() {
		$math = new ContrastMath();
		$this->assertSame( 21000, $math->ratio_milli( '#000000', '#ffffff' ) );
		$this->assertSame( 1000, $math->ratio_milli( '#777777', '#777777' ) );
		$this->assertSame( 4478, $math->ratio_milli( '#777777', '#ffffff' ) );
	}
}
