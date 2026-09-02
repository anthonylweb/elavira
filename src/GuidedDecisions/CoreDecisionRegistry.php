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
final class CoreDecisionRegistry {
	/** Build the Core registry. @return DecisionRegistry */
	public static function create() {
		$registry = new DecisionRegistry();
		$registry->register( self::link_purpose() );
		$registry->register( self::heading_structure() );
		return $registry;
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
				'rules'             => array(
					array(
						'id'      => 'elavira.link.purpose_review',
						'version' => '1.0.0',
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
				'rules'             => array(
					array(
						'id'      => 'elavira.heading.structure_review',
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

	/** Build an explanatory outcome. @param string $interpretation Interpretation. @param string $recommendation Recommendation. @param string $status Recommended status. @return array */
	private static function outcome( $interpretation, $recommendation, $status ) {
		return array(
			'interpretation' => $interpretation,
			'recommendation' => $recommendation,
			'status'         => $status,
		);
	}
}
