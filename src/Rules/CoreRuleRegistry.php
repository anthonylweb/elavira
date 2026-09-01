<?php
/**
 * Representative Core Rule Registry factory.
 *
 * @package Elavira
 */

namespace Elavira\Rules;

use Elavira\Findings\Values\Priority;
use Elavira\Rules\Evidence\EvidenceSchema;
use Elavira\Rules\Evidence\FieldDefinition;
use Elavira\Rules\Grouping\InputGrouping;
use Elavira\Rules\Grouping\RuleLevelGrouping;
use Elavira\Rules\Values\EvaluationType;
use Elavira\Rules\Values\FindingClassification;

/**
 * Builds the small initial Core rule set used to establish scanner contracts.
 */
final class CoreRuleRegistry {
	/**
	 * Build a registry containing representative Core rules.
	 *
	 * @return RuleRegistry
	 */
	public static function create() {
		$registry = new RuleRegistry();

		foreach ( self::rules() as $rule ) {
			$registry->register( $rule );
		}

		return $registry;
	}

	/**
	 * Build representative rule definitions.
	 *
	 * @return RuleDefinition[]
	 */
	private static function rules() {
		return array(
			self::image_alt_missing(),
			self::form_name_missing(),
			self::link_purpose_review(),
			self::heading_structure_review(),
			self::page_language_missing(),
		);
	}

