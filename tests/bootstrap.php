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
$GLOBALS['elavira_test_current_user_id']    = 7;
$GLOBALS['elavira_test_capabilities']       = array();
$GLOBALS['elavira_test_url_objects']        = array();
$GLOBALS['elavira_test_rest_routes']        = array();

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

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $route_namespace, $route, $args ) {
		$GLOBALS['elavira_test_rest_routes'][ $route_namespace . $route ] = $args;

		return true;
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

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		unset( $file );
		return 'https://example.com/wp-content/plugins/elavira/';
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return ! empty( $GLOBALS['elavira_test_capabilities'][ $capability ] );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return (int) $GLOBALS['elavira_test_current_user_id'];
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '/' ) {
		return 'https://example.com' . $path;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Test stub intentionally delegates to PHP.
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value, $url ) {
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . $value;
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format ) {
		return gmdate( $format, strtotime( '2026-08-31 12:00:00 UTC' ) );
	}
}

if ( ! function_exists( 'url_to_postid' ) ) {
	function url_to_postid( $url ) {
		return $GLOBALS['elavira_test_url_objects'][ $url ]['id'] ?? 0;
	}
}

if ( ! function_exists( 'get_post_status' ) ) {
	function get_post_status( $post_id ) {
		foreach ( $GLOBALS['elavira_test_url_objects'] as $object ) {
			if ( (int) $object['id'] === (int) $post_id ) {
				return $object['status']; }
		}
		return false;
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $post_id ) {
		unset( $post_id );
		return 'page'; }
}

if ( ! function_exists( 'get_post_type_object' ) ) {
	function get_post_type_object( $post_type ) {
		return (object) array(
			'name'               => $post_type,
			'publicly_queryable' => true,
		); }
}

if ( ! function_exists( 'is_post_type_viewable' ) ) {
	function is_post_type_viewable( $post_type ) {
		return ! empty( $post_type->publicly_queryable ); }
}
