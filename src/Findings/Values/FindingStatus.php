<?php
/**
 * Finding workflow status identifiers.
 *
 * @package Elavira
 */

namespace Elavira\Findings\Values;

/**
 * Statuses preserve user declarations separately from re-audit verification.
 */
final class FindingStatus {
	const OPEN                = 'open';
	const ADDRESSED           = 'addressed';
	const VERIFIED            = 'verified';
	const ACCEPTED_LIMITATION = 'accepted_limitation';
	const NEEDS_REVIEW        = 'needs_review';

	/**
	 * Return all finding workflow statuses.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array(
			self::OPEN,
			self::ADDRESSED,
			self::VERIFIED,
			self::ACCEPTED_LIMITATION,
			self::NEEDS_REVIEW,
		);
	}
}
