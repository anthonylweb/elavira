<?php
/**
 * Core audit persistence tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Audit\Values\AuditScope;
use Elavira\Audit\Values\AuditStatus;
use Elavira\Audit\Values\AuditUrlStatus;
use Elavira\Core\Migrations\CoreAuditSchema;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\FindingStatusHistoryRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Findings\Values\FindingStatus;
use Elavira\Findings\Values\Priority;
use Elavira\Findings\Values\ReviewState;
use Elavira\Tests\Support\FakeWpdb;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AuditPersistenceTest extends TestCase {
	private $database;
	private $audits;
	private $urls;
	private $findings;
	private $occurrences;
	private $history;

	protected function setUp(): void {
		$this->database  = new FakeWpdb();
		$GLOBALS['wpdb'] = $this->database;
		( new CoreAuditSchema( $this->database ) )->up();

		$this->audits      = new AuditRepository( $this->database );
		$this->urls        = new AuditUrlRepository( $this->database );
		$this->findings    = new FindingRepository( $this->database );
		$this->occurrences = new OccurrenceRepository( $this->database );
		$this->history     = new FindingStatusHistoryRepository( $this->database );
	}

	public function test_persists_and_retrieves_the_single_page_audit_graph() {
		$audit_id   = $this->create_audit();
		$url_id     = $this->create_url( $audit_id, 'https://example.com/accessibility/' );
		$finding_id = $this->create_finding( $audit_id );

		$first  = $this->occurrences->create(
			array(
				'finding_id'   => $finding_id,
				'audit_url_id' => $url_id,
				'target'       => array( 'selector' => 'main img:nth-of-type(1)' ),
				'evidence'     => array(
					'attribute' => 'alt',
					'outcome'   => 'missing',
				),
				'element'      => array( 'tag' => 'img' ),
			)
		);
		$second = $this->occurrences->create(
			array(
				'finding_id'   => $finding_id,
				'audit_url_id' => $url_id,
				'target'       => array( 'selector' => 'main img:nth-of-type(2)' ),
				'evidence'     => array(
					'attribute' => 'alt',
					'outcome'   => 'missing',
				),
				'element'      => array( 'tag' => 'img' ),
			)
		);

		$this->history->record(
			array(
				'finding_id'    => $finding_id,
				'status'        => FindingStatus::ADDRESSED,
				'actor_user_id' => 7,
				'reason'        => 'Content editor updated the images.',
			)
		);

		$this->assertSame( AuditScope::SINGLE_PAGE, $this->audits->find( $audit_id )['scope'] );
		$this->assertCount( 1, $this->urls->find_by_audit( $audit_id ) );
		$this->assertCount( 1, $this->findings->find_by_audit( $audit_id ) );
		$this->assertSame( array( $first, $second ), array_column( $this->occurrences->find_by_finding( $finding_id ), 'id' ) );
		$this->assertCount( 2, $this->occurrences->find_by_audit_url( $url_id ) );
		$this->assertSame( array( FindingStatus::OPEN, FindingStatus::ADDRESSED ), array_column( $this->history->find_by_finding( $finding_id ), 'status' ) );
	}

	public function test_rejects_invalid_parent_references() {
		$this->expectException( InvalidArgumentException::class );
		$this->create_url( 999, 'https://example.com/' );
	}

	public function test_occurrence_must_join_records_from_the_same_audit() {
		$first_audit  = $this->create_audit();
		$second_audit = $this->create_audit( 'Second audit' );
		$url_id       = $this->create_url( $first_audit, 'https://example.com/one/' );
		$finding_id   = $this->create_finding( $second_audit );

		$this->expectException( InvalidArgumentException::class );
		$this->occurrences->create(
			array(
				'finding_id'   => $finding_id,
				'audit_url_id' => $url_id,
				'target'       => array( 'selector' => 'main' ),
				'evidence'     => array( 'outcome' => 'failure' ),
				'element'      => array( 'tag' => 'main' ),
			)
		);
	}

	public function test_evidence_rejects_sensitive_fields_and_html_blobs() {
		$audit_id   = $this->create_audit();
		$url_id     = $this->create_url( $audit_id, 'https://example.com/' );
		$finding_id = $this->create_finding( $audit_id );

		$this->expectException( InvalidArgumentException::class );
		$this->occurrences->create(
			array(
				'finding_id'   => $finding_id,
				'audit_url_id' => $url_id,
				'target'       => array( 'selector' => 'form' ),
				'evidence'     => array( 'password' => 'secret' ),
				'element'      => array( 'html' => '<form>...</form>' ),
			)
		);
	}

	public function test_evidence_payload_is_bounded() {
		$audit_id   = $this->create_audit();
		$url_id     = $this->create_url( $audit_id, 'https://example.com/' );
		$finding_id = $this->create_finding( $audit_id );

		$this->expectException( InvalidArgumentException::class );
		$this->occurrences->create(
			array(
				'finding_id'   => $finding_id,
				'audit_url_id' => $url_id,
				'target'       => array( 'selector' => 'main' ),
				'evidence'     => array( 'detail' => str_repeat( 'x', 17000 ) ),
				'element'      => array( 'tag' => 'main' ),
			)
		);
	}

	public function test_core_repository_rejects_site_wide_scope() {
		$this->expectException( InvalidArgumentException::class );
		$this->audits->create(
			array(
				'label'             => 'Unsupported scope',
				'scope'             => 'site_wide',
				'wcag_target'       => '2.2_AA',
				'status'            => AuditStatus::PENDING,
				'configuration'     => array(),
				'scanner_version'   => 'browser-contract-1',
				'ruleset_version'   => 'rules-1',
				'editorial_version' => 'editorial-1',
			)
		);
	}

	public function test_review_state_is_distinct_from_priority() {
		$this->assertNotContains( FindingStatus::NEEDS_REVIEW, Priority::all() );
		$this->assertContains( ReviewState::NEEDS_REVIEW, ReviewState::all() );
	}

	private function create_audit( $label = 'Single page audit' ) {
		return $this->audits->create(
			array(
				'label'             => $label,
				'scope'             => AuditScope::SINGLE_PAGE,
				'wcag_target'       => '2.2_AA',
				'status'            => AuditStatus::PENDING,
				'owner_user_id'     => 7,
				'configuration'     => array( 'source' => 'admin_bar' ),
				'scanner_version'   => 'browser-contract-1',
				'ruleset_version'   => 'rules-1',
				'editorial_version' => 'editorial-1',
			)
		);
	}

	private function create_url( $audit_id, $url ) {
		return $this->urls->create(
			array(
				'audit_id'      => $audit_id,
				'canonical_url' => $url,
				'object_id'     => 42,
				'object_type'   => 'page',
				'status'        => AuditUrlStatus::PENDING,
			)
		);
	}

	private function create_finding( $audit_id ) {
		return $this->findings->create(
			array(
				'audit_id'            => $audit_id,
				'rule_id'             => 'image-alt',
				'rule_version'        => '1.0.0',
				'group_key'           => 'missing-alt',
				'category'            => 'detected_failure',
				'priority'            => Priority::HIGH,
				'review_state'        => ReviewState::NOT_REQUIRED,
				'wcag_criterion'      => '1.1.1',
				'wcag_level'          => 'A',
				'wcag_principle'      => 'perceivable',
				'editorial_version'   => 'editorial-1',
				'summary_snapshot'    => 'Images are missing text alternatives.',
				'affected_page_count' => 1,
				'occurrence_count'    => 2,
			)
		);
	}
}
