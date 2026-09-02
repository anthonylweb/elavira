<?php
/**
 * Scan-session state identifiers.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Values;

/** Short-lived Core scan lifecycle. */
final class ScanSessionState {
	const CREATED    = 'created';
	const ACTIVE     = 'active';
	const COMPLETING = 'completing';
	const COMPLETED  = 'completed';
	const FAILED     = 'failed';
	const EXPIRED    = 'expired';

	/** Return all session states. @return string[] */
	public static function all() {
		return array( self::CREATED, self::ACTIVE, self::COMPLETING, self::COMPLETED, self::FAILED, self::EXPIRED );
	}
}
