<?php
/**
 * Accessible occurrence review controls and report sections.
 *
 * @package Elavira
 */

namespace Elavira\Reports\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment, Generic.Files.LineLength, WordPress.Security.NonceVerification.Recommended

use Elavira\Findings\OccurrenceReview;
use Elavira\Core\Capabilities\Capability;

/** Native forms and disclosure controls work without JavaScript. */
final class OccurrenceReviewView {
	public static function label( $status ) {
		$labels = array(
			'needs_correction'    => __( 'Needs Correction', 'elavira' ),
			'open'                => __( 'Open', 'elavira' ),
			'needs_review'        => __( 'Needs Review', 'elavira' ),
			'addressed'           => __( 'Addressed', 'elavira' ),
			'accepted_limitation' => __( 'Accepted Limitation', 'elavira' ),
			'reviewed_not_issue'  => __( 'Reviewed — Not an Issue', 'elavira' ),
			'verified'            => __( 'Verified', 'elavira' ),
		);
		return $labels[ $status ] ?? $status;
	}
	public static function form( array $finding, array $occurrence ) {
		$status = OccurrenceReview::status( $occurrence, $finding );
		echo '<p><strong>' . esc_html__( 'Occurrence status:', 'elavira' ) . '</strong> ' . ( 'addressed' === $status ? '<span aria-hidden="true">✓ </span>' : '' ) . esc_html( self::label( $status ) ) . '</p>';

		if ( 'addressed' === $status ) {
			echo '<p>' . esc_html__( 'User marked Addressed. This decision does not verify a fix.', 'elavira' ) . '</p>';
		}
		if ( empty( $occurrence['current_status'] ) && (int) $finding['occurrence_count'] > 1 && empty( $occurrence['reviewed_exception_id'] ) ) {
			echo '<p>' . esc_html__( 'Legacy grouped decision: review this occurrence individually. Earlier group history is retained.', 'elavira' ) . '</p>';
		}
		if ( 'reviewed_not_issue' === $status && empty( $occurrence['reviewed_exception_id'] ) ) {
			echo '<p>' . esc_html__( 'This decision is saved for this audit. The element could not be matched uniquely enough to apply it automatically to later audits.', 'elavira' ) . '</p>'; }
		if ( ! current_user_can( Capability::MANAGE ) ) {
			self::latest_note( $occurrence );
			return;
		}
		$id       = absint( $occurrence['id'] );
		$draft    = ActivityForm::draft( $finding['id'], $id );
		$status   = $draft['status'] ?? $status;
		$statuses = array( 'open', 'needs_review', 'needs_correction', 'addressed', 'accepted_limitation' );
		if ( in_array( $finding['category'], array( 'potential_issue', 'human_review' ), true ) ) {
			$statuses[] = 'reviewed_not_issue';
		}
		echo '<form class="elavira-occurrence-review" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="elavira_update_finding"><input type="hidden" name="finding" value="' . esc_attr( $finding['id'] ) . '"><input type="hidden" name="occurrence" value="' . esc_attr( $id ) . '"><input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( 'elavira_update_finding_' . absint( $finding['id'] ) ) ) . '">';
		/* translators: %d: occurrence database ID. */
		echo '<div class="elavira-review-row"><div><label for="occurrence-status-' . esc_attr( $id ) . '">' . esc_html( sprintf( __( 'Review status for occurrence %d', 'elavira' ), $id ) ) . '</label><select id="occurrence-status-' . esc_attr( $id ) . '" name="finding_status">';
		foreach ( $statuses as $option ) {
			echo '<option value="' . esc_attr( $option ) . '" ' . selected( $option, $status, false ) . '>' . esc_html( self::label( $option ) ) . '</option>';
		}
		echo '</select></div><div>';
		ActivityForm::note( 'occurrence-note-' . $id, $status, $draft );
		echo '<p><button type="submit" class="button">' . esc_html__( 'Save occurrence review', 'elavira' ) . '</button></p></div><div>';
		self::latest_note( $occurrence );
		echo '</div></div></form>';
	}

	private static function latest_note( array $occurrence ) {
		if ( isset( $occurrence['latest_note'] ) && '' !== trim( $occurrence['latest_note'] ) ) {
			echo '<div class="elavira-latest-note"><strong>' . esc_html__( 'Latest Note', 'elavira' ) . '</strong><p>' . esc_html( $occurrence['latest_note'] ) . '</p></div>';
		}
	}

