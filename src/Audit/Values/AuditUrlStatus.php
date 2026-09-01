<?php
/**
 * Audit URL status identifiers.
 *
 * @package Elavira
 */

namespace Elavira\Audit\Values;

/**
 * Page-level lifecycle states without queue semantics.
 */
final class AuditUrlStatus {
	const PENDING   = 'pending';
	const SCANNING  = 'scanning';
	const COMPLETED = 'completed';
	const FAILED    = 'failed';
	const CANCELLED = 'cancelled';

	/**
	 * Return all page-level scan statuses.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array( self::PENDING, self::SCANNING, self::COMPLETED, self::FAILED, self::CANCELLED );
	}
}
