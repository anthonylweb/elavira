<?php
/**
 * Normalized trusted rule result.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Normalization;

/**
 * Transport-independent result suitable for later scan persistence orchestration.
 */
final class NormalizedRuleResult {
	/** Trusted normalized result data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param array $data Trusted normalized data.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/** Determine whether this outcome creates a finding.
	 *
	 * @return bool
	 */
	public function creates_finding() {
		return $this->data['creates_finding'];
	}

	/** Get the deterministic grouping key.
	 *
	 * @return string|null
	 */
	public function grouping_key() {
		return $this->data['grouping_key'];
	}

	/**
	 * Return normalized trusted metadata and evidence.
	 *
	 * @return array
	 */
	public function to_array() {
		return $this->data;
	}
}
