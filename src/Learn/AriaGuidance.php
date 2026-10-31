<?php
/**
 * Contextual ARIA references verified against WAI-ARIA 1.2.
 *
 * @package Elavira
 */

namespace Elavira\Learn;

// phpcs:disable Squiz.Commenting.FunctionComment, Generic.Files.LineLength

/** Explicit rule-to-reference mapping; unknown rules do not receive generic links. */
final class AriaGuidance {
	public static function trigger( $rule, array $evidence ) {
		$templates = array(
			/* translators: 1: first recorded ARIA evidence value, 2: related recorded role, reason, or count. */
			'elavira.aria.invalid_attribute'          => array( __( 'The attribute %1$s is not in the supported ARIA vocabulary. Recorded reason: %2$s.', 'elavira' ), 'attribute_name', 'reason' ),
			/* translators: 1: first recorded ARIA evidence value, 2: related recorded role, reason, or count. */
			'elavira.aria.invalid_value'              => array( __( 'The value of %1$s did not match its expected syntax. Recorded reason: %2$s.', 'elavira' ), 'attribute_name', 'value_reason' ),
			/* translators: 1: first recorded ARIA evidence value, 2: related recorded role, reason, or count. */
			'elavira.aria.prohibited_attribute'       => array( __( 'The naming attribute %1$s was reported on role %2$s, whose naming restrictions require review. For presentation or none, check whether the browser ignores the role before changing markup.', 'elavira' ), 'attribute_name', 'role' ),
			/* translators: 1: first recorded ARIA evidence value, 2: related recorded role, reason, or count. */
			'elavira.aria.required_attribute_missing' => array( __( 'The explicit role %1$s was missing the required attribute %2$s.', 'elavira' ), 'role', 'attribute_name' ),
			/* translators: 1: first recorded ARIA evidence value, 2: related recorded role, reason, or count. */
			'elavira.aria.broken_id_reference'        => array( __( 'The relationship in %1$s could not be resolved reliably. Recorded reason: %2$s.', 'elavira' ), 'attribute_name', 'reference_state' ),
			/* translators: 1: first recorded ARIA evidence value, 2: related recorded role, reason, or count. */
			'elavira.aria.duplicate_id_reference'     => array( __( 'The relationship in %1$s referenced an ID shared by %2$s elements.', 'elavira' ), 'attribute_name', 'duplicate_count' ),
			/* translators: 1: first recorded ARIA evidence value, 2: related recorded role, reason, or count. */
			'elavira.aria.hidden_focusable_content'   => array( __( 'A focusable element of type %1$s was detected within a region hidden through %2$s.', 'elavira' ), 'focusable_kind', 'hidden_source' ),
			/* translators: 1: first recorded ARIA evidence value, 2: related recorded role, reason, or count. */
			'elavira.aria.redundant_role'             => array( __( 'The native role %1$s already provides the explicit role %2$s. This is an educational review, not proof of a WCAG failure.', 'elavira' ), 'native_role', 'explicit_role' ),
		);
		if ( ! isset( $templates[ $rule ] ) ) {
			return ''; }
		$template = $templates[ $rule ];
		return sprintf( $template[0], str_replace( '_', ' ', (string) ( $evidence[ $template[1] ] ?? '' ) ), str_replace( '_', ' ', (string) ( $evidence[ $template[2] ] ?? '' ) ) );
	}

	public static function reference( $rule ) {
		$fragments = array(
			'elavira.aria.invalid_attribute'             => 'state_prop_def',
			'elavira.aria.invalid_value'                 => 'propcharacteristic_value',
			'elavira.aria.prohibited_attribute'          => 'namefromprohibited',
			'elavira.aria.required_attribute_missing'    => 'requiredState',
			'elavira.aria.broken_id_reference'           => 'valuetype_idref',
			'elavira.aria.duplicate_id_reference'        => 'valuetype_idref',
			'elavira.aria.hidden_focusable_content'      => 'aria-hidden',
			'elavira.aria.redundant_role'                => 'host_general_conflict',
			'elavira.semantics.invalid_role'             => 'roles',
			'elavira.semantics.native_semantics_overridden' => 'host_general_conflict',
			'elavira.semantics.control_missing_name'     => 'namecalculation',
			'elavira.semantics.required_state_missing'   => 'requiredState',
			'elavira.semantics.state_consistency_review' => 'state_prop_def',
		);
		return isset( $fragments[ $rule ] ) ? 'https://www.w3.org/TR/wai-aria-1.2/#' . $fragments[ $rule ] : '';
	}
	public static function render( $rule ) {
		$url = self::reference( $rule->id() );
		if ( ! $url ) {
			return; }
		$editorial = $rule->editorial();
		echo '<section id="aria-rule-context"><h2>' . esc_html( $editorial['title'] ) . '</h2><p>' . esc_html( $editorial['summary'] ) . '</p><p>' . esc_html( $editorial['what_to_check'] ?? '' ) . '</p>';
		echo '<p>' . esc_html__( 'The recorded evidence identifies the observed role, attribute, or relationship and the reason for the finding. Invalid ARIA alone does not establish a WCAG failure.', 'elavira' ) . '</p>';
		echo '<p>' . esc_html__( 'The presentation and none roles are valid ways to remove semantics when appropriate. Browsers ignore them on focusable elements or elements with global ARIA states or properties. Check the effective role before removing labels or changing markup. Historical findings may predate this conflict-resolution check; re-audit before deciding.', 'elavira' ) . '</p><ul><li><a href="' . esc_url( $url ) . '">' . esc_html__( 'WAI-ARIA requirements for this rule', 'elavira' ) . '</a></li><li><a href="https://www.w3.org/TR/wai-aria-1.2/#conflict_resolution_presentation_none">' . esc_html__( 'WAI-ARIA: when presentation and none roles are ignored', 'elavira' ) . '</a></li></ul></section>';
	}
}
