<?php
/**
 * Current human-authoritative Guided Decision library.
 *
 * @package Elavira
 */

namespace Elavira\GuidedDecisions;

// phpcs:disable Squiz.Commenting.FunctionComment -- Concise factory methods.
/** Builds current flows alongside separately retained historical graphs. */
final class CoreDecisionRegistry {
	public static function create() {
		$registry   = new DecisionRegistry();
		$historical = HistoricalDecisionRegistry::create();
		foreach ( $historical->all() as $definition ) {
			$registry->register( $definition, false ); }
		foreach ( self::definitions() as $data ) {
			$id = 'elavira.decision.' . $data['id'];
			if ( ! isset( $data['rules'] ) ) {
				$previous             = $historical->get( $id, '1.0.0' );
				$data['rules']        = $previous->get( 'rules' );
				$data['learn_topics'] = $previous->get( 'learn_topics' );
			}
			if ( 'keyboard_interaction' === $data['id'] ) {
				$data['rules'] = array_values(
					array_filter(
						$data['rules'],
						static function ( $rule ) {
							return 'elavira.focus.possible_hidden_focus' !== $rule['id'];
						}
					)
				);
			}
			$data['version']           = in_array( $data['id'], array( 'focus_visibility', 'aria_behavior', 'contrast_review' ), true ) ? '1.0.0' : '2.0.0';
			$data['editorial_version'] = $data['version'];
			$data['id']                = $id;
			$registry->register( new DecisionDefinition( $data ) );
		}
		return $registry;
	}
	private static function definitions() {
		return array(
			array(
				'id'       => 'link_purpose',
				'title'    => __( 'Review Link Purpose', 'elavira' ),
				'purpose'  => __( 'Decide whether someone can understand where this link goes before using it. Start with the recorded link, then check the current page if anything has changed.', 'elavira' ),
				'start'    => 'link_content',
				'steps'    => array(
					'link_content'      => array(
						'type'     => 'single_choice',
						'question' => __( 'What provides the meaningful content of this link?', 'elavira' ),
						'help'     => __( 'Look inside the clickable area. Choose text and an image only when both are inside the same link. A nearby image outside the link does not count.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'text',
								'label'  => __( 'Text', 'elavira' ),
								'target' => 'text_name',
							),
							array(
								'id'     => 'image_only',
								'label'  => __( 'An image, with no useful link text', 'elavira' ),
								'target' => 'image_words',
							),
							array(
								'id'     => 'mixed',
								'label'  => __( 'Text and an image', 'elavira' ),
								'target' => 'mixed_image',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'text_name'         => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the name announced for this link tell someone where it goes or what it does?', 'elavira' ),
						'help'     => __( 'Read the visible words and inspect the link name in your browser\'s accessibility panel or with a screen reader. The recorded simple name is only an excerpt, not a guaranteed complete browser accessibility-tree name. ARIA references such as aria-labelledby can change it. For repeated names, check each destination separately.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'clear',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'context',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'context'           => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the link together with its associated context explain the destination or action?', 'elavira' ),
						'help'     => __( 'Read the full sentence, paragraph, list item, or table cell containing the link. A table header may also provide context. This relationship must be available to assistive technology; being visually nearby alone is not enough. Example: “Learn about our services. Learn more” in one paragraph may explain a /services/ link. Check the full page when the recorded excerpt is incomplete.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'clear_context',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'text_change',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'image_words'       => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the linked image contain meaningful visible words?', 'elavira' ),
						'help'     => __( 'Look at the image itself, not its filename. Words such as “View Fall Collection” may tell sighted users what the link does.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'image_text_name',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'image_name',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'image_text_name'   => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the link\'s announced name include those meaningful words and communicate its purpose?', 'elavira' ),
						'help'     => __( 'Inspect the full link name with a screen reader or the browser\'s accessibility panel. Image alt text often supplies the name, but ARIA can override or combine naming sources. Example: a linked picture saying “View Fall Collection” needs that meaningful text and purpose in its alternative, not just “Clothing photo”.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'clear',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'image_text_change',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'image_name'        => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the link\'s announced name explain the destination or action?', 'elavira' ),
						'help'     => __( 'The image is functioning as a link. Its alternative text usually supplies the link name, so describing only the picture may miss the purpose. Example: “Envelope icon” describes a shape; “Contact us” describes a link that actually opens a Contact page. Inspect the full name if ARIA affects it.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'clear',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'image_context',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'image_context'     => array(
						'type'     => 'single_choice',
						'question' => __( 'Does associated context make the image link\'s purpose understandable despite its limited name?', 'elavira' ),
						'help'     => __( 'Read the containing sentence, paragraph, list item, or table context with the link. Confirm a usable link name exists and assistive technology can obtain that context. A filename or visual position alone does not explain the link.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'clear_context',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'image_change',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'mixed_image'       => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the image add information that the text inside this link does not already provide?', 'elavira' ),
						'help'     => __( 'Example: “View pricing” followed by a decorative arrow usually needs no extra image description. Empty alt="" can be correct. If the image adds words or information needed to understand the destination, that information needs an alternative.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'mixed_information',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'mixed_text',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'mixed_information' => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the combined link name include the useful text and the image\'s additional information?', 'elavira' ),
						'help'     => __( 'Check the full announced name, not just the image alt. Avoid repeating the same words unnecessarily. If the image contains meaningful words, include them in the alternative unless the link text already supplies them.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'text_name',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'mixed_change',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'mixed_text'        => array(
						'type'     => 'single_choice',
						'question' => __( 'Is the useful link text announced without a redundant image description?', 'elavira' ),
						'help'     => __( 'Check the name with a screen reader or browser accessibility panel. A decorative arrow should not add “arrow” or repeat “View pricing”. Empty alt can leave the useful text as the link name.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'text_name',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'decorative_change',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
				),
				'outcomes' => array(
					'clear'             => array(
						'interpretation'        => __( 'Your answers indicate that this link\'s name explains its purpose. A wording change may not be needed.', 'elavira' ),
						'recommendation'        => __( 'Keep the wording if it accurately matches the actual destination. Check other occurrences separately, especially when identical names lead to different places.', 'elavira' ),
						'verification_guidance' => __( 'Compare the rendered link name with its destination. Check it again after changing the link, image, or naming attributes.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'clear_context'     => array(
						'interpretation'        => __( 'Your answers indicate that the link\'s associated context explains its purpose. Short wording alone does not make it a failure.', 'elavira' ),
						'recommendation'        => __( 'You may keep the link if that context remains available to assistive technology. More descriptive link wording can still make scanning a list of links easier.', 'elavira' ),
						'verification_guidance' => __( 'Read the link and its full associated context with a screen reader. Confirm the destination is understandable without activating it first.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'text_change'       => array(
						'interpretation'        => __( 'Your answers suggest that the link and its context do not explain what it does.', 'elavira' ),
						'recommendation'        => __( 'Edit the link text in the page, menu, or template to describe the destination or action. Example: change “Learn more” to “Learn more about our services” only if that is its actual purpose. If ARIA overrides the words, have the responsible developer correct the naming source.', 'elavira' ),
						'verification_guidance' => __( 'Read the updated link in context and inspect its full announced name. Compare it with the actual destination, then re-audit for detectable naming issues.', 'elavira' ),
						'status'                => 'open',
					),
					'image_change'      => array(
						'interpretation'        => __( 'Your answers suggest that the image link\'s name does not explain its function.', 'elavira' ),
						'recommendation'        => __( 'Change the naming source so it communicates the destination or action. For a plain image-only link, edit the image alt text in the placed image or template. Example: alt="Contact us" may suit a Contact-page link; do not use it for a different action. If ARIA supplies the name, correct that source instead.', 'elavira' ),
						'verification_guidance' => __( 'Inspect the whole link\'s announced name after saving. Confirm it describes what activation does, and test the link destination. Re-auditing alone cannot approve the wording.', 'elavira' ),
						'status'                => 'open',
					),
					'image_text_change' => array(
						'interpretation'        => __( 'The alternative appears to leave out meaningful words or the purpose shown in the linked image.', 'elavira' ),
						'recommendation'        => __( 'Include the image\'s meaningful words and link purpose in the accessible alternative. For an image saying “View Fall Collection”, preserve those words if they describe the actual destination. Check whether the image alt or an ARIA naming source needs editing.', 'elavira' ),
						'verification_guidance' => __( 'Compare the visible image words with the full announced link name, then confirm the destination. Keep the alternative in sync when the image changes.', 'elavira' ),
						'status'                => 'open',
					),
					'mixed_change'      => array(
						'interpretation'        => __( 'Some useful information from the linked image appears to be missing from the link name.', 'elavira' ),
						'recommendation'        => __( 'Add the missing information to the image alternative or useful link text. Preserve meaningful visible words. Avoid repeating information the link text already supplies.', 'elavira' ),
						'verification_guidance' => __( 'Listen to or inspect the entire link name once. Confirm that the text and image information work together and communicate the destination.', 'elavira' ),
						'status'                => 'open',
					),
					'decorative_change' => array(
						'interpretation'        => __( 'The decorative image appears to add unnecessary wording to this link.', 'elavira' ),
						'recommendation'        => __( 'When useful text already supplies the purpose, give the decorative image alt="". For example, a decorative arrow after “View pricing” needs neither “arrow” nor a second “View pricing” alternative. Correct overriding ARIA separately if present.', 'elavira' ),
						'verification_guidance' => __( 'Check the full link name again. It should retain the useful text without announcing the decoration. Then confirm that the name still matches the destination.', 'elavira' ),
						'status'                => 'open',
					),
					'further_review'    => array(
						'interpretation'        => __( 'There is not enough information yet to decide whether this link needs a change.', 'elavira' ),
						'recommendation'        => __( 'Keep Needs Review while you inspect the link on the audited page. Use Technical Details to locate it. In the browser accessibility panel, select the link and read its full name and naming sources; follow any aria-labelledby references. Ask the site developer for help if those sources remain unclear.', 'elavira' ),
						'verification_guidance' => __( 'Return to these questions once you know the name, destination, and associated context. Missing recorded evidence is not proof of a missing name.', 'elavira' ),
						'status'                => 'needs_review',
					),
				),
			),
			array(
				'id'       => 'alternative_text',
				'title'    => __( 'Review Alternative Text', 'elavira' ),
				'purpose'  => __( 'Decide what this image contributes in this location and whether its alternative gives people the same useful information or function.', 'elavira' ),
				'start'    => 'image_role',
				'steps'    => array(
					'image_role'            => array(
						'type'     => 'single_choice',
						'question' => __( 'What does this image do here?', 'elavira' ),
						'help'     => __( 'Look at the image with the surrounding page. The same image may need different treatment in another location. If it is the content of a link or button, choose the functional option first.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'functional',
								'label'  => __( 'It is part of a link or button', 'elavira' ),
								'target' => 'functional_name',
							),
							array(
								'id'     => 'informative',
								'label'  => __( 'It conveys information', 'elavira' ),
								'target' => 'information_available',
							),
							array(
								'id'     => 'decorative',
								'label'  => __( 'It adds decoration only', 'elavira' ),
								'target' => 'decorative_check',
							),
							array(
								'id'     => 'complex',
								'label'  => __( 'It explains detailed data or relationships', 'elavira' ),
								'target' => 'complex_check',
							),
							array(
								'id'     => 'text',
								'label'  => __( 'It contains meaningful words', 'elavira' ),
								'target' => 'words_check',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'functional_name'       => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the complete link or button name explain its action or destination?', 'elavira' ),
						'help'     => __( 'Inspect the name announced by a screen reader or shown in the browser accessibility panel. An image of an envelope may need “Contact us” for a Contact link. Useful text in the same link can supply its name, leaving a decorative image with empty alt. Include meaningful words shown in the image.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'appropriate',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'functional',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'information_available' => array(
						'type'     => 'single_choice',
						'question' => __( 'Is the image\'s useful information already available in the alternative or nearby text?', 'elavira' ),
						'help'     => __( 'Identify what the reader needs from this image. Compare that information with its alt text and nearby explanation. A filename usually says little; a photo\'s relevant details depend on the surrounding story.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'appropriate',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'informative',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'decorative_check'      => array(
						'type'     => 'single_choice',
						'question' => __( 'Is the image intentionally given empty alt text?', 'elavira' ),
						'help'     => __( 'For an HTML image, alt="" tells assistive technology to skip decoration. A missing alt attribute is different and may cause a filename to be announced. Confirm the image adds no information and is not the only content of a link or button.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'appropriate',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'decorative',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'complex_check'         => array(
						'type'     => 'single_choice',
						'question' => __( 'Can someone obtain the important data and relationships without seeing the image?', 'elavira' ),
						'help'     => __( 'Check the short alternative and any nearby table or explanation. A chart description such as “Sales chart” does not convey the trends or values a reader needs. Long alt text alone may be difficult to navigate.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'appropriate',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'complex',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'words_check'           => array(
						'type'     => 'single_choice',
						'question' => __( 'Are the meaningful words available as text or in the image alternative?', 'elavira' ),
						'help'     => __( 'Read the words in the image and compare them with the available text. Prefer real text for headings and messages when practical; an alternative alone does not settle whether using an image of text is appropriate.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'appropriate',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'image_text',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
				),
				'outcomes' => array(
					'appropriate'    => array(
						'interpretation'        => __( 'Your answers suggest that this image\'s useful information or function is available without seeing it.', 'elavira' ),
						'recommendation'        => __( 'Keep the treatment if it matches the current context. For a missing-alt finding, still confirm that an alt attribute or other valid image treatment is actually present; nearby information alone does not repair missing markup.', 'elavira' ),
						'verification_guidance' => __( 'Check the rendered image and its announced alternative or containing control name. Re-audit detectable missing attributes, and review the wording yourself.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'functional'     => array(
						'interpretation'        => __( 'The functional image needs a clearer action or destination in the complete control name.', 'elavira' ),
						'recommendation'        => __( 'Update the image alt or the control\'s actual naming source to describe what activation does. Do not merely describe the picture. Leave decoration empty when useful text in the same control already supplies the purpose.', 'elavira' ),
						'verification_guidance' => __( 'Inspect the whole link or button name and activate it to confirm the wording matches its function.', 'elavira' ),
						'status'                => 'open',
					),
					'informative'    => array(
						'interpretation'        => __( 'Useful information in this image appears to be missing from the available text.', 'elavira' ),
						'recommendation'        => __( 'Write a concise alternative containing the details relevant to this page. In WordPress, check the placed image\'s Alternative Text setting; theme or plugin images may require a template change.', 'elavira' ),
						'verification_guidance' => __( 'Read the page without relying on the image. Confirm the alternative gives the same useful information and does not merely repeat a filename.', 'elavira' ),
						'status'                => 'open',
					),
					'decorative'     => array(
						'interpretation'        => __( 'The decorative image needs an explicit empty alternative.', 'elavira' ),
						'recommendation'        => __( 'Set alt="" on this decorative image. In WordPress, leave its alternative empty and confirm that the rendered markup contains the attribute. A theme or plugin may need correction if it omits it.', 'elavira' ),
						'verification_guidance' => __( 'Inspect the rendered markup, confirm a screen reader skips the decoration, and re-audit the missing-alt finding.', 'elavira' ),
						'status'                => 'open',
					),
					'complex'        => array(
						'interpretation'        => __( 'The image\'s detailed information needs a usable text explanation.', 'elavira' ),
						'recommendation'        => __( 'Add a nearby explanation or accessible data table for the important values and relationships. Give the image a short alternative identifying it and pointing readers to that explanation.', 'elavira' ),
						'verification_guidance' => __( 'Compare the explanation with the image\'s information. Check that the explanation is easy to find and navigate without seeing the diagram.', 'elavira' ),
						'status'                => 'open',
					),
					'image_text'     => array(
						'interpretation'        => __( 'Meaningful words in the image need an equivalent text alternative.', 'elavira' ),
						'recommendation'        => __( 'Prefer replacing the image text with real text when practical. Otherwise include the meaningful words in the alternative. If the image is linked, the alternative must also communicate that link\'s purpose.', 'elavira' ),
						'verification_guidance' => __( 'Compare the visible words with the rendered alternative and check the reading order. Separately review whether an image of text is necessary.', 'elavira' ),
						'status'                => 'open',
					),
					'further_review' => array(
						'interpretation'        => __( 'The right alternative depends on what this image contributes here.', 'elavira' ),
						'recommendation'        => __( 'Keep Needs Review and ask the content owner what information or action a reader should get from the image. Inspect its surrounding link or button before deciding it is decorative.', 'elavira' ),
						'verification_guidance' => __( 'Return when you can identify the image\'s purpose, then compare the available alternative with that purpose.', 'elavira' ),
						'status'                => 'needs_review',
					),
				),
			),
			array(
				'id'       => 'heading_structure',
				'title'    => __( 'Review Heading Structure', 'elavira' ),
				'purpose'  => __( 'Use the page outline to decide whether headings help readers find sections and understand how those sections fit together.', 'elavira' ),
				'start'    => 'sections',
				'steps'    => array(
					'sections'  => array(
						'type'     => 'single_choice',
						'question' => __( 'Do the headings name the sections they introduce?', 'elavira' ),
						'help'     => __( 'Read each heading and the content beneath it. Empty headings and headings used only to make text large do not help someone navigate the page.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'hierarchy',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_sections',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'hierarchy' => array(
						'type'     => 'single_choice',
						'question' => __( 'Do heading levels match the relationships between sections?', 'elavira' ),
						'help'     => __( 'View the page\'s heading list in a browser inspection tool or screen reader. H2 sections may contain H3 subsections. Returning from H4 to H2 can be appropriate when a new major section begins. A skipped level is a reason to inspect the outline, not an automatic failure.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'primary',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_hierarchy',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'primary'   => array(
						'type'     => 'single_choice',
						'question' => __( 'Can a reader identify the page\'s main topic and its major sections from the outline?', 'elavira' ),
						'help'     => __( 'Elavira recommends a clear primary page heading, commonly an H1. WCAG and HTML do not require exactly one H1. Consider the meaning of missing or multiple H1s in this particular document.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'markup',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_hierarchy',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'markup'    => array(
						'type'     => 'single_choice',
						'question' => __( 'Are visual section headings also exposed as headings to assistive technology?', 'elavira' ),
						'help'     => __( 'Compare the visible page with the heading list. Bold or large text alone does not make a heading; actual H1–H6 elements usually do.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_markup',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
				),
				'outcomes' => array(
					'reasonable'     => array(
						'interpretation'        => __( 'Your answers suggest that the heading outline communicates the page organization.', 'elavira' ),
						'recommendation'        => __( 'A structure change may not be needed. Record why the reported H1 or level pattern makes sense if you retain it.', 'elavira' ),
						'verification_guidance' => __( 'Read the outline in order and navigate its headings with a screen reader. Confirm each heading brings you to the expected section.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'fix_sections'   => array(
						'interpretation'        => __( 'Some headings appear not to identify real sections.', 'elavira' ),
						'recommendation'        => __( 'Replace empty headings with meaningful section titles or remove unused heading elements. Turn text used only for decoration into ordinary text styled with CSS.', 'elavira' ),
						'verification_guidance' => __( 'Read the revised heading list and the content under each heading. Confirm every listed heading helps locate a section.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_hierarchy'  => array(
						'interpretation'        => __( 'The outline appears to leave the page\'s organization unclear.', 'elavira' ),
						'recommendation'        => __( 'Choose a clear main heading and assign levels to sections and subsections based on their relationships. In the block editor, change the Heading block level; use styling to control size separately.', 'elavira' ),
						'verification_guidance' => __( 'Compare the new outline with the actual content hierarchy. Re-audit structural warnings, then judge the outline yourself.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_markup'     => array(
						'interpretation'        => __( 'Some visual section titles appear to be missing from the heading outline.', 'elavira' ),
						'recommendation'        => __( 'Use Heading blocks or H1–H6 elements for those titles, at levels that fit the outline. A theme template may need editing if it controls them.', 'elavira' ),
						'verification_guidance' => __( 'Inspect the rendered heading list and confirm the titles are present at the intended levels.', 'elavira' ),
						'status'                => 'open',
					),
					'further_review' => array(
						'interpretation'        => __( 'The page outline needs a closer look before choosing a change.', 'elavira' ),
						'recommendation'        => __( 'Open the heading list in a screen reader or browser inspection tool. Compare it with the visible sections and ask the content owner which sections contain others.', 'elavira' ),
						'verification_guidance' => __( 'Keep Needs Review until you can explain the page hierarchy. Neither a missing H1 nor a skipped level alone settles the decision.', 'elavira' ),
						'status'                => 'needs_review',
					),
				),
			),
			array(
				'id'       => 'form_labels_instructions',
				'title'    => __( 'Review Form Labels and Instructions', 'elavira' ),
				'purpose'  => __( 'Check whether people can identify this field, know what to enter, and understand any message asking them to correct it.', 'elavira' ),
				'start'    => 'concern',
				'steps'    => array(
					'concern'            => array(
						'type'     => 'single_choice',
						'question' => __( 'Which part of this form needs checking?', 'elavira' ),
						'help'     => __( 'Use the finding summary to choose. Review one concern at a time; a clear label does not prove that required-field instructions or errors are adequate.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'label',
								'label'  => __( 'The field\'s label or announced name', 'elavira' ),
								'target' => 'name',
							),
							array(
								'id'     => 'instructions',
								'label'  => __( 'Required fields or entry instructions', 'elavira' ),
								'target' => 'instructions',
							),
							array(
								'id'     => 'errors',
								'label'  => __( 'An error message', 'elavira' ),
								'target' => 'errors',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'name'               => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the field\'s announced name identify what to enter or choose?', 'elavira' ),
						'help'     => __( 'Focus the control with a screen reader or inspect its name in the browser accessibility panel. Expect a useful name such as “Email address”, followed by the control type. Names can come from a native label, aria-labelledby, aria-label, or another valid mechanism; a missing name does not prove there is no label element.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'visible',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_name',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'visible'            => array(
						'type'     => 'single_choice',
						'question' => __( 'Does a useful visible label remain available after typing?', 'elavira' ),
						'help'     => __( 'Type into the field. Placeholder examples often disappear; the user should still know which field this is. For standard fields prefer a visible label connected to the input.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_visible',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'instructions'       => array(
						'type'     => 'single_choice',
						'question' => __( 'Can someone tell what is required before submitting?', 'elavira' ),
						'help'     => __( 'Check whether required fields, necessary formats, and special restrictions are explained before the user needs them. A colored border alone does not explain that a field is required. If no special instructions are necessary, answer Yes.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'instruction_access',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_instructions',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'instruction_access' => array(
						'type'     => 'single_choice',
						'question' => __( 'Can assistive technology obtain the necessary instructions with the relevant field?', 'elavira' ),
						'help'     => __( 'Focus the field and listen for required state and relevant help. A nearby paragraph may need a code relationship such as aria-describedby so its connection is available without sight. Do not put lengthy instructions in the field name.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_instructions',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'errors'             => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the error identify the affected field and explain what went wrong?', 'elavira' ),
						'help'     => __( 'On a test form, submit missing or invalid data without sending a real transaction. Read the error text. “There is an error” or red coloring alone does not identify what to correct.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'error_access',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_errors',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'error_access'       => array(
						'type'     => 'single_choice',
						'question' => __( 'Can someone using a screen reader find the error and the field it concerns?', 'elavira' ),
						'help'     => __( 'Trigger the error again and listen. The form should announce or move attention to useful error feedback and make the message\'s relationship to the field available. Check that entered values and useful labels remain.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_errors',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
				),
				'outcomes' => array(
					'reasonable'       => array(
						'interpretation'        => __( 'Your answers suggest that the form concern you checked is adequately explained.', 'elavira' ),
						'recommendation'        => __( 'A change may not be needed for this concern. Repeat the flow for other reported label, instruction, or error issues. If you repaired a detected missing name, record Addressed and re-audit.', 'elavira' ),
						'verification_guidance' => __( 'Focus the field, enter a value, and exercise the relevant validation state. Confirm the name, instructions, or error remain understandable.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'fix_name'         => array(
						'interpretation'        => __( 'The control\'s announced name appears to be missing or unclear.', 'elavira' ),
						'recommendation'        => __( 'For a standard field, add a useful visible label and connect its for attribute to the input\'s unique id, or wrap the input in the label. Other valid naming techniques may suit a custom control. Correct the actual naming source rather than adding conflicting ARIA.', 'elavira' ),
						'verification_guidance' => __( 'Inspect the rendered name or listen with a screen reader. Confirm the field purpose is announced, then re-audit detectable missing-name issues.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_visible'      => array(
						'interpretation'        => __( 'The field appears to lose its useful visible label during entry.', 'elavira' ),
						'recommendation'        => __( 'Add a persistent visible label in the form editor or template and connect it to the control. Keep placeholder text as an optional example, not the only explanation.', 'elavira' ),
						'verification_guidance' => __( 'Type into and leave the field. Confirm its purpose stays visible and its announced name matches the visible wording.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_instructions' => array(
						'interpretation'        => __( 'Necessary entry instructions appear to be missing or disconnected from the field.', 'elavira' ),
						'recommendation'        => __( 'Explain required entries and necessary formats in visible text before submission. Use native required state where appropriate and connect extra help to the field when needed. Form configuration may handle this; otherwise ask the form or theme developer.', 'elavira' ),
						'verification_guidance' => __( 'Try the form without prior knowledge of its rules and with a screen reader. Confirm users hear the relevant help and can enter valid information.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_errors'       => array(
						'interpretation'        => __( 'The error feedback appears not to guide someone to a usable correction.', 'elavira' ),
						'recommendation'        => __( 'Name the field and describe the error in text. Give a correction suggestion when known and appropriate, such as the required date format. Connect the error to the field and make new feedback discoverable to a screen reader.', 'elavira' ),
						'verification_guidance' => __( 'Trigger the same invalid entry in a safe test. Confirm someone can locate the error, understand it, correct the value, and submit successfully.', 'elavira' ),
						'status'                => 'open',
					),
					'further_review'   => array(
						'interpretation'        => __( 'More inspection is needed before deciding what to change in this form.', 'elavira' ),
						'recommendation'        => __( 'Use the finding to locate the control. Ask the form developer to show its announced name, required instructions, and error relationship in the rendered form. Do not assume a placeholder or label tag alone settles accessibility.', 'elavira' ),
						'verification_guidance' => __( 'Keep Needs Review and return after testing the specific label, instruction, or error concern.', 'elavira' ),
						'status'                => 'needs_review',
					),
				),
			),
			array(
				'id'       => 'use_of_color',
				'title'    => __( 'Review Use of Color', 'elavira' ),
				'purpose'  => __( 'Decide whether the information remains understandable for someone who cannot distinguish the colors used.', 'elavira' ),
				'start'    => 'meaning',
				'steps'    => array(
					'meaning'    => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the color communicate information or identify an action?', 'elavira' ),
						'help'     => __( 'Look for required fields, error states, selected items, chart categories, or links distinguished from surrounding text. Color used only as decoration does not communicate a message.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'cue',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'cue'        => array(
						'type'     => 'single_choice',
						'question' => __( 'Is the same meaning clear from a visible cue other than color?', 'elavira' ),
						'help'     => __( 'Look for words, distinct shapes, patterns, or underlines. For an error, visible text can explain the problem. An accessible name hidden from sight alone does not help a sighted person who cannot distinguish colors.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'understand',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'understand' => array(
						'type'     => 'single_choice',
						'question' => __( 'Can someone interpret that cue without guessing the color?', 'elavira' ),
						'help'     => __( 'Read the labels or legend and inspect each state. Different symbols need understandable meanings; a tiny unexplained icon may not communicate the same message.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
				),
				'outcomes' => array(
					'reasonable'     => array(
						'interpretation'        => __( 'Your answers suggest that this meaning does not depend on color alone.', 'elavira' ),
						'recommendation'        => __( 'Keep the non-color explanation available in every relevant state. Contrast is a separate check; this decision does not measure it.', 'elavira' ),
						'verification_guidance' => __( 'View the component without distinguishing its colors and explain its meaning using the remaining cues. Check errors, selections, and chart legends as applicable.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'fix'            => array(
						'interpretation'        => __( 'Color appears to carry meaning that another visible cue does not explain.', 'elavira' ),
						'recommendation'        => __( 'Add a clear non-color cue: error text beside a field, an underline for a link, a selected-state label, or labeled patterns in a chart. Choose a cue that communicates this particular message.', 'elavira' ),
						'verification_guidance' => __( 'Repeat the task without relying on color. Confirm the new cue is understandable and visible, then check its contrast separately.', 'elavira' ),
						'status'                => 'open',
					),
					'further_review' => array(
						'interpretation'        => __( 'It is not yet clear whether the colors carry essential meaning.', 'elavira' ),
						'recommendation'        => __( 'Ask what each color tells the reader. Temporarily viewing a grayscale version can help expose dependencies, but still inspect whether labels and symbols explain the same message.', 'elavira' ),
						'verification_guidance' => __( 'Keep Needs Review until you can identify the meaning and an understandable non-color cue.', 'elavira' ),
						'status'                => 'needs_review',
					),
				),
			),
			array(
				'id'       => 'keyboard_interaction',
				'title'    => __( 'Review Keyboard Interaction', 'elavira' ),
				'purpose'  => __( 'Test the reported interaction using a keyboard. Reaching a control is only the first step: people also need to use it and leave it.', 'elavira' ),
				'start'    => 'reachable',
				'steps'    => array(
					'reachable' => array(
						'type'     => 'single_choice',
						'question' => __( 'Can you reach the reported control using its expected keyboard navigation?', 'elavira' ),
						'help'     => __( 'Start before the control and use Tab and Shift+Tab. Inside menus, tabs, or other composite widgets, arrow keys may move between items instead. Confirm the documented pattern rather than expecting every item to be a separate Tab stop.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'operable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_reach',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'operable'  => array(
						'type'     => 'single_choice',
						'question' => __( 'Can you perform the control\'s actions using the expected keys?', 'elavira' ),
						'help'     => __( 'Use Enter for a link; Enter or Space for a native button; Space for a checkbox. Menus, tabs, sliders, and other custom widgets need their own appropriate arrow-key and activation behavior. Try each action the mouse can perform, except functions inherently dependent on a movement path.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'leave',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_operate',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'leave'     => array(
						'type'     => 'single_choice',
						'question' => __( 'Can you leave the interaction using a predictable keyboard action?', 'elavira' ),
						'help'     => __( 'Try Tab and Shift+Tab. A modal dialog may correctly keep focus inside while open, but it needs a keyboard-operable close or completion action; Escape is customary for dismissible dialogs. Confirm focus returns to a sensible place after closing.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'order',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_leave',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'order'     => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the focus order let you complete the task without losing your place?', 'elavira' ),
						'help'     => __( 'Move forward and backward through the interaction. Check that related controls come in a meaningful sequence. For dialogs, check initial focus, containment while modal, and focus after closing. A positive tabindex is a risk pattern, not proof of failure.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_order',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
				),
				'outcomes' => array(
					'reasonable'     => array(
						'interpretation'        => __( 'Your keyboard test suggests that the reported interaction can be reached, used, and left in a meaningful order.', 'elavira' ),
						'recommendation'        => __( 'A keyboard change may not be needed for the states you tested. Also check visible focus and any overlays that might hide it.', 'elavira' ),
						'verification_guidance' => __( 'Repeat the full interaction after changes, including opening, operating, and closing custom widgets. A later audit cannot replace this manual behavior test.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'fix_reach'      => array(
						'interpretation'        => __( 'Your test suggests that a needed control cannot be reached by keyboard.', 'elavira' ),
						'recommendation'        => __( 'Prefer a native button for actions or a link with href for navigation. For a custom widget, implement the appropriate focus pattern. Adding tabindex alone does not provide keyboard operation.', 'elavira' ),
						'verification_guidance' => __( 'Start before the component and reach all of its functions with the expected navigation keys, then test activation and exit.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_operate'    => array(
						'interpretation'        => __( 'Your test suggests that a reachable control cannot perform all its required actions by keyboard.', 'elavira' ),
						'recommendation'        => __( 'Use native controls where practical. Otherwise implement the widget\'s expected activation and navigation keys in its code; a click handler or ARIA role alone does not add that behavior.', 'elavira' ),
						'verification_guidance' => __( 'Perform each relevant action without a mouse, including state changes, and confirm the announced state follows the visible result.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_leave'      => array(
						'interpretation'        => __( 'Your test suggests that someone could become stuck in this interaction.', 'elavira' ),
						'recommendation'        => __( 'Provide a keyboard-operable way to close or complete it and move focus to a sensible place. For a modal, keep background controls out of its focus sequence while open, then restore focus appropriately on close.', 'elavira' ),
						'verification_guidance' => __( 'Enter the interaction, operate it, and leave it in both navigation directions. Test its close action and Escape where supported.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_order'      => array(
						'interpretation'        => __( 'Your test suggests that focus order makes the task difficult to follow.', 'elavira' ),
						'recommendation'        => __( 'Put controls in a meaningful document order and remove positive tabindex values where native order works. For dialogs, set sensible initial focus and return focus to the opener or another logical control on close.', 'elavira' ),
						'verification_guidance' => __( 'Repeat the task with Tab, Shift+Tab, and widget navigation keys. Confirm focus stays in a predictable sequence through opening and closing states.', 'elavira' ),
						'status'                => 'open',
					),
					'further_review' => array(
						'interpretation'        => __( 'The keyboard behavior needs more testing before choosing a change.', 'elavira' ),
						'recommendation'        => __( 'Identify the control type and its expected keys with the developer. Test its complete task, including leaving or closing it. A suspicious DOM pattern does not establish a keyboard failure.', 'elavira' ),
						'verification_guidance' => __( 'Keep Needs Review and return after you can reproduce the expected interaction without a mouse.', 'elavira' ),
						'status'                => 'needs_review',
					),
				),
			),
			array(
				'id'       => 'audio_video_alternatives',
				'title'    => __( 'Review Audio and Video Alternatives', 'elavira' ),
				'purpose'  => __( 'Review alternatives for prerecorded media. This educational flow does not create a scanner finding or cover live broadcasts.', 'elavira' ),
				'start'    => 'type',
				'steps'    => array(
					'type'        => array(
						'type'     => 'single_choice',
						'question' => __( 'What kind of prerecorded content are you reviewing?', 'elavira' ),
						'help'     => __( 'Play the content and identify whether it contains sound, moving images, or both. If it is live, choose the last option for further guidance.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'audio',
								'label'  => __( 'Audio only', 'elavira' ),
								'target' => 'transcript',
							),
							array(
								'id'     => 'video',
								'label'  => __( 'Silent video', 'elavira' ),
								'target' => 'silent',
							),
							array(
								'id'     => 'both',
								'label'  => __( 'Video with audio', 'elavira' ),
								'target' => 'captions',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'Live media or I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'transcript'  => array(
						'type'     => 'single_choice',
						'question' => __( 'Is the speech and other meaningful sound available in an accurate transcript?', 'elavira' ),
						'help'     => __( 'Compare the transcript with the recording. Include speaker identification and sounds needed to understand the content, not only spoken words.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_transcript',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'silent'      => array(
						'type'     => 'single_choice',
						'question' => __( 'Is the meaningful visual information available in a text description or audio track?', 'elavira' ),
						'help'     => __( 'Without watching the silent video, could someone obtain the same information and follow the sequence from its alternative? Include important actions and on-screen words.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_silent',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'captions'    => array(
						'type'     => 'single_choice',
						'question' => __( 'Are accurate captions synchronized with the meaningful speech and sounds?', 'elavira' ),
						'help'     => __( 'Watch with sound off. Check timing, speaker changes, important sounds, and whether captions obscure essential content. Automatically generated captions need human checking.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'visuals',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_captions',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'visuals'     => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the video contain important information that the existing audio does not explain?', 'elavira' ),
						'help'     => __( 'Listen without watching. Consider demonstrations, scene changes, charts, and on-screen words. If the soundtrack already conveys all important visual information, extra description may be unnecessary.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'description',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'description' => array(
						'type'     => 'single_choice',
						'question' => __( 'Does an available audio-described version explain that missing visual information?', 'elavira' ),
						'help'     => __( 'At the AA target, prerecorded video with audio needs audio description of important visuals absent from its existing soundtrack. A transcript alone does not replace this description requirement.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_description',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
				),
				'outcomes' => array(
					'reasonable'      => array(
						'interpretation'        => __( 'Your answers suggest that the reviewed media alternatives cover its meaningful information.', 'elavira' ),
						'recommendation'        => __( 'Keep those alternatives available beside or within the media player. This does not evaluate the player\'s controls or every media requirement.', 'elavira' ),
						'verification_guidance' => __( 'Compare the entire recording with the alternative, test caption or description selection by keyboard, and verify that the alternative stays accurate after edits.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'fix_transcript'  => array(
						'interpretation'        => __( 'The audio recording needs a more complete text alternative.', 'elavira' ),
						'recommendation'        => __( 'Publish an accurate transcript near the recording, including necessary speaker identification and meaningful sounds.', 'elavira' ),
						'verification_guidance' => __( 'Read the transcript while listening and correct omissions. Confirm the transcript is easy to find without operating the player.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_silent'      => array(
						'interpretation'        => __( 'The silent video\'s information needs an equivalent alternative.', 'elavira' ),
						'recommendation'        => __( 'Add an accessible text description or audio track explaining the meaningful visuals in sequence, and make it easy to find from the video.', 'elavira' ),
						'verification_guidance' => __( 'Compare the full video with the alternative and confirm all essential actions and visible words are conveyed.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_captions'    => array(
						'interpretation'        => __( 'The video needs usable captions for its meaningful audio.', 'elavira' ),
						'recommendation'        => __( 'Add or correct synchronized captions, including essential sounds and speaker changes. A separate transcript alone is not synchronized access. Then return to check visual information.', 'elavira' ),
						'verification_guidance' => __( 'Watch the entire video with sound off and captions on. Check accuracy, timing, and whether caption controls work by keyboard.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_description' => array(
						'interpretation'        => __( 'Important visual information appears to need audio description.', 'elavira' ),
						'recommendation'        => __( 'Provide a described version or description track that explains the missing visual details in suitable gaps. If needed, revise the presentation so those details can be heard clearly.', 'elavira' ),
						'verification_guidance' => __( 'Listen without watching and confirm the description supplies the information needed to understand the video. Test access to the described version.', 'elavira' ),
						'status'                => 'open',
					),
					'further_review'  => array(
						'interpretation'        => __( 'The media type or required alternative needs further review.', 'elavira' ),
						'recommendation'        => __( 'Identify whether the content is prerecorded or live and what information sound and visuals each convey. Use the Audio and Video Learn topic or ask a media accessibility specialist about live content and exceptions.', 'elavira' ),
						'verification_guidance' => __( 'Do not record the content as acceptable based on uncertainty. Return when the media type and available alternatives are clear.', 'elavira' ),
						'status'                => 'needs_review',
					),
				),
			),
			array(
				'id'           => 'focus_visibility',
				'title'        => __( 'Review Focus Visibility and Overlays', 'elavira' ),
				'purpose'      => __( 'Check whether someone using a keyboard can see their current location, including when sticky headers, banners, or dialogs are present.', 'elavira' ),
				'rules'        => array(
					array(
						'id'      => 'elavira.focus.possible_hidden_focus',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.focus.obscured_review',
						'version' => '1.0.0',
					),
				),
				'learn_topics' => array(
					'focus_focus_order',
				),
				'start'        => 'visible',
				'steps'        => array(
					'visible' => array(
						'type'     => 'single_choice',
						'question' => __( 'Can you see which control has keyboard focus?', 'elavira' ),
						'help'     => __( 'Use Tab and Shift+Tab to reach the reported area. Look for an outline, underline, background change, or another clear indicator. Check controls after opening menus or dialogs as well.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'covered',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_indicator',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'covered' => array(
						'type'     => 'single_choice',
						'question' => __( 'Does page content ever completely cover the focused control?', 'elavira' ),
						'help'     => __( 'Move focus forward and backward past sticky headers, cookie banners, and overlays. Repeat at a narrow width and with zoom. Check the control itself, not only its outline. Fully hidden focus is the minimum obscuring concern; partial covering can still make use difficult.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reveal',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'reveal'  => array(
						'type'     => 'single_choice',
						'question' => __( 'Can you reveal the focused control with the keyboard without moving focus away?', 'elavira' ),
						'help'     => __( 'Try scrolling while the same control keeps focus. For content you opened, such as a banner or panel, check whether you can reveal the focused item without advancing to another control. Record the exact overlay state and action; exceptions need context.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'context',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_cover',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
				),
				'outcomes'     => array(
					'reasonable'     => array(
						'interpretation'        => __( 'Your test suggests that focus is visible and the control is not completely hidden in the states checked.', 'elavira' ),
						'recommendation'        => __( 'No change may be needed for the reported pattern. Aim to keep the whole control visible where practical; this is more usable than leaving only a small part visible.', 'elavira' ),
						'verification_guidance' => __( 'Repeat at narrow widths and zoom, with overlays open and closed, in both focus directions. Do not treat this as a complete focus-appearance assessment.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'context'        => array(
						'interpretation'        => __( 'The focused control can be revealed without moving focus, but the overlay\'s behavior needs a contextual decision.', 'elavira' ),
						'recommendation'        => __( 'Record how the overlay was opened and how you revealed the control. User-opened or movable content has specific exceptions; do not assume that every scrollable obstruction is acceptable. Keeping the control visible from the start is usually easier to use.', 'elavira' ),
						'verification_guidance' => __( 'Repeat the exact keyboard steps and consult the Focus Learn guidance or a specialist before recording this as not an issue.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'fix_indicator'  => array(
						'interpretation'        => __( 'Your test suggests that the current keyboard location is not visibly identifiable.', 'elavira' ),
						'recommendation'        => __( 'Restore the browser\'s focus styling or add a clear focus indicator in the theme or component CSS. Do not remove outlines without a usable replacement. Check every interactive state.', 'elavira' ),
						'verification_guidance' => __( 'Tab through the affected controls on their actual backgrounds and confirm you can identify focus throughout the interaction.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_cover'      => array(
						'interpretation'        => __( 'Your test suggests that page content hides the focused control and it cannot be revealed without moving focus.', 'elavira' ),
						'recommendation'        => __( 'Adjust the overlay size or positioning, reserve space for sticky content, or correct focus and scroll handling in the theme or component. Choose a fix that keeps the focused control visible without requiring a mouse.', 'elavira' ),
						'verification_guidance' => __( 'Repeat the original steps at narrow widths and zoom with each overlay state. Confirm focused controls remain visible and the page still scrolls and operates normally.', 'elavira' ),
						'status'                => 'open',
					),
					'further_review' => array(
						'interpretation'        => __( 'More keyboard testing is needed to judge focus visibility here.', 'elavira' ),
						'recommendation'        => __( 'Use the finding\'s target to locate the area. Record which control receives focus, which overlay is present, and whether the indicator or the whole control disappears. Ask the developer to reproduce the same steps.', 'elavira' ),
						'verification_guidance' => __( 'Keep Needs Review until you can repeat the observation at the relevant width, zoom, and overlay state.', 'elavira' ),
						'status'                => 'needs_review',
					),
				),
			),
			array(
				'id'           => 'aria_behavior',
				'title'        => __( 'Review Control Roles and States', 'elavira' ),
				'purpose'      => __( 'Compare what this control does with what assistive technology is told about it. ARIA supplies accessibility information; it does not add the behavior itself.', 'elavira' ),
				'rules'        => array(
					array(
						'id'      => 'elavira.semantics.state_consistency_review',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.semantics.native_semantics_overridden',
						'version' => '1.0.0',
					),
					array(
						'id'      => 'elavira.aria.prohibited_attribute',
						'version' => '1.0.0',
					),
				),
				'learn_topics' => array(
					'aria_accessible_controls',
					'keyboard_accessibility',
				),
				'start'        => 'native',
				'steps'        => array(
					'native'         => array(
						'type'     => 'single_choice',
						'question' => __( 'Can a native HTML control provide this interaction?', 'elavira' ),
						'help'     => __( 'Identify the actual action. A button performs an action; a link navigates; a checkbox toggles a choice. A native element often supplies its role, states, and keyboard behavior without extra ARIA.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'native_correct',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'role',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'native_correct' => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the rendered native control keep its appropriate built-in meaning?', 'elavira' ),
						'help'     => __( 'Inspect the browser accessibility panel. Check for a role or ARIA attribute that changes or conflicts with the control\'s actual purpose. Merely repeating a native role may be unnecessary without causing a user-facing failure.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'states',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_native',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'role'           => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the announced control type match its actual behavior?', 'elavira' ),
						'help'     => __( 'Inspect the role in the browser accessibility panel or listen with a screen reader. A control announced as a checkbox should behave like a checkbox, not a navigation link. For a custom widget, compare its documented interaction pattern.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'states',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_role',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'states'         => array(
						'type'     => 'single_choice',
						'question' => __( 'Do announced states match what changes on screen?', 'elavira' ),
						'help'     => __( 'Operate the control. Compare checked, selected, expanded, pressed, or disabled information with the actual UI as applicable. For example, a disclosure button should report expanded when its panel opens. If no state applies, answer Yes.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'keyboard',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_state',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'keyboard'       => array(
						'type'     => 'single_choice',
						'question' => __( 'Can the control be operated with the keys expected for that type?', 'elavira' ),
						'help'     => __( 'Try Enter or Space for a button, Space for a checkbox, and the appropriate arrows for a composite widget. ARIA cannot repair a missing keyboard handler.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix_keyboard',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
				),
				'outcomes'     => array(
					'reasonable'     => array(
						'interpretation'        => __( 'Your checks suggest that the control\'s exposed meaning agrees with its behavior.', 'elavira' ),
						'recommendation'        => __( 'No behavior change may be needed for what you tested. For a prohibited-attribute finding, still remove or correct the invalid combination; whether it creates an accessibility barrier depends on the resulting behavior.', 'elavira' ),
						'verification_guidance' => __( 'Repeat the interaction and inspect the role and state after each change. Re-audit diagnostics without treating a clean audit as proof of full usability.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'fix_native'     => array(
						'interpretation'        => __( 'An override appears to conflict with the native control\'s purpose.', 'elavira' ),
						'recommendation'        => __( 'Remove the conflicting role or ARIA attribute when native HTML already provides the required meaning. If the element is the wrong control for the action, choose the appropriate native element.', 'elavira' ),
						'verification_guidance' => __( 'Inspect the resulting role and operate the control by keyboard. Confirm its announced state follows its visible behavior.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_role'       => array(
						'interpretation'        => __( 'The announced control type appears to misrepresent its action.', 'elavira' ),
						'recommendation'        => __( 'Prefer the correct native element. If a custom widget is necessary, implement its complete role and interaction pattern; do not add a role simply to silence the report.', 'elavira' ),
						'verification_guidance' => __( 'Check the browser accessibility panel and screen-reader announcement, then operate every action using that control type\'s expected keys.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_state'      => array(
						'interpretation'        => __( 'The state exposed to assistive technology appears to disagree with the interface.', 'elavira' ),
						'recommendation'        => __( 'Update the relevant state whenever the UI changes, or rely on native state where possible. Check initialization as well as later events. Remove properties that are not supported by the actual role.', 'elavira' ),
						'verification_guidance' => __( 'Toggle or expand the control repeatedly. Confirm the visible and announced states agree before, during, and after interaction.', 'elavira' ),
						'status'                => 'open',
					),
					'fix_keyboard'   => array(
						'interpretation'        => __( 'The control\'s declared meaning does not yet have the expected keyboard behavior.', 'elavira' ),
						'recommendation'        => __( 'Use native HTML when practical, or implement the custom widget\'s full keyboard pattern. Continue with the Keyboard Interaction guidance for reachability, activation, and exit.', 'elavira' ),
						'verification_guidance' => __( 'Complete the interaction without a mouse and confirm state announcements stay accurate.', 'elavira' ),
						'status'                => 'open',
					),
					'further_review' => array(
						'interpretation'        => __( 'The role or state needs closer inspection before deciding what to change.', 'elavira' ),
						'recommendation'        => __( 'Ask the component developer to compare the browser accessibility panel with the rendered interaction. Identify the intended control type and follow state changes. Invalid ARIA alone does not establish a WCAG failure.', 'elavira' ),
						'verification_guidance' => __( 'Keep Needs Review until you know whether the exposed name, role, state, and keyboard behavior match the actual control.', 'elavira' ),
						'status'                => 'needs_review',
					),
				),
			),
			array(
				'id'           => 'contrast_review',
				'title'        => __( 'Review Uncertain Contrast', 'elavira' ),
				'purpose'      => __( 'Decide what needs measuring when Elavira cannot reliably calculate contrast. No ratio is inferred from an uncertain background.', 'elavira' ),
				'rules'        => array(
					array(
						'id'      => 'elavira.contrast.cannot_determine',
						'version' => '1.0.0',
					),
				),
				'learn_topics' => array(
					'color_contrast',
				),
				'start'        => 'type',
				'steps'        => array(
					'type'             => array(
						'type'     => 'single_choice',
						'question' => __( 'What needs to be distinguished from its background here?', 'elavira' ),
						'help'     => __( 'Locate the reported content and state. Text, a control boundary, and a meaningful graphic can need different comparisons. Inspect the recorded reason for uncertainty.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'text',
								'label'  => __( 'Text people need to read', 'elavira' ),
								'target' => 'text_applies',
							),
							array(
								'id'     => 'non_text',
								'label'  => __( 'A control or meaningful graphic', 'elavira' ),
								'target' => 'non_text_applies',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'text_applies'     => array(
						'type'     => 'single_choice',
						'question' => __( 'Is this ordinary content text rather than an exempt case?', 'elavira' ),
						'help'     => __( 'Text contrast exceptions include logos, incidental text, and text in inactive controls. Do not treat a label as exempt merely because it is small or secondary. If you cannot establish an exception, choose I\'m not sure.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'large',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'exception',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'large'            => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the text qualify as large text for the contrast threshold?', 'elavira' ),
						'help'     => __( 'Check the rendered size and weight. Large text is at least 24 CSS pixels, or about 18.67 CSS pixels when bold. Smaller text needs the normal-text threshold.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'measure_large',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'measure_text',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'non_text_applies' => array(
						'type'     => 'single_choice',
						'question' => __( 'Is this visual part needed to identify the control, its state, or meaningful information?', 'elavira' ),
						'help'     => __( 'Consider whether the shape or boundary is necessary to recognize the control or understand a graphic. Decorative parts and inactive controls are exempt; unmodified browser controls have an exception. Do not assume every border needs measuring.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'measure_non_text',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'exception',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'measure_text'     => array(
						'type'     => 'single_choice',
						'question' => __( 'Does a reliable measurement reach at least 4.5:1 in every applicable state?', 'elavira' ),
						'help'     => __( 'Use a contrast checker with the effective rendered text and background colors. For a photograph or gradient, check the weakest contrast behind the text. Avoid sampling anti-aliased text edges. If transparency or layering prevents a reliable comparison, choose I\'m not sure.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'measure_large'    => array(
						'type'     => 'single_choice',
						'question' => __( 'Does a reliable measurement reach at least 3:1 in every applicable state?', 'elavira' ),
						'help'     => __( 'Use the effective rendered foreground and background colors. Check the weakest area behind the large text and its hover or focus states as relevant. If compositing prevents a trustworthy measurement, choose I\'m not sure.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
					'measure_non_text' => array(
						'type'     => 'single_choice',
						'question' => __( 'Does the necessary visual part reach at least 3:1 against adjacent colors?', 'elavira' ),
						'help'     => __( 'Measure the colors needed to identify the component, state, or graphic, including relevant focus or selected states. Which boundary matters depends on the design. If you cannot identify a reliable applicable comparison, choose I\'m not sure.', 'elavira' ),
						'options'  => array(
							array(
								'id'     => 'yes',
								'label'  => __( 'Yes', 'elavira' ),
								'target' => 'reasonable',
							),
							array(
								'id'     => 'no',
								'label'  => __( 'No', 'elavira' ),
								'target' => 'fix',
							),
							array(
								'id'     => 'uncertain',
								'label'  => __( 'I\'m not sure', 'elavira' ),
								'target' => 'further_review',
							),
						),
					),
				),
				'outcomes'     => array(
					'reasonable'     => array(
						'interpretation'        => __( 'Your measurements suggest that this comparison meets the applicable threshold in the states tested.', 'elavira' ),
						'recommendation'        => __( 'A color change may not be needed for that comparison. Record the measured colors, ratio, content type, and tested states in your review notes.', 'elavira' ),
						'verification_guidance' => __( 'Repeat the measurement after background, type size, theme, or state-style changes. This manual result does not turn Elavira\'s uncertain measurement into an automated pass.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'exception'      => array(
						'interpretation'        => __( 'Your answer suggests that this particular contrast requirement may not apply to the selected content.', 'elavira' ),
						'recommendation'        => __( 'Document which exception or nonessential visual part you identified and why it applies. Do not exempt nearby content automatically; necessary text and controls still need their own checks.', 'elavira' ),
						'verification_guidance' => __( 'Recheck the actual function and state with the applicable contrast guidance. If the exception is uncertain, retain Needs Review rather than assuming acceptance.', 'elavira' ),
						'status'                => 'needs_review',
					),
					'fix'            => array(
						'interpretation'        => __( 'Your measurement suggests that the necessary content does not have enough contrast.', 'elavira' ),
						'recommendation'        => __( 'Choose a darker foreground, lighter background, or another pair that meets the applicable threshold. For text over an image, a solid backing may provide reliable separation. Adjust the responsible theme or component styles, including affected states.', 'elavira' ),
						'verification_guidance' => __( 'Measure the rendered result again using the correct threshold and weakest background area. Confirm the change also works in relevant interactive states.', 'elavira' ),
						'status'                => 'open',
					),
					'further_review' => array(
						'interpretation'        => __( 'There is not yet a reliable basis for a contrast decision.', 'elavira' ),
						'recommendation'        => __( 'Keep Needs Review. Identify the essential content and inspect the effective colors, background layers, opacity, and interactive state with the developer. Use a contrast measurement tool or specialist when complex backgrounds prevent a trustworthy comparison.', 'elavira' ),
						'verification_guidance' => __( 'Return when you have a reliable applicable measurement or a well-supported exception. Do not guess a ratio or treat a visual impression as a measurement.', 'elavira' ),
						'status'                => 'needs_review',
					),
				),
			),
		);
	}
}
