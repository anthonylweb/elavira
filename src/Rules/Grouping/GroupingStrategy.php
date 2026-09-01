<?php
/**
 * Finding grouping strategy contract.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Grouping;

use Elavira\Rules\RuleDefinition;

/**
 * Generates a server-authoritative grouped Finding key.
 */
interface GroupingStrategy {
	/**
	 * Generate a stable bounded key from validated inputs.
	 *
	 * @param RuleDefinition $rule   Trusted rule definition.
	 * @param array          $inputs Validated grouping inputs.
	 * @return string
	 */
	public function key( RuleDefinition $rule, array $inputs );
}
