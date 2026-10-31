<?php
/**
 * Current decision graphs, actual rendering, and authorized request regression.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\GuidedDecisions\DecisionNavigator;
use Elavira\GuidedDecisions\DecisionContext;
use Elavira\GuidedDecisions\DecisionState;
use Elavira\Tests\Support\DecisionFixture;
use Elavira\Tests\Support\DecisionRedirect;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/Support/DecisionRequest.php';

// phpcs:disable Squiz.Commenting.FunctionComment -- Test method names describe intent.

/** Covers current flows without replacing historical graph regression tests. */
final class GuidedDecisionUxTest extends TestCase {
	protected function tearDown(): void {
		$_GET                                    = array();
		$_POST                                   = array();
		$GLOBALS['elavira_test_capabilities']    = array();
		$GLOBALS['elavira_decision_nonce_valid'] = false;
	}

	public function test_every_current_flow_renders_native_questions_and_registered_help() {
		foreach ( CoreDecisionRegistry::create()->all() as $decision ) {
			$fixture = new DecisionFixture( substr( $decision->get( 'id' ), strlen( 'elavira.decision.' ) ) );
			$html    = $fixture->render();
			$this->assertStringContainsString( esc_html( $decision->get( 'title' ) ), $html );
			$this->assertStringContainsString( '<fieldset aria-describedby="elavira-question-help"><legend>', $html );
			$this->assertStringContainsString( 'type="radio"', $html );
			$this->assertStringContainsString( 'name="answer"', $html );
			$this->assertStringContainsString( 'required', $html );
			$this->assertStringContainsString( 'target="_blank" rel="noopener noreferrer"', $html );
			$this->assertStringContainsString( '(opens in a new tab)', $html );
			foreach ( $decision->get( 'steps' ) as $step ) {
				$this->assertNotEmpty( $step['help'] );
				$this->assertContains( 'uncertain', array_column( $step['options'], 'id' ) );
			}
			foreach ( $decision->get( 'outcomes' ) as $outcome ) {
				$this->assertNotEmpty( $outcome['interpretation'] );
				$this->assertNotEmpty( $outcome['recommendation'] );
				$this->assertNotEmpty( $outcome['verification_guidance'] );
			}
		}
	}

	/** Exercise meaningful link outcomes.
	 *
	 * @dataProvider link_paths
	 */
	public function test_link_paths( array $answers, $outcome ) {
		$fixture = new DecisionFixture( 'link_purpose' );
		$result  = ( new DecisionNavigator() )->resolve( $fixture->decision, $answers );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( $outcome, $result['outcome_id'] );
		$html = $fixture->render( $answers );
		$this->assertStringContainsString( esc_html( $result['outcome']['recommendation'] ), $html );
		$this->assertStringContainsString( esc_html( $result['outcome']['verification_guidance'] ), $html );
		$this->assertStringContainsString( 'How to verify it', $html );
	}

	public function link_paths() {
		return array(
			'learn more with context'      => array( array( 'text', 'no', 'yes' ), 'clear_context' ),
			'learn more without context'   => array( array( 'text', 'no', 'no' ), 'text_change' ),
			'descriptive text'             => array( array( 'text', 'yes' ), 'clear' ),
			'envelope icon'                => array( array( 'image_only', 'no', 'no', 'no' ), 'image_change' ),
			'contact alternative'          => array( array( 'image_only', 'no', 'yes' ), 'clear' ),
			'image words absent'           => array( array( 'image_only', 'yes', 'no' ), 'image_text_change' ),
			'image words present'          => array( array( 'image_only', 'yes', 'yes' ), 'clear' ),
			'decorative arrow'             => array( array( 'mixed', 'no', 'yes', 'yes' ), 'clear' ),
			'redundant arrow'              => array( array( 'mixed', 'no', 'no' ), 'decorative_change' ),
			'additional image information' => array( array( 'mixed', 'yes', 'no' ), 'mixed_change' ),
			'unknown name'                 => array( array( 'image_only', 'no', 'uncertain' ), 'further_review' ),
		);
	}

