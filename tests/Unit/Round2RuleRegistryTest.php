<?php
/**
 * Phase 1 production ruleset Round 2 tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Findings\Values\Priority;
use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\Learn\CoreTopicRegistry;
use Elavira\Rules\Browser\BrowserEvidenceValidator;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Rules\Grouping\SupersessionResolver;
use Elavira\Rules\Normalization\RuleResultNormalizer;
use Elavira\Rules\Values\EvaluationType;
use Elavira\Rules\Values\RuleOutcome;
use PHPUnit\Framework\TestCase;

/** Verifies server-owned Round 2 semantics and compatibility. */
final class Round2RuleRegistryTest extends TestCase {
	/**
	 * Current Core rule registry.
	 *
	 * @var \Elavira\Rules\RuleRegistry
	 */
	private $rules;

	protected function setUp(): void {
		$this->rules = CoreRuleRegistry::create();
	}

	public function test_round_two_rule_inventory_and_historical_versions_are_registered() {
		$current = array(
			'elavira.links.missing_accessible_name' => '1.0.0',
			'elavira.link.purpose_review'           => '2.0.0',
			'elavira.links.identical_name_different_destination' => '2.0.0',
			'elavira.structure.missing_h1'          => '1.0.0',
			'elavira.structure.multiple_h1'         => '1.0.0',
			'elavira.structure.heading_level_jump'  => '1.0.0',
			'elavira.structure.empty_heading'       => '1.0.0',
			'elavira.heading.structure_review'      => '2.0.0',
			'elavira.document.language_missing'     => '2.0.0',
			'elavira.document.invalid_language'     => '2.0.0',
		);
		foreach ( $current as $id => $version ) {
			$this->assertSame( $version, $this->rules->get( $id, $version )->version(), $id );
		}
		$this->assertSame( '1.0.0', $this->rules->get( 'elavira.link.purpose_review', '1.0.0' )->version() );
		$this->assertSame( '1.0.0', $this->rules->get( 'elavira.links.identical_name_different_destination', '1.0.0' )->version() );
		$this->assertSame( '1.0.0', $this->rules->get( 'elavira.document.invalid_language', '1.0.0' )->version() );
		$this->assertSame( Priority::HIGH, $this->rules->get( 'elavira.document.language_missing', '1.0.0' )->priority() );
	}

	public function test_link_rules_have_required_server_owned_semantics() {
		$missing = $this->rules->get( 'elavira.links.missing_accessible_name', '1.0.0' );
		$this->assertSame( EvaluationType::AUTOMATIC_FAILURE, $missing->evaluation_type() );
		$this->assertSame( Priority::HIGH, $missing->priority() );
		$this->assertSame(
			array( '2.4.4', '4.1.2' ),
			array_map(
				static function ( $mapping ) {
					return $mapping->to_array()['criterion'];
				},
				$missing->wcag()
			)
		);

		foreach ( array( array( 'elavira.link.purpose_review', '2.0.0' ), array( 'elavira.links.identical_name_different_destination', '2.0.0' ) ) as $identity ) {
			$rule = $this->rules->get( $identity[0], $identity[1] );
			$this->assertSame( EvaluationType::POTENTIAL_ISSUE, $rule->evaluation_type() );
			$this->assertSame( Priority::MEDIUM, $rule->priority() );
		}
	}

	public function test_heading_rules_are_conservative_and_structural_warnings_are_explicit() {
		foreach ( array( 'elavira.structure.missing_h1', 'elavira.structure.multiple_h1' ) as $id ) {
			$rule = $this->rules->get( $id, '1.0.0' );
			$this->assertSame( EvaluationType::HUMAN_REVIEW, $rule->evaluation_type() );
			$this->assertSame( 'structural_warning', $rule->editorial()['presentation'] );
			$this->assertStringContainsString( 'WCAG and HTML do not require exactly one H1.', $rule->editorial()['recommendation'] );
		}
		$this->assertSame( EvaluationType::POTENTIAL_ISSUE, $this->rules->get( 'elavira.structure.heading_level_jump', '1.0.0' )->evaluation_type() );
		$this->assertSame( EvaluationType::POTENTIAL_ISSUE, $this->rules->get( 'elavira.structure.empty_heading', '1.0.0' )->evaluation_type() );
		$this->assertSame( EvaluationType::HUMAN_REVIEW, $this->rules->get( 'elavira.heading.structure_review', '2.0.0' )->evaluation_type() );
	}

	public function test_language_rules_use_medium_priority_and_do_not_claim_prose_matching() {
		foreach ( array( array( 'elavira.document.language_missing', '2.0.0' ), array( 'elavira.document.invalid_language', '2.0.0' ) ) as $identity ) {
			$rule = $this->rules->get( $identity[0], $identity[1] );
			$this->assertSame( EvaluationType::AUTOMATIC_FAILURE, $rule->evaluation_type() );
			$this->assertSame( Priority::MEDIUM, $rule->priority() );
			$this->assertSame( '3.1.1', $rule->wcag()[0]->to_array()['criterion'] );
		}
		$this->assertStringContainsString( 'do not guess from the page content', $this->rules->get( 'elavira.document.invalid_language', '2.0.0' )->editorial()['recommendation'] );
	}

