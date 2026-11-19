<?php
/**
 * Internal priority identifiers.
 *
 * @package Elavira
 */

namespace Elavira\Findings\Values;

/**
 * Priority excludes Needs Review, which is a human-evaluation state.
 */
final class Priority {
	const HIGH   = 'high';
	const MEDIUM = 'medium';
	const LOW    = 'low';

	/**
	 * Return internal priority values.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array( self::HIGH, self::MEDIUM, self::LOW );
	}
}
