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
	 * Resolve an origin-relative request without appending the home path twice.
	 *
	 * @param string $request_uri Origin-relative request URI.
	 * @return string
	 * @throws InvalidArgumentException When the request is not origin-relative.
	 */
	public function current_request( $request_uri ) {
		if ( ! is_string( $request_uri ) || '/' !== substr( $request_uri, 0, 1 ) || '//' === substr( $request_uri, 0, 2 ) ) {
			throw new InvalidArgumentException( 'Invalid scan request path.' );
		}
		$home   = wp_parse_url( home_url( '/' ) );
		$origin = $home['scheme'] . '://' . $home['host'];
		if ( isset( $home['port'] ) ) {
			$origin .= ':' . $home['port'];
		}
		return $this->authorize( $origin . $request_uri );
	}

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

		$path         = '/' . ltrim( $target['path'] ?? '/', '/' );
		$decoded_path = rawurldecode( $path );
		if ( false !== strpos( $decoded_path, '\\' ) || preg_match( '#(?:^|/)\.{1,2}(?:/|$)|%[0-9a-f]{2}#i', $decoded_path ) ) {
			throw new InvalidArgumentException( 'Ambiguous scan paths cannot be audited.' );
		}
		$home_path = rtrim( $home['path'] ?? '', '/' );
		if ( '' !== $home_path && $decoded_path !== $home_path && 0 !== strpos( $decoded_path, $home_path . '/' ) ) {
			throw new InvalidArgumentException( 'Core scans can only target this site.' );
		}
		$site_path      = rtrim( (string) wp_parse_url( site_url( '/' ), PHP_URL_PATH ), '/' );
		$relative_paths = array( $decoded_path );
		$rest_url       = rest_url();
		$rest_path      = rtrim( (string) wp_parse_url( $rest_url, PHP_URL_PATH ), '/' );
		if ( ! wp_parse_url( $rest_url, PHP_URL_QUERY ) && '' !== $rest_path && ( $decoded_path === $rest_path || 0 === strpos( $decoded_path, $rest_path . '/' ) ) ) {
			throw new InvalidArgumentException( 'WordPress REST endpoints cannot be scanned.' );
		}
		foreach ( array_unique( array( $home_path, $site_path ) ) as $base ) {
			if ( '' !== $base && 0 === strpos( $decoded_path, $base . '/' ) ) {
				$relative_paths[] = substr( $decoded_path, strlen( $base ) );
			}
		}
		foreach ( $relative_paths as $relative_path ) {
			if ( preg_match( '#^/+(?:wp-admin(?:/|$)|wp-login\.php(?:/|$)|wp-(?:cron|comments-post|trackback|signup|activate|mail|links-opml)\.php(?:/|$)|xmlrpc\.php(?:/|$)|wp-json(?:/|$))#i', $relative_path ) ) {
				throw new InvalidArgumentException( 'WordPress administrative and internal endpoints cannot be scanned.' );
			}
		}

		$query = array();
		if ( isset( $target['query'] ) ) {
			parse_str( $target['query'], $query );
			unset( $query['elavira_scan'] );
			$this->validate_query( $query );
		}

		$url = strtolower( $target['scheme'] ) . '://' . strtolower( $target['host'] );
		if ( ( 'https' === strtolower( $target['scheme'] ) ? 443 : 80 ) !== $target_port ) {
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

	/**
	 * Reject recognizable private parameters; preserve public page selectors.
	 *
	 * @param array $query Decoded parameters, including nested values.
	 * @return void
	 * @throws InvalidArgumentException When a private or internal parameter appears.
	 */
	private function validate_query( array $query ) {
		foreach ( $query as $key => $value ) {
			$name = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1_$2', (string) $key ) );
			if ( preg_match( '/(?:^|[_\-.])(token|nonce|wpnonce|password|passwd|pwd|secret|authorization|auth|session|sessionid|email|phone|signature|credential|api_key|apikey)(?:$|[_\-.])/', $name ) || in_array( $name, array( 'rest_route', 'preview', 'preview_id', 'customize_changeset_uuid' ), true ) ) {
				throw new InvalidArgumentException( 'sensitive_query' );
			}
			if ( is_array( $value ) ) {
				$this->validate_query( $value );
			} elseif ( is_string( $value ) && ( is_email( $value ) || preg_match( '/^(?:Bearer\s|eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.)/i', $value ) ) ) {
				throw new InvalidArgumentException( 'sensitive_query' );
			}
		}
	}
}
