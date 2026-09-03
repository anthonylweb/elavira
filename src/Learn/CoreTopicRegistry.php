<?php
/**
 * Bundled Core Learn library.
 *
 * @package Elavira
 */

namespace Elavira\Learn;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, WordPress.Arrays.ArrayDeclarationSpacing -- Compact product-authored definitions are intentional.

/** Builds the initial product-authored Learn topics. */
final class CoreTopicRegistry {
	/** Build and validate the registry. @return TopicRegistry */
	public static function create() {
		$registry = new TopicRegistry();
		foreach ( self::topics() as $topic ) {
			$registry->register( new TopicDefinition( $topic ) );
		}
		foreach ( $registry->all() as $topic ) {
			foreach ( $topic->get( 'related_topics' ) as $related ) {
				if ( ! $registry->find( $related ) ) {
					throw new \InvalidArgumentException( 'Unknown related Learn topic.' );
				}
			}
		}
		return $registry;
	}

	/** Topic definitions. @return array[] */
	private static function topics() {
		return array_merge(
			array(
				self::topic(
					'images_alternative_text',
					__( 'Images and alternative text', 'elavira' ),
					__( 'Images', 'elavira' ),
					'perceivable',
					array(
						array(
							'criterion' => '1.1.1',
							'level'     => 'A',
						),
					),
					__( 'Text alternatives communicate an image’s purpose when it cannot be seen.', 'elavira' ),
					__( 'People who are blind, have low vision, turn images off, or encounter a failed image may rely on the alternative.', 'elavira' ),
					__( 'Informative images have concise equivalent text. Decorative images use an empty alternative attribute. Complex and functional images need treatment appropriate to their purpose.', 'elavira' ),
					__( 'Missing alternatives, repeating nearby captions, using filenames, and describing decorative detail are common mistakes.', 'elavira' ),
					__( 'Use the Alternative Text field in the Image block or Media Library. Decide the image’s purpose in its page context; do not assume Media Library text is right everywhere the image appears.', 'elavira' ),
					array( __( 'Use a native `alt` attribute on `img`. Use `alt=""` for decorative images; do not remove the attribute. A linked or button image needs a name that describes the action.', 'elavira' ) ),
					array( 'elavira.image.alt_missing' ),
					array( 'forms_labels', 'links_navigation' ),
					array( 'images', 'alternative text', 'non-text content', 'alt' )
				),
				self::topic(
					'forms_labels',
					__( 'Forms, labels, and accessible names', 'elavira' ),
					__( 'Forms', 'elavira' ),
					'robust',
					array(
						array(
							'criterion' => '4.1.2',
							'level'     => 'A',
						),
						array(
							'criterion' => '3.3.2',
							'level'     => 'A',
						),
					),
					__( 'A form control needs a programmatic name and clear instructions so people know what information or action it expects.', 'elavira' ),
					__( 'Screen-reader users, people using voice control, and people with cognitive disabilities can be blocked by unnamed or unclear controls.', 'elavira' ),
					__( 'Visible labels are associated with their controls. Required formats and errors are explained in text before or when they are needed.', 'elavira' ),
					__( 'Placeholder-only labels, unassociated text, duplicate names, and icon buttons without names are common mistakes.', 'elavira' ),
					__( 'Check the field settings in the Form block or form plugin, then inspect the rendered page. Plugin behavior varies, so confirm that the visible label remains associated with the control.', 'elavira' ),
					array( __( '<label for="email">Email</label> pairs with <input id="email" type="email">. Prefer this native relationship. Use ARIA naming only when native labeling is unsuitable, and verify the computed accessible name.', 'elavira' ) ),
					array( 'elavira.form.accessible_name_missing' ),
					array( 'images_alternative_text', 'headings_structure' ),
					array( 'forms', 'labels', 'accessible names', 'instructions' )
				),
				self::topic(
					'links_navigation',
					__( 'Link purpose and navigation', 'elavira' ),
					__( 'Navigation', 'elavira' ),
					'operable',
					array(
						array(
							'criterion' => '2.4.4',
							'level'     => 'A',
						),
					),
					__( 'A link’s purpose should be understandable from its name and relevant programmatic context.', 'elavira' ),
					__( 'Screen-reader users may browse a list of links, while people with cognitive disabilities benefit from predictable, descriptive wording.', 'elavira' ),
					__( 'Link text identifies the destination or action. Repeated links with different destinations are distinguishable where needed.', 'elavira' ),
					__( '“Click here,” empty links, raw URLs, and identical names for different destinations often require review.', 'elavira' ),
					__( 'Edit link text in blocks, Navigation, template parts, or widget settings. Review the rendered context because themes and plugins can add surrounding text or accessible names.', 'elavira' ),
					array( __( 'Use an `a` element for navigation and a `button` for actions. Ensure the computed accessible name and programmatic context communicate purpose.', 'elavira' ) ),
					array( 'elavira.link.purpose_review' ),
					array( 'headings_structure', 'images_alternative_text' ),
					array( 'links', 'navigation', 'link purpose', 'accessible name' )
				),
				self::topic(
					'headings_structure',
					__( 'Headings and page structure', 'elavira' ),
					__( 'Structure', 'elavira' ),
					'perceivable',
					array(
						array(
							'criterion' => '1.3.1',
							'level'     => 'A',
						),
						array(
							'criterion' => '2.4.6',
							'level'     => 'AA',
						),
					),
					__( 'Headings name sections and communicate how page content is organized.', 'elavira' ),
					__( 'Screen-reader users often navigate by heading, and clear structure supports readers with cognitive or attention disabilities.', 'elavira' ),
					__( 'Headings describe their sections and form a logical outline based on content hierarchy, not visual size.', 'elavira' ),
					__( 'Using bold paragraphs as headings, choosing levels for appearance, empty headings, and unexplained level jumps are common mistakes.', 'elavira' ),
					__( 'Use Heading blocks for real sections and choose their levels by structure. Also review headings introduced by templates, patterns, widgets, and plugins on the rendered page.', 'elavira' ),
					array( __( 'Use native `h1`–`h6` elements. CSS controls appearance; heading rank communicates nesting. A level jump is a review signal, not automatically a WCAG failure.', 'elavira' ) ),
					array( 'elavira.heading.structure_review' ),
					array( 'links_navigation', 'forms_labels' ),
					array( 'headings', 'structure', 'outline', 'page organization' )
				),
				self::topic(
					'language_content',
					__( 'Document language and understandable content', 'elavira' ),
					__( 'Content', 'elavira' ),
					'understandable',
					array(
						array(
							'criterion' => '3.1.1',
							'level'     => 'A',
						),
						array(
							'criterion' => '3.1.2',
							'level'     => 'AA',
						),
					),
					__( 'The page’s default human language helps software interpret and pronounce its content correctly.', 'elavira' ),
					__( 'Screen-reader users and people using translation, reading, or pronunciation tools depend on accurate language information.', 'elavira' ),
					__( 'The document declares its primary language, and passages in another language are identified when WCAG requires it.', 'elavira' ),
					__( 'A missing or incorrect document language and unmarked language changes can cause incorrect pronunciation.', 'elavira' ),
					__( 'Set the Site Language under WordPress Settings → General. The active theme should output WordPress language attributes; multilingual plugins may manage language changes differently.', 'elavira' ),
					array( __( 'Output `language_attributes()` on the root `html` element in a classic theme. Preserve valid language attributes in block-theme templates and integrations.', 'elavira' ) ),
					array( 'elavira.document.language_missing' ),
					array(),
					array( 'language', 'content', 'lang', 'pronunciation' )
				),
			),
			self::completion_topics()
		);
	}

