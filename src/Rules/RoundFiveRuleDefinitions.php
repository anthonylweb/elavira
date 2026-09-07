<?php
/**
 * Phase 1 production ruleset Round 5 definitions.
 *
 * @package Elavira
 */

namespace Elavira\Rules;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.WP.I18n.NonSingularStringLiteralText -- Trusted compact registry copy.

use Elavira\Findings\Values\Priority;
use Elavira\Rules\Evidence\EvidenceSchema;
use Elavira\Rules\Evidence\FieldDefinition;
use Elavira\Rules\Grouping\RuleLevelGrouping;
use Elavira\Rules\Values\EvaluationType;
use Elavira\Rules\Values\FindingClassification;

/** Builds versioned color and contrast contracts. */
final class RoundFiveRuleDefinitions {
	/** Return all Round 5 definitions. @return RuleDefinition[] */
	public static function all() {
		$text       = self::fields(
			array(
				'foreground'      => array( 'string', 7, array() ),
				'background'      => array( 'string', 7, array() ),
				'ratio_milli'     => array( 'integer', 0, array() ),
				'required_milli'  => array( 'integer', 0, array( 4500 ) ),
				'text_size'       => array( 'string', 16, array( 'normal' ) ),
				'font_size_milli' => array( 'integer', 0, array() ),
				'font_weight'     => array( 'integer', 0, array() ),
				'determination'   => array( 'string', 24, array( 'opaque', 'composited_alpha' ) ),
				'rendered_state'  => array( 'string', 16, array( 'current' ) ),
			)
		);
		$large_text = self::fields(
			array_merge(
				array(),
				array(
					'foreground'      => array( 'string', 7, array() ),
					'background'      => array( 'string', 7, array() ),
					'ratio_milli'     => array( 'integer', 0, array() ),
					'required_milli'  => array( 'integer', 0, array( 3000 ) ),
					'text_size'       => array( 'string', 16, array( 'large' ) ),
					'font_size_milli' => array( 'integer', 0, array() ),
					'font_weight'     => array( 'integer', 0, array() ),
					'determination'   => array( 'string', 24, array( 'opaque', 'composited_alpha' ) ),
					'rendered_state'  => array( 'string', 16, array( 'current' ) ),
				)
			)
		);
		return array(
			self::rule( 'elavira.contrast.text_insufficient', EvaluationType::AUTOMATIC_FAILURE, Priority::HIGH, '1.4.3:AA:perceivable', 'Normal text has insufficient determinate contrast', 'Elavira established the current rendered foreground and background relationship for applicable normal text and calculated a ratio below 4.5:1.', 'People with low vision may be unable to distinguish text from its background.', 'Adjust the text or background colors so the effective ratio is at least 4.5:1, then re-audit.', $text, array( 'elavira.contrast.cannot_determine' ) ),
			self::rule( 'elavira.contrast.large_text_insufficient', EvaluationType::AUTOMATIC_FAILURE, Priority::HIGH, '1.4.3:AA:perceivable', 'Large text has insufficient determinate contrast', 'Elavira established that the current rendered text qualifies as large-scale and calculated a ratio below 3:1.', 'People with low vision may be unable to distinguish large text from its background.', 'Adjust the text or background colors so the effective ratio is at least 3:1, then re-audit.', $large_text, array( 'elavira.contrast.cannot_determine' ) ),
			self::rule(
				'elavira.contrast.non_text_insufficient',
				EvaluationType::AUTOMATIC_FAILURE,
				Priority::HIGH,
				'1.4.11:AA:perceivable',
				'Applicable non-text visual information has insufficient contrast',
				'Elavira established an applicable essential component or graphical relationship below 3:1.',
				'People with low vision may be unable to identify an essential control, state, or graphical object.',
				'Increase the applicable visual contrast to at least 3:1 without relying on an unnecessary decorative boundary.',
				self::fields(
					array(
						'foreground'      => array( 'string', 7, array() ),
						'background'      => array( 'string', 7, array() ),
						'ratio_milli'     => array( 'integer', 0, array() ),
						'required_milli'  => array( 'integer', 0, array( 3000 ) ),
						'visual_category' => array( 'string', 24, array( 'essential_component', 'graphical_object' ) ),
						'determination'   => array( 'string', 24, array( 'opaque', 'composited_alpha' ) ),
						'rendered_state'  => array( 'string', 16, array( 'current' ) ),
					)
				),
				array( 'elavira.contrast.cannot_determine' )
			),
			self::rule(
				'elavira.contrast.cannot_determine',
				EvaluationType::HUMAN_REVIEW,
				Priority::MEDIUM,
				'1.4.3:AA:perceivable',
				'Rendered contrast could not be determined reliably',
				'Elavira found a relevant current-state text or component candidate but could not establish its effective color relationship.',
				'People with low vision may encounter insufficient contrast that static calculation cannot responsibly confirm.',
				'Test the identified current and relevant alternate states with an appropriate contrast tool; resolve complex layering if testing confirms insufficient contrast.',
				self::fields(
					array(
						'candidate'      => array( 'string', 24, array( 'normal_text', 'large_text', 'non_text' ) ),
						'reason'         => array( 'string', 40, array( 'background_gradient', 'background_image', 'unresolved_transparency', 'ancestor_opacity', 'complex_layering', 'pseudo_element_interference', 'blend_or_filter', 'complex_graphic', 'state_not_evaluated' ) ),
						'required_milli' => array( 'integer', 0, array( 3000, 4500 ) ),
						'rendered_state' => array( 'string', 16, array( 'current' ) ),
					)
				),
				array()
			),
			self::rule(
				'elavira.color.color_only_review',
				EvaluationType::HUMAN_REVIEW,
				Priority::HIGH,
				'1.4.1:A:perceivable',
				'Status may rely on color alone',
				'A rendered control explicitly reports an invalid state without a bounded referenced error description or message.',
				'People who cannot perceive a color difference may miss the error or required response.',
				'Verify that visible text, meaningful iconography, pattern, shape, or another non-color cue communicates the same status.',
				self::fields(
					array(
						'meaning'        => array( 'string', 24, array( 'error_status' ) ),
						'non_color_cue'  => array( 'string', 32, array( 'no_referenced_message' ) ),
						'rendered_state' => array( 'string', 16, array( 'current' ) ),
					)
				),
				array(),
				'color_contrast',
				'use_of_color'
			),
		);
	}

