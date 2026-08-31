<?php
/**
 * Core feature availability.
 *
 * @package Elavira
 */

namespace Elavira\Core\Features;

/**
 * Enables Core features and exposes one boundary for optional Premium features.
 */
final class CoreFeatureAvailability implements FeatureAvailability {
	/**
	 * Determine whether a recognized optional feature is available.
	 *
	 * @param string $feature Stable feature identifier.
	 * @return bool
	 */
	public function is_available( $feature ) {
		if ( in_array( $feature, Feature::core(), true ) ) {
			return true;
		}

		if ( ! in_array( $feature, Feature::premium(), true ) ) {
			return false;
		}

		/**
		 * Filters whether an optional Elavira feature is available.
		 *
		 * Premium may use this single boundary without changing domain logic.
		 *
		 * @since 0.1.0
		 *
		 * @param bool   $available Feature availability. Defaults to false.
		 * @param string $feature   Stable feature identifier.
		 */
		return (bool) apply_filters( 'elavira_feature_available', false, $feature );
	}
}
