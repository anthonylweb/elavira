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
	const PASS              = 'pass';
	const AUTOMATIC_FAILURE = 'automatic_failure';
	const POTENTIAL_ISSUE   = 'potential_issue';
	const HUMAN_REVIEW      = 'human_review';
	const NOT_EVALUATED     = 'not_evaluated';

	/** Legacy browser-contract values accepted for existing scanner clients. */
	const LEGACY_FAILURE         = 'failure';
	const LEGACY_REVIEW_REQUIRED = 'review_required';

	/**
	 * Return all browser evidence outcomes.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array( self::PASS, self::AUTOMATIC_FAILURE, self::POTENTIAL_ISSUE, self::HUMAN_REVIEW, self::NOT_EVALUATED );
	}

	/** Return accepted transport values, including explicit legacy aliases. @return string[] */
	public static function accepted() {
		return array_merge( self::all(), array( self::LEGACY_FAILURE, self::LEGACY_REVIEW_REQUIRED ) );
	}
}
