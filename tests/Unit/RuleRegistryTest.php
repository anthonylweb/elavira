<?php
/**
 * Rule Registry tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Findings\Values\Priority;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Rules\Evidence\EvidenceSchema;
use Elavira\Rules\Grouping\RuleLevelGrouping;
use Elavira\Rules\RuleDefinition;
use Elavira\Rules\RuleRegistry;
use Elavira\Rules\Values\EvaluationType;
use Elavira\Rules\Values\FindingClassification;
use Elavira\Rules\WcagMapping;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RuleRegistryTest extends TestCase {
	public function test_core_registry_contains_stable_representative_rules() {
		$registry = CoreRuleRegistry::create();

		$this->assertCount( 52, $registry->all() );
		$this->assertSame( '1.0.0', $registry->get( 'elavira.image.alt_missing', '1.0.0' )->version() );
		$this->assertSame( EvaluationType::HUMAN_REVIEW, $registry->get( 'elavira.heading.structure_review', '1.0.0' )->evaluation_type() );
		$this->assertSame( EvaluationType::HUMAN_REVIEW, $registry->get( 'elavira.heading.structure_review', '2.0.0' )->evaluation_type() );
	}

	public function test_registry_rejects_duplicate_rule_id_and_version() {
		$rule     = $this->rule();
		$registry = new RuleRegistry();
		$registry->register( $rule );

		$this->expectException( InvalidArgumentException::class );
		$registry->register( $rule );
	}

	public function test_same_rule_id_can_register_a_new_semantic_version() {
		$registry = new RuleRegistry();
		$registry->register( $this->rule( '1.0.0' ) );
		$registry->register( $this->rule( '2.0.0' ) );

		$this->assertCount( 2, $registry->all() );
		$this->assertSame( '2.0.0', $registry->get( 'elavira.test.example', '2.0.0' )->version() );
	}

	public function test_rule_rejects_invalid_id() {
		$data       = $this->rule_data();
		$data['id'] = '1.1.1';

		$this->expectException( InvalidArgumentException::class );
		new RuleDefinition( $data );
	}

	public function test_rule_rejects_conflicting_evaluation_classification() {
		$data                    = $this->rule_data();
		$data['evaluation_type'] = EvaluationType::HUMAN_REVIEW;

		$this->expectException( InvalidArgumentException::class );
		new RuleDefinition( $data );
	}

	public function test_wcag_mapping_rejects_unsupported_level_and_criterion() {
		$this->expectException( InvalidArgumentException::class );
		new WcagMapping( 'not-a-criterion', 'AAA', 'perceivable' );
	}

	public function test_best_practice_rule_may_be_explicitly_unmapped() {
		$data         = $this->rule_data();
		$data['wcag'] = array();
		$rule         = new RuleDefinition( $data );
		$this->assertSame( array(), $rule->wcag() );
	}

	private function rule( $version = '1.0.0' ) {
		$data            = $this->rule_data();
		$data['version'] = $version;
		return new RuleDefinition( $data );
	}

	private function rule_data() {
		return array(
			'id'                 => 'elavira.test.example',
			'version'            => '1.0.0',
			'editorial_version'  => '1.0.0',
			'title'              => 'Example rule',
			'description'        => 'Example description.',
			'evaluation_type'    => EvaluationType::AUTOMATIC_FAILURE,
			'classification'     => FindingClassification::DETECTED_FAILURE,
			'priority'           => Priority::LOW,
			'wcag'               => array( new WcagMapping( '1.1.1', 'A', 'perceivable' ) ),
			'summary'            => 'Example summary.',
			'why_it_matters'     => 'Example impact.',
			'recommendation'     => 'Example recommendation.',
			'wordpress_guidance' => 'Example WordPress guidance.',
			'developer_guidance' => 'Example developer guidance.',
			'evidence_schema'    => new EvidenceSchema( array(), 2 ),
			'grouping_schema'    => new EvidenceSchema( array(), 2 ),
			'grouping_strategy'  => new RuleLevelGrouping(),
		);
	}
}
