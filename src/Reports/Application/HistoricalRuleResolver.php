<?php
/**
 * Historical report editorial resolution.
 *
 * @package Elavira
 */

namespace Elavira\Reports\Application;

use Elavira\Rules\RuleRegistry;
use Throwable;

/** Resolves only metadata compatible with the persisted rule/editorial versions. */
final class HistoricalRuleResolver {
	/** Rule registry.
	 *
	 * @var RuleRegistry
	 */
	private $rules;

	/** Create the resolver.
	 *
	 * @param RuleRegistry $rules Rule registry.
	 */
	public function __construct( RuleRegistry $rules ) {
		$this->rules = $rules;
	}

	/** Count current browser checks once per rule ID, excluding historical versions. @return int */
	public function current_rule_count() {
		$ids = array();
		foreach ( $this->rules->all() as $rule ) {
			$ids[ $rule->id() ] = true;
		}
		return count( $ids );
	}

	/** Resolve exact compatible guidance; never substitute another rule/editorial version.
	 *
	 * @param array $finding Persisted finding.
	 * @return array
	 * @throws \RuntimeException When the exact editorial version is unavailable.
	 */
	public function resolve( array $finding ) {
		try {
			$rule = $this->rules->get( $finding['rule_id'], $finding['rule_version'] );
			if ( $rule->editorial_version() !== $finding['editorial_version'] ) {
				throw new \RuntimeException( 'Editorial version unavailable.' );
			}
			return array(
				'available'       => true,
				'editorial'       => $rule->editorial(),
				'evaluation_type' => $rule->evaluation_type(),
			);
		} catch ( Throwable $error ) {
			return array(
				'available'       => false,
				'editorial'       => array(
					'title'   => $finding['summary_snapshot'],
					'summary' => $finding['summary_snapshot'],
				),
				'evaluation_type' => '',
			);
		}
	}
}
