<?php
/**
 * Validated-input finding grouping.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Grouping;

use Elavira\Rules\RuleDefinition;

/**
 * Groups occurrences by a deterministic hash of whitelisted scalar inputs.
 */
final class InputGrouping implements GroupingStrategy {
	/** Build a grouping key from validated inputs.
	 *
	 * @param RuleDefinition $rule   Trusted rule.
	 * @param array          $inputs Validated grouping inputs.
	 * @return string
	 */
	public function key( RuleDefinition $rule, array $inputs ) {
		ksort( $inputs );
		$digest = hash( 'sha256', (string) wp_json_encode( $inputs ) );

		return 'rule:' . $rule->id() . ':' . substr( $digest, 0, 32 );
	}
}
