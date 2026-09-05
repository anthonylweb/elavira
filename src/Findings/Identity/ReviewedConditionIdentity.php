<?php
/**
 * Trusted identity for reviewed conditions.
 *
 * @package Elavira
 */

namespace Elavira\Findings\Identity;

// phpcs:disable Squiz.Commenting.FunctionComment, Generic.Commenting.DocComment.MissingShort

/** Derives privacy-conscious hashes from already validated bounded evidence. */
final class ReviewedConditionIdentity {
	/** @return array{target:string,evidence:string} */
	public function fingerprints( array $target, array $evidence, array $element ) {
		$target_material = array(
			'locator'    => $target['locator'] ?? '',
			'element_id' => $target['element_id'] ?? '',
			'tag_name'   => $target['tag_name'] ?? '',
			'element'    => $element,
			'selector'   => $target['selector'] ?? '',
		);
		return array(
			'target'   => hash( 'sha256', (string) wp_json_encode( $this->canonicalize( $target_material ) ) ),
			'evidence' => hash( 'sha256', (string) wp_json_encode( $this->canonicalize( $evidence ) ) ),
		);
	}

	/** Recursively stabilize associative-array ordering. @param mixed $value Value. @return mixed */
	private function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = $this->canonicalize( $item );
		}
		return $value;
	}
}