	/** Add the explicitly required Start Here and scanner-independent topic areas. @return array[] */
	private static function completion_topics() {
		return array(
			self::start_topic(
				'understanding_wcag',
				__( 'Understanding WCAG', 'elavira' ),
				__( 'Learn what web accessibility and WCAG mean, who barriers affect, and how WCAG levels work.', 'elavira' ),
				array(
					__( 'Web accessibility means designing and maintaining websites so people with disabilities can perceive, understand, navigate, interact with, and contribute to them.', 'elavira' ),
					__( 'Barriers may affect people with visual, hearing, physical, speech, cognitive, learning, language, or neurological disabilities. Accessible design also helps people using different devices or working in difficult environments.', 'elavira' ),
					__( 'WCAG 2.2 is an international technical standard organized under Perceivable, Operable, Understandable, and Robust principles. Levels A and AA are nested requirements, not quality scores.', 'elavira' ),
					__( 'Elavira explains criteria relevant to its educational and audit scope, but its reference is not a substitute for the normative W3C standard.', 'elavira' ),
				),
				array( 'understanding_conformance', 'understanding_audit_results' ),
				array( 'accessibility', 'WCAG', 'principles', 'levels' )
			),
			self::start_topic(
				'understanding_conformance',
				__( 'Understanding conformance', 'elavira' ),
				__( 'Understand why automated findings, a WCAG target, and an organization’s conformance statement are different.', 'elavira' ),
				array(
					__( 'Conformance describes whether a complete web page satisfies all applicable requirements at a stated WCAG level. A scan result is evidence about selected detectable conditions, not a conformance determination.', 'elavira' ),
					__( 'Automated tools cannot evaluate every WCAG requirement. Context, content meaning, usability, and assistive-technology behavior often require human review.', 'elavira' ),
					__( 'Elavira keeps WCAG Target, Plugin Findings, and Organization’s Stated Conformance separate. The organization remains responsible for its public statement.', 'elavira' ),
					__( 'Accessibility is maintained over time: identify barriers, understand them, make deliberate changes, re-audit where applicable, and verify with appropriate human testing.', 'elavira' ),
				),
				array( 'understanding_wcag', 'understanding_audit_results' ),
				array( 'conformance', 'compliance', 'statement', 'automated testing' )
			),
			self::start_topic(
				'understanding_audit_results',
				__( 'Understanding audit results', 'elavira' ),
				__( 'Learn how Audits, Reports, Findings, Guided Decisions, Learn, and the Accessibility Statement support deliberate accessibility work.', 'elavira' ),
				array(
					__( 'An Audit evaluates one rendered public page with the current Core rules. Reports group evidence into Findings and distinguish detected failures, potential issues, and items needing human review.', 'elavira' ),
					__( 'A Finding explains what Elavira observed. Learn teaches the general concept, while a compatible Guided Decision can support judgment about a specific contextual question.', 'elavira' ),
					__( 'Detecting and understanding an issue do not remediate it. A person changes the relevant content, design, theme, or plugin configuration; Elavira does not make that change automatically.', 'elavira' ),
					__( 'Re-auditing can verify detectable conditions. Human testing remains necessary where automation cannot determine the result. Accepted limitations may be reviewed as advisory Accessibility Statement recommendations, but publication is always explicit.', 'elavira' ),
				),
				array( 'understanding_wcag', 'understanding_conformance' ),
				array( 'audits', 'reports', 'findings', 'verification', 'guided decisions', 'statement' )
			),
			self::topic( 'keyboard_accessibility', __( 'Keyboard accessibility', 'elavira' ), __( 'Interaction', 'elavira' ), 'operable', self::wcag( array( '2.1.1:A', '2.1.2:A' ) ), __( 'People must be able to operate interactive content with a keyboard or keyboard-equivalent input.', 'elavira' ), __( 'People who cannot use a mouse, including many screen-reader and switch-device users, depend on keyboard operation.', 'elavira' ), __( 'Every action is reachable and operable in a logical order, with no keyboard trap.', 'elavira' ), __( 'Mouse-only menus, drag-only controls, custom widgets without key handling, and focus trapped without an exit are common barriers.', 'elavira' ), __( 'Test menus, forms, blocks, dialogs, and plugin interfaces using Tab, Shift+Tab, Enter, Space, arrow keys where expected, and Escape where appropriate. Theme and plugin behavior varies.', 'elavira' ), array( __( 'Prefer native interactive elements. Custom widgets need documented keyboard behavior, managed focus where appropriate, and a way to leave every component.', 'elavira' ) ), array(), array( 'focus_focus_order', 'links_navigation' ), array( 'keyboard', 'tab', 'interaction', 'keyboard trap' ) ),
			self::topic( 'focus_focus_order', __( 'Focus and focus order', 'elavira' ), __( 'Interaction', 'elavira' ), 'operable', self::wcag( array( '2.4.3:A', '2.4.7:AA', '2.4.11:AA' ) ), __( 'Keyboard focus shows where interaction will occur and should move in a meaningful order without being obscured.', 'elavira' ), __( 'Keyboard, screen-reader, switch-device, and voice-input users rely on predictable focus.', 'elavira' ), __( 'Focus follows the reading and interaction order, remains visible, and returns predictably after temporary interfaces close.', 'elavira' ), __( 'Removing outlines, positive tabindex values, visual and DOM order mismatches, and focus hidden behind sticky content are common barriers.', 'elavira' ), __( 'Review the rendered order of blocks, navigation, template parts, dialogs, and plugin controls. CSS visual reordering and third-party overlays can change the experience.', 'elavira' ), array( __( 'Retain or strengthen `:focus-visible` styles, follow DOM order, avoid positive `tabindex`, and manage focus only when a state change makes it necessary.', 'elavira' ) ), array(), array( 'keyboard_accessibility', 'headings_structure' ), array( 'focus', 'focus order', 'focus visible', 'tabindex' ) ),
			self::topic( 'color_contrast', __( 'Color and contrast', 'elavira' ), __( 'Visual design', 'elavira' ), 'perceivable', self::wcag( array( '1.4.1:A', '1.4.3:AA', '1.4.11:AA' ) ), __( 'Information should not depend on color alone, and text and essential interface graphics need sufficient contrast.', 'elavira' ), __( 'People with low vision, color-vision differences, or display and lighting constraints may miss low-contrast or color-only information.', 'elavira' ), __( 'Text, controls, focus indicators, and meaningful graphics remain distinguishable, while status and instructions use text or another non-color cue.', 'elavira' ), __( 'Light gray text, color-only errors, low-contrast placeholders, and controls that disappear against their background are common mistakes.', 'elavira' ), __( 'Check global Styles, block colors, navigation, buttons, links, forms, and plugin output in the rendered page. Theme palettes do not guarantee every color combination meets contrast requirements.', 'elavira' ), array( __( 'Test computed foreground and background colors in every state. Do not use color as the only programmatic or visual signal.', 'elavira' ) ), array(), array( 'links_navigation', 'focus_focus_order' ), array( 'color', 'contrast', 'visual design', 'non-text contrast' ) ),
			self::topic( 'audio_video', __( 'Audio and video alternatives', 'elavira' ), __( 'Media', 'elavira' ), 'perceivable', self::wcag( array( '1.2.1:A', '1.2.2:A', '1.2.3:A', '1.2.5:AA' ) ), __( 'Time-based media may require captions, transcripts, audio description, or another alternative appropriate to its content.', 'elavira' ), __( 'People who are Deaf, hard of hearing, blind, have low vision, or cannot play media in their environment may need alternatives.', 'elavira' ), __( 'Prerecorded speech has accurate synchronized captions, important visual information is conveyed, and alternatives match the media purpose.', 'elavira' ), __( 'Auto-generated captions left unreviewed, transcripts that omit visual information, and unlabeled media controls are common barriers.', 'elavira' ), __( 'Review Video, Audio, Embed, and third-party player blocks. WordPress and media-platform capabilities vary; verify captions, transcripts, descriptions, and controls in the rendered player.', 'elavira' ), array( __( 'Provide standards-supported caption tracks and accessible controls. Ensure keyboard operation and accessible names for custom players, and test the final embedded experience.', 'elavira' ) ), array(), array( 'images_alternative_text', 'keyboard_accessibility' ), array( 'audio', 'video', 'captions', 'transcript', 'audio description' ) ),
			self::topic( 'motion_animation', __( 'Motion and animation', 'elavira' ), __( 'Visual design', 'elavira' ), 'operable', self::wcag( array( '2.2.2:A', '2.3.1:A' ) ), __( 'Moving, blinking, auto-updating, and flashing content must not prevent people from reading or safely using a page.', 'elavira' ), __( 'Motion may distract people with attention or cognitive disabilities, trigger vestibular symptoms, or create seizure risk when content flashes.', 'elavira' ), __( 'Nonessential motion can be paused or avoided, essential updates remain understandable, and content does not flash beyond safe thresholds.', 'elavira' ), __( 'Autoplay carousels without pause controls, parallax without reduction, and persistent animation used to communicate status are common mistakes.', 'elavira' ), __( 'Review Cover, Gallery, animation, slider, and plugin settings. Disable unnecessary autoplay and provide controls where required; verify theme and plugin output on the rendered page.', 'elavira' ), array( __( 'Respect `prefers-reduced-motion` for nonessential effects, provide pause controls when required, and avoid relying on animation alone to communicate information.', 'elavira' ) ), array(), array( 'keyboard_accessibility', 'focus_focus_order' ), array( 'motion', 'animation', 'flashing', 'autoplay', 'reduced motion' ) ),
		);
	}

