<?php
/**
 * Phase 1 production ruleset Round 3 tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Findings\Values\Priority;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Rules\Values\EvaluationType;
use PHPUnit\Framework\TestCase;

/** Verifies server-owned Round 3 semantics. */
final class Round3RuleRegistryTest extends TestCase {
	/** Confirm the exact approved inventory and certainty boundary. */
	public function test_round_three_inventory() {
		$rules    = CoreRuleRegistry::create();
		$expected = array(
			'elavira.form.accessible_name_missing'       => array( '2.0.0', EvaluationType::AUTOMATIC_FAILURE, Priority::HIGH, '4.1.2' ),
			'elavira.forms.broken_label_reference'       => array( '1.0.0', EvaluationType::POTENTIAL_ISSUE, Priority::HIGH, '1.3.1' ),
			'elavira.forms.placeholder_only_label'       => array( '1.0.0', EvaluationType::POTENTIAL_ISSUE, Priority::HIGH, '3.3.2' ),
			'elavira.forms.required_instruction_review'  => array( '1.0.0', EvaluationType::HUMAN_REVIEW, Priority::MEDIUM, '3.3.2' ),
			'elavira.forms.error_identification_review'  => array( '1.0.0', EvaluationType::HUMAN_REVIEW, Priority::HIGH, '3.3.1' ),
			'elavira.semantics.control_missing_name'     => array( '1.0.0', EvaluationType::AUTOMATIC_FAILURE, Priority::HIGH, '4.1.2' ),
			'elavira.semantics.invalid_role'             => array( '1.0.0', EvaluationType::POTENTIAL_ISSUE, Priority::HIGH, '4.1.2' ),
			'elavira.semantics.required_state_missing'   => array( '1.0.0', EvaluationType::AUTOMATIC_FAILURE, Priority::HIGH, '4.1.2' ),
			'elavira.semantics.state_consistency_review' => array( '1.0.0', EvaluationType::HUMAN_REVIEW, Priority::HIGH, '4.1.2' ),
			'elavira.semantics.native_semantics_overridden' => array( '1.0.0', EvaluationType::POTENTIAL_ISSUE, Priority::MEDIUM, '4.1.2' ),
			'elavira.aria.invalid_attribute'             => array( '1.0.0', EvaluationType::POTENTIAL_ISSUE, Priority::MEDIUM, '' ),
			'elavira.aria.invalid_value'                 => array( '1.0.0', EvaluationType::POTENTIAL_ISSUE, Priority::HIGH, '4.1.2' ),
			'elavira.aria.prohibited_attribute'          => array( '1.0.0', EvaluationType::POTENTIAL_ISSUE, Priority::HIGH, '' ),
			'elavira.aria.required_attribute_missing'    => array( '1.0.0', EvaluationType::POTENTIAL_ISSUE, Priority::HIGH, '4.1.2' ),
			'elavira.aria.broken_id_reference'           => array( '1.0.0', EvaluationType::POTENTIAL_ISSUE, Priority::HIGH, '1.3.1' ),
			'elavira.aria.hidden_focusable_content'      => array( '1.0.0', EvaluationType::POTENTIAL_ISSUE, Priority::HIGH, '' ),
			'elavira.aria.duplicate_id_reference'        => array( '1.0.0', EvaluationType::POTENTIAL_ISSUE, Priority::HIGH, '1.3.1' ),
			'elavira.aria.redundant_role'                => array( '1.0.0', EvaluationType::POTENTIAL_ISSUE, Priority::LOW, '' ),
		);
		foreach ( $expected as $id => $contract ) {
			$rule = $rules->get( $id, $contract[0] );
			$this->assertSame( $contract[1], $rule->evaluation_type(), $id );
			$this->assertSame( $contract[2], $rule->priority(), $id );
			$this->assertSame( $contract[3], $rule->wcag() ? $rule->wcag()[0]->criterion() : '', $id );
		}
		$this->assertSame( '1.0.0', $rules->get( 'elavira.form.accessible_name_missing', '1.0.0' )->version() );
	}

	/** Consequence rules own deterministic supersession metadata. */
	public function test_consequence_supersession_is_registry_declared() {
		$rules = CoreRuleRegistry::create();
		foreach ( array( 'elavira.semantics.control_missing_name', 'elavira.forms.broken_label_reference', 'elavira.aria.broken_id_reference' ) as $diagnostic ) {
			$this->assertTrue( $rules->supersedes( 'elavira.form.accessible_name_missing', '2.0.0', $diagnostic ) );
		}
		foreach ( array( 'elavira.aria.invalid_value', 'elavira.aria.required_attribute_missing' ) as $diagnostic ) {
			$this->assertTrue( $rules->supersedes( 'elavira.semantics.required_state_missing', '1.0.0', $diagnostic ) );
		}
	}
}
