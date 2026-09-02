<?php
/**
 * Plugin Name:       Elavira
 * Plugin URI:        https://elavira.com/
 * Description:       Accessibility auditing and guidance for WordPress.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Elavira
 * Text Domain:       elavira
 * Domain Path:       /languages
 *
 * @package Elavira
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ELAVIRA_VERSION', '0.1.0' );
define( 'ELAVIRA_SCHEMA_VERSION', '0.4.0' );
define( 'ELAVIRA_FILE', __FILE__ );
define( 'ELAVIRA_DIR', __DIR__ );
define( 'ELAVIRA_URL', plugin_dir_url( __FILE__ ) );

$elavira_autoloader = __DIR__ . '/vendor/autoload.php';

if ( ! is_readable( $elavira_autoloader ) ) {
	return;
}

require $elavira_autoloader;

register_activation_hook( ELAVIRA_FILE, array( Elavira\Core\Lifecycle\Activator::class, 'activate' ) );
register_deactivation_hook( ELAVIRA_FILE, array( Elavira\Core\Lifecycle\Deactivator::class, 'deactivate' ) );

Elavira\Core\Bootstrap::boot();
