<?php
/**
 * Phase 1 production ruleset Round 3 definitions.
 *
 * @package Elavira
 */

namespace Elavira\Rules;

// phpcs:disable Squiz.Commenting.FunctionComment -- Compact product-authored registry definitions are intentional.

use Elavira\Findings\Values\Priority;
use Elavira\Rules\Evidence\EvidenceSchema;
use Elavira\Rules\Evidence\FieldDefinition;
use Elavira\Rules\Grouping\RuleLevelGrouping;
use Elavira\Rules\Values\EvaluationType;
use Elavira\Rules\Values\FindingClassification;

/** Builds versioned Forms, semantics, and ARIA contracts. */
final class RoundThreeRuleDefinitions {
	/** Return all Round 3 definitions. @return RuleDefinition[] */
	public static function all() {
		return array(
			self::rule(
				'elavira.form.accessible_name_missing',
				EvaluationType::AUTOMATIC_FAILURE,
				Priority::HIGH,
				array( '4.1.2:A:robust' ),
				array(
					'title'   => __( 'Form control is missing an accessible name', 'elavira' ),
					'summary' => __( 'Form control is missing an accessible name.', 'elavira' ),
				),
				__( 'A rendered native form control that requires a name has no supported programmatic naming source.', 'elavira' ),
				self::fields(
					array(
						'control_type' => array( 32, array() ),
						'name_state'   => array( 24, array( 'missing' ) ),
					)
				),
				array( 'elavira.semantics.control_missing_name', 'elavira.forms.broken_label_reference', 'elavira.aria.broken_id_reference' ),
				'2.0.0',
				'form_labels_instructions'
			),
			self::rule(
				'elavira.forms.broken_label_reference',
				EvaluationType::POTENTIAL_ISSUE,
				Priority::HIGH,
				array( '1.3.1:A:perceivable' ),
				array(
					'title'   => __( 'Explicit form label relationship is broken', 'elavira' ),
					'summary' => __( 'Explicit form label relationship is broken.', 'elavira' ),
				),
				__( 'A label for reference is empty, missing, duplicated, or does not resolve to a labelable control. Another naming source may preserve the accessible result.', 'elavira' ),
				self::fields(
					array(
						'reference_state' => array( 24, array( 'empty', 'missing', 'duplicate', 'not_labelable' ) ),
						'control_type'    => array( 32, array() ),
					)
				),
				array(),
				'1.0.0',
				'form_labels_instructions'
			),
			self::rule(
				'elavira.forms.placeholder_only_label',
				EvaluationType::POTENTIAL_ISSUE,
				Priority::HIGH,
				array( '3.3.2:A:understandable' ),
				array(
					'title'   => __( 'Placeholder appears to be the only label', 'elavira' ),
					'summary' => __( 'Placeholder appears to be the only label.', 'elavira' ),
				),
				__( 'A text-entry control has a placeholder-derived accessible name but no persistent supported labeling source.', 'elavira' ),
				self::fields(
					array(
						'control_type'      => array( 32, array( 'input', 'textarea' ) ),
						'placeholder_state' => array( 24, array( 'only_label' ) ),
					)
				),
				array(),
				'1.0.0',
				'form_labels_instructions'
			),
			self::rule(
				'elavira.forms.required_instruction_review',
				EvaluationType::HUMAN_REVIEW,
				Priority::MEDIUM,
				array( '3.3.2:A:understandable' ),
				array(
					'title'   => __( 'Required-field communication needs review', 'elavira' ),
					'summary' => __( 'Required-field communication needs review.', 'elavira' ),
				),
				__( 'A rendered control declares a required state. A person must verify that required status and necessary instructions are visible and programmatically available.', 'elavira' ),
				self::fields(
					array(
						'required_source' => array( 24, array( 'native', 'aria', 'both' ) ),
						'control_type'    => array( 32, array() ),
					)
				),
				array(),
				'1.0.0',
				'form_labels_instructions'
			),
			self::rule(
				'elavira.forms.error_identification_review',
				EvaluationType::HUMAN_REVIEW,
				Priority::HIGH,
				array( '3.3.1:A:understandable', '3.3.3:AA:understandable' ),
				array(
					'title'   => __( 'Form error handling needs interaction review', 'elavira' ),
					'summary' => __( 'Form error handling needs interaction review.', 'elavira' ),
				),
				__( 'A rendered form contains an explicit invalid state or error-reporting region. Static inspection cannot verify submission, identification, announcement, focus, or suggestions.', 'elavira' ),
				self::fields( array( 'trigger' => array( 24, array( 'aria_invalid', 'alert', 'live_region' ) ) ) ),
				array(),
				'1.0.0',
				'form_labels_instructions'
			),
			self::rule(
				'elavira.semantics.control_missing_name',
				EvaluationType::AUTOMATIC_FAILURE,
				Priority::HIGH,
				array( '4.1.2:A:robust' ),
				array(
					'title'   => __( 'Interactive component is missing an accessible name', 'elavira' ),
					'summary' => __( 'Interactive component is missing an accessible name.', 'elavira' ),
				),
				__( 'An in-scope rendered ARIA widget that requires a name has no supported programmatic naming source.', 'elavira' ),
				self::fields(
					array(
						'role'       => array( 32, array() ),
						'name_state' => array( 24, array( 'missing' ) ),
					)
				),
				array( 'elavira.aria.broken_id_reference' )
			),
			self::rule(
				'elavira.semantics.invalid_role',
				EvaluationType::POTENTIAL_ISSUE,
				Priority::HIGH,
				array( '4.1.2:A:robust' ),
				array(
					'title'   => __( 'Explicit ARIA role token is invalid', 'elavira' ),
					'summary' => __( 'Explicit ARIA role token is invalid.', 'elavira' ),
				),
				__( 'An explicit role contains no recognized non-abstract WAI-ARIA 1.2 fallback token. Syntax alone does not establish a WCAG failure.', 'elavira' ),
				self::fields(
					array(
						'role_state'          => array( 24, array( 'no_valid_token' ) ),
						'first_invalid_token' => array( 64, array() ),
						'native_role_state'   => array( 24, array( 'available', 'none' ) ),
					)
				)
			),
			self::rule(
				'elavira.semantics.required_state_missing',
				EvaluationType::AUTOMATIC_FAILURE,
				Priority::HIGH,
				array( '4.1.2:A:robust' ),
				array(
					'title'   => __( 'Required semantic state or property is unavailable', 'elavira' ),
					'summary' => __( 'Required semantic state or property is unavailable.', 'elavira' ),
				),
				__( 'The resolved ARIA role unambiguously requires a state or property that is absent or deterministically invalid.', 'elavira' ),
				self::fields(
					array(
						'role'               => array( 32, array() ),
						'required_attribute' => array( 64, array() ),
						'state_reason'       => array( 24, array( 'missing', 'invalid' ) ),
					)
				),
				array( 'elavira.aria.invalid_value', 'elavira.aria.required_attribute_missing' )
			),
			self::rule(
				'elavira.semantics.state_consistency_review',
				EvaluationType::HUMAN_REVIEW,
				Priority::HIGH,
				array( '4.1.2:A:robust' ),
				array(
					'title'   => __( 'Exposed component state needs interaction review', 'elavira' ),
					'summary' => __( 'Exposed component state needs interaction review.', 'elavira' ),
				),
				__( 'A mutable ARIA state is present. A person must compare it with visible presentation and actual behavior throughout interaction.', 'elavira' ),
				self::fields(
					array(
						'role'            => array( 32, array() ),
						'state_attribute' => array( 64, array() ),
					)
				)
			),
			self::rule(
				'elavira.semantics.native_semantics_overridden',
				EvaluationType::POTENTIAL_ISSUE,
				Priority::MEDIUM,
				array( '4.1.2:A:robust' ),
				array(
					'title'   => __( 'Explicit role may conflict with native semantics', 'elavira' ),
					'summary' => __( 'Explicit role may conflict with native semantics.', 'elavira' ),
				),
				__( 'A narrowly recognized native interactive element is assigned a substantially different role that may confuse its exposed semantics.', 'elavira' ),
				self::fields(
					array(
						'native_role'   => array( 32, array() ),
						'explicit_role' => array( 32, array() ),
					)
				)
			),
			self::rule(
				'elavira.aria.invalid_attribute',
				EvaluationType::POTENTIAL_ISSUE,
				Priority::MEDIUM,
				array(),
				array(
					'title'   => __( 'ARIA attribute name is unknown', 'elavira' ),
					'summary' => __( 'ARIA attribute name is unknown.', 'elavira' ),
				),
				__( 'A rendered aria-* attribute is not in the bundled WAI-ARIA 1.2 vocabulary. The spelling defect alone is not a proven WCAG failure.', 'elavira' ),
				self::fields(
					array(
						'attribute_name' => array( 64, array() ),
						'reason'         => array( 24, array( 'unknown' ) ),
					)
				)
			),
			self::rule(
				'elavira.aria.invalid_value',
				EvaluationType::POTENTIAL_ISSUE,
				Priority::HIGH,
				array( '4.1.2:A:robust' ),
				array(
					'title'   => __( 'ARIA attribute value is invalid', 'elavira' ),
					'summary' => __( 'ARIA attribute value is invalid.', 'elavira' ),
				),
				__( 'A supported ARIA attribute has a deterministically invalid token, enumeration, numeric value, or ID-reference form.', 'elavira' ),
				self::fields(
					array(
						'attribute_name' => array( 64, array() ),
						'value_reason'   => array( 32, array( 'invalid_boolean', 'invalid_tristate', 'invalid_integer', 'invalid_number', 'empty_reference' ) ),
					)
				)
			),
			self::rule(
				'elavira.aria.prohibited_attribute',
				EvaluationType::POTENTIAL_ISSUE,
				Priority::HIGH,
				array(),
				array(
					'title'   => __( 'ARIA naming attribute is prohibited for this role', 'elavira' ),
					'summary' => __( 'ARIA naming attribute is prohibited for this role.', 'elavira' ),
				),
				__( 'A role that prohibits author-provided naming uses aria-label or aria-labelledby. This syntax condition does not by itself establish a WCAG failure.', 'elavira' ),
				self::fields(
					array(
						'role'           => array( 32, array() ),
						'attribute_name' => array( 64, array( 'aria-label', 'aria-labelledby' ) ),
					)
				)
			),
			self::rule(
				'elavira.aria.required_attribute_missing',
				EvaluationType::POTENTIAL_ISSUE,
				Priority::HIGH,
				array( '4.1.2:A:robust' ),
				array(
					'title'   => __( 'ARIA role is missing a required attribute', 'elavira' ),
					'summary' => __( 'ARIA role is missing a required attribute.', 'elavira' ),
				),
				__( 'A resolved role is missing an attribute required by the bounded WAI-ARIA 1.2 contract. A separate consequence rule becomes primary when the semantic state is unavailable.', 'elavira' ),
				self::fields(
					array(
						'role'           => array( 32, array() ),
						'attribute_name' => array( 64, array() ),
					)
				)
			),
			self::rule(
				'elavira.aria.broken_id_reference',
				EvaluationType::POTENTIAL_ISSUE,
				Priority::HIGH,
				array( '1.3.1:A:perceivable' ),
				array(
					'title'   => __( 'ARIA ID reference is broken', 'elavira' ),
					'summary' => __( 'ARIA ID reference is broken.', 'elavira' ),
				),
				__( 'An applicable ARIA ID-reference is empty, missing, ambiguous, or resolves to no usable text where naming or description requires text. A valid fallback prevents a missing-name failure.', 'elavira' ),
				self::fields(
					array(
						'attribute_name'  => array( 64, array() ),
						'reference_state' => array( 32, array( 'empty', 'missing', 'duplicate', 'no_usable_text' ) ),
						'token_count'     => array( 0, array(), 'integer' ),
					)
				)
			),
			self::rule(
				'elavira.aria.hidden_focusable_content',
				EvaluationType::POTENTIAL_ISSUE,
				Priority::HIGH,
				array(),
				array(
					'title'   => __( 'ARIA-hidden content contains a focusable descendant', 'elavira' ),
					'summary' => __( 'ARIA-hidden content contains a focusable descendant.', 'elavira' ),
				),
				__( 'A rendered aria-hidden region contains a conservatively focusable descendant. Script-controlled focus and interaction still require manual testing.', 'elavira' ),
				self::fields(
					array(
						'focusable_kind' => array( 32, array( 'link', 'button', 'input', 'select', 'textarea', 'tabindex', 'media' ) ),
						'hidden_source'  => array( 24, array( 'aria_hidden' ) ),
					)
				)
			),
			self::rule(
				'elavira.aria.duplicate_id_reference',
				EvaluationType::POTENTIAL_ISSUE,
				Priority::HIGH,
				array( '1.3.1:A:perceivable' ),
				array(
					'title'   => __( 'ARIA relationship references a duplicated ID', 'elavira' ),
					'summary' => __( 'ARIA relationship references a duplicated ID.', 'elavira' ),
				),
				__( 'An ARIA or HTML labeling relationship depends on an ID used by multiple elements, making the relationship ambiguous.', 'elavira' ),
				self::fields(
					array(
						'attribute_name'  => array( 64, array() ),
						'duplicate_count' => array( 0, array(), 'integer' ),
					)
				)
			),
			self::rule(
				'elavira.aria.redundant_role',
				EvaluationType::POTENTIAL_ISSUE,
				Priority::LOW,
				array(),
				array(
					'title'   => __( 'Explicit role repeats native semantics', 'elavira' ),
					'summary' => __( 'Explicit role repeats native semantics.', 'elavira' ),
				),
				__( 'A narrow native HTML pattern repeats its implicit role. This is best-practice education and has no direct WCAG mapping.', 'elavira' ),
				self::fields(
					array(
						'native_role'   => array( 32, array() ),
						'explicit_role' => array( 32, array() ),
					)
				)
			),
		);
	}

