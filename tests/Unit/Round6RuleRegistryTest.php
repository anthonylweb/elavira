<?php
/**
 * Round 6 image and cross-ruleset consistency tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Findings\Values\Priority;
use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\Learn\CoreTopicRegistry;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Rules\Values\EvaluationType;
use PHPUnit\Framework\TestCase;

final class Round6RuleRegistryTest extends TestCase {
	public function test_image_inventory_preserves_missing_alt_and_adds_exact_review_contracts() {
		$rules = CoreRuleRegistry::create();
		$this->assertSame( EvaluationType::AUTOMATIC_FAILURE, $rules->get( 'elavira.image.alt_missing', '1.0.0' )->evaluation_type() );
		$this->assertSame( Priority::HIGH, $rules->get( 'elavira.image.alt_missing', '1.0.0' )->priority() );
		$expected = array(
			'elavira.images.alt_quality_review' => EvaluationType::HUMAN_REVIEW,
			'elavira.images.filename_alt'       => EvaluationType::POTENTIAL_ISSUE,
			'elavira.images.long_alt_review'    => EvaluationType::HUMAN_REVIEW,
		);
		foreach ( $expected as $id => $evaluation ) {
			$rule = $rules->get( $id, '1.0.0' );
			$this->assertSame( $evaluation, $rule->evaluation_type() );
			$this->assertSame( Priority::MEDIUM, $rule->priority() );
			$this->assertSame( '1.1.1', $rule->wcag()[0]->criterion() );
			$this->assertSame( 'images_alternative_text', $rule->editorial()['learn_topic'] );
			$this->assertSame( 'elavira.decision.alternative_text', CoreDecisionRegistry::create()->for_rule( $id, '1.0.0' )->get( 'id' ) );
		}
		$this->assertContains( 'elavira.images.alt_quality_review', $rules->get( 'elavira.images.filename_alt', '1.0.0' )->supersedes() );
		$this->assertStringContainsString( 'Length alone is not a WCAG failure', $rules->get( 'elavira.images.long_alt_review', '1.0.0' )->editorial()['description'] );
	}

	public function test_every_rule_identity_mapping_priority_and_relationship_is_consistent() {
		$rules = CoreRuleRegistry::create();
		$keys  = array();
		$learn = CoreTopicRegistry::create();
		foreach ( $rules->all() as $rule ) {
			$key = $rule->id() . '@' . $rule->version();
			$this->assertArrayNotHasKey( $key, $keys );
			$keys[ $key ] = true;
			$this->assertContains( $rule->evaluation_type(), EvaluationType::all(), true );
			$this->assertContains( $rule->priority(), Priority::all(), true );
			foreach ( $rule->wcag() as $mapping ) {
				$this->assertNotSame( '', $mapping->criterion() );
				$this->assertContains( $mapping->level(), array( 'A', 'AA' ), true );
			}
			$topic = $rule->editorial()['learn_topic'];
			if ( $topic ) {
				$this->assertNotNull( $learn->find( $topic ) ); }
		}
	}

	public function test_supersession_graph_is_acyclic() {
		$rules = CoreRuleRegistry::create();
		$graph = array();
		foreach ( $rules->all() as $rule ) {
			$graph[ $rule->id() ] = $rule->supersedes(); }
		foreach ( $graph as $start => $ignored ) {
			$stack = array( array( $start, array() ) );
			while ( $stack ) {
				list( $current, $path ) = array_pop( $stack );
				$this->assertNotContains( $current, $path, 'Supersession cycle from ' . $start );
				$path[] = $current;
				foreach ( $graph[ $current ] ?? array() as $next ) {
					$stack[] = array( $next, $path ); }
			}
		}
	}

	public function test_every_guided_decision_binding_resolves_an_exact_rule_version() {
		$rules = CoreRuleRegistry::create();
		foreach ( CoreDecisionRegistry::create()->all() as $decision ) {
			foreach ( $decision->get( 'rules' ) as $binding ) {
				$this->assertSame( $binding['version'], $rules->get( $binding['id'], $binding['version'] )->version() );
			}
		}
	}

	public function test_every_learn_related_rule_is_a_registered_stable_identity() {
		$registered = array();
		foreach ( CoreRuleRegistry::create()->all() as $rule ) {
			$registered[ $rule->id() ] = true; }
		foreach ( CoreTopicRegistry::create()->all() as $topic ) {
			foreach ( $topic->get( 'related_rules' ) as $rule_id ) {
				$this->assertArrayHasKey( $rule_id, $registered );
			}
		}
	}

	public function test_image_evidence_change_changes_reviewed_exception_identity() {
		$identity = new ReviewedConditionIdentity();
		$target   = array(
			'selector' => 'main img',
			'tag_name' => 'img',
		);
		$element  = array( 'tag_name' => 'img' );
		$first    = $identity->fingerprints(
			$target,
			array(
				'alt_fingerprint' => '11111111',
				'context'         => 'standalone',
			),
			$element
		);
		$changed  = $identity->fingerprints(
			$target,
			array(
				'alt_fingerprint' => '22222222',
				'context'         => 'standalone',
			),
			$element
		);
		$this->assertNotSame( $first['evidence'], $changed['evidence'] );
	}
}
