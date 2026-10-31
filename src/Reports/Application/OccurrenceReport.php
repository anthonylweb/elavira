<?php
/**
 * Consistent occurrence counts and report sections.
 *
 * @package Elavira
 */

namespace Elavira\Reports\Application;

// phpcs:disable Squiz.Commenting.FunctionComment, Generic.Files.LineLength

use Elavira\Findings\OccurrenceReview;

/** Bounded by the scanner's maximum 1,000 persisted primary observations. */
final class OccurrenceReport {
	public static function correction_count( array $sections ) {
		$count = 0;
		foreach ( $sections as $items ) {
			foreach ( $items as $item ) {
				$count += 'needs_correction' === $item['status'] ? 1 : 0;
			}
		}
		return $count;
	}
	public static function filter( array $sections, array $filters ) {
		foreach ( $sections as $key => $items ) {
			$sections[ $key ] = array_values(
				array_filter(
					$items,
					function ( $item ) use ( $filters ) {
						foreach ( array( 'priority', 'review_state', 'wcag_level' ) as $field ) {
							if ( ! empty( $filters[ $field ] ) && $item['finding'][ $field ] !== $filters[ $field ] ) {
								return false; }
						}
						if ( ! empty( $filters['current_status'] ) && $item['status'] !== $filters['current_status'] ) {
							return false; }
						return empty( $filters['search'] ) || false !== stripos( $item['finding']['summary_snapshot'] . ' ' . $item['finding']['rule_id'], $filters['search'] );
					}
				)
			);
		}
		return $sections;
	}
	public static function build( array $findings, array $occurrences ) {
		$indexed  = array_column( $findings, null, 'id' );
		$sections = array(
			'active'    => array(),
			'addressed' => array(),
			'accepted'  => array(),
			'verified'  => array(),
		);
		foreach ( $occurrences as $occurrence ) {
			if ( ! isset( $indexed[ $occurrence['finding_id'] ] ) || ! empty( $occurrence['superseded_by_occurrence_id'] ) ) {
				continue;
			}
			$finding                = $indexed[ $occurrence['finding_id'] ];
			$occurrence['status']   = OccurrenceReview::status( $occurrence, $finding );
			$occurrence['finding']  = $finding;
			$section                = array(
				'reviewed_not_issue' => 'accepted',
				'addressed'          => 'addressed',
				'verified'           => 'verified',
			)[ $occurrence['status'] ] ?? 'active';
			$sections[ $section ][] = $occurrence;
		}
		return $sections;
	}
}
