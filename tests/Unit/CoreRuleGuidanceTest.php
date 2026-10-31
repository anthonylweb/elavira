<?php
/**
 * Editorial coverage and unchanged scanner contracts.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Rules\CoreRuleRegistry;
use Elavira\Rules\CoreRuleGuidance;
use Elavira\Rules\RuleDefinition;
use Elavira\Reports\Application\HistoricalRuleResolver;
use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\Learn\CoreTopicRegistry;
use PHPUnit\Framework\TestCase;

final class CoreRuleGuidanceTest extends TestCase {
	private function baseline() {
		return json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/docs/AUDIT_RULE_EDITORIAL_BASELINE.json' ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_every_current_contract_has_intentional_structured_guidance() {
		$current = array();
		foreach ( CoreRuleRegistry::create()->all() as $rule ) {
			$current[ $rule->id() ] = $rule;
		}
		$keys = array();
		foreach ( $current as $rule ) {
			$key    = $rule->id() . '@' . $rule->version();
			$keys[] = $key;
			$copy   = $rule->editorial();
			foreach ( array( 'summary', 'why_it_matters', 'what_to_check', 'recommendation', 'verification_guidance', 'wordpress_guidance', 'developer_guidance' ) as $field ) {
				$this->assertNotEmpty( $copy[ $field ], $key . ' ' . $field );
				$this->assertNotSame( 'Review this element.', $copy[ $field ], $key );
			}
			$this->assertNotSame( $copy['what_to_check'], $copy['recommendation'], $key );
			$this->assertNotSame( 'Run the audit again', $copy['verification_guidance'], $key );
		}
		$this->assertEqualsCanonicalizing( $keys, array_keys( CoreRuleGuidance::all() ) );
	}

	public function test_current_copy_is_revised_and_historical_copy_is_retained_exactly() {
		$rules    = CoreRuleRegistry::create();
		$resolver = new HistoricalRuleResolver( $rules );
		foreach ( $this->baseline() as $before ) {
			$rule = $rules->get( $before['id'], $before['version'] );
			$key  = $before['id'] . '@' . $before['version'];
			$this->assertSame( $before['editorial_version'], $rule->editorial_version(), $key );
			$this->assertSame( $before['outcome'], $rule->evaluation_type(), $key );
			$resolved = $resolver->resolve(
				array(
					'rule_id'           => $before['id'],
					'rule_version'      => $before['version'],
					'editorial_version' => $before['editorial_version'],
					'summary_snapshot'  => 'Stored summary',
				)
			);
			$this->assertTrue( $resolved['available'], $key );
			if ( ! $before['current'] ) {
				$historical = $resolved['editorial'];
				$this->assertFalse( $historical['wordpress_guidance_useful'] );
				unset( $historical['wordpress_guidance_useful'] );
				$this->assertSame( $before['editorial'], $historical, $key );
				$this->assertArrayNotHasKey( $key, CoreRuleGuidance::all() );
				continue;
			}
			foreach ( array( 'summary', 'why_it_matters', 'recommendation', 'wordpress_guidance', 'developer_guidance' ) as $field ) {
				$this->assertNotSame( $before['editorial'][ $field ], $resolved['editorial'][ $field ], $key . ' ' . $field );
			}
		}
	}

	public function test_editorial_refinement_cannot_change_detection_metadata() {
		$base     = ( new \ReflectionMethod( CoreRuleRegistry::class, 'rules' ) )->invoke( null );
		$base     = array_merge( $base, \Elavira\Rules\RoundThreeRuleDefinitions::all(), \Elavira\Rules\RoundFourRuleDefinitions::all(), \Elavira\Rules\RoundFiveRuleDefinitions::all(), \Elavira\Rules\RoundSixRuleDefinitions::all() );
		$registry = CoreRuleRegistry::create();
		$data     = new \ReflectionProperty( RuleDefinition::class, 'data' );
		foreach ( $base as $original ) {
			$current = $registry->get( $original->id(), $original->version() );
			$this->assertEquals( array_diff_key( $data->getValue( $original ), $original->editorial() ), array_diff_key( $data->getValue( $current ), $current->editorial() ) );
		}
		$this->expectException( \InvalidArgumentException::class );
		$base[0]->with_editorial( array( 'evaluation_type' => 'human_review' ) );
	}

	public function test_help_mappings_resolve_exactly_and_unknown_versions_do_not() {
		$decisions = CoreDecisionRegistry::create();
		$topics    = CoreTopicRegistry::create();
		foreach ( CoreRuleRegistry::create()->all() as $rule ) {
			$copy = $rule->editorial();
			if ( $copy['learn_topic'] ) {
				$this->assertNotNull( $topics->find( $copy['learn_topic'] ), $rule->id() );
			}
			$decision = $decisions->for_rule( $rule->id(), $rule->version() );
			$this->assertSame( (bool) $copy['guided_decision'], (bool) $decision, $rule->id() );
			if ( $decision ) {
				$this->assertTrue( $decision->supports( $rule->id(), $rule->version() ) );
				$this->assertSame( 'elavira.decision.' . $copy['guided_decision'], $decision->get( 'id' ) );
			}
			$this->assertNull( $decisions->for_rule( $rule->id(), '99.0.0' ) );
		}
		$this->assertSame( 'elavira.decision.focus_visibility', $decisions->for_rule( 'elavira.focus.obscured_review', '1.0.0' )->get( 'id' ) );
		$this->assertNull( $decisions->for_rule( 'elavira.semantics.control_missing_name', '1.0.0' ) );
	}

	public function test_linked_image_guidance_teaches_function_context_and_acceptable_decisions() {
		$copy = CoreRuleRegistry::create()->get( 'elavira.images.alt_quality_review', '1.0.0' )->editorial();
		$this->assertStringContainsString( 'alternative may already be appropriate', $copy['summary'] );
		$this->assertStringContainsString( 'destination or action', $copy['recommendation'] );
		$this->assertStringContainsString( 'alt="" can be correct', $copy['recommendation'] );
		$this->assertStringContainsString( 'View Fall Collection', $copy['recommendation'] );
		$this->assertStringContainsString( 'not a guaranteed complete accessibility-tree name', $copy['developer_guidance'] );
		$this->assertStringContainsString( 'choose wording for the actual destination', $copy['examples'][0]['explanation'] );
		$this->assertStringContainsString( 'review finding can remain', $copy['verification_guidance'] );
	}

	public function test_all_new_prose_uses_literal_translation_calls() {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Rules/CoreRuleGuidance.php' );
		$count  = count( CoreRuleGuidance::all() );
		foreach ( array( 'summary', 'why_it_matters', 'what_to_check', 'recommendation', 'verification_guidance', 'wordpress_guidance', 'developer_guidance' ) as $field ) {
			preg_match_all( "/'" . $field . "'\\s*=>\\s*__\\(\\s*'(?:[^'\\\\]|\\\\.)*', 'elavira' \\)/u", $source, $matches );
			$this->assertCount( $count, $matches[0], $field );
		}
		$examples = array_sum(
			array_map(
				static function ( $copy ) {
					return count( $copy['examples'] );
				},
				CoreRuleGuidance::all()
			)
		);
		foreach ( array( 'instead_of', 'consider', 'explanation' ) as $field ) {
			preg_match_all( "/'" . $field . "'\\s*=>\\s*__\\(\\s*'(?:[^'\\\\]|\\\\.)*', 'elavira' \\)/u", $source, $matches );
			$this->assertCount( $examples, $matches[0], $field );
		}
	}
}
