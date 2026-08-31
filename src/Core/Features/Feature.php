<?php
/**
 * Feature identifiers.
 *
 * @package Elavira
 */

namespace Elavira\Core\Features;

/**
 * Stable machine-readable Core and Premium feature identifiers.
 */
final class Feature {
	const RENDERED_SINGLE_PAGE_SCANNING = 'rendered_single_page_scanning';
	const FINDINGS                      = 'findings';
	const REPORTS_HISTORY               = 'reports_history';
	const XLSX_EXPORT                   = 'xlsx_export';
	const GUIDED_DECISIONS              = 'guided_decisions';
	const ACCESSIBILITY_STATEMENT       = 'accessibility_statement';
	const VISITOR_TOOLBAR               = 'visitor_toolbar';
	const LEARN                         = 'learn';
	const CORE_SETTINGS                 = 'core_settings';

	const SITE_WIDE_AUDITS          = 'site_wide_audits';
	const BATCH_AUDITS              = 'selected_url_content_batch_audits';
	const REMOTE_BROWSER_WORKERS    = 'remote_browser_workers';
	const PERSISTENT_AUDIT_QUEUE    = 'persistent_site_wide_audit_queue';
	const CUSTOM_AUDIT_NAMES        = 'custom_audit_names';
	const ADVANCED_URL_RULES        = 'advanced_url_content_rules';
	const REPORT_RETENTION_CONTROLS = 'report_retention_controls';
	const DEVELOPER_DIAGNOSTICS     = 'developer_diagnostics';
	const PERMISSION_MANAGEMENT     = 'permission_management';

	/**
	 * Return all Core feature identifiers.
	 *
	 * @return string[]
	 */
	public static function core() {
		return array(
			self::RENDERED_SINGLE_PAGE_SCANNING,
			self::FINDINGS,
			self::REPORTS_HISTORY,
			self::XLSX_EXPORT,
			self::GUIDED_DECISIONS,
			self::ACCESSIBILITY_STATEMENT,
			self::VISITOR_TOOLBAR,
			self::LEARN,
			self::CORE_SETTINGS,
		);
	}

	/**
	 * Return all recognized Premium feature identifiers.
	 *
	 * @return string[]
	 */
	public static function premium() {
		return array(
			self::SITE_WIDE_AUDITS,
			self::BATCH_AUDITS,
			self::REMOTE_BROWSER_WORKERS,
			self::PERSISTENT_AUDIT_QUEUE,
			self::CUSTOM_AUDIT_NAMES,
			self::ADVANCED_URL_RULES,
			self::REPORT_RETENTION_CONTROLS,
			self::DEVELOPER_DIAGNOSTICS,
			self::PERMISSION_MANAGEMENT,
		);
	}
}