	/** Build string/integer evidence fields. @return FieldDefinition[] */
	private static function fields( array $definitions ) {
		$fields = array();
		foreach ( $definitions as $name => $definition ) {
			$type            = isset( $definition[2] ) && 'integer' === $definition[2] ? FieldDefinition::TYPE_INTEGER : FieldDefinition::TYPE_STRING;
			$fields[ $name ] = new FieldDefinition( $type, true, $definition[0], $definition[1] );
		}
		return $fields;
	}

	/** Build one compact trusted definition. @return RuleDefinition */
	private static function rule( $id, $evaluation, $priority, array $wcag, $title, $description, array $evidence, array $supersedes = array(), $version = '1.0.0', $decision = null ) {
		$classification = array(
			EvaluationType::AUTOMATIC_FAILURE => FindingClassification::DETECTED_FAILURE,
			EvaluationType::POTENTIAL_ISSUE   => FindingClassification::POTENTIAL_ISSUE,
			EvaluationType::HUMAN_REVIEW      => FindingClassification::HUMAN_REVIEW,
		);
		$mappings       = array_map(
			static function ( $item ) {
				list( $criterion, $level, $principle ) = explode( ':', $item );
				return new WcagMapping( $criterion, $level, $principle );
			},
			$wcag
		);
		$review         = EvaluationType::AUTOMATIC_FAILURE === $evaluation ? __( 'Correct the rendered semantic consequence, then re-audit. Do not treat an Addressed status as verification.', 'elavira' ) : __( 'Review the rendered component in context and with appropriate assistive technology before deciding whether remediation is required.', 'elavira' );
		return new RuleDefinition(
			array(
				'id'                 => $id,
				'version'            => $version,
				'editorial_version'  => $version,
				'title'              => $title['title'],
				'description'        => $description,
				'evaluation_type'    => $evaluation,
				'classification'     => $classification[ $evaluation ],
				'priority'           => $priority,
				'wcag'               => $mappings,
				'summary'            => $title['summary'],
				'why_it_matters'     => $description,
				'recommendation'     => $review,
				'wordpress_guidance' => __( 'Inspect the responsible block, form plugin, theme, or custom component and verify the rendered result before changing it.', 'elavira' ),
				'developer_guidance' => __( 'Prefer native HTML semantics. Inspect the computed accessibility information and test behavior rather than relying on source syntax alone.', 'elavira' ),
				'learn_topic'        => 'forms_labels',
				'guided_decision'    => $decision,
				'evidence_schema'    => new EvidenceSchema( $evidence, 2048 ),
				'grouping_schema'    => new EvidenceSchema( array(), 2 ),
				'grouping_strategy'  => new RuleLevelGrouping(),
				'supersedes'         => $supersedes,
			)
		);
	}
}
