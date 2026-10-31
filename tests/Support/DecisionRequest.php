<?php
/**
 * Test-only request boundaries for the actual decision controller.
 *
 * @package Elavira
 */

namespace Elavira\GuidedDecisions\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.NonceVerification -- Test-only boundary shims.

function check_admin_referer( $action ) {
	$finding_id = isset( $_POST['finding'] ) ? absint( wp_unslash( $_POST['finding'] ) ) : 0;
	if ( empty( $GLOBALS['elavira_decision_nonce_valid'] ) || 'elavira_decision_' . $finding_id !== $action ) {
		throw new \RuntimeException( 'Rejected nonce.' );
	}
}

function wp_safe_redirect( $url ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test exception transports the redirect URL for assertions.
	throw new \Elavira\Tests\Support\DecisionRedirect( $url );
}

function wp_die( $message, $title, $options ) {
	unset( $title );
	// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test exception transports controller output for assertions.
	throw new \RuntimeException( $message, $options['response'] );
}
