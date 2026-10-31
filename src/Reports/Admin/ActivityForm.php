<?php
/**
 * Shared accessible workflow-note fields and private rejected drafts.
 *
 * @package Elavira
 */

namespace Elavira\Reports\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment, Generic.Files.LineLength

/** Drafts are bounded, short lived, and isolated by user and occurrence. */
final class ActivityForm {

	public static function key( $finding, $occurrence ) {
		return 'elavira_draft_' . get_current_user_id() . '_' . absint( $finding ) . '_' . absint( $occurrence );
	}

	public static function reject( $finding, $occurrence, $status, $note ) {
		set_transient(
			self::key( $finding, $occurrence ),
			array(
				'status' => $status,
				'note'   => substr( $note, 0, 1000 ),
			),
			600
		);
	}

	public static function draft( $finding, $occurrence ) {
		$key   = self::key( $finding, $occurrence );
		$draft = get_transient( $key );
		delete_transient( $key );
		return is_array( $draft ) ? $draft : array();
	}

	public static function note( $id, $status, array $draft, $activity = false ) {
		$required = 'needs_correction' === $status;
		$optional = $activity ? __( 'Activity note (optional)', 'elavira' ) : __( 'Review note (optional)', 'elavira' );
		$label    = __( 'What needs correction (required)', 'elavira' );
		$error    = __( 'Explain what needs correction before saving. Your status has not changed.', 'elavira' );
		echo '<label for="' . esc_attr( $id ) . '" data-error="' . esc_attr( $error ) . '" data-optional-label="' . esc_attr( $optional ) . '" data-required-label="' . esc_attr( $label ) . '">' . esc_html( $required ? $label : $optional ) . '</label>';
		echo '<p id="' . esc_attr( $id . '-help' ) . '" class="description">' . esc_html__( 'Explain what needs correction when choosing Needs Correction. For other statuses, a note is optional.', 'elavira' ) . '</p>';
		echo '<textarea id="' . esc_attr( $id ) . '" name="reason" rows="4" maxlength="1000" aria-describedby="' . esc_attr( $id . '-help' . ( $draft ? ' ' . $id . '-error' : '' ) ) . '"' . ( $required ? ' required' : '' ) . ( $draft ? ' aria-invalid="true"' : '' ) . '>' . esc_textarea( $draft['note'] ?? '' ) . '</textarea>';
		echo '<p id="' . esc_attr( $id . '-error' ) . '" class="elavira-note-error"' . ( $draft ? ' role="alert"' : ' hidden' ) . '>' . esc_html( $required && '' === trim( $draft['note'] ?? '' ) ? $error : __( 'The update was not saved. Review your status and note, then try again.', 'elavira' ) ) . '</p>';
	}
}
