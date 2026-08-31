<?php
/**
 * Capability installation.
 *
 * @package Elavira
 */

namespace Elavira\Core\Capabilities;

/**
 * Grants Core capabilities to administrators by default.
 */
final class CapabilityInstaller {
	/**
	 * Install capabilities safely and repeatedly.
	 *
	 * @return void
	 */
	public function install() {
		$administrator = get_role( 'administrator' );

		if ( null === $administrator ) {
			return;
		}

		foreach ( Capability::all() as $capability ) {
			$administrator->add_cap( $capability );
		}
	}
}
