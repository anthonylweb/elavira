<?php
/**
 * Plugin deactivation lifecycle.
 *
 * @package Elavira
 */

namespace Elavira\Core\Lifecycle;

/**
 * Stops runtime behavior without deleting persistent data.
 */
final class Deactivator {
	/**
	 * Deactivate the plugin without uninstalling data.
	 *
	 * @return void
	 */
	public static function deactivate() {
		/**
		 * Fires when Elavira is deactivated.
		 *
		 * Deactivation deliberately performs no data deletion.
		 *
		 * @since 0.1.0
		 */
		do_action( 'elavira_deactivated' );
	}
}
