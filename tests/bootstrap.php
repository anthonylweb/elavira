<?php
/**
 * Foundation unit-test bootstrap.
 *
 * @package Elavira
 */

require dirname( __DIR__ ) . '/vendor/autoload.php';

$GLOBALS['elavira_test_options']            = array();
$GLOBALS['elavira_test_filters']            = array();
$GLOBALS['elavira_test_administrator']      = null;
$GLOBALS['elavira_test_actions']            = array();
$GLOBALS['elavira_test_activation_hooks']   = array();
$GLOBALS['elavira_test_deactivation_hooks'] = array();
$GLOBALS['elavira_test_fired_actions']      = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['elavira_test_options'] )
			? $GLOBALS['elavira_test_options'][ $name ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		unset( $autoload );
		$GLOBALS['elavira_test_options'][ $name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value, ...$args ) {
		if ( isset( $GLOBALS['elavira_test_filters'][ $hook_name ] ) ) {
			return $GLOBALS['elavira_test_filters'][ $hook_name ]( $value, ...$args );
		}

		return $value;
	}
}

if ( ! function_exists( 'get_role' ) ) {
	function get_role( $role ) {
		return 'administrator' === $role ? $GLOBALS['elavira_test_administrator'] : null;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10 ) {
		$GLOBALS['elavira_test_actions'][ $hook_name ][ $priority ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		$GLOBALS['elavira_test_activation_hooks'][ $file ] = $callback;
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {
		$GLOBALS['elavira_test_deactivation_hooks'][ $file ] = $callback;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook_name, ...$args ) {
		$GLOBALS['elavira_test_fired_actions'][ $hook_name ][] = $args;
	}
}
