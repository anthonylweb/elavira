<?php
/**
 * Structured WCAG mapping.
 *
 * @package Elavira
 */

namespace Elavira\Rules;

use InvalidArgumentException;

/**
 * Immutable WCAG 2.2 success-criterion metadata owned by Core.
 */
final class WcagMapping {
	/** Success criterion.
	 *
	 * @var string
	 */
	private $criterion;

	/** Conformance level.
	 *
	 * @var string
	 */
	private $level;

	/** WCAG principle.
	 *
	 * @var string
	 */
	private $principle;

	/**
	 * Constructor.
	 *
	 * @param string $criterion WCAG criterion identifier.
	 * @param string $level     WCAG conformance level.
	 * @param string $principle WCAG principle.
	 * @throws InvalidArgumentException When WCAG metadata is invalid.
	 */
	public function __construct( $criterion, $level, $principle ) {
		if ( ! is_string( $criterion ) || ! preg_match( '/^[1-4]\.[1-9][0-9]*\.[1-9][0-9]*$/', $criterion ) ) {
			throw new InvalidArgumentException( 'Invalid WCAG criterion.' );
		}

		if ( ! in_array( $level, array( 'A', 'AA' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid Core WCAG level.' );
		}

		if ( ! in_array( $principle, array( 'perceivable', 'operable', 'understandable', 'robust' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid WCAG principle.' );
		}

		$this->criterion = $criterion;
		$this->level     = $level;
		$this->principle = $principle;
	}

	/** Get the criterion. @return string */
	public function criterion() {
		return $this->criterion;
	}

	/** Get the level. @return string */
	public function level() {
		return $this->level;
	}

	/** Get the principle. @return string */
	public function principle() {
		return $this->principle;
	}

	/**
	 * Return persistence-friendly structured metadata.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'criterion' => $this->criterion,
			'level'     => $this->level,
			'principle' => $this->principle,
		);
	}
}
