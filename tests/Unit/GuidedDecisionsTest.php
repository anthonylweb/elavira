<?php
/**
 * Guided Decision domain tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\GuidedDecisions\DecisionDefinition;
use Elavira\GuidedDecisions\DecisionNavigator;
use Elavira\GuidedDecisions\DecisionRegistry;
use Elavira\GuidedDecisions\DecisionState;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GuidedDecisionsTest extends TestCase {
	public function test_registry_resolves_exact_rule_version_only() {
		$registry = CoreDecisionRegistry::create();
		$this->assertSame( 'elavira.decision.link_purpose', $registry->for_rule( 'elavira.link.purpose_review', '1.0.0' )->get( 'id' ) );
		$this->assertNull( $registry->for_rule( 'elavira.link.purpose_review', '2.0.0' ) );
	}

	public function test_duplicate_decision_is_rejected() {
		$registry   = new DecisionRegistry();
		$definition = $this->definition();
		$registry->register( $definition );
		$this->expectException( InvalidArgumentException::class );
		$registry->register( $definition );
	}

	public function test_invalid_branch_target_is_rejected() {
		$data = $this->data();
		$data['steps']['question']['options'][0]['target'] = 'missing';
		$this->expectException( InvalidArgumentException::class );
		new DecisionDefinition( $data );
	}

	public function test_cycle_is_rejected() {
		$data = $this->data();
		$data['steps']['question']['options'][0]['target'] = 'question';
		$this->expectException( InvalidArgumentException::class );
		new DecisionDefinition( $data );
	}

	public function test_unreachable_outcome_is_rejected() {
		$data                       = $this->data();
		$data['outcomes']['unused'] = $data['outcomes']['done'];
		$this->expectException( InvalidArgumentException::class );
		new DecisionDefinition( $data );
	}

	public function test_navigator_validates_answers_and_resolves_server_branch() {
		$decision = CoreDecisionRegistry::create()->for_rule( 'elavira.link.purpose_review', '1.0.0' );
		$result   = ( new DecisionNavigator() )->resolve( $decision, array( 'no', 'yes' ) );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( 'remediation_likely', $result['outcome_id'] );
		$this->assertSame( 'open', $result['outcome']['status'] );
	}

	public function test_invalid_answer_is_rejected_without_changing_finding_classification_or_status() {
		$finding = array(
			'category'       => 'human_review',
			'current_status' => 'needs_review',
		);
		try {
			( new DecisionNavigator() )->resolve( CoreDecisionRegistry::create()->for_rule( 'elavira.link.purpose_review', '1.0.0' ), array( 'browser_target' ) );
			$this->fail( 'An unregistered answer was accepted.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertSame( 'human_review', $finding['category'] );
			$this->assertSame( 'needs_review', $finding['current_status'] );
		}
	}

	public function test_heading_flow_preserves_human_review_recommendation() {
		$decision = CoreDecisionRegistry::create()->for_rule( 'elavira.heading.structure_review', '1.0.0' );
		$result   = ( new DecisionNavigator() )->resolve( $decision, array( 'yes', 'yes', 'no' ) );
		$this->assertSame( 'structure_reasonable', $result['outcome_id'] );
		$this->assertSame( 'needs_review', $result['outcome']['status'] );
	}

	public function test_answer_state_supports_back_and_restart_without_persistence() {
		$state   = new DecisionState();
		$encoded = $state->encode( array( 'yes', 'no' ) );
		$this->assertSame( array( 'yes', 'no' ), $state->decode( $encoded ) );
		$this->assertSame( array(), $state->decode( '' ) );
	}

	public function test_tampered_answer_state_is_rejected() {
		$this->expectException( InvalidArgumentException::class );
		( new DecisionState() )->decode( 'tampered.invalid' );
	}

	public function test_answer_state_cannot_be_replayed_in_another_finding_context() {
		$state  = new DecisionState();
		$signed = $state->encode( array( 'yes' ), 'finding-1|decision-1' );
		$this->expectException( InvalidArgumentException::class );
		$state->decode( $signed, 'finding-2|decision-1' );
	}

	public function test_state_changing_controller_paths_require_finding_bound_nonces() {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/GuidedDecisions/Admin/DecisionPage.php' );
		$this->assertSame( 2, substr_count( $source, "check_admin_referer( 'elavira_decision_' . \$finding_id )" ) );
		$this->assertStringNotContainsString( 'FindingStatus::VERIFIED', $source );
	}

	private function definition() {
		return new DecisionDefinition( $this->data() );
	}

	private function data() {
		return array(
			'id'                => 'elavira.decision.test',
			'version'           => '1.0.0',
			'editorial_version' => '1.0.0',
			'title'             => 'Test',
			'purpose'           => 'Test purpose',
			'rules'             => array(
				array(
					'id'      => 'elavira.test.rule',
					'version' => '1.0.0',
				),
			),
			'start'             => 'question',
			'steps'             => array(
				'question' => array(
					'type'     => 'yes_no',
					'question' => 'Question?',
					'options'  => array(
						array(
							'id'     => 'yes',
							'label'  => 'Yes',
							'target' => 'done',
						),
						array(
							'id'     => 'no',
							'label'  => 'No',
							'target' => 'done',
						),
					),
				),
			),
			'outcomes'          => array(
				'done' => array(
					'interpretation' => 'Interpretation',
					'recommendation' => 'Recommendation',
					'status'         => 'needs_review',
				),
			),
		);
	}
}
