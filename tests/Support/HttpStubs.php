<?php
/**
 * WordPress HTTP test doubles.
 *
 * @package Elavira
 */

/** HTTP URL resolver stub. */
class WP_Http {
	public static function make_absolute_url( $location, $base ) {
		unset( $base );
		return strpos( $location, '://' ) !== false ? $location : 'https://example.com/' . ltrim( $location, '/' );
	}
}

class WP_Error {
	public function __construct( $code, $message = '', $data = array() ) {}
}
