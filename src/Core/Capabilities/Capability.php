<?php
/**
 * Capability identifiers.
 *
 * @package Elavira
 */

namespace Elavira\Core\Capabilities;

/**
 * Stable capability names used for authorization.
 */
final class Capability {
	const MANAGE           = 'elavira_manage';
	const RUN_AUDITS       = 'elavira_run_audits';
	const VIEW_REPORTS     = 'elavira_view_reports';
	const MANAGE_STATEMENT = 'elavira_manage_statement';
	const MANAGE_TOOLBAR   = 'elavira_manage_toolbar';
	const MANAGE_SETTINGS  = 'elavira_manage_settings';

	/**
	 * Return all Core capabilities.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array(
			self::MANAGE,
			self::RUN_AUDITS,
			self::VIEW_REPORTS,
			self::MANAGE_STATEMENT,
			self::MANAGE_TOOLBAR,
			self::MANAGE_SETTINGS,
		);
	}
}
