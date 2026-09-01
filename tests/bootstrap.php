<?php
/**
 * Foundation unit-test bootstrap.
 *
 * @package Elavira
 */

require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

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

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( $url ) {
		return false !== filter_var( $url, FILTER_VALIDATE_URL ) ? $url : false;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		unset( $type, $gmt );
		return '2026-08-31 12:00:00';
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $sql ) {
		preg_match_all( '/CREATE TABLE ([^\s(]+)/', $sql, $matches );

		foreach ( $matches[1] as $table ) {
			$GLOBALS['wpdb']->create_table( $table );
		}

		$GLOBALS['wpdb']->schema_sql = $sql;

		return $matches[1];
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}
