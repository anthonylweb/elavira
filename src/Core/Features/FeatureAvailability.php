<?php
/**
 * Feature availability contract.
 *
 * @package Elavira
 */

namespace Elavira\Core\Features;

/**
 * Provides the single boundary for optional feature checks.
 */
interface FeatureAvailability {
	/**
	 * Determine whether a feature is available.
	 *
	 * @param string $feature Stable feature identifier.
	 * @return bool
	 */
	public function is_available( $feature );
}
