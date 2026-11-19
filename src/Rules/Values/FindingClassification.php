<?php
/**
 * Finding classification identifiers.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Values;

/**
 * Represents scanner certainty separately from priority and workflow status.
 */
final class FindingClassification {
	const DETECTED_FAILURE = 'detected_failure';
	const POTENTIAL_ISSUE  = 'potential_issue';
	const HUMAN_REVIEW     = 'human_review';

	/**
	 * Return all finding classifications.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array( self::DETECTED_FAILURE, self::POTENTIAL_ISSUE, self::HUMAN_REVIEW );
	}
}
