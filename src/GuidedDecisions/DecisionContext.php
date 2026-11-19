<?php
/**
 * Bounded presentation of recorded finding context.
 *
 * @package Elavira
 */

namespace Elavira\GuidedDecisions;

use Elavira\Reports\Admin\OccurrencePresenter;

// phpcs:disable Squiz.Commenting.FunctionComment -- Small presentation helpers.

/** Never interprets missing evidence as an accessibility conclusion. */
final class DecisionContext {
	public function present( array $occurrence, $rule_id ) {
		$presented = ( new OccurrencePresenter() )->present( $occurrence, $rule_id );
		$evidence  = $this->decode( $occurrence['evidence_json'] ?? '' );
		$target    = $this->decode( $occurrence['target_json'] ?? '' );
		$labels    = array(
			'attribute'       => __( 'Recorded attribute', 'elavira' ),
			'attribute_name'  => __( 'Recorded attribute', 'elavira' ),
			'attribute_value' => __( 'Recorded value', 'elavira' ),
			'role'            => __( 'Recorded role', 'elavira' ),
			'native_role'     => __( 'Native control type', 'elavira' ),
			'explicit_role'   => __( 'Assigned ARIA role', 'elavira' ),
			'state_attribute' => __( 'State to compare with the interface', 'elavira' ),
			'candidate'       => __( 'Contrast content type', 'elavira' ),
			'rendered_state'  => __( 'Recorded interaction state', 'elavira' ),
			'style_pattern'   => __( 'Recorded focus style pattern', 'elavira' ),
			'reason'          => __( 'Recorded reason', 'elavira' ),
			'contrast_ratio'  => __( 'Recorded contrast ratio', 'elavira' ),
			'ratio'           => __( 'Recorded ratio', 'elavira' ),
			'required_ratio'  => __( 'Recorded threshold', 'elavira' ),
			'foreground'      => __( 'Recorded foreground', 'elavira' ),
			'background'      => __( 'Recorded background', 'elavira' ),
			'control_type'    => __( 'Control type', 'elavira' ),
		);
		foreach ( $labels as $key => $label ) {
			if ( isset( $evidence[ $key ] ) && is_scalar( $evidence[ $key ] ) ) {
				$presented['rows'][] = array(
					'label' => $label,
					'value' => substr( (string) $evidence[ $key ], 0, 512 ),
				);
			}
		}
		foreach (
			array(
				'ratio_milli'    => __( 'Recorded contrast ratio', 'elavira' ),
				'required_milli' => __( 'Recorded contrast threshold', 'elavira' ),
			) as $key => $label
		) {
			if ( isset( $evidence[ $key ] ) && is_int( $evidence[ $key ] ) && $evidence[ $key ] > 0 ) {
				$presented['rows'][] = array(
					'label' => $label,
					'value' => number_format_i18n( $evidence[ $key ] / 1000, 3 ) . ':1',
				);
			}
		}
		if ( isset( $target['selector'] ) && is_string( $target['selector'] ) ) {
			$presented['rows'][] = array(
				'label' => __( 'Location selector', 'elavira' ),
				'value' => substr( $target['selector'], 0, 512 ),
			);
		}
		return $presented;
	}

	/** Only an unambiguous single recorded link may skip the content question. */
	public function initial_answers( $decision, array $occurrences, $total ) {
		if ( 'elavira.decision.link_purpose' !== $decision->get( 'id' ) || '2.0.0' !== $decision->get( 'version' ) || 1 !== (int) $total || 1 !== count( $occurrences ) ) {
			return array();
		}
		$element = $this->decode( $occurrences[0]['element_json'] ?? '' );
		$kind    = $element['link_kind'] ?? '';
		return in_array( $kind, array( 'text', 'image_only' ), true ) ? array( $kind ) : array();
	}

	private function decode( $json ) {
		if ( ! is_string( $json ) || strlen( $json ) > 16384 ) {
			return array();
		}
		$value = json_decode( $json, true, 10 );
		return is_array( $value ) ? $value : array();
	}
}
