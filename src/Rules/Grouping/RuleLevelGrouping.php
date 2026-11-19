<?php
/**
 * Rule-level finding grouping.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Grouping;

use Elavira\Rules\RuleDefinition;

/**
 * Groups every occurrence of a rule into one Finding per Audit.
 */
final class RuleLevelGrouping implements GroupingStrategy {
	/** Build a rule-level grouping key.
	 *
	 * @param RuleDefinition $rule   Trusted rule.
	 * @param array          $inputs Validated grouping inputs.
	 * @return string
	 */
	public function key( RuleDefinition $rule, array $inputs ) {
		unset( $inputs );
		return 'rule:' . $rule->id();
	}
}
