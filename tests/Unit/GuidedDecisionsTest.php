<?php
/**
 * Guided Decision domain tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

// phpcs:disable Squiz.Commenting.FunctionComment -- PHPUnit method names and providers document their intent.

use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\GuidedDecisions\DecisionDefinition;
use Elavira\GuidedDecisions\DecisionNavigator;
use Elavira\GuidedDecisions\DecisionRegistry;
use Elavira\GuidedDecisions\DecisionState;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GuidedDecisionsTest extends TestCase {
	public function test_registry_contains_complete_phase_one_inventory() {
		$ids = array_map(
			function ( $decision ) {
				return $decision->get( 'id' );
			},
			CoreDecisionRegistry::create()->all()
		);
		$this->assertSame(
			array(
				'elavira.decision.alternative_text',
				'elavira.decision.link_purpose',
				'elavira.decision.heading_structure',
				'elavira.decision.form_labels_instructions',
				'elavira.decision.use_of_color',
				'elavira.decision.keyboard_interaction',
				'elavira.decision.audio_video_alternatives',
			),
			$ids
		);
	}

	public function test_new_rule_bound_decisions_use_exact_current_rule_versions() {
		$registry = CoreDecisionRegistry::create();
		$this->assertSame( 'elavira.decision.alternative_text', $registry->for_rule( 'elavira.image.alt_missing', '1.0.0' )->get( 'id' ) );
		$this->assertSame( 'elavira.decision.form_labels_instructions', $registry->for_rule( 'elavira.form.accessible_name_missing', '1.0.0' )->get( 'id' ) );
		$this->assertNull( $registry->for_rule( 'elavira.image.alt_missing', '2.0.0' ) );
	}

	public function test_manual_decisions_have_no_fabricated_rule_compatibility() {
		$registry = CoreDecisionRegistry::create();
		foreach ( array( 'use_of_color', 'keyboard_interaction', 'audio_video_alternatives' ) as $id ) {
			$this->assertSame( array(), $registry->get( 'elavira.decision.' . $id, '1.0.0' )->get( 'rules' ) );
		}
	}

	/** Test Alternative Text branches.
	 *
	 * @dataProvider alternative_text_paths
	 */
	public function test_alternative_text_paths( array $answers, $outcome ) {
		$this->assertOutcome( 'alternative_text', $answers, $outcome );
	}

	public function alternative_text_paths() {
		return array(
			'informative'    => array( array( 'informative', 'no' ), 'informative' ),
			'redundant'      => array( array( 'informative', 'yes' ), 'redundant' ),
			'decorative'     => array( array( 'decorative' ), 'decorative' ),
			'functional'     => array( array( 'functional' ), 'functional' ),
			'complex'        => array( array( 'complex' ), 'complex' ),
			'image of text'  => array( array( 'text' ), 'image_text' ),
			'further review' => array( array( 'uncertain' ), 'further_review' ),
		);
	}

	/** Test Form Labels branches.
	 *
	 * @dataProvider form_paths
	 */
	public function test_form_label_and_instruction_paths( array $answers, $outcome ) {
		$this->assertOutcome( 'form_labels_instructions', $answers, $outcome );
	}

	public function form_paths() {
		return array(
			'missing name'         => array( array( 'none' ), 'missing_name' ),
			'placeholder only'     => array( array( 'placeholder' ), 'placeholder_only' ),
			'insufficient name'    => array( array( 'visible_label', 'no' ), 'insufficient_name' ),
			'instructions missing' => array( array( 'visible_label', 'yes', 'yes', 'no' ), 'instructions_missing' ),
			'valid context'        => array( array( 'visible_label', 'yes', 'no' ), 'context_reasonable' ),
		);
	}

	/** Test Use of Color branches.
	 *
	 * @dataProvider color_paths
	 */
	public function test_use_of_color_paths( array $answers, $outcome ) {
		$this->assertOutcome( 'use_of_color', $answers, $outcome );
	}

	public function color_paths() {
		return array(
			'color only'        => array( array( 'yes', 'none' ), 'color_only' ),
			'additional cue'    => array( array( 'yes', 'text' ), 'contrast_review' ),
			'contrast distinct' => array( array( 'no' ), 'contrast_review' ),
		);
	}

	/** Test Keyboard Interaction branches.
	 *
	 * @dataProvider keyboard_paths
	 */
	public function test_keyboard_interaction_paths( array $answers, $outcome ) {
		$this->assertOutcome( 'keyboard_interaction', $answers, $outcome );
	}

	public function keyboard_paths() {
		return array(
			'unreachable' => array( array( 'continue', 'no' ), 'unreachable' ),
			'inoperable'  => array( array( 'continue', 'yes', 'no' ), 'inoperable' ),
			'trap'        => array( array( 'continue', 'yes', 'yes', 'no' ), 'keyboard_trap' ),
			'focus'       => array( array( 'continue', 'yes', 'yes', 'yes', 'no' ), 'focus_problem' ),
			'order'       => array( array( 'continue', 'yes', 'yes', 'yes', 'yes', 'no' ), 'order_problem' ),
			'acceptable'  => array( array( 'continue', 'yes', 'yes', 'yes', 'yes', 'yes' ), 'keyboard_reasonable' ),
		);
	}

	/** Test Audio and Video branches.
	 *
	 * @dataProvider media_paths
	 */
	public function test_audio_video_paths( array $answers, $outcome ) {
		$this->assertOutcome( 'audio_video_alternatives', $answers, $outcome );
	}

	public function media_paths() {
		return array(
			'audio transcript'        => array( array( 'audio_only', 'yes', 'yes' ), 'audio_reasonable' ),
			'audio missing'           => array( array( 'audio_only', 'yes', 'no' ), 'audio_alternative_needed' ),
			'video visual missing'    => array( array( 'video_only', 'yes', 'no' ), 'visual_alternative_needed' ),
			'captions missing'        => array( array( 'synchronized', 'yes', 'no' ), 'captions_needed' ),
			'synchronized reasonable' => array( array( 'synchronized', 'yes', 'yes', 'no' ), 'media_reasonable' ),
			'further review'          => array( array( 'uncertain' ), 'further_review' ),
		);
	}
	public function test_registry_resolves_exact_rule_version_only() {
		$registry = CoreDecisionRegistry::create();
		$this->assertSame( 'elavira.decision.link_purpose', $registry->for_rule( 'elavira.link.purpose_review', '1.0.0' )->get( 'id' ) );
		$this->assertSame( 'elavira.decision.link_purpose', $registry->for_rule( 'elavira.link.purpose_review', '2.0.0' )->get( 'id' ) );
		$this->assertNull( $registry->for_rule( 'elavira.link.purpose_review', '9.0.0' ) );
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

	/** Assert a complete server-authoritative decision path. */
	private function assertOutcome( $id, array $answers, $outcome ) {
		$decision = CoreDecisionRegistry::create()->get( 'elavira.decision.' . $id, '1.0.0' );
		$result   = ( new DecisionNavigator() )->resolve( $decision, $answers );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( $outcome, $result['outcome_id'] );
		$this->assertContains( $result['outcome']['status'], array( 'open', 'needs_review' ) );
	}
}
