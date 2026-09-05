<?php
/**
 * Rule result normalization.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Normalization;

use Elavira\Findings\Values\ReviewState;
use Elavira\Rules\Browser\BrowserRuleResult;
use Elavira\Rules\Values\EvaluationType;
use Elavira\Rules\Values\RuleOutcome;

/**
 * Resolves browser evidence into server-authoritative finding metadata.
 */
final class RuleResultNormalizer {
	/**
	 * Normalize one validated browser result.
	 *
	 * Pass results remain transient by setting creates_finding to false.
	 *
	 * @param BrowserRuleResult $result Validated browser result.
	 * @return NormalizedRuleResult
	 */
	public function normalize( BrowserRuleResult $result ) {
		$rule            = $result->rule();
		$creates_finding = in_array( $result->outcome(), array( RuleOutcome::AUTOMATIC_FAILURE, RuleOutcome::POTENTIAL_ISSUE, RuleOutcome::HUMAN_REVIEW ), true );
		$mappings        = array_map(
			static function ( $mapping ) {
				return $mapping->to_array();
			},
			$rule->wcag()
		);
		$primary         = reset( $mappings );
		$review_state    = EvaluationType::AUTOMATIC_FAILURE === $rule->evaluation_type()
			? ReviewState::NOT_REQUIRED
			: ReviewState::NEEDS_REVIEW;

		return new NormalizedRuleResult(
			array(
				'creates_finding'   => $creates_finding,
				'rule_id'           => $rule->id(),
				'rule_version'      => $rule->version(),
				'editorial_version' => $rule->editorial_version(),
				'outcome'           => $result->outcome(),
				'classification'    => $rule->classification(),
				'priority'          => $rule->priority(),
				'review_state'      => $review_state,
				'wcag'              => $mappings,
				'wcag_criterion'    => $primary['criterion'],
				'wcag_level'        => $primary['level'],
				'wcag_principle'    => $primary['principle'],
				'editorial'         => $rule->editorial(),
				'grouping_key'      => $creates_finding ? $rule->grouping_strategy()->key( $rule, $result->grouping() ) : null,
				'target'            => $result->target(),
				'evidence'          => $result->evidence(),
				'element'           => $result->element(),
			)
		);
	}
}
