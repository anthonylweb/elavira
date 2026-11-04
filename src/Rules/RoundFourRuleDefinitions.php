<?php
/**
 * Phase 1 production ruleset Round 4 definitions.
 *
 * @package Elavira
 */

namespace Elavira\Rules;

// phpcs:disable Squiz.Commenting.FunctionComment -- Compact trusted registry copy is centralized here.

use Elavira\Findings\Values\Priority;
use Elavira\Rules\Evidence\EvidenceSchema;
use Elavira\Rules\Evidence\FieldDefinition;
use Elavira\Rules\Grouping\RuleLevelGrouping;
use Elavira\Rules\Values\EvaluationType;
use Elavira\Rules\Values\FindingClassification;

/** Builds versioned keyboard and focus review contracts. */
final class RoundFourRuleDefinitions {
	/** Return all Round 4 definitions. @return RuleDefinition[] */
	public static function all() {
		return array(
			self::rule(
				'elavira.keyboard.positive_tabindex',
				Priority::HIGH,
				'2.4.3:A:operable',
				array(
					'title'   => __( 'Positive tabindex needs focus-order review', 'elavira' ),
					'summary' => __( 'Positive tabindex needs focus-order review.', 'elavira' ),
				),
				__( 'A visible interactive or focusable element uses a positive tabindex, which can move keyboard focus away from logical DOM and reading order.', 'elavira' ),
				__( 'Start before the region; use Tab through it, compare focus with visual and content order, then repeat with Shift+Tab.', 'elavira' ),
				__( 'Prefer logical DOM order and the normal document focus order instead of positive tabindex values.', 'elavira' ),
				self::fields(
					array(
						'tabindex_category' => array( 16, array( 'positive' ) ),
						'tabindex_value'    => array( 0, array(), 'integer' ),
						'focusable_kind'    => array( 24, array() ),
					)
				),
				array( 'elavira.focus.order_review' ),
				'focus_focus_order',
				'keyboard_interaction'
			),
			self::rule(
				'elavira.keyboard.non_native_interactive',
				Priority::HIGH,
				'2.1.1:A:operable',
				array(
					'title'   => __( 'Non-native interactive element needs keyboard review', 'elavira' ),
					'summary' => __( 'Non-native interactive element needs keyboard review.', 'elavira' ),
				),
				__( 'A visible non-native element exposes an interactive role or focusability signal. That pattern does not prove keyboard inoperability.', 'elavira' ),
				__( 'Reach it using Tab or the widget’s expected navigation model; verify activation, state changes, visible focus, and a usable way to leave it.', 'elavira' ),
				__( 'Prefer a native HTML control where it meets the need; otherwise implement and test the documented keyboard pattern and semantic states.', 'elavira' ),
				self::fields(
					array(
						'role'               => array( 32, array() ),
						'interaction_signal' => array( 24, array( 'interactive_role', 'focusable_role' ) ),
						'tabindex_category'  => array( 16, array( 'missing', 'negative', 'zero', 'positive' ) ),
					)
				),
				array( 'elavira.keyboard.custom_widget_review' ),
				'keyboard_accessibility',
				'keyboard_interaction'
			),
			self::rule(
				'elavira.keyboard.possible_unreachable_control',
				Priority::HIGH,
				'2.1.1:A:operable',
				array(
					'title'   => __( 'Custom control may require keyboard-reachability testing', 'elavira' ),
					'summary' => __( 'Custom control may require keyboard-reachability testing.', 'elavira' ),
				),
				__( 'A visible non-native interactive role has no ordinary tab stop or uses a negative tabindex. Script-managed or composite navigation may still be valid.', 'elavira' ),
				__( 'Begin at a logical point before the control, attempt to reach and operate it without a pointer, and test any documented arrow-key model.', 'elavira' ),
				__( 'Use a native control where feasible, or provide an appropriate focus-management and keyboard interaction model.', 'elavira' ),
				self::fields(
					array(
						'role'         => array( 32, array() ),
						'focusability' => array( 24, array( 'no_tab_stop', 'negative_tabindex' ) ),
					)
				),
				array( 'elavira.keyboard.non_native_interactive', 'elavira.keyboard.custom_widget_review' ),
				'keyboard_accessibility',
				'keyboard_interaction'
			),
			self::rule(
				'elavira.keyboard.possible_trap',
				Priority::HIGH,
				'2.1.2:A:operable',
				array(
					'title'   => __( 'Application-style region needs keyboard-trap testing', 'elavira' ),
					'summary' => __( 'Application-style region needs keyboard-trap testing.', 'elavira' ),
				),
				__( 'A visible application-role region contains multiple focusable descendants. Static inspection cannot establish that focus becomes trapped.', 'elavira' ),
				__( 'Keyboard into the region, traverse controls forward and backward, try Escape where conventional, and confirm focus can leave without a pointer or unexpected action.', 'elavira' ),
				__( 'Provide standard movement and exit behavior; intentional modal containment must include an accessible keyboard dismissal path.', 'elavira' ),
				self::fields(
					array(
						'structure'             => array( 32, array( 'application_region' ) ),
						'focusable_descendants' => array( 0, array(), 'integer' ),
					)
				),
				array(),
				'keyboard_accessibility',
				'keyboard_interaction'
			),
			self::rule(
				'elavira.keyboard.custom_widget_review',
				Priority::MEDIUM,
				'2.1.1:A:operable',
				array(
					'title'   => __( 'Custom widget needs interaction-pattern review', 'elavira' ),
					'summary' => __( 'Custom widget needs interaction-pattern review.', 'elavira' ),
				),
				__( 'A visible non-native composite or specialized ARIA widget requires manual verification of its role-specific keyboard behavior.', 'elavira' ),
				__( 'Test only the keys expected for this widget type, such as Tab, arrows, Enter, Space, Escape, Home, or End as its documented pattern requires.', 'elavira' ),
				__( 'Prefer native controls where suitable; otherwise implement the applicable WAI-ARIA keyboard interaction pattern completely.', 'elavira' ),
				self::fields(
					array(
						'role'           => array( 32, array() ),
						'widget_pattern' => array( 24, array( 'composite', 'specialized' ) ),
					)
				),
				array(),
				'keyboard_accessibility',
				'keyboard_interaction'
			),
			self::rule(
				'elavira.focus.possible_hidden_focus',
				Priority::HIGH,
				'2.4.7:AA:operable',
				array(
					'title'   => __( 'Author styles may suppress the focus indicator', 'elavira' ),
					'summary' => __( 'Author styles may suppress the focus indicator.', 'elavira' ),
				),
				__( 'A bounded author focus rule removes the outline without declaring another detectable focus-state border, shadow, or background treatment. Interaction may still provide a valid indicator elsewhere.', 'elavira' ),
				__( 'Keyboard through the affected controls and visually confirm a persistent, perceivable focus indicator in every relevant state.', 'elavira' ),
				__( 'Retain native focus styling or add a clear focus-visible treatment; verify visibility rather than relying on outline removal alone.', 'elavira' ),
				self::fields(
					array(
						'style_pattern' => array( 40, array( 'outline_removed_no_alternative' ) ),
						'style_source'  => array( 24, array( 'author_stylesheet' ) ),
					)
				),
				array(),
				'focus_focus_order',
				'keyboard_interaction'
			),
			self::rule(
				'elavira.focus.order_review',
				Priority::HIGH,
				'2.4.3:A:operable',
				array(
					'title'   => __( 'Focus order needs manual review', 'elavira' ),
					'summary' => __( 'Focus order needs manual review.', 'elavira' ),
				),
				__( 'A bounded focus configuration may alter sequential focus order. Static inspection cannot determine whether the resulting sequence preserves meaning and operation.', 'elavira' ),
				__( 'Use Tab and Shift+Tab through the region and compare the actual focus sequence with its logical reading, visual, and interaction order.', 'elavira' ),
				__( 'Align DOM and normal focus order with the intended sequence and avoid positive tabindex values.', 'elavira' ),
				self::fields( array( 'order_trigger' => array( 24, array( 'positive_tabindex' ) ) ) ),
				array(),
				'focus_focus_order',
				'keyboard_interaction'
			),
			self::rule(
				'elavira.focus.obscured_review',
				Priority::HIGH,
				'2.4.11:AA:operable',
				array(
					'title'   => __( 'Persistent overlay may obscure focused content', 'elavira' ),
					'summary' => __( 'Persistent overlay may obscure focused content.', 'elavira' ),
				),
				__( 'A substantial fixed or sticky, elevated region touches a viewport boundary and may overlap focused content during keyboard navigation.', 'elavira' ),
				__( 'Move focus near each overlay boundary, test scrolling, and confirm every focused component remains sufficiently visible.', 'elavira' ),
				__( 'Adjust scrolling offsets, spacing, overlay dimensions, or focus movement so focused components remain visible.', 'elavira' ),
				self::fields(
					array(
						'position' => array( 16, array( 'fixed', 'sticky' ) ),
						'boundary' => array( 16, array( 'top', 'bottom' ) ),
						'coverage' => array( 16, array( 'half', 'most' ) ),
					)
				),
				array(),
				'focus_focus_order',
				'keyboard_interaction'
			),
			self::rule(
				'elavira.focus.modal_management_review',
				Priority::HIGH,
				'2.4.3:A:operable',
				array(
					'title'   => __( 'Dialog focus management needs interaction review', 'elavira' ),
					'summary' => __( 'Dialog focus management needs interaction review.', 'elavira' ),
				),
				__( 'A visible open native dialog or ARIA modal dialog requires human verification of focus entry, containment, dismissal, background restriction, and restoration.', 'elavira' ),
				__( 'Open it by keyboard, inspect its name and initial focus, traverse it, dismiss it by keyboard, and confirm focus returns logically and background interaction is restricted.', 'elavira' ),
				__( 'Use native dialog behavior or a complete accessible dialog pattern with an accessible name, managed focus, keyboard dismissal, and logical restoration.', 'elavira' ),
				self::fields(
					array(
						'dialog_pattern' => array( 24, array( 'native_open', 'aria_modal' ) ),
						'role'           => array( 24, array( 'dialog', 'alertdialog' ) ),
					)
				),
				array( 'elavira.keyboard.possible_trap', 'elavira.keyboard.non_native_interactive', 'elavira.keyboard.custom_widget_review' ),
				'focus_focus_order',
				'keyboard_interaction'
			),
		);
	}

