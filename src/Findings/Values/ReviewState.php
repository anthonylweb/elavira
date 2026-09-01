<?php
/**
 * Human review state identifiers.
 *
 * @package Elavira
 */

namespace Elavira\Findings\Values;

/**
 * Review state remains independent from internal priority.
 */
final class ReviewState {
	const NOT_REQUIRED = 'not_required';
	const NEEDS_REVIEW = 'needs_review';
	const REVIEWED     = 'reviewed';

	/**
	 * Return all human review states.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array( self::NOT_REQUIRED, self::NEEDS_REVIEW, self::REVIEWED );
	}
}
