<?php
/**
 * Signed request-bound Guided Decision state.
 *
 * @package Elavira
 */

namespace Elavira\GuidedDecisions;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag -- Concise codec comments are intentional.

use InvalidArgumentException;

/** Prevents the browser from becoming branching authority. */
final class DecisionState {
	/** Encode ordered answers with an integrity signature. @param array $answers Answers. @param string $binding Finding/decision binding. @return string */
	public function encode( array $answers, $binding = '' ) {
		$json = wp_json_encode( array_values( $answers ) );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe transport encoding, not obfuscation.
		$payload   = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
		$signature = hash_hmac( 'sha256', $payload . '|' . $binding, wp_salt( 'nonce' ) );
		return $payload . '.' . $signature;
	}

	/** Decode and verify ordered answers. @param string $state Signed state. @param string $binding Finding/decision binding. @return array */
	public function decode( $state, $binding = '' ) {
		if ( '' === $state ) {
			return array();
		}
		$parts = explode( '.', $state, 2 );
		if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0] . '|' . $binding, wp_salt( 'nonce' ) ), $parts[1] ) ) {
			throw new InvalidArgumentException( 'Guided Decision state is invalid.' );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes signed URL-safe transport state.
		$json    = base64_decode( strtr( $parts[0], '-_', '+/' ), true );
		$answers = json_decode( $json, true );
		if ( ! is_array( $answers ) || count( $answers ) > 20 ) {
			throw new InvalidArgumentException( 'Guided Decision answers are invalid.' );
		}
		return array_map( 'sanitize_key', $answers );
	}
}
