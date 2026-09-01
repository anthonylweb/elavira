<?php
/**
 * Audit scope identifiers.
 *
 * @package Elavira
 */

namespace Elavira\Audit\Values;

/**
 * Core audit scopes. Additional Premium scopes can be added without schema changes.
 */
final class AuditScope {
	const SINGLE_PAGE = 'single_page';

	/**
	 * Return Core audit scopes.
	 *
	 * @return string[]
	 */
	public static function core() {
		return array( self::SINGLE_PAGE );
	}
}
