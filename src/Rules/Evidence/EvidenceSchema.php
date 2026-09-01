<?php
/**
 * Lightweight structured evidence schema.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Evidence;

use Elavira\Core\Database\PersistenceValidator;
use InvalidArgumentException;

/**
 * Whitelists a small set of bounded scalar fields for one evidence object.
 */
final class EvidenceSchema {
	/** Named evidence fields.
	 *
	 * @var FieldDefinition[]
	 */
	private $fields;

	/** Maximum encoded size.
	 *
	 * @var int
	 */
	private $max_bytes;

	/**
	 * Constructor.
	 *
	 * @param FieldDefinition[] $fields    Named field definitions.
	 * @param int               $max_bytes Maximum encoded object bytes.
	 * @throws InvalidArgumentException When the schema is invalid.
	 */
	public function __construct( array $fields, $max_bytes ) {
		if ( count( $fields ) > 20 || $max_bytes < 2 ) {
			throw new InvalidArgumentException( 'Invalid evidence schema bounds.' );
		}

		foreach ( $fields as $name => $definition ) {
			if ( ! is_string( $name ) || ! preg_match( '/^[a-z][a-z0-9_]*$/', $name ) || ! $definition instanceof FieldDefinition ) {
				throw new InvalidArgumentException( 'Invalid evidence schema field.' );
			}
		}

		$this->fields    = $fields;
		$this->max_bytes = $max_bytes;
	}

	/**
	 * Validate a browser-submitted evidence object.
	 *
	 * @param mixed $values Candidate associative array.
	 * @return array
	 * @throws InvalidArgumentException When fields are missing, unknown, malformed, or unsafe.
	 */
	public function validate( $values ) {
		if ( ! is_array( $values ) || count( $values ) > count( $this->fields ) ) {
			throw new InvalidArgumentException( 'Invalid evidence object.' );
		}

		$unknown = array_diff( array_keys( $values ), array_keys( $this->fields ) );

		if ( array() !== $unknown ) {
			throw new InvalidArgumentException( 'Evidence contains an unknown field.' );
		}

		$validated = array();

		foreach ( $this->fields as $name => $definition ) {
			if ( ! array_key_exists( $name, $values ) ) {
				if ( $definition->required() ) {
					throw new InvalidArgumentException( 'Evidence is missing a required field.' );
				}

				continue;
			}

			$validated[ $name ] = $definition->validate( $values[ $name ] );
		}

		PersistenceValidator::json( $validated, $this->max_bytes, 'browser evidence' );

		return $validated;
	}
}