	/** Build a Start Here topic using the same immutable content contract. @return array */
	private static function start_topic( $id, $title, $summary, array $paragraphs, array $related, array $keywords ) {
		$sections = array();
		foreach ( $paragraphs as $index => $paragraph ) {
			$headings                        = array( __( 'Overview', 'elavira' ), __( 'Why it matters', 'elavira' ), __( 'What to understand', 'elavira' ), __( 'What to do next', 'elavira' ) );
			$sections[ $headings[ $index ] ] = $paragraph;
		}
		return array( 'id' => $id, 'version' => '1.0.0', 'title' => $title, 'summary' => $summary, 'category' => __( 'Start Here', 'elavira' ), 'principle' => 'foundational', 'wcag' => array(), 'sections' => $sections, 'wordpress_guidance' => array( __( 'Use Learn alongside the rendered site, its WordPress editing screen, and Elavira reports. Make deliberate changes in the appropriate block, theme, or plugin, then verify the result.', 'elavira' ) ), 'developer_guidance' => array(), 'related_rules' => array(), 'related_topics' => $related, 'resources' => self::resources(), 'keywords' => $keywords );
	}

	/** Convert compact criterion metadata into the topic structure. @return array[] */
	private static function wcag( array $items ) {
		return array_map(
			function ( $item ) {
				list( $criterion, $level ) = explode( ':', $item, 2 );
				return array( 'criterion' => $criterion, 'level' => $level );
			},
			$items
		);
	}

