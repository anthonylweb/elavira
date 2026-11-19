<?php
/**
 * Core Settings values.
 *
 * @package Elavira
 */

namespace Elavira\Settings;

// phpcs:disable Squiz.Commenting.FunctionComment -- Compact value object comments are intentional.

/** Owns the bounded Core-wide settings option. */
final class CoreSettings {
	const OPTION = 'elavira_core_settings';

	/** Defaults. @return array */
	public static function defaults() {
		return array(
			'default_wcag_target'  => '2.2_AA',
			'admin_notifications'  => true,
			'admin_bar_visibility' => true,
		);
	}

	/** Read settings. @return array */
	public static function get() {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/** Normalize settings. @param mixed $value Raw value. @return array */
	public static function sanitize( $value ) {
		$value  = is_array( $value ) ? $value : array();
		$target = isset( $value['default_wcag_target'] ) ? sanitize_text_field( $value['default_wcag_target'] ) : '2.2_AA';
		if ( ! in_array( $target, array( '2.2_A', '2.2_AA' ), true ) ) {
			$target = '2.2_AA';
		}
		return array(
			'default_wcag_target'  => $target,
			'admin_notifications'  => array_key_exists( 'admin_notifications', $value ) ? ! empty( $value['admin_notifications'] ) : true,
			'admin_bar_visibility' => array_key_exists( 'admin_bar_visibility', $value ) ? ! empty( $value['admin_bar_visibility'] ) : true,
		);
	}
}
