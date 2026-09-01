<?php
/**
 * Elavira database table names.
 *
 * @package Elavira
 */

namespace Elavira\Core\Database;

/**
 * Builds collision-safe table names with the active WordPress prefix.
 */
final class TableNames {
	/**
	 * WordPress table prefix.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Constructor.
	 *
	 * @param string $prefix WordPress table prefix.
	 */
	public function __construct( $prefix ) {
		$this->prefix = $prefix . 'elavira_';
	}

	/**
	 * Return the audits table name.
	 *
	 * @return string
	 */
	public function audits() {
		return $this->prefix . 'audits';
	}

	/**
	 * Return the audit URLs table name.
	 *
	 * @return string
	 */
	public function audit_urls() {
		return $this->prefix . 'audit_urls';
	}

	/**
	 * Return the findings table name.
	 *
	 * @return string
	 */
	public function findings() {
		return $this->prefix . 'findings';
	}

	/**
	 * Return the occurrences table name.
	 *
	 * @return string
	 */
	public function occurrences() {
		return $this->prefix . 'occurrences';
	}

	/**
	 * Return the finding status history table name.
	 *
	 * @return string
	 */
	public function finding_status_history() {
		return $this->prefix . 'finding_status_history';
	}

	/** Return the short-lived scan sessions table name. @return string */
	public function scan_sessions() {
		return $this->prefix . 'scan_sessions';
	}

	/** Return the accepted scan batches table name. @return string */
	public function scan_batches() {
		return $this->prefix . 'scan_batches';
	}

	/**
	 * Return every Core audit table.
	 *
	 * @return string[]
	 */
	public function all() {
		return array(
			$this->audits(),
			$this->audit_urls(),
			$this->findings(),
			$this->occurrences(),
			$this->finding_status_history(),
			$this->scan_sessions(),
			$this->scan_batches(),
		);
	}
}
