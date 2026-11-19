<?php
/**
 * Structured Statement field validation.
 *
 * @package Elavira
 */

namespace Elavira\Statement;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag -- Concise validation comments are intentional.

use InvalidArgumentException;

/** Defines the bounded, plain-text Statement content contract. */
final class StatementFields {
	/** Validate and sanitize submitted fields. @param array $input Raw input. @return array */
	public static function sanitize( array $input ) {
		$fields = array(
			'title'                  => self::text( $input, 'title', 191, false ),
			'organization'           => self::text( $input, 'organization', 191, false ),
			'commitment'             => self::text( $input, 'commitment', 4000 ),
			'wcag_target'            => self::choice( $input, 'wcag_target', array( '2.2_A', '2.2_AA' ) ),
			'plugin_findings_status' => self::choice( $input, 'plugin_findings_status', array( 'not_assessed', 'unresolved_findings', 'no_findings_identified' ) ),
			'stated_conformance'     => self::choice( $input, 'stated_conformance', array( 'not_stated', 'partially_conformant', 'fully_conformant', 'non_conformant' ) ),
			'known_limitations'      => self::text( $input, 'known_limitations', 8000 ),
			'feedback'               => self::text( $input, 'feedback', 4000 ),
			'contact_email'          => self::email( $input['contact_email'] ?? '' ),
			'contact_url'            => self::url( $input['contact_url'] ?? '' ),
			'contact_phone'          => self::text( $input, 'contact_phone', 64 ),
			'compatibility'          => self::text( $input, 'compatibility', 4000 ),
			'assessment_approach'    => self::text( $input, 'assessment_approach', 4000 ),
			'additional_information' => self::text( $input, 'additional_information', 4000 ),
		);
		if ( '' === $fields['title'] || '' === $fields['organization'] ) {
			throw new InvalidArgumentException( 'Statement title and organization are required.' );
		}
		return $fields;
	}

	/** Return safe initial fields. @return array */
	public static function defaults() {
		return self::sanitize(
			array(
				'title'                  => __( 'Accessibility Statement', 'elavira' ),
				'organization'           => get_bloginfo( 'name' ),
				'commitment'             => __( 'We are committed to improving the accessibility of this website.', 'elavira' ),
				'wcag_target'            => '2.2_AA',
				'plugin_findings_status' => 'not_assessed',
				'stated_conformance'     => 'not_stated',
			)
		);
	}

	/** Sanitize bounded plain text. @param array $input Input. @param string $key Key. @param int $limit Limit. @param bool $multiline Multiline. @return string */
	private static function text( array $input, $key, $limit, $multiline = true ) {
		$value = (string) ( $input[ $key ] ?? '' );
		$value = $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		return substr( $value, 0, $limit );
	}

	/** Validate an enum. @param array $input Input. @param string $key Key. @param array $allowed Allowed. @return string */
	private static function choice( array $input, $key, array $allowed ) {
		$value = sanitize_text_field( $input[ $key ] ?? '' );
		if ( ! in_array( $value, $allowed, true ) ) {
			throw new InvalidArgumentException( 'A Statement field contains an unsupported value.' );
		}
		return $value;
	}

	/** Validate an optional email. @param string $value Value. @return string */
	private static function email( $value ) {
		$raw   = trim( (string) $value );
		$value = sanitize_email( $raw );
		if ( '' !== $raw && ( '' === $value || ! is_email( $value ) ) ) {
			throw new InvalidArgumentException( 'The contact email address is invalid.' );
		}
		return $value;
	}

	/** Validate an optional HTTP(S) URL. @param string $value Value. @return string */
	private static function url( $value ) {
		$raw    = trim( (string) $value );
		$scheme = strtolower( (string) wp_parse_url( $raw, PHP_URL_SCHEME ) );
		$value  = esc_url_raw( $raw, array( 'http', 'https' ) );
		if ( '' !== $raw && ( '' === $value || ! in_array( $scheme, array( 'http', 'https' ), true ) ) ) {
			throw new InvalidArgumentException( 'The contact URL is invalid.' );
		}
		return $value;
	}
}
