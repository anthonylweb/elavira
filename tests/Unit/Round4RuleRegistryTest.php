<?php
/**
 * Round 4 registry tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Findings\Values\Priority;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Rules\Values\EvaluationType;
use PHPUnit\Framework\TestCase;

/** Verifies the human-authoritative Round 4 inventory. */
final class Round4RuleRegistryTest extends TestCase {
	/** All nine rules retain server-owned metadata. */
	public function test_round_four_inventory() {
		$expected = array(
			'elavira.keyboard.positive_tabindex'      => array( Priority::HIGH, '2.4.3' ),
			'elavira.keyboard.non_native_interactive' => array( Priority::HIGH, '2.1.1' ),
			'elavira.keyboard.possible_unreachable_control' => array( Priority::HIGH, '2.1.1' ),
			'elavira.keyboard.possible_trap'          => array( Priority::HIGH, '2.1.2' ),
			'elavira.keyboard.custom_widget_review'   => array( Priority::MEDIUM, '2.1.1' ),
			'elavira.focus.possible_hidden_focus'     => array( Priority::HIGH, '2.4.7' ),
			'elavira.focus.order_review'              => array( Priority::HIGH, '2.4.3' ),
			'elavira.focus.obscured_review'           => array( Priority::HIGH, '2.4.11' ),
			'elavira.focus.modal_management_review'   => array( Priority::HIGH, '2.4.3' ),
		);
		$registry = CoreRuleRegistry::create();
		foreach ( $expected as $id => $metadata ) {
			$rule = $registry->get( $id, '1.0.0' );
			$this->assertSame( EvaluationType::HUMAN_REVIEW, $rule->evaluation_type(), $id );
			$this->assertSame( $metadata[0], $rule->priority(), $id );
			$this->assertSame( $metadata[1], $rule->wcag()[0]->criterion(), $id );
			$this->assertSame( in_array( $id, array( 'elavira.focus.obscured_review', 'elavira.focus.possible_hidden_focus' ), true ) ? 'focus_visibility' : 'keyboard_interaction', $rule->editorial()['guided_decision'], $id );
		}
	}

	/** Specific review observations supersede generic duplicates. */
	public function test_round_four_supersession_is_declared() {
		$registry = CoreRuleRegistry::create();
		$this->assertTrue( $registry->supersedes( 'elavira.keyboard.positive_tabindex', '1.0.0', 'elavira.focus.order_review' ) );
		$this->assertTrue( $registry->supersedes( 'elavira.keyboard.possible_unreachable_control', '1.0.0', 'elavira.keyboard.non_native_interactive' ) );
		$this->assertTrue( $registry->supersedes( 'elavira.focus.modal_management_review', '1.0.0', 'elavira.keyboard.custom_widget_review' ) );
	}
}
