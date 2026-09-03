<?php
/**
 * Lightweight WCAG reference entry.
 *
 * @package Elavira
 */

namespace Elavira\Learn;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment -- Concise immutable model comments are intentional.

/** Immutable criterion metadata relevant to the bundled Learn scope. */
final class WcagCriterion {
	/** Data. @var array */
	private $data;

	/** Create an entry. @param array $data Trusted metadata. */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/** Read metadata. @param string $key Key. @return mixed */
	public function get( $key ) {
		return $this->data[ $key ] ?? null;
	}
}
