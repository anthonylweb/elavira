<?php
/**
 * Phase 1 production ruleset Round 6 definitions.
 *
 * @package Elavira
 */

namespace Elavira\Rules;

// phpcs:disable Squiz.Commenting.FunctionComment -- Trusted compact registry copy.

use Elavira\Findings\Values\Priority;
use Elavira\Rules\Evidence\EvidenceSchema;
use Elavira\Rules\Evidence\FieldDefinition;
use Elavira\Rules\Grouping\RuleLevelGrouping;
use Elavira\Rules\Values\EvaluationType;
use Elavira\Rules\Values\FindingClassification;

/** Builds versioned image-review contracts. */
final class RoundSixRuleDefinitions {
	/** Return all new Round 6 definitions. @return RuleDefinition[] */
	public static function all() {
		$common = array(
			'alt_excerpt'     => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 120 ),
			'alt_fingerprint' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 8 ),
			'context'         => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 24, array( 'standalone', 'functional_link', 'functional_control', 'figure_caption' ) ),
		);

		return array(
			self::rule(
				'elavira.images.alt_quality_review',
				EvaluationType::HUMAN_REVIEW,
				array(
					'title'   => __( 'Contextual text-alternative review is warranted', 'elavira' ),
					'summary' => __( 'Contextual text-alternative review is warranted.', 'elavira' ),
				),
				__( 'An image with a supplied alternative appears in a functional or captioned context where its purpose and possible redundancy require human judgment.', 'elavira' ),
				__( 'Review this text alternative in context. Decide whether the image is informative, decorative, functional, complex, redundant with nearby content, or an image of text.', 'elavira' ),
				array_merge( $common, array( 'review_reason' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 32, array( 'functional_context', 'caption_context' ) ) ) ),
				array(),
				'alternative_text'
			),
			self::rule(
				'elavira.images.filename_alt',
				EvaluationType::POTENTIAL_ISSUE,
				array(
					'title'   => __( 'Text alternative resembles an image filename', 'elavira' ),
					'summary' => __( 'Text alternative resembles an image filename.', 'elavira' ),
				),
				__( 'The supplied alternative matches a bounded filename-style pattern. This pattern may come from a media filename or import workflow, but context remains authoritative.', 'elavira' ),
				__( 'Review whether the alternative communicates the image’s purpose in this rendered context. Do not replace it automatically.', 'elavira' ),
				array_merge( $common, array( 'pattern' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 24, array( 'image_extension', 'camera_filename' ) ) ) ),
				array( 'elavira.images.alt_quality_review' ),
				'alternative_text'
			),
			self::rule(
				'elavira.images.long_alt_review',
				EvaluationType::HUMAN_REVIEW,
				array(
					'title'   => __( 'Extended text alternative may need a different equivalent strategy', 'elavira' ),
					'summary' => __( 'Extended text alternative may need a different equivalent strategy.', 'elavira' ),
				),
				__( 'A bounded Elavira heuristic found an extended, multi-sentence alternative. Length alone is not a WCAG failure and does not establish that the alternative is incorrect.', 'elavira' ),
				__( 'Review whether a concise alternative should identify the image or purpose while important detail is provided through adjacent text, structured content, a data table, or another suitable equivalent.', 'elavira' ),
				array_merge(
					$common,
					array(
						'alt_length'    => new FieldDefinition( FieldDefinition::TYPE_INTEGER, true ),
						'word_count'    => new FieldDefinition( FieldDefinition::TYPE_INTEGER, true ),
						'review_reason' => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 32, array( 'extended_multi_sentence' ) ),
					)
				),
				array( 'elavira.images.alt_quality_review' ),
				'alternative_text'
			),
		);
	}

	private static function rule( $id, $evaluation, $title, $description, $recommendation, array $evidence, array $supersedes, $decision ) {
		return new RuleDefinition(
			array(
				'id'                 => $id,
				'version'            => '1.0.0',
				'editorial_version'  => '1.0.0',
				'title'              => $title['title'],
				'description'        => $description,
				'evaluation_type'    => $evaluation,
				'classification'     => EvaluationType::POTENTIAL_ISSUE === $evaluation ? FindingClassification::POTENTIAL_ISSUE : FindingClassification::HUMAN_REVIEW,
				'priority'           => Priority::MEDIUM,
				'wcag'               => array( new WcagMapping( '1.1.1', 'A', 'perceivable' ) ),
				'summary'            => $title['summary'],
				'why_it_matters'     => __( 'People who cannot see an image rely on an alternative that communicates its purpose in context.', 'elavira' ),
				'recommendation'     => $recommendation,
				'wordpress_guidance' => __( 'Review the Image block or Media Library alternative-text field and any featured-image, theme, page-builder, custom-field, or plugin output. A Media Library change may not affect every use; verify the rendered public page.', 'elavira' ),
				'developer_guidance' => __( 'Determine the image purpose in context and render an appropriate native alt attribute. Functional images must contribute an action or destination name. Complex images may need a concise alternative plus a suitable detailed equivalent.', 'elavira' ),
				'learn_topic'        => 'images_alternative_text',
				'guided_decision'    => $decision,
				'evidence_schema'    => new EvidenceSchema( $evidence, 1024 ),
				'grouping_schema'    => new EvidenceSchema( array(), 2 ),
				'grouping_strategy'  => new RuleLevelGrouping(),
				'supersedes'         => $supersedes,
			)
		);
	}
}
