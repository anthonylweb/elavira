<?php
/**
 * Evidence field definition.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Evidence;

use InvalidArgumentException;

/**
 * Defines one bounded scalar field accepted from browser evidence.
 */
final class FieldDefinition {
	const TYPE_STRING  = 'string';
	const TYPE_INTEGER = 'integer';
	const TYPE_BOOLEAN = 'boolean';

	/** Scalar field type.
	 *
	 * @var string
	 */
	private $type;

	/** Whether the field is required.
	 *
	 * @var bool
	 */
	private $required;

	/** Maximum string length.
	 *
	 * @var int
	 */
	private $max_length;

	/** Optional allowed values.
	 *
	 * @var array
	 */
	private $allowed_values;

	/**
	 * Preserve bounded literal attribute text for escaped reporting.
	 *
	 * @var bool
	 */
	private $preserve_text;

	/**
	 * Constructor.
	 *
	 * @param string $type           Scalar type.
	 * @param bool   $required       Whether the field is required.
	 * @param int    $max_length     Maximum string bytes.
	 * @param array  $allowed_values Optional exact scalar values.
	 * @param bool   $preserve_text Preserve literal attribute text; escape when rendered.
	 * @throws InvalidArgumentException When the definition is invalid.
	 */
	public function __construct( $type, $required = false, $max_length = 0, array $allowed_values = array(), $preserve_text = false ) {
		if ( ! in_array( $type, array( self::TYPE_STRING, self::TYPE_INTEGER, self::TYPE_BOOLEAN ), true ) ) {
			throw new InvalidArgumentException( 'Invalid evidence field type.' );
		}

		if ( self::TYPE_STRING === $type && ( $max_length < 1 || $max_length > 2048 ) ) {
			throw new InvalidArgumentException( 'String evidence fields require a safe maximum length.' );
		}

		$this->type           = $type;
		$this->required       = (bool) $required;
		$this->max_length     = (int) $max_length;
		$this->allowed_values = $allowed_values;
		$this->preserve_text  = (bool) $preserve_text;
	}

	/** Determine whether the field is required.
	 *
	 * @return bool
	 */
	public function required() {
		return $this->required;
	}

	/**
	 * Validate and return one scalar value.
	 *
	 * @param mixed $value Browser-submitted value.
	 * @return bool|int|string
	 * @throws InvalidArgumentException When the value violates this definition.
	 */
	public function validate( $value ) {
		if ( self::TYPE_STRING === $this->type ) {
			if ( ! is_string( $value ) || strlen( $value ) > $this->max_length || ( ! $this->preserve_text && preg_match( '/<[^>]*>/', $value ) ) ) {
				throw new InvalidArgumentException( 'Invalid string evidence field.' );
			}

			$value = $this->preserve_text ? $value : sanitize_text_field( $value );
		} elseif ( self::TYPE_INTEGER === $this->type ) {
			if ( ! is_int( $value ) ) {
				throw new InvalidArgumentException( 'Invalid integer evidence field.' );
			}
		} elseif ( ! is_bool( $value ) ) {
			throw new InvalidArgumentException( 'Invalid boolean evidence field.' );
		}

		if ( array() !== $this->allowed_values && ! in_array( $value, $this->allowed_values, true ) ) {
			throw new InvalidArgumentException( 'Evidence field contains an unsupported value.' );
		}

		return $value;
	}
}