	private static function fields( array $fields ) {
		$result = array();
		foreach ( $fields as $name => $definition ) {
			$type            = 'integer' === $definition[0] ? FieldDefinition::TYPE_INTEGER : FieldDefinition::TYPE_STRING;
			$result[ $name ] = new FieldDefinition( $type, true, $definition[1], $definition[2] );
		}
		return $result;
	}

	private static function rule( $id, $evaluation, $priority, $wcag, $title, $observed, $impact, $recommendation, array $evidence, array $supersedes, $learn = 'color_contrast', $decision = null ) {
		list( $criterion, $level, $principle ) = explode( ':', $wcag );
		return new RuleDefinition(
			array(
				'id'                 => $id,
				'version'            => '1.0.0',
				'editorial_version'  => '1.0.0',
				'title'              => __( $title, 'elavira' ),
				'description'        => __( $observed, 'elavira' ),
				'evaluation_type'    => $evaluation,
				'classification'     => EvaluationType::HUMAN_REVIEW === $evaluation ? FindingClassification::HUMAN_REVIEW : FindingClassification::DETECTED_FAILURE,
				'priority'           => $priority,
				'wcag'               => array( new WcagMapping( $criterion, $level, $principle ) ),
				'summary'            => __( $title . '.', 'elavira' ),
				'why_it_matters'     => __( $impact, 'elavira' ),
				'recommendation'     => __( $recommendation, 'elavira' ),
				'wordpress_guidance' => __( 'Review the responsible theme, block, Global Styles, or component styles and verify every relevant state after changes.', 'elavira' ),
				'developer_guidance' => __( 'Use WCAG relative luminance and effective composited colors; do not infer accessibility from color distance or source tokens.', 'elavira' ),
				'learn_topic'        => $learn,
				'guided_decision'    => $decision,
				'evidence_schema'    => new EvidenceSchema( $evidence, 1024 ),
				'grouping_schema'    => new EvidenceSchema( array(), 2 ),
				'grouping_strategy'  => new RuleLevelGrouping(),
				'supersedes'         => $supersedes,
			)
		);
	}
}
