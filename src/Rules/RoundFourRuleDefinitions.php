<?php
/**
 * Phase 1 production ruleset Round 4 definitions.
 *
 * @package Elavira
 */

namespace Elavira\Rules;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.WP.I18n.NonSingularStringLiteralText -- Compact trusted registry copy is centralized here.

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
				'Positive tabindex needs focus-order review',
				'A visible interactive or focusable element uses a positive tabindex, which can move keyboard focus away from logical DOM and reading order.',
				'Start before the region; use Tab through it, compare focus with visual and content order, then repeat with Shift+Tab.',
				'Prefer logical DOM order and the normal document focus order instead of positive tabindex values.',
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
				'Non-native interactive element needs keyboard review',
				'A visible non-native element exposes an interactive role or focusability signal. That pattern does not prove keyboard inoperability.',
				'Reach it using Tab or the widget’s expected navigation model; verify activation, state changes, visible focus, and a usable way to leave it.',
				'Prefer a native HTML control where it meets the need; otherwise implement and test the documented keyboard pattern and semantic states.',
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
				'Custom control may require keyboard-reachability testing',
				'A visible non-native interactive role has no ordinary tab stop or uses a negative tabindex. Script-managed or composite navigation may still be valid.',
				'Begin at a logical point before the control, attempt to reach and operate it without a pointer, and test any documented arrow-key model.',
				'Use a native control where feasible, or provide an appropriate focus-management and keyboard interaction model.',
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
				'Application-style region needs keyboard-trap testing',
				'A visible application-role region contains multiple focusable descendants. Static inspection cannot establish that focus becomes trapped.',
				'Keyboard into the region, traverse controls forward and backward, try Escape where conventional, and confirm focus can leave without a pointer or unexpected action.',
				'Provide standard movement and exit behavior; intentional modal containment must include an accessible keyboard dismissal path.',
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
				'Custom widget needs interaction-pattern review',
				'A visible non-native composite or specialized ARIA widget requires manual verification of its role-specific keyboard behavior.',
				'Test only the keys expected for this widget type, such as Tab, arrows, Enter, Space, Escape, Home, or End as its documented pattern requires.',
				'Prefer native controls where suitable; otherwise implement the applicable WAI-ARIA keyboard interaction pattern completely.',
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
				'Author styles may suppress the focus indicator',
				'A bounded author focus rule removes the outline without declaring another detectable focus-state border, shadow, or background treatment. Interaction may still provide a valid indicator elsewhere.',
				'Keyboard through the affected controls and visually confirm a persistent, perceivable focus indicator in every relevant state.',
				'Retain native focus styling or add a clear focus-visible treatment; verify visibility rather than relying on outline removal alone.',
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
			self::rule( 'elavira.focus.order_review', Priority::HIGH, '2.4.3:A:operable', 'Focus order needs manual review', 'A bounded focus configuration may alter sequential focus order. Static inspection cannot determine whether the resulting sequence preserves meaning and operation.', 'Use Tab and Shift+Tab through the region and compare the actual focus sequence with its logical reading, visual, and interaction order.', 'Align DOM and normal focus order with the intended sequence and avoid positive tabindex values.', self::fields( array( 'order_trigger' => array( 24, array( 'positive_tabindex' ) ) ) ), array(), 'focus_focus_order', 'keyboard_interaction' ),
			self::rule(
				'elavira.focus.obscured_review',
				Priority::HIGH,
				'2.4.11:AA:operable',
				'Persistent overlay may obscure focused content',
				'A substantial fixed or sticky, elevated region touches a viewport boundary and may overlap focused content during keyboard navigation.',
				'Move focus near each overlay boundary, test scrolling, and confirm every focused component remains sufficiently visible.',
				'Adjust scrolling offsets, spacing, overlay dimensions, or focus movement so focused components remain visible.',
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
				'Dialog focus management needs interaction review',
				'A visible open native dialog or ARIA modal dialog requires human verification of focus entry, containment, dismissal, background restriction, and restoration.',
				'Open it by keyboard, inspect its name and initial focus, traverse it, dismiss it by keyboard, and confirm focus returns logically and background interaction is restricted.',
				'Use native dialog behavior or a complete accessible dialog pattern with an accessible name, managed focus, keyboard dismissal, and logical restoration.',
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
				'title'              => __( $title, 'elavira' ),
				'description'        => __( $observed, 'elavira' ),
				'evaluation_type'    => EvaluationType::HUMAN_REVIEW,
				'classification'     => FindingClassification::HUMAN_REVIEW,
				'priority'           => $priority,
				'wcag'               => array( new WcagMapping( $criterion, $level, $principle ) ),
				'summary'            => __( $title . '.', 'elavira' ),
				'why_it_matters'     => __( $observed, 'elavira' ),
				'recommendation'     => __( $manual_test, 'elavira' ),
				'wordpress_guidance' => __( $remediation, 'elavira' ),
				'developer_guidance' => __( $remediation, 'elavira' ),
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
