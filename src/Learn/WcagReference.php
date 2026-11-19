<?php
/**
 * Lightweight WCAG reference registry.
 *
 * @package Elavira
 */

namespace Elavira\Learn;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment -- Concise registry comments are intentional.

use Elavira\Rules\RuleRegistry;

/** Builds criterion relationships from server-owned topics and rules. */
final class WcagReference {
	/** Criteria. @var WcagCriterion[] */
	private $criteria = array();

	/** Create reference. @param TopicRegistry $topics Topics. @param RuleRegistry $rules Rules. */
	public function __construct( TopicRegistry $topics, RuleRegistry $rules ) {
		$rule_map = array();
		foreach ( $rules->all() as $rule ) {
			foreach ( $rule->wcag() as $mapping ) {
				$rule_map[ $mapping->criterion() ][] = $rule->id();
			}
		}
		$topic_map = array();
		foreach ( $topics->all() as $topic ) {
			foreach ( $topic->get( 'wcag' ) as $mapping ) {
				$topic_map[ $mapping['criterion'] ]['level']     = $mapping['level'];
				$topic_map[ $mapping['criterion'] ]['principle'] = self::principle( $mapping['criterion'] );
				$topic_map[ $mapping['criterion'] ]['topics'][]  = $topic->get( 'id' );
			}
		}
		foreach ( $topic_map as $criterion => $data ) {
			$this->criteria[ $criterion ] = new WcagCriterion(
				array(
					'criterion' => $criterion,
					'level'     => $data['level'],
					'principle' => $data['principle'],
					'title'     => self::titles()[ $criterion ] ?? __( 'Accessibility requirement', 'elavira' ),
					'summary'   => self::summaries()[ $criterion ] ?? __( 'Review the official WCAG success criterion and its supporting guidance for the complete requirement.', 'elavira' ),
					'topics'    => array_values( array_unique( $data['topics'] ) ),
					'rules'     => array_values( array_unique( $rule_map[ $criterion ] ?? array() ) ),
				)
			);
		}
		uksort( $this->criteria, 'version_compare' );
	}

	/** Derive the normative WCAG principle from the criterion number. @return string */
	private static function principle( $criterion ) {
		return array(
			'1' => 'perceivable',
			'2' => 'operable',
			'3' => 'understandable',
			'4' => 'robust',
		)[ substr( $criterion, 0, 1 ) ];
	}

	/** Find a validated criterion. @param string $criterion Criterion number. @return WcagCriterion|null */
	public function find( $criterion ) {
		$criterion = preg_match( '/^[1-4]\.[0-9]+\.[0-9]+$/', (string) $criterion ) ? (string) $criterion : '';
		return $this->criteria[ $criterion ] ?? null;
	}

	/** Filter the small trusted reference. @return WcagCriterion[] */
	public function search( $term = '', $principle = '', $level = '' ) {
		$term = strtolower( substr( trim( (string) $term ), 0, 100 ) );
		return array_values(
			array_filter(
				$this->criteria,
				function ( WcagCriterion $item ) use ( $term, $principle, $level ) {
					if ( '' !== $principle && $principle !== $item->get( 'principle' ) ) {
						return false; }
					if ( '' !== $level && $level !== $item->get( 'level' ) ) {
						return false; }
					return '' === $term || false !== strpos( strtolower( $item->get( 'criterion' ) . ' ' . $item->get( 'title' ) . ' ' . $item->get( 'summary' ) ), $term );
				}
			)
		);
	}

	/** Official WCAG short titles for the bundled scope. @return string[] */
	private static function titles() {
		return array(
			'1.1.1'  => __( 'Non-text Content', 'elavira' ),
			'1.2.1'  => __( 'Audio-only and Video-only (Prerecorded)', 'elavira' ),
			'1.2.2'  => __( 'Captions (Prerecorded)', 'elavira' ),
			'1.2.3'  => __( 'Audio Description or Media Alternative (Prerecorded)', 'elavira' ),
			'1.2.5'  => __( 'Audio Description (Prerecorded)', 'elavira' ),
			'1.3.1'  => __( 'Info and Relationships', 'elavira' ),
			'1.4.1'  => __( 'Use of Color', 'elavira' ),
			'1.4.3'  => __( 'Contrast (Minimum)', 'elavira' ),
			'1.4.11' => __( 'Non-text Contrast', 'elavira' ),
			'2.1.1'  => __( 'Keyboard', 'elavira' ),
			'2.1.2'  => __( 'No Keyboard Trap', 'elavira' ),
			'2.2.2'  => __( 'Pause, Stop, Hide', 'elavira' ),
			'2.3.1'  => __( 'Three Flashes or Below Threshold', 'elavira' ),
			'2.4.3'  => __( 'Focus Order', 'elavira' ),
			'2.4.4'  => __( 'Link Purpose (In Context)', 'elavira' ),
			'2.4.6'  => __( 'Headings and Labels', 'elavira' ),
			'2.4.7'  => __( 'Focus Visible', 'elavira' ),
			'2.4.11' => __( 'Focus Not Obscured (Minimum)', 'elavira' ),
			'3.1.1'  => __( 'Language of Page', 'elavira' ),
			'3.1.2'  => __( 'Language of Parts', 'elavira' ),
			'3.3.2'  => __( 'Labels or Instructions', 'elavira' ),
			'4.1.2'  => __( 'Name, Role, Value', 'elavira' ),
		);
	}

	/** Concise non-normative orientation, not replacement criterion text. @return string[] */
	private static function summaries() {
		return array(
			'1.1.1'  => __( 'Provide an appropriate text alternative for non-text content.', 'elavira' ),
			'1.3.1'  => __( 'Preserve information, structure, and relationships programmatically.', 'elavira' ),
			'1.4.1'  => __( 'Do not use color as the only visual means of communicating information.', 'elavira' ),
			'1.4.3'  => __( 'Provide sufficient contrast for text and images of text.', 'elavira' ),
			'1.4.11' => __( 'Provide sufficient contrast for essential interface components and meaningful graphics.', 'elavira' ),
			'2.1.1'  => __( 'Make functionality operable through a keyboard interface, subject to the criterion’s exception.', 'elavira' ),
			'2.1.2'  => __( 'Ensure keyboard focus can move away from every component.', 'elavira' ),
			'2.2.2'  => __( 'Provide required controls for moving, blinking, scrolling, or auto-updating information.', 'elavira' ),
			'2.3.1'  => __( 'Keep flashing content within the WCAG threshold.', 'elavira' ),
			'2.4.3'  => __( 'Use a focus order that preserves meaning and operation.', 'elavira' ),
			'2.4.4'  => __( 'Make link purpose understandable from its text or permitted context.', 'elavira' ),
			'2.4.6'  => __( 'Use headings and labels that describe topic or purpose.', 'elavira' ),
			'2.4.7'  => __( 'Provide a visible keyboard focus indicator.', 'elavira' ),
			'2.4.11' => __( 'Keep focused components from being entirely hidden by author-created content.', 'elavira' ),
			'3.1.1'  => __( 'Programmatically identify the default human language of each page.', 'elavira' ),
			'3.1.2'  => __( 'Programmatically identify language changes when the criterion requires it.', 'elavira' ),
			'3.3.2'  => __( 'Provide labels or instructions when content requires user input.', 'elavira' ),
			'4.1.2'  => __( 'Expose the name, role, state, and value of interface components to software.', 'elavira' ),
		);
	}
}
