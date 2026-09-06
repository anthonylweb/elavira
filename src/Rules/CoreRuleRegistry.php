<?php
/**
 * Core Rule Registry factory.
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
	 * Build a registry containing current and historically supported Core rules.
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
	 * Build current and historically supported rule definitions.
	 *
	 * @return RuleDefinition[]
	 */
	private static function rules() {
		return array(
			self::image_alt_missing(),
			self::form_name_missing(),
			// Historical representative contracts remain exactly resolvable.
			self::link_purpose_review(),
			self::heading_structure_review(),
			self::page_language_missing(),
			// Round 2 production contracts. Later registrations are current for duplicate stable IDs.
			self::link_name_missing(),
			self::link_purpose_ambiguous(),
			self::identical_link_name_different_destination(),
			self::missing_h1(),
			self::multiple_h1(),
			self::heading_level_jump(),
			self::empty_heading(),
			self::heading_semantics_review(),
			self::page_language_missing_current(),
			self::page_language_invalid(),
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

	/** Define the current missing link-name rule. @return RuleDefinition */
	private static function link_name_missing() {
		return new RuleDefinition(
			array(
				'id'                 => 'elavira.links.missing_accessible_name',
				'version'            => '1.0.0',
				'editorial_version'  => '1.0.0',
				'title'              => __( 'Link is missing an accessible name', 'elavira' ),
				'description'        => __( 'A rendered link has no objectively available accessible name.', 'elavira' ),
				'evaluation_type'    => EvaluationType::AUTOMATIC_FAILURE,
				'classification'     => FindingClassification::DETECTED_FAILURE,
				'priority'           => Priority::HIGH,
				'wcag'               => array( new WcagMapping( '2.4.4', 'A', 'operable' ), new WcagMapping( '4.1.2', 'A', 'robust' ) ),
				'summary'            => __( 'Links are missing accessible names.', 'elavira' ),
				'why_it_matters'     => __( 'People using assistive technology need a name to identify a link and understand that it can be activated.', 'elavira' ),
				'recommendation'     => __( 'Provide link content or another standards-supported accessible name that communicates the link purpose.', 'elavira' ),
				'wordpress_guidance' => __( 'Review the linked text, image alternative text, menu label, or block settings that supply the rendered link name.', 'elavira' ),
				'developer_guidance' => __( 'Ensure the rendered anchor has a non-empty computed accessible name from visible content or a valid naming relationship.', 'elavira' ),
				'learn_topic'        => 'links_navigation',
				'evidence_schema'    => self::schema( array( 'accessible_name_state' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, array( 'missing' ) ) ) ),
				'grouping_schema'    => self::schema( array(), 2 ),
				'grouping_strategy'  => new RuleLevelGrouping(),
			)
		);
	}

	/** Define the current conservative ambiguous-link contract. @return RuleDefinition */
	private static function link_purpose_ambiguous() {
		$labels = array( 'click_here', 'here', 'read_more', 'more', 'learn_more' );
		return new RuleDefinition(
			array(
				'id'                 => 'elavira.link.purpose_review',
				'version'            => '2.0.0',
				'editorial_version'  => '2.0.0',
				'title'              => __( 'Link purpose may be ambiguous', 'elavira' ),
				'description'        => __( 'A link uses a short generic accessible name whose purpose may depend on surrounding context.', 'elavira' ),
				'evaluation_type'    => EvaluationType::POTENTIAL_ISSUE,
				'classification'     => FindingClassification::POTENTIAL_ISSUE,
				'priority'           => Priority::MEDIUM,
				'wcag'               => array( new WcagMapping( '2.4.4', 'A', 'operable' ) ),
				'summary'            => __( 'Links use generic names that may not communicate their purpose.', 'elavira' ),
				'why_it_matters'     => __( 'Generic names can be unclear when links are encountered out of context, although programmatically associated context may make a particular link acceptable.', 'elavira' ),
				'recommendation'     => __( 'Review each link with its programmatically associated context and clarify the accessible name where its purpose remains unclear.', 'elavira' ),
				'wordpress_guidance' => __( 'Review the link text and surrounding block content. Use descriptive linked text when the purpose is not clear from supported context.', 'elavira' ),
				'developer_guidance' => __( 'Check the computed accessible name and context exposed to assistive technology; do not rely on visual proximity alone.', 'elavira' ),
				'learn_topic'        => 'links_navigation',
				'guided_decision'    => 'link_purpose',
				'evidence_schema'    => self::schema(
					array(
						'label_kind'       => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, $labels ),
						'destination_kind' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, array( 'internal', 'external', 'fragment', 'other', 'unknown' ) ),
					)
				),
				'grouping_schema'    => self::schema( array( 'label_kind' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, $labels ) ) ),
				'grouping_strategy'  => new InputGrouping(),
			)
		);
	}

	/** Define repeated link names with different destinations. @return RuleDefinition */
	private static function identical_link_name_different_destination() {
		return new RuleDefinition(
			array(
				'id'                 => 'elavira.links.identical_name_different_destination',
				'version'            => '1.0.0',
				'editorial_version'  => '1.0.0',
				'title'              => __( 'Identical link names lead to different destinations', 'elavira' ),
				'description'        => __( 'Rendered links share the same normalized accessible name but lead to materially different destinations.', 'elavira' ),
				'evaluation_type'    => EvaluationType::POTENTIAL_ISSUE,
				'classification'     => FindingClassification::POTENTIAL_ISSUE,
				'priority'           => Priority::MEDIUM,
				'wcag'               => array( new WcagMapping( '2.4.4', 'A', 'operable' ) ),
				'summary'            => __( 'Links with the same accessible name lead to different destinations.', 'elavira' ),
				'why_it_matters'     => __( 'People may not be able to distinguish links when the same name is presented without the surrounding page context.', 'elavira' ),
				'recommendation'     => __( 'Review whether each link purpose is clear from its accessible name and programmatically associated context.', 'elavira' ),
				'wordpress_guidance' => __( 'Use descriptive linked text in blocks, navigation, or widgets when identical names perform different actions.', 'elavira' ),
				'developer_guidance' => __( 'Compare computed accessible names and resolved destinations, preserving meaningful query and fragment differences.', 'elavira' ),
				'learn_topic'        => 'links_navigation',
				'guided_decision'    => 'link_purpose',
				'evidence_schema'    => self::schema(
					array(
						'name_fingerprint'        => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16 ),
						'destination_fingerprint' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16 ),
						'destination_kind'        => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, array( 'internal', 'external', 'fragment', 'other', 'unknown' ) ),
						'destination_state'       => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, array( 'different' ) ),
					)
				),
				'grouping_schema'    => self::schema( array( 'name_fingerprint' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16 ) ) ),
				'grouping_strategy'  => new InputGrouping(),
			)
		);
	}

	/** Define the missing-H1 structural warning. @return RuleDefinition */
	private static function missing_h1() {
		return self::h1_warning(
			'elavira.structure.missing_h1',
			__( 'Page has no level-one heading', 'elavira' ),
			__( 'The rendered page has no visible h1 element.', 'elavira' ),
			__( 'A clear level-one heading is strongly recommended', 'elavira' ),
			__( 'Elavira strongly recommends a clear level-one heading to establish the primary topic of the page and support a well-structured document. A missing H1 is not, by itself, an automatic WCAG failure.', 'elavira' ),
			__( 'Review the document structure and add a clear primary heading when that best represents the page. Do not add a heading merely to satisfy a count.', 'elavira' ),
			array( 'h1_count' => new FieldDefinition( FieldDefinition::TYPE_INTEGER, true, 0, array( 0 ) ) ),
			array( 'elavira.heading.structure_review' )
		);
	}

	/** Define the multiple-H1 structural warning. @return RuleDefinition */
	private static function multiple_h1() {
		return self::h1_warning(
			'elavira.structure.multiple_h1',
			__( 'Page has multiple level-one headings', 'elavira' ),
			__( 'The rendered page has more than one visible h1 element.', 'elavira' ),
			__( 'Multiple level-one headings need structural review', 'elavira' ),
			__( 'Multiple level-one headings are not automatically a WCAG failure. Elavira strongly recommends reviewing whether one clear primary page heading would better represent the page’s document structure.', 'elavira' ),
			__( 'Review the purpose and hierarchy of the level-one headings. Retain multiple H1 elements when they accurately represent the document; otherwise clarify the primary heading.', 'elavira' ),
			array( 'h1_state' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, array( 'multiple' ) ) )
		);
	}

	/**
	 * Build one H1 structural warning.
	 *
	 * @param string            $id             Rule ID.
	 * @param string            $title          Title.
	 * @param string            $description    Description.
	 * @param string            $summary        Summary.
	 * @param string            $impact         Impact guidance.
	 * @param string            $recommendation Recommendation.
	 * @param FieldDefinition[] $evidence       Evidence fields.
	 * @param string[]          $supersedes      Superseded rule IDs.
	 * @return RuleDefinition
	 */
	private static function h1_warning( $id, $title, $description, $summary, $impact, $recommendation, array $evidence, array $supersedes = array() ) {
		return new RuleDefinition(
			array(
				'id'                 => $id,
				'version'            => '1.0.0',
				'editorial_version'  => '1.0.0',
				'title'              => $title,
				'description'        => $description,
				'evaluation_type'    => EvaluationType::HUMAN_REVIEW,
				'classification'     => FindingClassification::HUMAN_REVIEW,
				'priority'           => Priority::MEDIUM,
				'wcag'               => array( new WcagMapping( '1.3.1', 'A', 'perceivable' ) ),
				'summary'            => $summary,
				'why_it_matters'     => $impact,
				'recommendation'     => $recommendation,
				'wordpress_guidance' => __( 'Review Heading blocks and template headings in the rendered page. Choose heading levels for document structure, not visual size.', 'elavira' ),
				'developer_guidance' => __( 'Use native heading elements to represent the document hierarchy. WCAG and HTML do not require exactly one H1.', 'elavira' ),
				'learn_topic'        => 'headings_structure',
				'guided_decision'    => 'heading_structure',
				'presentation'       => 'structural_warning',
				'evidence_schema'    => self::schema( $evidence ),
				'grouping_schema'    => self::schema( array(), 2 ),
				'grouping_strategy'  => new RuleLevelGrouping(),
				'supersedes'         => $supersedes,
			)
		);
	}

	/** Define a conservative heading-level jump review. @return RuleDefinition */
	private static function heading_level_jump() {
		$levels = array( 1, 2, 3, 4, 5, 6 );
		return self::heading_review_rule(
			'elavira.structure.heading_level_jump',
			'1.0.0',
			EvaluationType::POTENTIAL_ISSUE,
			FindingClassification::POTENTIAL_ISSUE,
			__( 'Heading hierarchy skips a level', 'elavira' ),
			__( 'A visible heading follows a heading more than one level higher in the rendered sequence.', 'elavira' ),
			__( 'Heading levels may not reflect the intended document hierarchy.', 'elavira' ),
			__( 'Skipped heading levels can make relationships between sections less clear, although page context determines whether the observed sequence is problematic.', 'elavira' ),
			__( 'Review the surrounding sections and use heading levels that accurately represent their hierarchy.', 'elavira' ),
			array(
				'previous_level' => new FieldDefinition( FieldDefinition::TYPE_INTEGER, true, 0, $levels ),
				'current_level'  => new FieldDefinition( FieldDefinition::TYPE_INTEGER, true, 0, $levels ),
			),
			array(
				'previous_level' => new FieldDefinition( FieldDefinition::TYPE_INTEGER, true, 0, $levels ),
				'current_level'  => new FieldDefinition( FieldDefinition::TYPE_INTEGER, true, 0, $levels ),
			)
		);
	}

	/** Define empty semantic heading review. @return RuleDefinition */
	private static function empty_heading() {
		return self::heading_review_rule( 'elavira.structure.empty_heading', '1.0.0', EvaluationType::POTENTIAL_ISSUE, FindingClassification::POTENTIAL_ISSUE, __( 'Semantic heading is empty', 'elavira' ), __( 'A visible semantic heading has no accessible text content.', 'elavira' ), __( 'Empty headings may create confusing navigation stops.', 'elavira' ), __( 'People who navigate by headings may encounter a heading with no useful name. The markup alone does not establish a complete WCAG failure.', 'elavira' ), __( 'Review the element in context. Remove unintended empty headings or provide meaningful heading text that describes the section.', 'elavira' ), array( 'heading_level' => new FieldDefinition( FieldDefinition::TYPE_INTEGER, true, 0, array( 1, 2, 3, 4, 5, 6 ) ) ), array() );
	}

	/** Define the current bounded contextual heading review. @return RuleDefinition */
	private static function heading_semantics_review() {
		return self::heading_review_rule( 'elavira.heading.structure_review', '2.0.0', EvaluationType::HUMAN_REVIEW, FindingClassification::HUMAN_REVIEW, __( 'Page heading semantics need review', 'elavira' ), __( 'The rendered page or its main content contains no visible semantic heading elements.', 'elavira' ), __( 'The main page content may lack a semantic heading structure.', 'elavira' ), __( 'Content without semantic headings may be harder to understand and navigate, but a person must determine which content requires headings.', 'elavira' ), __( 'Review whether the main content contains sections that need descriptive semantic headings. Do not add headings that do not represent real structure.', 'elavira' ), array( 'issue' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 32, array( 'no_visible_headings', 'main_without_heading' ) ) ), array() );
	}

	/**
	 * Build a heading review rule.
	 *
	 * @param string            $id             Rule ID.
	 * @param string            $version        Rule version.
	 * @param string            $evaluation     Evaluation type.
	 * @param string            $classification Finding classification.
	 * @param string            $title          Title.
	 * @param string            $description    Description.
	 * @param string            $summary        Summary.
	 * @param string            $impact         Impact guidance.
	 * @param string            $recommendation Recommendation.
	 * @param FieldDefinition[] $evidence       Evidence fields.
	 * @param FieldDefinition[] $grouping       Grouping fields.
	 * @return RuleDefinition
	 */
	private static function heading_review_rule( $id, $version, $evaluation, $classification, $title, $description, $summary, $impact, $recommendation, array $evidence, array $grouping ) {
		return new RuleDefinition(
			array(
				'id'                 => $id,
				'version'            => $version,
				'editorial_version'  => $version,
				'title'              => $title,
				'description'        => $description,
				'evaluation_type'    => $evaluation,
				'classification'     => $classification,
				'priority'           => Priority::MEDIUM,
				'wcag'               => array( new WcagMapping( '1.3.1', 'A', 'perceivable' ) ),
				'summary'            => $summary,
				'why_it_matters'     => $impact,
				'recommendation'     => $recommendation,
				'wordpress_guidance' => __( 'Review Heading blocks and template headings in the rendered page. Choose levels according to document structure.', 'elavira' ),
				'developer_guidance' => __( 'Use native h1–h6 elements to represent sections and keep the rendered hierarchy meaningful.', 'elavira' ),
				'learn_topic'        => 'headings_structure',
				'guided_decision'    => 'heading_structure',
				'evidence_schema'    => self::schema( $evidence ),
				'grouping_schema'    => self::schema( $grouping ),
				'grouping_strategy'  => $grouping ? new InputGrouping() : new RuleLevelGrouping(),
			)
		);
	}

	/** Define current missing-language semantics while retaining the stable ID. @return RuleDefinition */
	private static function page_language_missing_current() {
		return self::language_rule( 'elavira.document.language_missing', '2.0.0', __( 'Page language is missing', 'elavira' ), __( 'The rendered document does not declare a non-empty default human language.', 'elavira' ), __( 'The page language is not declared.', 'elavira' ), __( 'Add a valid primary language tag to the rendered html element.', 'elavira' ), array( 'language_state' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, array( 'missing', 'empty' ) ) ) );
	}

	/** Define objectively invalid primary language tags. @return RuleDefinition */
	private static function page_language_invalid() {
		return self::language_rule(
			'elavira.document.invalid_language',
			'1.0.0',
			__( 'Page language tag is invalid', 'elavira' ),
			__( 'The rendered document declares a primary language tag with an objectively invalid structure.', 'elavira' ),
			__( 'The page language declaration is invalid.', 'elavira' ),
			__( 'Correct the primary language tag. Elavira does not infer whether the declaration matches the page prose.', 'elavira' ),
			array(
				'language_state'    => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 16, array( 'invalid' ) ),
				'declared_language' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 64 ),
			)
		);
	}

	/**
	 * Build a language rule.
	 *
	 * @param string            $id             Rule ID.
	 * @param string            $version        Rule version.
	 * @param string            $title          Title.
	 * @param string            $description    Description.
	 * @param string            $summary        Summary.
	 * @param string            $recommendation Recommendation.
	 * @param FieldDefinition[] $evidence       Evidence fields.
	 * @return RuleDefinition
	 */
	private static function language_rule( $id, $version, $title, $description, $summary, $recommendation, array $evidence ) {
		return new RuleDefinition(
			array(
				'id'                 => $id,
				'version'            => $version,
				'editorial_version'  => $version,
				'title'              => $title,
				'description'        => $description,
				'evaluation_type'    => EvaluationType::AUTOMATIC_FAILURE,
				'classification'     => FindingClassification::DETECTED_FAILURE,
				'priority'           => Priority::MEDIUM,
				'wcag'               => array( new WcagMapping( '3.1.1', 'A', 'understandable' ) ),
				'summary'            => $summary,
				'why_it_matters'     => __( 'Assistive technologies use the declared language to pronounce and interpret content correctly.', 'elavira' ),
				'recommendation'     => $recommendation,
				'wordpress_guidance' => __( 'Confirm the WordPress Site Language and inspect whether the active theme outputs a valid lang attribute on the rendered html element.', 'elavira' ),
				'developer_guidance' => __( 'Use the WordPress language attributes API and emit a valid BCP 47-style primary language tag.', 'elavira' ),
				'learn_topic'        => 'language_content',
				'evidence_schema'    => self::schema( $evidence ),
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
