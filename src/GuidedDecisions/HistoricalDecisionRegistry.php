<?php
/**
 * Representative Core Guided Decisions.
 *
 * @package Elavira
 */

namespace Elavira\GuidedDecisions;

// phpcs:disable Squiz.Commenting.FunctionComment -- Concise factory comments are intentional.

use Elavira\Findings\Values\FindingStatus;

/** Builds the initial server-owned decision library. */
final class HistoricalDecisionRegistry {
	/** Build the Core registry. @return DecisionRegistry */
	public static function create() {
		$registry = new DecisionRegistry();
		$registry->register( self::alternative_text() );
		$registry->register( self::link_purpose() );
		$registry->register( self::heading_structure() );
		$registry->register( self::form_labels_instructions() );
		$registry->register( self::use_of_color() );
		$registry->register( self::keyboard_interaction() );
		$registry->register( self::audio_video_alternatives() );
		return $registry;
	}

	/** Define contextual alternative-text review. @return DecisionDefinition */
	private static function alternative_text() {
		return new DecisionDefinition(
			array(
				'id'                => 'elavira.decision.alternative_text',
				'version'           => '1.0.0',
				'editorial_version' => '1.0.0',
				'title'             => __( 'Review alternative text', 'elavira' ),
				'purpose'           => __( 'Determine an appropriate text-alternative approach from the image’s purpose in its actual context.', 'elavira' ),
				'rules'             => array(
					array(
						'id'      => 'elavira.image.alt_missing',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.images.alt_quality_review',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.images.filename_alt',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.images.long_alt_review',
						'version' => '1.0.0',
					),
				),
				'learn_topics'      => array( 'images_alternative_text' ),
				'start'             => 'image_role',
				'steps'             => array(
					'image_role'       => self::single_choice(
						__( 'What is the image’s primary purpose in this context?', 'elavira' ),
						array(
							'informative' => array( __( 'It communicates information', 'elavira' ), 'same_information' ),
							'decorative'  => array( __( 'It is purely decorative', 'elavira' ), 'decorative' ),
							'functional'  => array( __( 'It acts as a link or control', 'elavira' ), 'functional' ),
							'complex'     => array( __( 'It is a chart, diagram, infographic, or other complex image', 'elavira' ), 'complex' ),
							'text'        => array( __( 'It contains meaningful text', 'elavira' ), 'image_text' ),
							'uncertain'   => array( __( 'I am not sure', 'elavira' ), 'further_review' ),
						)
					),
					'same_information' => self::yes_no( __( 'Is the same information already communicated in adjacent text?', 'elavira' ), 'redundant', 'informative' ),
				),
				'outcomes'          => array(
					'decorative'     => self::outcome( __( 'The image may be treated as decorative.', 'elavira' ), __( 'Confirm it adds no information or function, then provide an intentionally empty text alternative through the responsible content or code. Do not add descriptive text merely because an image exists.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
					'redundant'      => self::outcome( __( 'The image may be redundant with adjacent text.', 'elavira' ), __( 'Confirm the adjacent text communicates the same purpose. If so, use an intentionally empty alternative so the information is not announced twice.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
					'informative'    => self::outcome( __( 'The image likely needs a meaningful text alternative.', 'elavira' ), __( 'Write a concise alternative that communicates the image’s purpose in this context, then re-audit. Elavira does not generate or apply the wording.', 'elavira' ), FindingStatus::OPEN ),
					'functional'     => self::outcome( __( 'The functional image needs an accessible name describing its action or destination.', 'elavira' ), __( 'Provide an accessible name for the link or control based on what it does, not merely what the image looks like, then test the rendered control.', 'elavira' ), FindingStatus::OPEN ),
					'complex'        => self::outcome( __( 'The complex image likely needs both a short alternative and a more complete explanation.', 'elavira' ), __( 'Provide a concise text alternative and make the important data or relationships available in nearby text, a table, or another suitable explanation.', 'elavira' ), FindingStatus::OPEN ),
					'image_text'     => self::outcome( __( 'The meaningful text in the image must be available as text.', 'elavira' ), __( 'Prefer real text where practical. Otherwise provide an alternative that communicates the same words and purpose, while separately checking applicable image-of-text requirements.', 'elavira' ), FindingStatus::OPEN ),
					'further_review' => self::outcome( __( 'The appropriate treatment needs further human review.', 'elavira' ), __( 'Review the image with its surrounding content and intended action before choosing decorative, informative, functional, complex, or image-of-text treatment.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
				),
			)
		);
	}

	/** Define link-purpose contextual review. @return DecisionDefinition */
	private static function link_purpose() {
		return new DecisionDefinition(
			array(
				'id'                => 'elavira.decision.link_purpose',
				'version'           => '1.0.0',
				'editorial_version' => '1.0.0',
				'title'             => __( 'Review link purpose', 'elavira' ),
				'purpose'           => __( 'Consider whether people using assistive technology can understand what the link does from its accessible name and programmatically associated context.', 'elavira' ),
				'learn_topics'      => array( 'links_navigation' ),
				'rules'             => array(
					array(
						'id'      => 'elavira.link.purpose_review',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.link.purpose_review',
						'version' => '2.0.0',
					),
					array(
						'id'      => 'elavira.links.identical_name_different_destination',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.links.identical_name_different_destination',
						'version' => '2.0.0',
					),
				),
				'start'             => 'purpose_clear',
				'steps'             => array(
					'purpose_clear'          => self::yes_no( __( 'Is the link purpose clear from its accessible name or programmatically associated context?', 'elavira' ), 'context_available', 'different_destinations' ),
					'context_available'      => self::yes_no( __( 'Would that context be available when assistive technology presents the link?', 'elavira' ), 'context_sufficient', 'remediation_likely' ),
					'different_destinations' => self::yes_no( __( 'Do links with the same ambiguous name lead to different destinations or actions?', 'elavira' ), 'remediation_likely', 'context_available' ),
				),
				'outcomes'          => array(
					'context_sufficient' => self::outcome( __( 'The available context may communicate the link purpose.', 'elavira' ), __( 'Review the link with assistive technology and keep the finding in human review until your evaluation is complete.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
					'remediation_likely' => self::outcome( __( 'The link purpose likely needs clarification.', 'elavira' ), __( 'Revise the accessible link name or its programmatically associated context, then mark it Addressed only after making the change.', 'elavira' ), FindingStatus::OPEN ),
				),
			)
		);
	}

	/** Define heading-structure contextual review. @return DecisionDefinition */
	private static function heading_structure() {
		return new DecisionDefinition(
			array(
				'id'                => 'elavira.decision.heading_structure',
				'version'           => '1.0.0',
				'editorial_version' => '1.0.0',
				'title'             => __( 'Review heading structure', 'elavira' ),
				'purpose'           => __( 'Consider whether headings describe real sections and communicate the organization of the rendered page.', 'elavira' ),
				'learn_topics'      => array( 'headings_structure' ),
				'rules'             => array(
					array(
						'id'      => 'elavira.heading.structure_review',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.heading.structure_review',
						'version' => '2.0.0',
					),
					array(
						'id'      => 'elavira.structure.missing_h1',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.structure.multiple_h1',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.structure.heading_level_jump',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.structure.empty_heading',
						'version' => '1.0.0',
					),
				),
				'start'             => 'real_sections',
				'steps'             => array(
					'real_sections'   => self::yes_no( __( 'Do the headings identify actual sections of content rather than only provide visual styling?', 'elavira' ), 'hierarchy_clear', 'remediation_likely' ),
					'hierarchy_clear' => self::yes_no( __( 'Does the heading hierarchy communicate the page organization clearly when read without visual styling?', 'elavira' ), 'visual_headings', 'remediation_likely' ),
					'visual_headings' => self::yes_no( __( 'Is any text functioning visually as a heading without semantic heading markup?', 'elavira' ), 'remediation_likely', 'structure_reasonable' ),
				),
				'outcomes'          => array(
					'structure_reasonable' => self::outcome( __( 'The heading structure may communicate the content organization appropriately.', 'elavira' ), __( 'Complete any remaining assistive-technology review before deciding whether the finding can be retained as reviewed.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
					'remediation_likely'   => self::outcome( __( 'The heading structure likely needs remediation.', 'elavira' ), __( 'Use semantic headings that reflect the document structure, then mark Addressed only after changing the rendered page.', 'elavira' ), FindingStatus::OPEN ),
				),
			)
		);
	}

	/** Define form-label and instruction review. @return DecisionDefinition */
	private static function form_labels_instructions() {
		return new DecisionDefinition(
			array(
				'id'                => 'elavira.decision.form_labels_instructions',
				'version'           => '1.0.0',
				'editorial_version' => '1.0.0',
				'title'             => __( 'Review form labels and instructions', 'elavira' ),
				'purpose'           => __( 'Review whether a form control communicates its accessible name, purpose, required state, instructions, format, and relevant errors.', 'elavira' ),
				'rules'             => array(
					array(
						'id'      => 'elavira.form.accessible_name_missing',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.form.accessible_name_missing',
						'version' => '2.0.0',
					),
					array(
						'id'      => 'elavira.forms.broken_label_reference',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.forms.placeholder_only_label',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.forms.required_instruction_review',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.forms.error_identification_review',
						'version' => '1.0.0',
					),
				),
				'learn_topics'      => array( 'forms_labels' ),
				'start'             => 'name_source',
				'steps'             => array(
					'name_source'          => self::single_choice(
						__( 'How does the control receive its accessible name?', 'elavira' ),
						array(
							'visible_label' => array( __( 'A programmatically associated visible label', 'elavira' ), 'purpose_clear' ),
							'labelledby'    => array( __( 'Visible text referenced programmatically', 'elavira' ), 'purpose_clear' ),
							'aria_label'    => array( __( 'Another supported programmatic name', 'elavira' ), 'purpose_clear' ),
							'placeholder'   => array( __( 'Placeholder text only', 'elavira' ), 'placeholder_only' ),
							'none'          => array( __( 'No accessible name', 'elavira' ), 'missing_name' ),
							'uncertain'     => array( __( 'I am not sure', 'elavira' ), 'further_review' ),
						)
					),
					'purpose_clear'        => self::yes_no( __( 'Does the accessible name clearly communicate the control’s purpose?', 'elavira' ), 'instructions_needed', 'insufficient_name' ),
					'instructions_needed'  => self::yes_no( __( 'Are instructions needed for required state, expected format, completion, or relevant error correction?', 'elavira' ), 'instructions_present', 'context_reasonable' ),
					'instructions_present' => self::yes_no( __( 'Are those instructions persistently available and programmatically associated where necessary?', 'elavira' ), 'context_reasonable', 'instructions_missing' ),
				),
				'outcomes'          => array(
					'missing_name'         => self::outcome( __( 'The control is missing an accessible name.', 'elavira' ), __( 'Add a concise programmatic name, preferably using a properly associated visible label where suitable, then re-audit the rendered form.', 'elavira' ), FindingStatus::OPEN ),
					'placeholder_only'     => self::outcome( __( 'Placeholder text alone is not a reliable persistent label.', 'elavira' ), __( 'Provide a persistent accessible naming pattern appropriate to the control. Keep placeholder text, if useful, as an example rather than the only label.', 'elavira' ), FindingStatus::OPEN ),
					'insufficient_name'    => self::outcome( __( 'The accessible name likely does not communicate the control’s purpose.', 'elavira' ), __( 'Clarify the name through an appropriate visible or programmatic label and confirm it in the rendered form.', 'elavira' ), FindingStatus::OPEN ),
					'instructions_missing' => self::outcome( __( 'Necessary instructions or error guidance appear to be missing or unavailable.', 'elavira' ), __( 'Provide the required state, format, completion, or error information before it is needed and associate it programmatically where necessary.', 'elavira' ), FindingStatus::OPEN ),
					'context_reasonable'   => self::outcome( __( 'The control’s label and instructional context may be appropriate.', 'elavira' ), __( 'Complete keyboard and assistive-technology review of the rendered form before closing the human review.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
					'further_review'       => self::outcome( __( 'The control’s accessible naming source needs further human review.', 'elavira' ), __( 'Inspect the rendered accessibility information and visible instructions; placeholder presence alone does not establish an adequate label.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
				),
			)
		);
	}

	/** Define manual use-of-color review. @return DecisionDefinition */
	private static function use_of_color() {
		return new DecisionDefinition(
			array(
				'id'                => 'elavira.decision.use_of_color',
				'version'           => '1.0.0',
				'editorial_version' => '1.0.0',
				'title'             => __( 'Review use of color', 'elavira' ),
				'purpose'           => __( 'Determine whether color is the only perceivable cue for information, status, an action, or a distinction.', 'elavira' ),
				'rules'             => array(
					array(
						'id'      => 'elavira.color.color_only_review',
						'version' => '1.0.0',
					),
				),
				'learn_topics'      => array( 'color_contrast' ),
				'start'             => 'color_meaning',
				'steps'             => array(
					'color_meaning' => self::yes_no( __( 'Is color used to communicate information, status, a required action, or a distinction?', 'elavira' ), 'other_cue', 'contrast_review' ),
					'other_cue'     => self::single_choice(
						__( 'What equivalent perceivable cue communicates the same meaning without relying on color?', 'elavira' ),
						array(
							'none'       => array( __( 'No equivalent cue', 'elavira' ), 'color_only' ),
							'text'       => array( __( 'Text', 'elavira' ), 'contrast_review' ),
							'icon_name'  => array( __( 'An icon with equivalent text or an accessible name', 'elavira' ), 'contrast_review' ),
							'visual_cue' => array( __( 'A pattern, shape, underline, position, or other perceivable indicator', 'elavira' ), 'contrast_review' ),
							'uncertain'  => array( __( 'I am not sure', 'elavira' ), 'further_review' ),
						)
					),
				),
				'outcomes'          => array(
					'color_only'      => self::outcome( __( 'Color appears to be the only cue and likely needs remediation.', 'elavira' ), __( 'Add an equivalent text or other perceivable indicator. Separately test contrast; solving color-only communication does not establish sufficient contrast.', 'elavira' ), FindingStatus::OPEN ),
					'contrast_review' => self::outcome( __( 'Meaning may be available without color alone.', 'elavira' ), __( 'Confirm the non-color cue remains understandable, then evaluate text and non-text contrast separately. This review does not determine contrast ratios.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
					'further_review'  => self::outcome( __( 'Whether color is the only meaningful cue needs further human review.', 'elavira' ), __( 'Review the content without color and confirm that text or another perceivable indicator communicates the same meaning; assess contrast separately.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
				),
			)
		);
	}

	/** Define manual keyboard-interaction review. @return DecisionDefinition */
	private static function keyboard_interaction() {
		return new DecisionDefinition(
			array(
				'id'                => 'elavira.decision.keyboard_interaction',
				'version'           => '1.0.0',
				'editorial_version' => '1.0.0',
				'title'             => __( 'Review keyboard interaction', 'elavira' ),
				'purpose'           => __( 'Guide a human keyboard test of reachability, expected operation, focus escape, visibility, and order.', 'elavira' ),
				'rules'             => array(
					array(
						'id'      => 'elavira.keyboard.positive_tabindex',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.keyboard.non_native_interactive',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.keyboard.possible_unreachable_control',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.keyboard.possible_trap',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.keyboard.custom_widget_review',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.focus.possible_hidden_focus',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.focus.order_review',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.focus.modal_management_review',
						'version' => '1.0.0',
					),
				),
				'learn_topics'      => array( 'keyboard_accessibility', 'focus_focus_order' ),
				'start'             => 'instructions',
				'steps'             => array(
					'instructions'  => self::information( __( 'Test the rendered interaction', 'elavira' ), __( 'Use only the keyboard. For native controls use their expected keys; for custom widgets compare behavior with the widget’s documented keyboard pattern. Receiving Tab focus alone does not establish operability.', 'elavira' ), 'reachable' ),
					'reachable'     => self::yes_no( __( 'Can the interactive functionality be reached using the keyboard?', 'elavira' ), 'operable', 'unreachable' ),
					'operable'      => self::yes_no( __( 'Can every relevant action be performed using the expected keyboard interaction?', 'elavira' ), 'can_leave', 'inoperable' ),
					'can_leave'     => self::yes_no( __( 'Can keyboard focus move away from the component without an undocumented method?', 'elavira' ), 'focus_visible', 'keyboard_trap' ),
					'focus_visible' => self::yes_no( __( 'Is keyboard focus visibly identifiable throughout the interaction?', 'elavira' ), 'focus_order', 'focus_problem' ),
					'focus_order'   => self::yes_no( __( 'Does focus move in an order that preserves meaning and expected operation?', 'elavira' ), 'keyboard_reasonable', 'order_problem' ),
				),
				'outcomes'          => array(
					'unreachable'         => self::outcome( __( 'The functionality is not keyboard reachable.', 'elavira' ), __( 'Use a native interactive element or provide equivalent keyboard focusability, then test the entire interaction again.', 'elavira' ), FindingStatus::OPEN ),
					'inoperable'          => self::outcome( __( 'The component receives focus but is not fully keyboard operable.', 'elavira' ), __( 'Implement the expected keyboard behavior for every action. Focusability alone is insufficient.', 'elavira' ), FindingStatus::OPEN ),
					'keyboard_trap'       => self::outcome( __( 'The interaction appears to create a keyboard trap.', 'elavira' ), __( 'Ensure users can move focus away using standard interaction, or clearly document a nonstandard method only where WCAG permits it.', 'elavira' ), FindingStatus::OPEN ),
					'focus_problem'       => self::outcome( __( 'Keyboard focus is not consistently visible.', 'elavira' ), __( 'Provide a visible focus indicator in every relevant state without removing usable native focus styling.', 'elavira' ), FindingStatus::OPEN ),
					'order_problem'       => self::outcome( __( 'The focus order may not preserve meaning or operation.', 'elavira' ), __( 'Align DOM and focus order with the intended reading and interaction sequence, then repeat the keyboard test.', 'elavira' ), FindingStatus::OPEN ),
					'keyboard_reasonable' => self::outcome( __( 'The observed keyboard interaction may be appropriate.', 'elavira' ), __( 'Repeat the test across states and relevant assistive technologies before treating the review as complete.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
				),
			)
		);
	}

	/** Define manual prerecorded-media alternatives review. @return DecisionDefinition */
	private static function audio_video_alternatives() {
		return new DecisionDefinition(
			array(
				'id'                => 'elavira.decision.audio_video_alternatives',
				'version'           => '1.0.0',
				'editorial_version' => '1.0.0',
				'title'             => __( 'Review audio and video alternatives', 'elavira' ),
				'purpose'           => __( 'Identify relevant alternatives for prerecorded audio-only, video-only, or synchronized media.', 'elavira' ),
				'rules'             => array(),
				'learn_topics'      => array( 'audio_video' ),
				'start'             => 'media_type',
				'steps'             => array(
					'media_type'           => self::single_choice(
						__( 'Which type of prerecorded media are you reviewing?', 'elavira' ),
						array(
							'audio_only'   => array( __( 'Audio only', 'elavira' ), 'audio_information' ),
							'video_only'   => array( __( 'Video only, without synchronized audio', 'elavira' ), 'visual_information' ),
							'synchronized' => array( __( 'Synchronized audio and video', 'elavira' ), 'meaningful_speech' ),
							'uncertain'    => array( __( 'I am not sure', 'elavira' ), 'further_review' ),
						)
					),
					'audio_information'    => self::yes_no( __( 'Does the audio-only content communicate meaningful speech or sound?', 'elavira' ), 'transcript_available', 'further_review' ),
					'transcript_available' => self::yes_no( __( 'Is an equivalent transcript or other text alternative available?', 'elavira' ), 'audio_reasonable', 'audio_alternative_needed' ),
					'visual_information'   => self::yes_no( __( 'Does the video-only content communicate meaningful visual information?', 'elavira' ), 'visual_alternative', 'further_review' ),
					'meaningful_speech'    => self::yes_no( __( 'Does the synchronized media contain meaningful speech or other audio information?', 'elavira' ), 'captions_available', 'meaningful_visuals' ),
					'captions_available'   => self::yes_no( __( 'Are accurate synchronized captions available for that audio information?', 'elavira' ), 'meaningful_visuals', 'captions_needed' ),
					'meaningful_visuals'   => self::yes_no( __( 'Does the media contain meaningful visual information not communicated in the audio?', 'elavira' ), 'visual_alternative', 'media_reasonable' ),
					'visual_alternative'   => self::yes_no( __( 'Is that meaningful visual information available through audio description or an appropriate equivalent alternative?', 'elavira' ), 'media_reasonable', 'visual_alternative_needed' ),
				),
				'outcomes'          => array(
					'audio_reasonable'          => self::outcome( __( 'The audio-only content may have an appropriate text alternative.', 'elavira' ), __( 'Confirm the transcript conveys meaningful speech and sounds. A transcript is text; it is not the same as synchronized captions.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
					'audio_alternative_needed'  => self::outcome( __( 'The audio-only content likely needs an equivalent alternative.', 'elavira' ), __( 'Provide an accessible transcript or other equivalent presentation of the prerecorded audio information.', 'elavira' ), FindingStatus::OPEN ),
					'captions_needed'           => self::outcome( __( 'The synchronized media likely needs accurate captions.', 'elavira' ), __( 'Provide synchronized captions for meaningful speech and audio information. Subtitles or a transcript alone may not provide the same synchronized access.', 'elavira' ), FindingStatus::OPEN ),
					'visual_alternative_needed' => self::outcome( __( 'Meaningful visual information likely needs an appropriate alternative.', 'elavira' ), __( 'Provide audio description or another equivalent treatment appropriate to the prerecorded media and applicable WCAG criterion.', 'elavira' ), FindingStatus::OPEN ),
					'media_reasonable'          => self::outcome( __( 'The reviewed media alternatives may address the identified information.', 'elavira' ), __( 'Verify caption accuracy, transcript equivalence, visual-information coverage, and player accessibility as applicable before completing the review.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
					'further_review'            => self::outcome( __( 'The media type or meaningful information needs further human review.', 'elavira' ), __( 'Identify whether the prerecorded content is audio-only, video-only, or synchronized and what speech, sound, or visual information it communicates before selecting alternatives.', 'elavira' ), FindingStatus::NEEDS_REVIEW ),
				),
			)
		);
	}

	/** Build a yes/no step. @param string $question Question. @param string $yes Yes target. @param string $no No target. @return array */
	private static function yes_no( $question, $yes, $no ) {
		return array(
			'type'     => 'yes_no',
			'question' => $question,
			'options'  => array(
				array(
					'id'     => 'yes',
					'label'  => __( 'Yes', 'elavira' ),
					'target' => $yes,
				),
				array(
					'id'     => 'no',
					'label'  => __( 'No', 'elavira' ),
					'target' => $no,
				),
			),
		);
	}

	/** Build a single-choice step. @param string $question Question. @param array $choices Choice ID to label/target. @return array */
	private static function single_choice( $question, array $choices ) {
		$options = array();
		foreach ( $choices as $id => $choice ) {
			$options[] = array(
				'id'     => $id,
				'label'  => $choice[0],
				'target' => $choice[1],
			);
		}
		return array(
			'type'     => 'single_choice',
			'question' => $question,
			'options'  => $options,
		);
	}

	/** Build an informational step. @param string $heading Heading. @param string $content Content. @param string $target Target. @return array */
	private static function information( $heading, $content, $target ) {
		return array(
			'type'     => 'information',
			'question' => $heading,
			'content'  => $content,
			'options'  => array(
				array(
					'id'     => 'continue',
					'label'  => __( 'Continue', 'elavira' ),
					'target' => $target,
				),
			),
		);
	}

	/** Build an explanatory outcome. @param string $interpretation Interpretation. @param string $recommendation Recommendation. @param string $status Recommended status. @return array */
	private static function outcome( $interpretation, $recommendation, $status ) {
		return array(
			'interpretation' => $interpretation,
			'recommendation' => $recommendation,
			'status'         => $status,
		);
	}
}
