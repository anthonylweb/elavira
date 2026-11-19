<?php
/**
 * Visitor Toolbar settings.
 *
 * @package Elavira
 */

namespace Elavira\Toolbar;

/** Owns the small site-level Toolbar option. */
final class ToolbarSettings {
	const OPTION = 'elavira_toolbar_settings';

	/** Supported optional controls. @return string[] */
	public static function controls() {
		return array( 'text_size', 'text_spacing', 'line_height', 'high_contrast', 'appearance', 'grayscale', 'highlight_links', 'enhanced_focus', 'reading_guide', 'readable_font' );
	}

	/** Default configuration. @return array */
	public static function defaults() {
		return array(
			'enabled'  => false,
			'position' => 'bottom_right',
			'controls' => array_fill_keys( self::controls(), true ),
		);
	}

	/** Read and normalize configuration. @return array */
	public static function get() {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Validate an option-shaped value.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public static function sanitize( $value ) {
		$value    = is_array( $value ) ? $value : array();
		$defaults = self::defaults();
		$position = isset( $value['position'] ) ? sanitize_key( $value['position'] ) : $defaults['position'];
		if ( ! in_array( $position, array( 'bottom_right', 'bottom_left', 'top_right', 'top_left' ), true ) ) {
			$position = $defaults['position'];
		}
		$enabled  = ! empty( $value['enabled'] );
		$selected = isset( $value['controls'] ) && is_array( $value['controls'] ) ? $value['controls'] : $defaults['controls'];
		$controls = array();
		foreach ( self::controls() as $control ) {
			$controls[ $control ] = ! empty( $selected[ $control ] );
		}
		return compact( 'enabled', 'position', 'controls' );
	}
}
