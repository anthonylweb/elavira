<?php
/**
 * Persistence-boundary validation.
 *
 * @package Elavira
 */

namespace Elavira\Core\Database;

use DateTime;
use InvalidArgumentException;

/**
 * Validates bounded values before they reach WordPress database APIs.
 */
final class PersistenceValidator {
	const CONFIGURATION_MAX_BYTES = 65535;
	const TARGET_MAX_BYTES        = 8192;
	const EVIDENCE_MAX_BYTES      = 16384;
	const ELEMENT_MAX_BYTES       = 8192;

	/**
	 * Validate an allowed machine-readable identifier.
	 *
	 * @param string   $value   Candidate value.
	 * @param string[] $allowed Allowed values.
	 * @param string   $field   Field name for errors.
	 * @return string
	 * @throws InvalidArgumentException When the value is not allowed.
	 */
	public static function one_of( $value, array $allowed, $field ) {
		unset( $field );

		if ( ! is_string( $value ) || ! in_array( $value, $allowed, true ) ) {
			throw new InvalidArgumentException( 'A persisted value is not allowed.' );
		}

		return $value;
	}

	/**
	 * Validate and sanitize bounded text.
	 *
	 * @param mixed  $value     Candidate value.
	 * @param int    $max_bytes Maximum UTF-8 byte length.
	 * @param string $field     Field name for errors.
	 * @param bool   $multiline Whether line breaks are permitted.
	 * @return string
	 * @throws InvalidArgumentException When the value is invalid or too large.
	 */
	public static function text( $value, $max_bytes, $field, $multiline = false ) {
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'A persisted text value is invalid.' );
		}

		$value = $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );

		if ( strlen( $value ) > $max_bytes ) {
			throw new InvalidArgumentException( 'A persisted text value is too large.' );
		}

		return $value;
	}

	/**
	 * Validate a stable version/identifier string.
	 *
	 * @param mixed  $value Candidate value.
	 * @param string $field Field name for errors.
	 * @return string
	 * @throws InvalidArgumentException When the identifier is invalid.
	 */
	public static function identifier( $value, $field ) {
		$field = sanitize_key( $field );
		$value = self::text( $value, 191, $field );

		if ( '' === $value || ! preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ) {
			throw new InvalidArgumentException( 'A persisted identifier is invalid.' );
		}

		return $value;
	}

	/**
	 * Encode structured, privacy-conscious JSON within a byte limit.
	 *
	 * @param mixed  $value     Structured array.
	 * @param int    $max_bytes Maximum encoded bytes.
	 * @param string $field     Field name for errors.
	 * @return string
	 * @throws InvalidArgumentException When the structure is invalid, prohibited, or too large.
	 */
	public static function json( $value, $max_bytes, $field ) {
		unset( $field );

		if ( ! is_array( $value ) ) {
			throw new InvalidArgumentException( 'A persisted structured value is invalid.' );
		}

		self::reject_sensitive_keys( $value );
		$encoded = wp_json_encode( $value );

		if ( false === $encoded || strlen( $encoded ) > $max_bytes ) {
			throw new InvalidArgumentException( 'A persisted structured value is invalid or too large.' );
		}

		return $encoded;
	}

	/**
	 * Validate an optional UTC database timestamp.
	 *
	 * @param mixed  $value Candidate timestamp or null.
	 * @param string $field Field name for errors.
	 * @return string|null
	 * @throws InvalidArgumentException When the timestamp format is invalid.
	 */
	public static function datetime( $value, $field ) {
		unset( $field );

		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'A persisted timestamp is invalid.' );
		}

		$date = DateTime::createFromFormat( 'Y-m-d H:i:s', $value );

		if ( false === $date || $date->format( 'Y-m-d H:i:s' ) !== $value ) {
			throw new InvalidArgumentException( 'A persisted timestamp is invalid.' );
		}

		return $value;
	}

	/**
	 * Reject keys that invite storage of secrets, form values, or HTML blobs.
	 *
	 * @param array $value Structured evidence.
	 * @return void
	 * @throws InvalidArgumentException When a prohibited key is present.
	 */
	private static function reject_sensitive_keys( array $value ) {
		$blocked = array(
			'password',
			'token',
			'authorization',
			'cookie',
			'secret',
			'form_value',
			'input_value',
			'html',
			'inner_html',
			'outer_html',
			'page_html',
			'request_body',
			'response_body',
		);

		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && in_array( strtolower( $key ), $blocked, true ) ) {
				throw new InvalidArgumentException( 'Structured evidence contains a prohibited field.' );
			}

			if ( is_array( $item ) ) {
				self::reject_sensitive_keys( $item );
			}
		}
	}
}