	public static function summary( array $sections, $grouped_count = null ) {
		$groups = array();
		foreach ( $sections as $items ) {
			foreach ( $items as $item ) {
				$groups[ $item['finding_id'] ] = true;
			}
		}
		echo '<h3>' . esc_html__( 'Finding Summary', 'elavira' ) . '</h3><ul class="elavira-finding-meta"><li><strong>' . esc_html__( 'Grouped findings:', 'elavira' ) . '</strong> ' . esc_html( number_format_i18n( null === $grouped_count ? count( $groups ) : $grouped_count ) ) . '</li>';
		foreach ( array(
			'active'    => __( 'Active occurrences', 'elavira' ),
			'addressed' => __( 'Addressed occurrences', 'elavira' ),
			'accepted'  => __( 'Accepted occurrences', 'elavira' ),
			'verified'  => __( 'Verified occurrences', 'elavira' ),
		) as $key => $label ) {
			echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( number_format_i18n( count( $sections[ $key ] ) ) ) . '</li>';
		}
		echo '<li><strong>' . esc_html__( 'Needs Correction occurrences (included in active):', 'elavira' ) . '</strong> ' . esc_html( number_format_i18n( \Elavira\Reports\Application\OccurrenceReport::correction_count( $sections ) ) ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Total detected occurrences:', 'elavira' ) . '</strong> ' . esc_html( number_format_i18n( array_sum( array_map( 'count', $sections ) ) ) ) . '</li></ul>';
	}

	public static function sections( array $sections, $rules, $reviews = null ) {
		$visible = array();
		foreach ( $sections as $key => $items ) {
			$pages           = max( 1, (int) ceil( count( $items ) / 20 ) );
			$page            = min( $pages, max( 1, absint( $_GET[ 'occurrence_page_' . $key ] ?? 1 ) ) );
			$visible[ $key ] = array_slice( $items, ( $page - 1 ) * 20, 20 );
		}
		$ids = array();
		foreach ( $visible as $items ) {
			$ids = array_merge( $ids, array_column( $items, 'id' ) );
		}
		$notes = $reviews ? $reviews->latest_notes( $ids ) : array();
		foreach ( array(
			'active'    => __( 'Active findings', 'elavira' ),
			'addressed' => __( 'Addressed findings', 'elavira' ),
			'verified'  => __( 'Verified findings', 'elavira' ),
			'accepted'  => __( 'Accepted findings', 'elavira' ),
		) as $key => $label ) {
			$items = $sections[ $key ];
			if ( ! $items && 'accepted' !== $key ) {
				continue;
			}
			$heading = $label . ' (' . number_format_i18n( count( $items ) ) . ')';
			echo 'accepted' === $key ? '<details class="elavira-accepted"><summary>' . esc_html( $heading ) . '</summary>' : '<section><h3>' . esc_html( $heading ) . '</h3>';
			if ( 'accepted' === $key ) {
				echo '<p>' . esc_html__( 'Reviewed — Not an Issue. These occurrences were detected in this audit and are excluded from active counts. Change the status to reconsider a decision.', 'elavira' ) . '</p>';
			}
			$parameter = 'occurrence_page_' . $key;
			$pages     = max( 1, (int) ceil( count( $items ) / 20 ) );
			$page      = min( $pages, max( 1, absint( $_GET[ $parameter ] ?? 1 ) ) );
			echo '<ol class="elavira-occurrences">';
			foreach ( $visible[ $key ] as $occurrence ) {
				$occurrence['latest_note'] = $notes[ $occurrence['id'] ] ?? '';
				$finding                   = $occurrence['finding'];
				$resolved                  = $rules->resolve( $finding );
				$url                       = admin_url( 'admin.php?page=elavira-finding&finding=' . absint( $finding['id'] ) . '&occurrence=' . absint( $occurrence['id'] ) );
				echo '<li class="elavira-occurrence-' . esc_attr( $key ) . '"><h4><a href="' . esc_url( $url ) . '">' . esc_html( $resolved['editorial']['title'] ) . '</a></h4><p class="description">' . esc_html( $resolved['editorial']['summary'] ?? $finding['summary_snapshot'] ) . '</p>';
				$target = (array) json_decode( $occurrence['target_json'], true );
				echo '<p><code>' . esc_html( $target['selector'] ?? '' ) . '</code></p>';
				self::form( $finding, $occurrence );
				echo '</li>';
			}
			echo '</ol>';
			if ( $pages > 1 ) {
				echo '<nav aria-label="' . esc_attr( $label ) . '">';
				for ( $number = 1; $number <= $pages; ++$number ) {
					echo '<a class="button" href="' . esc_url( add_query_arg( $parameter, $number ) ) . '"' . ( $number === $page ? ' aria-current="page"' : '' ) . '>' . esc_html( number_format_i18n( $number ) ) . '</a> ';
				}
				echo '</nav>';
			}
			echo 'accepted' === $key ? '</details>' : '</section>';
		}
	}
}
