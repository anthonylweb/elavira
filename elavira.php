<?php
/**
 * Plugin Name:       Elavira
 * Plugin URI:        https://elavira.org/
 * Description:       Accessibility auditing and guidance for WordPress.
 * Version:           0.2.6
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Elavira
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       elavira
 *
 * @package Elavira
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ELAVIRA_VERSION', '0.2.6' );
define( 'ELAVIRA_SCHEMA_VERSION', '0.8.0' );
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
