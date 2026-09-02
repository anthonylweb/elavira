<?php
/**
 * Canonical scan-target authorization.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Security;

use InvalidArgumentException;

/** Restricts Core scans to public HTTP(S) URLs on the exact WordPress origin. */
final class CanonicalUrl {
	/**
	 * Validate and normalize a requested current-site URL.
	 *
	 * @param string $requested Requested URL.
	 * @return string
	 * @throws InvalidArgumentException When the target is not an allowed public site URL.
	 */
	public function authorize( $requested ) {
		if ( ! is_string( $requested ) || strlen( $requested ) > 2048 || false === wp_http_validate_url( $requested ) ) {
			throw new InvalidArgumentException( 'Choose a valid public URL on this WordPress site.' );
		}

		$target = wp_parse_url( $requested );
		$home   = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $target ) || ! is_array( $home ) || ! in_array( strtolower( $target['scheme'] ?? '' ), array( 'http', 'https' ), true ) ) {
			throw new InvalidArgumentException( 'The scan target must use HTTP or HTTPS.' );
		}

		$target_port = (int) ( $target['port'] ?? ( 'https' === strtolower( $target['scheme'] ) ? 443 : 80 ) );
		$home_port   = (int) ( $home['port'] ?? ( 'https' === strtolower( $home['scheme'] ) ? 443 : 80 ) );
		if ( strtolower( $target['scheme'] ) !== strtolower( $home['scheme'] ?? '' ) || strtolower( $target['host'] ?? '' ) !== strtolower( $home['host'] ?? '' ) || $target_port !== $home_port ) {
			throw new InvalidArgumentException( 'Core scans can only target this site.' );
		}

		$path = '/' . ltrim( $target['path'] ?? '/', '/' );
		if ( preg_match( '#^/(?:wp-admin(?:/|$)|wp-login\.php$|wp-cron\.php$|xmlrpc\.php$|wp-json(?:/|$))#i', $path ) ) {
			throw new InvalidArgumentException( 'WordPress administrative and internal endpoints cannot be scanned.' );
		}

		$query = array();
		if ( isset( $target['query'] ) ) {
			parse_str( $target['query'], $query );
			unset( $query['elavira_scan'] );
		}

		$url = strtolower( $target['scheme'] ) . '://' . strtolower( $target['host'] );
		if ( ! in_array( $target_port, array( 80, 443 ), true ) ) {
			$url .= ':' . $target_port;
		}
		$url .= $path;
		if ( array() !== $query ) {
			ksort( $query );
			$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		$object_id = url_to_postid( $url );
		if ( $object_id ) {
			$post_type = get_post_type_object( get_post_type( $object_id ) );
			if ( 'publish' !== get_post_status( $object_id ) || ! $post_type || ! is_post_type_viewable( $post_type ) ) {
				throw new InvalidArgumentException( 'Private or non-public WordPress content cannot be scanned.' );
			}
		}

		return esc_url_raw( $url );
	}
}
