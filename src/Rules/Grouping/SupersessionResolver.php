<?php
/**
 * Cross-rule supersession resolution.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Grouping;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, Generic.Commenting.DocComment.MissingShort, Generic.Files.LineLength

use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\Rules\RuleRegistry;

/** Resolves declared same-target precedence while retaining diagnostic evidence. */
final class SupersessionResolver {
	/** @var RuleRegistry */
	private $rules;
	/** @var ReviewedConditionIdentity */
	private $identity;

	/** @param RuleRegistry $rules Registry. @param ReviewedConditionIdentity $identity Identity service. */
	public function __construct( RuleRegistry $rules, ReviewedConditionIdentity $identity ) {
		$this->rules    = $rules;
		$this->identity = $identity;
	}

	/** Resolve a complete normalized batch deterministically. @return array[] */
	public function resolve( array $results ) {
		usort(
			$results,
			static function ( array $left, array $right ) {
				return strcmp( $left['rule_id'] . '@' . $left['rule_version'], $right['rule_id'] . '@' . $right['rule_version'] );
			}
		);
		foreach ( $results as $index => &$result ) {
			$result['diagnostics']         = $result['diagnostics'] ?? array();
			$result['_target_fingerprint'] = $this->identity->cross_rule_target( $result['target'] );
			foreach ( $results as $candidate_index => $candidate ) {
				if ( $index === $candidate_index || empty( $candidate['creates_finding'] ) ) {
					continue;
				}
				$candidate_target = $this->identity->cross_rule_target( $candidate['target'] );
				if ( $result['_target_fingerprint'] === $candidate_target && $this->rules->supersedes( $result['rule_id'], $result['rule_version'], $candidate['rule_id'] ) ) {
					$result['diagnostics'][]                        = array(
						'rule_id'      => $candidate['rule_id'],
						'rule_version' => $candidate['rule_version'],
						'outcome'      => $candidate['outcome'],
						'evidence'     => $candidate['evidence'],
					);
					$results[ $candidate_index ]['creates_finding'] = false;
				}
			}
		}
		unset( $result );
		foreach ( $results as &$result ) {
			unset( $result['_target_fingerprint'] );
		}
		return $results;
	}
}
