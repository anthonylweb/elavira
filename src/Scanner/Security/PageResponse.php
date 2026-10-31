<?php
/**
 * Public HTTP response validation before creating an audit.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Security;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Generic.Files.LineLength

/** Follows only reauthorized same-site redirects, without forwarding credentials. */
final class PageResponse {
	public static function message( $code ) {
		$messages = array(
			'page_missing'    => __( 'The requested page could not be found (HTTP 404). Check the URL or explicitly choose Audit 404 page.', 'elavira' ),
			'page_blocked'    => __( 'Authentication or access restrictions blocked this request (HTTP 401/403). No audit was created.', 'elavira' ),
			'request_failed'  => __( 'The page request failed or timed out. Check connectivity and try again.', 'elavira' ),
			'response_failed' => __( 'The server returned an unsuccessful response. No audit was created.', 'elavira' ),
			'redirect_failed' => __( 'The redirect could not be followed safely. Enter the final public URL.', 'elavira' ),
			'expected_404'    => __( 'Audit 404 page requires a URL returning HTTP 404. This URL returned a successful response.', 'elavira' ),
		);
		return $messages[ $code ] ?? $code;
	}

	public function check( $url, $audit_404 = false ) {
		$policy = new CanonicalUrl();
		for ( $hop = 0; $hop < 6; ++$hop ) {
			$url      = $policy->authorize( $url );
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => 10,
					'redirection'         => 0,
					'limit_response_size' => 1024,
					'cookies'             => array(),
				)
			);
			if ( is_wp_error( $response ) ) {
				throw new \RuntimeException( 'request_failed' );
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				$location = wp_remote_retrieve_header( $response, 'location' );
				if ( ! is_string( $location ) || '' === $location ) {
					throw new \RuntimeException( 'redirect_failed' );
				}
				$url = \WP_Http::make_absolute_url( $location, $url );
				continue;
			}
			if ( 404 === $code ) {
				if ( $audit_404 ) {
					return $url;
				}
				throw new \RuntimeException( 'page_missing' );
			}
			if ( 401 === $code || 403 === $code ) {
				throw new \RuntimeException( 'page_blocked' );
			}
			if ( $code < 200 || $code >= 300 ) {
				throw new \RuntimeException( 'response_failed' );
			}
			if ( $audit_404 ) {
				throw new \RuntimeException( 'expected_404' );
			}
			return $url;
		}
		throw new \RuntimeException( 'redirect_failed' );
	}
}
