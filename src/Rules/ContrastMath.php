<?php
/**
 * Server-authoritative WCAG contrast calculations.
 *
 * @package Elavira
 */

namespace Elavira\Rules;

use InvalidArgumentException;

/** Calculates contrast from normalized opaque sRGB colors. */
final class ContrastMath {
	/**
	 * Return the contrast ratio multiplied by 1000.
	 *
	 * @param string $foreground Normalized foreground color.
	 * @param string $background Normalized background color.
	 * @return int
	 */
	public function ratio_milli( $foreground, $background ) {
		$first  = $this->luminance( $foreground );
		$second = $this->luminance( $background );
		return (int) round( ( max( $first, $second ) + 0.05 ) / ( min( $first, $second ) + 0.05 ) * 1000 );
	}

	/**
	 * Calculate relative luminance for #rrggbb.
	 *
	 * @param string $color Normalized color.
	 * @return float
	 * @throws InvalidArgumentException When the color is not normalized opaque sRGB.
	 */
	private function luminance( $color ) {
		if ( ! is_string( $color ) || ! preg_match( '/^#[0-9a-f]{6}$/', $color ) ) {
			throw new InvalidArgumentException( 'Contrast colors must be normalized opaque sRGB hex values.' );
		}
		$channels = array_map(
			static function ( $offset ) use ( $color ) {
				$value = hexdec( substr( $color, $offset, 2 ) ) / 255;
				return $value <= 0.04045 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
			},
			array( 1, 3, 5 )
		);
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}
}
