<?php
/**
 * Read legacy generated notes without rewriting user text or history.
 *
 * @package Elavira
 */

namespace Elavira\Reports\Application;

// phpcs:disable Squiz.Commenting.FunctionComment, Generic.Files.LineLength

/** Only a matching persisted decision can establish generated provenance. */
final class ActivityNote {
	public static function display( array $entry, array $decisions ) {
		foreach ( $decisions as $decision ) {
			if ( (int) $entry['actor_user_id'] !== (int) $decision['actor_user_id'] || $entry['created_at'] !== $decision['completed_at'] ) {
				continue;
			}
			$prefix = $decision['decision_id'] . ' ' . $decision['decision_version'] . ' / ' . $decision['outcome_id'] . ': ';
			foreach ( (array) json_decode( $decision['answers_json'], true ) as $answer ) {
				if ( isset( $answer['workflow_status'], $answer['label'] ) && $entry['status'] === $answer['workflow_status'] && $entry['reason'] === $prefix . $answer['label'] ) {
					return $answer['label'];
				}
			}
		}
		return $entry['reason'];
	}
}
