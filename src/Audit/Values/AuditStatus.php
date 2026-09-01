<?php
/**
 * Audit status identifiers.
 *
 * @package Elavira
 */

namespace Elavira\Audit\Values;

/**
 * Lifecycle states for a Core single-page audit.
 */
final class AuditStatus {
	const PENDING   = 'pending';
	const RUNNING   = 'running';
	const COMPLETED = 'completed';
	const FAILED    = 'failed';
	const CANCELLED = 'cancelled';

	/**
	 * Return all audit lifecycle statuses.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array( self::PENDING, self::RUNNING, self::COMPLETED, self::FAILED, self::CANCELLED );
	}
}
