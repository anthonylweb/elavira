<?php
/**
 * Rule evaluation type identifiers.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Values;

/**
 * Distinguishes automated certainty from cases requiring human judgment.
 */
final class EvaluationType {
	const AUTOMATIC_FAILURE = 'automatic_failure';
	const POTENTIAL_ISSUE   = 'potential_issue';
	const HUMAN_REVIEW      = 'human_review';

	/**
	 * Return all supported evaluation types.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array( self::AUTOMATIC_FAILURE, self::POTENTIAL_ISSUE, self::HUMAN_REVIEW );
	}
}