	/** Shared authoritative resources. @return array[] */
	private static function resources() {
		return array( array( 'label' => __( 'W3C Web Content Accessibility Guidelines (WCAG) 2.2', 'elavira' ), 'url' => self::wcag_url() ) );
	}

	/** Return the centralized normative WCAG resource URL. @return string */
	public static function wcag_url() {
		return 'https://www.w3.org/TR/WCAG22/';
	}

	/** Build common structured fields. @return array */
	private static function topic( $id, $title, $category, $principle, array $wcag, $summary, $affected, $good, $mistakes, $wordpress, array $developer, array $rules, array $related, array $keywords ) {
		return array(
			'id'                 => $id,
			'version'            => '1.0.0',
			'title'              => $title,
			'summary'            => $summary,
			'category'           => $category,
			'principle'          => $principle,
			'wcag'               => $wcag,
			'sections'           => array(
				__( 'Why it matters', 'elavira' )       => $summary,
				__( 'Who it affects', 'elavira' )       => $affected,
				__( 'What good looks like', 'elavira' ) => $good,
				__( 'Common mistakes', 'elavira' )      => $mistakes,
				__( 'How Elavira helps', 'elavira' )    => $rules ? __( 'Elavira may identify selected detectable conditions related to this topic. A Finding is not a complete evaluation; review the rendered page and its WCAG context.', 'elavira' ) : __( 'Current Core rules do not provide a complete automated evaluation of this topic. Review it manually on the rendered page and with appropriate assistive technologies.', 'elavira' ),
			),
			'wordpress_guidance' => array( $wordpress ),
			'developer_guidance' => $developer,
			'related_rules'      => $rules,
			'related_topics'     => $related,
			'resources'          => self::resources(),
			'keywords'           => $keywords,
		);
	}
}