	public function test_current_repeated_link_evidence_requires_a_bounded_name_excerpt() {
		$evidence = array(
			'accessible_name_excerpt' => str_repeat( 'a', 120 ),
			'name_fingerprint'        => '12345678',
			'destination_fingerprint' => '87654321',
			'destination_kind'        => 'internal',
			'destination_state'       => 'different',
		);
		$this->assertSame( $evidence, $this->rules->get( 'elavira.links.identical_name_different_destination', '2.0.0' )->evidence_schema()->validate( $evidence ) );
	}

	public function test_language_registry_asset_is_loaded_before_the_scanner() {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Scanner/Frontend/ScanPage.php' );
		$this->assertStringContainsString( "'elavira-language-primary-subtags'", $source );
		$this->assertStringContainsString( "array( 'elavira-language-primary-subtags', 'elavira-aria-vocabulary' )", $source );
	}

	public function test_round_two_browser_evidence_normalizes_without_browser_editorial_authority() {
		$payload = array(
			'rule_id'      => 'elavira.structure.missing_h1',
			'rule_version' => '1.0.0',
			'outcome'      => RuleOutcome::HUMAN_REVIEW,
			'target'       => array(
				'tag_name' => 'html',
				'selector' => 'html',
			),
			'evidence'     => array( 'h1_count' => 0 ),
			'element'      => array( 'tag_name' => 'html' ),
			'grouping'     => array(),
		);
		$result  = ( new RuleResultNormalizer() )->normalize( ( new BrowserEvidenceValidator( $this->rules ) )->validate( $payload ) )->to_array();
		$this->assertSame( RuleOutcome::HUMAN_REVIEW, $result['outcome'] );
		$this->assertSame( Priority::MEDIUM, $result['priority'] );
		$this->assertSame( 'structural_warning', $result['editorial']['presentation'] );
	}

	public function test_guided_decisions_and_learn_topics_resolve_exactly() {
		$decisions = CoreDecisionRegistry::create();
		$this->assertNotNull( $decisions->for_rule( 'elavira.link.purpose_review', '1.0.0' ) );
		$this->assertNotNull( $decisions->for_rule( 'elavira.link.purpose_review', '2.0.0' ) );
		$this->assertNotNull( $decisions->for_rule( 'elavira.structure.missing_h1', '1.0.0' ) );
		$this->assertNull( $decisions->for_rule( 'elavira.structure.missing_h1', '2.0.0' ) );
		$topics = CoreTopicRegistry::create();
		foreach ( $this->rules->all() as $rule ) {
			$this->assertNotNull( $topics->find( $rule->editorial()['learn_topic'] ), $rule->id() );
		}
	}

	public function test_reports_render_structural_warning_as_visible_text() {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Reports/Admin/ReportsPage.php' );
		$this->assertStringContainsString( "'Structural Warning'", $source );
		$this->assertStringContainsString( 'does not establish an accessibility failure', $source );
		$this->assertStringContainsString( "'structural_warning'", $source );
	}

	public function test_missing_h1_supersedes_only_the_same_target_context_review() {
		$resolver = new SupersessionResolver( $this->rules, new ReviewedConditionIdentity() );
		$target   = array(
			'tag_name' => 'html',
			'selector' => 'html',
		);
		$element  = array( 'tag_name' => 'html' );
		$resolved = $resolver->resolve(
			array(
				array(
					'creates_finding' => true,
					'rule_id'         => 'elavira.heading.structure_review',
					'rule_version'    => '2.0.0',
					'outcome'         => RuleOutcome::HUMAN_REVIEW,
					'target'          => $target,
					'evidence'        => array( 'issue' => 'no_visible_headings' ),
					'element'         => $element,
				),
				array(
					'creates_finding' => true,
					'rule_id'         => 'elavira.structure.missing_h1',
					'rule_version'    => '1.0.0',
					'outcome'         => RuleOutcome::HUMAN_REVIEW,
					'target'          => $target,
					'evidence'        => array( 'h1_count' => 0 ),
					'element'         => $element,
				),
			)
		);
		$active   = array_values(
			array_filter(
				$resolved,
				static function ( $result ) {
					return $result['creates_finding'];
				}
			)
		);
		$this->assertCount( 1, $active );
		$this->assertSame( 'elavira.structure.missing_h1', $active[0]['rule_id'] );
		$this->assertSame( 'elavira.heading.structure_review', $active[0]['diagnostics'][0]['rule_id'] );
	}
}
