<?php
/**
 * Generic supersession contract tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

// phpcs:disable Generic.Files.LineLength

use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\Findings\Values\Priority;
use Elavira\Rules\Evidence\EvidenceSchema;
use Elavira\Rules\Grouping\RuleLevelGrouping;
use Elavira\Rules\Grouping\SupersessionResolver;
use Elavira\Rules\RuleDefinition;
use Elavira\Rules\RuleRegistry;
use Elavira\Rules\Values\EvaluationType;
use Elavira\Rules\Values\FindingClassification;
use Elavira\Rules\WcagMapping;
use PHPUnit\Framework\TestCase;

final class SupersessionResolverTest extends TestCase {
	public function test_declared_precedence_is_deterministic_and_preserves_diagnostics() {
		$registry = new RuleRegistry();
		$registry->register( $this->rule( 'elavira.test.generic', array() ) );
		$registry->register( $this->rule( 'elavira.test.primary', array( 'elavira.test.generic' ) ) );
		$resolver = new SupersessionResolver( $registry, new ReviewedConditionIdentity() );
		$resolved = $resolver->resolve( array( $this->result( 'elavira.test.generic', 'same' ), $this->result( 'elavira.test.primary', 'same' ) ) );
		$active   = array_values(
			array_filter(
				$resolved,
				static function ( $result ) {
					return $result['creates_finding'];
				}
			)
		);
		$this->assertCount( 1, $active );
		$this->assertSame( 'elavira.test.primary', $active[0]['rule_id'] );
		$this->assertSame( 'elavira.test.generic', $active[0]['diagnostics'][0]['rule_id'] );
		$this->assertSame( $resolved, $resolver->resolve( array_reverse( array( $this->result( 'elavira.test.generic', 'same' ), $this->result( 'elavira.test.primary', 'same' ) ) ) ) );
	}

	public function test_distinct_targets_or_undeclared_problems_are_not_collapsed() {
		$registry = new RuleRegistry();
		$registry->register( $this->rule( 'elavira.test.generic', array() ) );
		$registry->register( $this->rule( 'elavira.test.primary', array( 'elavira.test.generic' ) ) );
		$resolver = new SupersessionResolver( $registry, new ReviewedConditionIdentity() );
		$resolved = $resolver->resolve( array( $this->result( 'elavira.test.generic', 'one' ), $this->result( 'elavira.test.primary', 'two' ) ) );
		$this->assertCount(
			2,
			array_filter(
				$resolved,
				static function ( $result ) {
					return $result['creates_finding'];
				}
			)
		);
	}

	private function rule( $id, array $supersedes ) {
		return new RuleDefinition(
			array(
				'id'                 => $id,
				'version'            => '1.0.0',
				'editorial_version'  => '1.0.0',
				'title'              => 'Test rule',
				'description'        => 'Test description',
				'evaluation_type'    => EvaluationType::AUTOMATIC_FAILURE,
				'classification'     => FindingClassification::DETECTED_FAILURE,
				'priority'           => Priority::HIGH,
				'wcag'               => array( new WcagMapping( '4.1.2', 'A', 'robust' ) ),
				'summary'            => 'Summary',
				'why_it_matters'     => 'Impact',
				'recommendation'     => 'Recommendation',
				'wordpress_guidance' => 'WordPress guidance',
				'developer_guidance' => 'Developer guidance',
				'evidence_schema'    => new EvidenceSchema( array(), 128 ),
				'grouping_schema'    => new EvidenceSchema( array(), 128 ),
				'grouping_strategy'  => new RuleLevelGrouping(),
				'supersedes'         => $supersedes,
			)
		);
	}

	private function result( $rule_id, $locator ) {
		return array(
			'creates_finding' => true,
			'rule_id'         => $rule_id,
			'rule_version'    => '1.0.0',
			'outcome'         => 'automatic_failure',
			'target'          => array(
				'tag_name' => 'button',
				'selector' => '#button',
				'locator'  => $locator,
			),
			'evidence'        => array(),
			'element'         => array( 'tag_name' => 'button' ),
		);
	}
}