	/** Build evidence fields. @return FieldDefinition[] */
	private static function fields( array $definitions ) {
		$fields = array();
		foreach ( $definitions as $name => $definition ) {
			$type            = isset( $definition[2] ) && 'integer' === $definition[2] ? FieldDefinition::TYPE_INTEGER : FieldDefinition::TYPE_STRING;
			$fields[ $name ] = new FieldDefinition( $type, true, $definition[0], $definition[1] );
		}
		return $fields;
	}

	/** Build one server-authoritative Human Review rule. @return RuleDefinition */
	private static function rule( $id, $priority, $wcag, $title, $observed, $manual_test, $remediation, array $evidence, array $supersedes, $learn, $decision ) {
		list( $criterion, $level, $principle ) = explode( ':', $wcag );
		return new RuleDefinition(
			array(
				'id'                 => $id,
				'version'            => '1.0.0',
				'editorial_version'  => '1.0.0',
				'title'              => $title['title'],
				'description'        => $observed,
				'evaluation_type'    => EvaluationType::HUMAN_REVIEW,
				'classification'     => FindingClassification::HUMAN_REVIEW,
				'priority'           => $priority,
				'wcag'               => array( new WcagMapping( $criterion, $level, $principle ) ),
				'summary'            => $title['summary'],
				'why_it_matters'     => $observed,
				'recommendation'     => $manual_test,
				'wordpress_guidance' => $remediation,
				'developer_guidance' => $remediation,
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
