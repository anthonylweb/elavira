<?php
/**
 * Regression coverage for occurrence report updates.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Findings\OccurrenceReview;
use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Findings\Persistence\ReviewedExceptionRepository;
use Elavira\Reports\Application\OccurrenceReport;
use Elavira\Tests\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

final class OccurrenceReviewTest extends TestCase {
	private $db;
	protected function setUp(): void {
		$this->db         = new FakeWpdb();
		$this->db->tables = array(
			'wp_elavira_audits'                 => array(
				1 => array(
					'id'     => 1,
					'status' => 'completed',
				),
			),
			'wp_elavira_audit_urls'             => array(
				1 => array(
					'id'                 => 1,
					'audit_id'           => 1,
					'canonical_url'      => 'https://example.com/a',
					'canonical_url_hash' => hash( 'sha256', 'https://example.com/a' ),
				),
			),
			'wp_elavira_findings'               => array(
				1 => array(
					'id'               => 1,
					'audit_id'         => 1,
					'rule_id'          => 'elavira.aria.invalid_attribute',
					'rule_version'     => '1.0.0',
					'category'         => 'potential_issue',
					'current_status'   => 'open',
					'occurrence_count' => 2,
					'summary_snapshot' => 'ARIA fixture',
				),
			),
			'wp_elavira_occurrences'            => array(),
			'wp_elavira_reviewed_exceptions'    => array(),
			'wp_elavira_finding_status_history' => array(),
		);
		for ( $id = 1; $id <= 2; ++$id ) {
			$target   = array(
				'identity_version' => 2,
				'element_id'       => 'item-' . $id,
				'tag_name'         => 'div',
				'selector'         => '#item-' . $id,
			);
			$evidence = array(
				'attribute_name' => 'aria-unknown',
				'reason'         => 'unknown',
			);
			$identity = ( new ReviewedConditionIdentity() )->fingerprints( $target, $evidence, array( 'tag_name' => 'div' ) );
			$this->db->tables['wp_elavira_occurrences'][ $id ] = array(
				'id'                            => $id,
				'finding_id'                    => 1,
				'audit_url_id'                  => 1,
				'current_status'                => 'open',
				'target_json'                   => json_encode( $target ),
				'evidence_json'                 => json_encode( $evidence ),
				'element_json'                  => '{"tag_name":"div"}',
				'reviewed_exception_id'         => 0,
				'target_fingerprint'            => $identity['target'],
				'material_evidence_fingerprint' => $identity['evidence'],
			);
		}
	}
	public function test_correction_requires_a_note_without_partial_writes_and_counts_as_active() {
		$review = new OccurrenceReview( $this->db );
		$before = $this->db->tables;
		foreach ( array( '', " \n\t", '<b></b>' ) as $blank ) {
			try {
				$review->update( 1, 1, 'needs_correction', $blank, 7 );
				$this->fail( 'Blank correction was accepted.' );
			} catch ( \InvalidArgumentException $error ) {
				$this->assertSame( $before, $this->db->tables );
			}
		}
		$review->update( 1, 1, 'needs_correction', 'Repair the label relationship.', 7 );
		$reloaded = new OccurrenceRepository( $this->db );
		$this->assertSame( 'needs_correction', $reloaded->find( 1 )['current_status'] );
		$this->assertSame( 'open', $reloaded->find( 2 )['current_status'] );
		$sections = OccurrenceReport::build( array_values( $this->db->tables['wp_elavira_findings'] ), $reloaded->find_by_finding( 1 ) );
		$this->assertCount( 2, $sections['active'] );
		$this->assertSame( 1, OccurrenceReport::correction_count( $sections ) );
		$filtered = OccurrenceReport::filter( $sections, array( 'current_status' => 'needs_correction' ) );
		$this->assertCount( 1, $filtered['active'] );
		$this->assertSame( 'Repair the label relationship.', $review->history( 1 )[0]['reason'] );
		$this->assertSame( 2, $this->db->tables['wp_elavira_findings'][1]['active_occurrence_count'] );
	}

	public function test_latest_notes_persist_independently_and_ignore_status_only_updates() {
		$review = new OccurrenceReview( $this->db );
		$review->update( 1, 1, 'addressed', 'First note', 7 );
		$review->update( 1, 1, 'addressed', "Latest note\nSecond line", 7 );
		$review->update( 1, 1, 'needs_review', '', 7 );
		$review->update( 1, 2, 'open', 'Other occurrence', 7 );
		// Reload the service, as happens on the redirected report request.
		$review = new OccurrenceReview( $this->db );
		$before = count( $this->db->queries );
		$this->assertSame(
			array(
				1 => "Latest note\nSecond line",
				2 => 'Other occurrence',
			),
			$review->latest_notes( array( 1, 2, 99 ) )
		);
		$this->assertCount( $before + 1, $this->db->queries );
		$this->assertCount( 3, $review->history( 1 ) );
		$this->assertSame( array(), $review->latest_notes( array() ) );
	}

	public function test_latest_note_orders_by_timestamp_then_id_and_excludes_legacy_notes() {
		$table = 'wp_elavira_finding_status_history';
		foreach ( array(
			array( 1, 1, 'Newest timestamp', '2026-10-31 12:00:00' ),
			array( 2, 1, 'Older timestamp', '2026-10-30 12:00:00' ),
			array( 3, 1, 'Tie winner', '2026-10-31 12:00:00' ),
			array( 4, 1, " \n\t", '2026-10-31 13:00:00' ),
			array( 5, 0, 'Legacy grouped note', '2026-10-31 14:00:00' ),
		) as $row ) {
			$this->db->tables[ $table ][ $row[0] ] = array(
				'id'            => $row[0],
				'occurrence_id' => $row[1],
				'reason'        => $row[2],
				'created_at'    => $row[3],
			);
		}
		$this->assertSame( array( 1 => 'Tie winner' ), ( new OccurrenceReview( $this->db ) )->latest_notes( array( 1, 2 ) ) );
	}

	public function test_latest_note_is_escaped_and_input_remains_empty_in_every_status() {
		$finding    = $this->db->tables['wp_elavira_findings'][1];
		$occurrence = $this->db->tables['wp_elavira_occurrences'][1];
		foreach ( array( 'open', 'addressed', 'reviewed_not_issue', 'verified' ) as $status ) {
			$occurrence['current_status'] = $status;
			$occurrence['latest_note']    = "<script>alert(1)</script> & text\nNext line";
			ob_start();
			\Elavira\Reports\Admin\OccurrenceReviewView::form( $finding, $occurrence );
			$html = ob_get_clean();
			$this->assertStringContainsString( 'Latest Note', $html );
			$this->assertStringContainsString( "&lt;script&gt;alert(1)&lt;/script&gt; &amp; text\nNext line", $html );
			$this->assertStringNotContainsString( '<script>', $html );
		}
		unset( $occurrence['latest_note'] );
		ob_start();
		\Elavira\Reports\Admin\OccurrenceReviewView::form( $finding, $occurrence );
		$this->assertStringNotContainsString( 'Latest Note', ob_get_clean() );
	}

	public function test_independent_decisions_counts_history_and_reset() {
		$review = new OccurrenceReview( $this->db );
		$review->update( 1, 1, 'addressed', 'Fixed first target', 7 );
		$this->assertSame( 'open', $this->db->tables['wp_elavira_occurrences'][2]['current_status'] );
		$review->update( 1, 2, 'reviewed_not_issue', 'Reviewed second target', 7 );
		$this->assertCount( 1, $review->history( 1 ) );
		$this->assertCount( 1, $review->history( 2 ) );
		$this->assertSame( 0, $this->db->tables['wp_elavira_findings'][1]['active_occurrence_count'] );
		$repo     = new OccurrenceRepository( $this->db );
		$sections = OccurrenceReport::build( $this->db->tables['wp_elavira_findings'], $repo->find_by_audit( 1 ) );
		$this->assertCount( 1, $sections['addressed'] );
		$this->assertCount( 1, $sections['accepted'] );
		$this->assertCount( 0, $sections['active'] );
		( new ReviewedExceptionRepository( $this->db ) )->reset_all( 7 );
		$sections = OccurrenceReport::build( $this->db->tables['wp_elavira_findings'], $repo->find_by_audit( 1 ) );
		$this->assertCount( 0, $sections['accepted'] );
		$this->assertCount( 1, $sections['active'] );
		$this->assertCount( 1, $review->history( 2 ) );
	}
	public function test_legacy_exception_source_preserves_acceptance_and_is_reversible() {
		$review = new OccurrenceReview( $this->db );
		$review->update( 1, 1, 'reviewed_not_issue', 'Legacy review', 7 );
		$this->db->tables['wp_elavira_occurrences'][1]['current_status']        = '';
		$this->db->tables['wp_elavira_occurrences'][1]['reviewed_exception_id'] = 0;
		$repo = new OccurrenceRepository( $this->db );
		$row  = $repo->find( 1 );
		$this->assertSame( 'reviewed_not_issue', OccurrenceReview::status( $row, $this->db->tables['wp_elavira_findings'][1] ) );
		$review->update( 1, 1, 'needs_review', 'Reconsider legacy decision', 7 );
		$this->assertSame( 0, $this->db->tables['wp_elavira_reviewed_exceptions'][1]['active'] );
		$this->assertCount( 2, $review->history( 1 ) );
	}

	public function test_reconsidering_one_exception_does_not_reset_another() {
		$review = new OccurrenceReview( $this->db );
		$review->update( 1, 1, 'reviewed_not_issue', '', 7 );
		$review->update( 1, 2, 'reviewed_not_issue', '', 7 );
		$review->update( 1, 1, 'needs_review', '', 7 );
		$this->assertSame( 0, $this->db->tables['wp_elavira_reviewed_exceptions'][1]['active'] );
		$this->assertSame( 1, $this->db->tables['wp_elavira_reviewed_exceptions'][2]['active'] );
	}
	public function test_failure_rolls_back_status_and_manual_verified_is_rejected() {
		$this->db->fail_next_insert = true;
		try {
			( new OccurrenceReview( $this->db ) )->update( 1, 1, 'addressed', '', 7 );
			$this->fail( 'Expected persistence failure' );
		} catch ( \RuntimeException $error ) {
			$this->assertSame( 'open', $this->db->tables['wp_elavira_occurrences'][1]['current_status'] ); }
		$this->expectException( \InvalidArgumentException::class );
		( new OccurrenceReview( $this->db ) )->update( 1, 1, 'verified', '', 7 );
	}
	public function test_legacy_group_history_is_not_guessed() {
		$finding = array(
			'occurrence_count' => 2,
			'current_status'   => 'addressed',
		);
		$this->assertSame( 'needs_review', OccurrenceReview::status( array(), $finding ) );
		$finding['occurrence_count'] = 1;
		$this->assertSame( 'addressed', OccurrenceReview::status( array(), $finding ) );
		$this->assertSame( 'reviewed_not_issue', OccurrenceReview::status( array( 'reviewed_exception_id' => 1 ), $finding ) );
	}
	public function test_stable_id_survives_selector_reordering_but_material_change_does_not() {
		$identity           = new ReviewedConditionIdentity();
		$target             = array(
			'identity_version' => 2,
			'element_id'       => 'a',
			'tag_name'         => 'a',
			'selector'         => 'li:nth-of-type(1)>a',
		);
		$first              = $identity->fingerprints( $target, array( 'reason' => 'one' ), array() );
		$target['selector'] = 'li:nth-of-type(2)>a';
		$this->assertSame( $first, $identity->fingerprints( $target, array( 'reason' => 'one' ), array() ) );
		$this->assertNotSame( $first, $identity->fingerprints( $target, array( 'reason' => 'two' ), array() ) );
		$this->assertFalse(
			$identity->matchable(
				array(
					'identity_version' => 2,
					'tag_name'         => 'a',
					'selector'         => 'li:nth-of-type(1)>a',
				),
				array()
			)
		);
	}
	public function test_previous_decision_is_page_rule_version_and_evidence_scoped() {
		$repo = new OccurrenceRepository( $this->db );
		$row  = $this->db->tables['wp_elavira_occurrences'][1];
		$page = $this->db->tables['wp_elavira_audit_urls'][1]['canonical_url_hash'];
		$this->assertSame( 1, $repo->previous( 2, 'elavira.aria.invalid_attribute', '1.0.0', $page, $row['target_fingerprint'], $row['material_evidence_fingerprint'] )['id'] );
		$this->assertNull( $repo->previous( 2, 'elavira.aria.invalid_attribute', '2.0.0', $page, $row['target_fingerprint'], $row['material_evidence_fingerprint'] ) );
		$this->db->tables['wp_elavira_occurrences'][2] = array_merge( $row, array( 'id' => 2 ) );
		$this->assertNull( $repo->previous( 2, 'elavira.aria.invalid_attribute', '1.0.0', $page, $row['target_fingerprint'], $row['material_evidence_fingerprint'] ) );
	}
}