	public function test_image_only_guidance_teaches_function_words_and_no_redundancy() {
		$flow = CoreDecisionRegistry::create()->get( 'elavira.decision.link_purpose', '2.0.0' );
		$this->assertStringContainsString( 'Envelope icon', $flow->step( 'image_name' )['help'] );
		$this->assertStringContainsString( 'do not use it for a different action', $flow->outcome( 'image_change' )['recommendation'] );
		$this->assertStringContainsString( 'View Fall Collection', $flow->outcome( 'image_text_change' )['recommendation'] );
		$this->assertStringContainsString( 'alt=""', $flow->outcome( 'decorative_change' )['recommendation'] );
		$this->assertStringContainsString( 'not a guaranteed complete browser accessibility-tree name', $flow->step( 'text_name' )['help'] );
	}

	public function test_recorded_evidence_is_escaped_and_auto_routing_is_explicit_and_bounded() {
		$fixture = new DecisionFixture(
			'link_purpose',
			array(
				'tag_name'          => 'a',
				'link_kind'         => 'image_only',
				'image_filename'    => 'envelope.svg',
				'image_alt_excerpt' => '<script>alert(1)</script>',
				'link_destination'  => 'https://example.org/contact/',
			)
		);
		$html    = $fixture->render();
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'from recorded evidence', $html );
		$this->assertStringContainsString( '<legend>Does the linked image contain meaningful visible words?</legend>', $html );
		$this->assertStringContainsString( 'https://example.org/contact/', $html );
		$this->assertStringContainsString( 'aria-labelledby', $fixture->render( array( 'image_only', 'no', 'uncertain' ) ) );
		$context = new DecisionContext();
		$rows    = array( array( 'element_json' => '{"link_kind":"image_only"}' ) );
		$this->assertSame( array(), $context->initial_answers( $fixture->decision, $rows, 2 ) );
		$rows[0]['element_json'] = '{"link_kind":"mixed_or_unknown"}';
		$this->assertSame( array(), $context->initial_answers( $fixture->decision, $rows, 1 ) );
	}

	public function test_missing_historical_evidence_and_return_links_are_safe() {
		$fixture = new DecisionFixture( 'link_purpose', array(), array(), true );
		$html    = $fixture->render();
		$this->assertStringContainsString( 'No occurrence details are available', $html );
		$this->assertStringContainsString( '<legend>What provides the meaningful content', $html );
		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		foreach ( $dom->getElementsByTagName( 'a' ) as $link ) {
			if ( false !== strpos( $link->getAttribute( 'href' ), 'elavira-learn' ) ) {
				$this->assertSame( '_blank', $link->getAttribute( 'target' ) );
			} else {
				$this->assertSame( '', $link->getAttribute( 'target' ) );
				$this->assertStringContainsString( 'audit=1', $link->getAttribute( 'href' ) );
			}
		}
	}

	public function test_historical_graphs_retain_every_original_field() {
		$baseline = json_decode( ltrim( file_get_contents( dirname( __DIR__, 2 ) . '/docs/GUIDED_DECISION_BASELINE.json' ), "\xEF\xBB\xBF" ), true );
		$this->assertCount( 7, $baseline );
		foreach ( $baseline as $row ) {
			$definition = CoreDecisionRegistry::create()->get( $row['id'], $row['version'] );
			foreach ( $row as $key => $value ) {
				$this->assertSame( $value, $definition->get( $key ), $row['id'] . '/' . $key );
			}
		}
	}

	public function test_current_coverage_and_no_invented_version_matches() {
		$current = array();
		foreach ( \Elavira\Rules\CoreRuleRegistry::create()->all() as $rule ) {
			$current[ $rule->id() ] = $rule;
		}
		$count    = 0;
		$registry = CoreDecisionRegistry::create();
		foreach ( $current as $rule ) {
			$count += $registry->for_rule( $rule->id(), $rule->version() ) ? 1 : 0;
			$this->assertNull( $registry->for_rule( $rule->id(), '99.0.0' ) );
		}
		$this->assertSame( 30, $count );
		$this->assertNull( $registry->for_rule( 'elavira.contrast.text_insufficient', '1.0.0' ) );
	}

	public function test_uncertainty_never_becomes_a_failure_and_every_path_terminates() {
		foreach ( CoreDecisionRegistry::create()->all() as $flow ) {
			$this->walk( $flow, $flow->get( 'start' ), array() );
		}
	}

	private function walk( $flow, $step_id, array $answers ) {
		foreach ( $flow->step( $step_id )['options'] as $option ) {
			$path   = array_merge( $answers, array( $option['id'] ) );
			$result = ( new DecisionNavigator() )->resolve( $flow, $path );
			if ( $result['complete'] ) {
				$this->assertContains( $result['outcome']['status'], array( 'open', 'needs_review' ) );
				if ( 'uncertain' === $option['id'] ) {
					$this->assertSame( 'needs_review', $result['outcome']['status'] );
				}
			} else {
				$this->walk( $flow, $result['step_id'], $path );
			}
		}
	}

	public function test_complete_saves_conclusion_actor_and_note_without_automatic_status_change() {
		$fixture = new DecisionFixture( 'link_purpose' );
		$fixture->render();
		$_POST                                   = array(
			'finding'         => 1,
			'state'           => $fixture->state( array( 'text', 'yes' ) ),
			'workflow_status' => '',
			'review_note'     => 'Checked the services destination.',
		);
		$GLOBALS['elavira_decision_nonce_valid'] = true;
		$before                                  = $fixture->database->tables['wp_elavira_findings'][1]['current_status'];
		try {
			$fixture->page->complete();
			$this->fail( 'Completion did not redirect.' );
		} catch ( DecisionRedirect $redirect ) {
			$this->assertStringContainsString( 'decision_completed', $redirect->getMessage() );
			$this->assertStringContainsString( 'finding=1', $redirect->getMessage() );
		}
		$records = $fixture->repository->find_by_finding( 1 );
		$this->assertCount( 1, $records );
		$this->assertSame( 7, $records[0]['actor_user_id'] );
		$this->assertSame( '2.0.0', $records[0]['decision_version'] );
		$this->assertStringContainsString( 'name explains its purpose', $records[0]['recommendation'] );
		$this->assertStringContainsString( 'Checked the services destination.', $records[0]['answers_json'] );
		$this->assertSame( $before, $fixture->database->tables['wp_elavira_findings'][1]['current_status'] );
	}

	public function test_generated_activity_keeps_note_plain_and_provenance_in_decision_record() {
		$fixture = new DecisionFixture( 'link_purpose' );
		$fixture->render();
		$_POST                                   = array(
			'finding'         => 1,
			'state'           => $fixture->state( array( 'text', 'yes' ) ),
			'workflow_status' => 'addressed',
			'review_note'     => 'review structure',
		);
		$GLOBALS['elavira_decision_nonce_valid'] = true;
		try {
			$fixture->page->complete();
			$this->fail( 'Completion did not redirect.' );
		} catch ( DecisionRedirect $redirect ) {
			$this->assertStringContainsString( 'decision_completed', $redirect->getMessage() );
		}
		$entries = array_values( $fixture->database->tables['wp_elavira_finding_status_history'] );
		$last    = end( $entries );
		$this->assertSame( 'review structure', $last['reason'] );
		$this->assertSame( 'addressed', $last['status'] );
		$decision = $fixture->repository->find_by_finding( 1 )[0];
		$this->assertSame( 'elavira.decision.link_purpose', $decision['decision_id'] );
		$this->assertSame( '2.0.0', $decision['decision_version'] );
		$this->assertNotEmpty( $decision['outcome_id'] );
	}

	public function test_rejected_nonce_cannot_save_a_decision() {
		$fixture                                 = new DecisionFixture( 'link_purpose' );
		$_POST                                   = array( 'finding' => 1 );
		$GLOBALS['elavira_decision_nonce_valid'] = false;
		$this->expectExceptionMessage( 'Rejected nonce.' );
		$fixture->page->complete();
	}

	public function test_manual_media_outcome_cannot_change_a_finding() {
		$fixture = new DecisionFixture( 'audio_video_alternatives' );
		$html    = $fixture->render( array( 'audio', 'yes' ) );
		$this->assertStringContainsString( 'Back to Guided Decisions', $html );
		$this->assertStringContainsString( 'How to verify it', $html );
		$this->assertStringNotContainsString( 'Save decision', $html );
		$this->assertStringNotContainsString( 'workflow_status', $html );
	}
}
