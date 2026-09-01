<?php
/**
 * Browser rule-result outcomes.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Values;

/**
 * Pass results may be consumed transiently and do not become Findings.
 */
final class RuleOutcome {
	const FAILURE         = 'failure';
	const REVIEW_REQUIRED = 'review_required';
	const PASS            = 'pass';

	/**
	 * Return all browser evidence outcomes.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array( self::FAILURE, self::REVIEW_REQUIRED, self::PASS );
	}
}
