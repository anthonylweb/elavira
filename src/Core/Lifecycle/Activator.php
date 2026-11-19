<?php
/**
 * Plugin activation lifecycle.
 *
 * @package Elavira
 */

namespace Elavira\Core\Lifecycle;

/**
 * Coordinates repeatable installation operations.
 */
final class Activator {
	/**
	 * Activate or upgrade the plugin foundation.
	 *
	 * @return void
	 */
	public static function activate() {
		( new Installer() )->install();

		/**
		 * Fires after Elavira activation tasks have completed.
		 *
		 * @since 0.1.0
		 */
		do_action( 'elavira_activated' );
	}
}