	/** Define the missing image alternative rule.
	 *
	 * @return RuleDefinition
	 */
	private static function image_alt_missing() {
		return new RuleDefinition(
			array(
				'id'                 => 'elavira.image.alt_missing',
				'version'            => '1.0.0',
				'editorial_version'  => '1.0.0',
				'title'              => __( 'Image is missing an alternative text attribute', 'elavira' ),
				'description'        => __( 'An image element does not provide an alt attribute.', 'elavira' ),
				'evaluation_type'    => EvaluationType::AUTOMATIC_FAILURE,
				'classification'     => FindingClassification::DETECTED_FAILURE,
				'priority'           => Priority::HIGH,
				'wcag'               => array( new WcagMapping( '1.1.1', 'A', 'perceivable' ) ),
				'summary'            => __( 'Images are missing alternative text attributes.', 'elavira' ),
				'why_it_matters'     => __( 'People who cannot see an image need a text alternative or an explicit indication that it is decorative.', 'elavira' ),
				'recommendation'     => __( 'Determine each image purpose and provide appropriate alternative text or an empty alt attribute for a decorative image.', 'elavira' ),
				'wordpress_guidance' => __( 'Review the image Alternative Text field in the Media Library or block settings, then re-audit the rendered page.', 'elavira' ),
				'developer_guidance' => __( 'Ensure rendered img elements always include an alt attribute whose value reflects the image purpose.', 'elavira' ),
				'learn_topic'        => 'images_alternative_text',
				'guided_decision'    => 'alternative_text',
				'evidence_schema'    => self::schema(
					array(
						'alt_state' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, array( 'missing' ) ),
					)
				),
				'grouping_schema'    => self::schema( array(), 2 ),
				'grouping_strategy'  => new RuleLevelGrouping(),
			)
		);
	}

	/** Define the missing form-control name rule.
	 *
	 * @return RuleDefinition
	 */
	private static function form_name_missing() {
		$control_types = array( 'button', 'checkbox', 'file', 'input', 'radio', 'select', 'textarea' );

		return new RuleDefinition(
			array(
				'id'                 => 'elavira.form.accessible_name_missing',
				'version'            => '1.0.0',
				'editorial_version'  => '1.0.0',
				'title'              => __( 'Form control is missing an accessible name', 'elavira' ),
				'description'        => __( 'A rendered form control has no programmatically determinable accessible name.', 'elavira' ),
				'evaluation_type'    => EvaluationType::AUTOMATIC_FAILURE,
				'classification'     => FindingClassification::DETECTED_FAILURE,
				'priority'           => Priority::HIGH,
				'wcag'               => array( new WcagMapping( '4.1.2', 'A', 'robust' ) ),
				'summary'            => __( 'Form controls are missing accessible names.', 'elavira' ),
				'why_it_matters'     => __( 'People using assistive technology need a reliable name to understand and operate each control.', 'elavira' ),
				'recommendation'     => __( 'Provide a visible label or another standards-compliant accessible name that describes the control purpose.', 'elavira' ),
				'wordpress_guidance' => __( 'Check the form block or form-plugin field settings for a visible label, then inspect the rendered result.', 'elavira' ),
				'developer_guidance' => __( 'Associate a label element with the control whenever possible; use ARIA naming only when native labeling is not suitable.', 'elavira' ),
				'learn_topic'        => 'forms_labels',
				'guided_decision'    => 'form_labels_instructions',
				'evidence_schema'    => self::schema(
					array(
						'control_type'          => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, $control_types ),
						'accessible_name_state' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, array( 'missing' ) ),
					)
				),
				'grouping_schema'    => self::schema(
					array(
						'control_type' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, $control_types ),
					)
				),
				'grouping_strategy'  => new InputGrouping(),
			)
		);
	}

	/** Define the link-purpose review rule.
	 *
	 * @return RuleDefinition
	 */
	private static function link_purpose_review() {
		$states = array( 'empty', 'generic', 'ambiguous' );

		return new RuleDefinition(
			array(
				'id'                 => 'elavira.link.purpose_review',
				'version'            => '1.0.0',
				'editorial_version'  => '1.0.0',
				'title'              => __( 'Link purpose needs review', 'elavira' ),
				'description'        => __( 'A link has empty, generic, or potentially ambiguous text that needs human evaluation in context.', 'elavira' ),
				'evaluation_type'    => EvaluationType::POTENTIAL_ISSUE,
				'classification'     => FindingClassification::POTENTIAL_ISSUE,
				'priority'           => Priority::MEDIUM,
				'wcag'               => array( new WcagMapping( '2.4.4', 'A', 'operable' ) ),
				'summary'            => __( 'Link purposes require human review.', 'elavira' ),
				'why_it_matters'     => __( 'People navigating links out of context need enough information to understand where each link goes or what it does.', 'elavira' ),
				'recommendation'     => __( 'Review the link together with its surrounding content and make its purpose clear when necessary.', 'elavira' ),
				'wordpress_guidance' => __( 'Edit the linked text in the relevant block, menu, or widget when its purpose is unclear in context.', 'elavira' ),
				'developer_guidance' => __( 'Ensure the computed accessible name and programmatic context communicate a meaningful purpose.', 'elavira' ),
				'learn_topic'        => 'links_navigation',
				'guided_decision'    => 'link_purpose',
				'evidence_schema'    => self::schema(
					array(
						'text_state'       => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, $states ),
						'destination_kind' => new FieldDefinition( FieldDefinition::TYPE_STRING, false, 16, array( 'internal', 'external', 'fragment', 'unknown' ) ),
					)
				),
				'grouping_schema'    => self::schema(
					array(
						'text_state' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, $states ),
					)
				),
				'grouping_strategy'  => new InputGrouping(),
			)
		);
	}

	/** Define the heading-structure review rule.
	 *
	 * @return RuleDefinition
	 */
	private static function heading_structure_review() {
		$levels = array( 1, 2, 3, 4, 5, 6 );

		return new RuleDefinition(
			array(
				'id'                 => 'elavira.heading.structure_review',
				'version'            => '1.0.0',
				'editorial_version'  => '1.0.0',
				'title'              => __( 'Heading structure needs review', 'elavira' ),
				'description'        => __( 'The rendered heading sequence may not describe the page structure clearly.', 'elavira' ),
				'evaluation_type'    => EvaluationType::HUMAN_REVIEW,
				'classification'     => FindingClassification::HUMAN_REVIEW,
				'priority'           => Priority::MEDIUM,
				'wcag'               => array( new WcagMapping( '1.3.1', 'A', 'perceivable' ) ),
				'summary'            => __( 'Heading structure requires human evaluation.', 'elavira' ),
				'why_it_matters'     => __( 'A meaningful heading hierarchy helps people understand and navigate page organization.', 'elavira' ),
				'recommendation'     => __( 'Review whether headings describe sections accurately and form a logical hierarchy.', 'elavira' ),
				'wordpress_guidance' => __( 'Review Heading blocks and template headings in the rendered page, changing levels only when structure requires it.', 'elavira' ),
				'developer_guidance' => __( 'Use native heading elements according to document structure rather than visual size alone.', 'elavira' ),
				'learn_topic'        => 'headings_structure',
				'guided_decision'    => 'heading_structure',
				'evidence_schema'    => self::schema(
					array(
						'current_level'  => new FieldDefinition( FieldDefinition::TYPE_INTEGER, true, 0, $levels ),
						'previous_level' => new FieldDefinition( FieldDefinition::TYPE_INTEGER, true, 0, $levels ),
						'issue'          => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 32, array( 'skipped_level', 'structure_unclear' ) ),
					)
				),
				'grouping_schema'    => self::schema(
					array(
						'issue' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 32, array( 'skipped_level', 'structure_unclear' ) ),
					)
				),
				'grouping_strategy'  => new InputGrouping(),
			)
		);
	}

	/** Define the missing page-language rule.
	 *
	 * @return RuleDefinition
	 */
	private static function page_language_missing() {
		return new RuleDefinition(
			array(
				'id'                 => 'elavira.document.language_missing',
				'version'            => '1.0.0',
				'editorial_version'  => '1.0.0',
				'title'              => __( 'Page language is missing', 'elavira' ),
				'description'        => __( 'The rendered document does not declare its default human language.', 'elavira' ),
				'evaluation_type'    => EvaluationType::AUTOMATIC_FAILURE,
				'classification'     => FindingClassification::DETECTED_FAILURE,
				'priority'           => Priority::HIGH,
				'wcag'               => array( new WcagMapping( '3.1.1', 'A', 'understandable' ) ),
				'summary'            => __( 'The page language is not declared.', 'elavira' ),
				'why_it_matters'     => __( 'Assistive technologies use the declared language to pronounce and interpret content correctly.', 'elavira' ),
				'recommendation'     => __( 'Ensure the rendered html element declares the page default language.', 'elavira' ),
				'wordpress_guidance' => __( 'Confirm the WordPress Site Language and inspect whether the active theme outputs it on the rendered html element.', 'elavira' ),
				'developer_guidance' => __( 'Use the WordPress language attributes API in the document element and preserve valid theme output.', 'elavira' ),
				'learn_topic'        => 'language_content',
				'evidence_schema'    => self::schema(
					array(
						'language_state' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, array( 'missing', 'empty' ) ),
					)
				),
				'grouping_schema'    => self::schema( array(), 2 ),
				'grouping_strategy'  => new RuleLevelGrouping(),
			)
		);
	}

	/**
	 * Create a bounded evidence schema.
	 *
	 * @param FieldDefinition[] $fields    Field definitions.
	 * @param int               $max_bytes Maximum encoded bytes.
	 * @return EvidenceSchema
	 */
	private static function schema( array $fields, $max_bytes = 2048 ) {
		return new EvidenceSchema( $fields, $max_bytes );
	}
}
