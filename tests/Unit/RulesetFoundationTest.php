<?php
/**
 * Phase 1 ruleset foundation tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

// phpcs:disable Generic.Files.LineLength

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Audit\Values\AuditScope;
use Elavira\Audit\Values\AuditStatus;
use Elavira\Audit\Values\AuditUrlStatus;
use Elavira\Core\Database\TableNames;
use Elavira\Core\Migrations\CoreAuditSchema;
use Elavira\Core\Migrations\RulesetFoundationSchema;
use Elavira\Core\Migrations\MigrationRunner;
use Elavira\Core\Version\VersionManager;
use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\FindingStatusHistoryRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Findings\Persistence\ReviewedExceptionRepository;
use Elavira\Findings\Values\FindingStatus;
use Elavira\Reports\Application\UpdateFindingStatus;
use Elavira\Rules\Values\RuleOutcome;
use Elavira\Tests\Support\FakeWpdb;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RulesetFoundationTest extends TestCase {
	private $database;
	private $findings;
	private $history;
	private $occurrences;
	private $urls;
	private $exceptions;
	private $finding_id;
	private $url_hash;
	private $identity;

	protected function setUp(): void {
		$this->database  = new FakeWpdb();
		$GLOBALS['wpdb'] = $this->database;
		( new CoreAuditSchema( $this->database ) )->up();
		( new RulesetFoundationSchema( $this->database ) )->up();
		$this->findings    = new FindingRepository( $this->database );
		$this->history     = new FindingStatusHistoryRepository( $this->database );
		$this->occurrences = new OccurrenceRepository( $this->database );
		$this->urls        = new AuditUrlRepository( $this->database );
		$this->exceptions  = new ReviewedExceptionRepository( $this->database );
		$this->identity    = new ReviewedConditionIdentity();

		$audit_id         = ( new AuditRepository( $this->database ) )->create(
			array(
				'label'             => 'Review fixture',
				'scope'             => AuditScope::SINGLE_PAGE,
				'wcag_target'       => '2.2_AA',
				'status'            => AuditStatus::COMPLETED,
				'owner_user_id'     => 7,
				'configuration'     => array(),
				'scanner_version'   => 'scanner-1',
				'ruleset_version'   => 'rules-1',
				'editorial_version' => 'editorial-1',
				'started_at'        => '2026-08-31 12:00:00',
				'completed_at'      => '2026-08-31 12:01:00',
			)
		);
		$url_id           = $this->urls->create(
			array(
				'audit_id'        => $audit_id,
				'canonical_url'   => 'https://example.com/reviewed/',
				'object_id'       => 12,
				'object_type'     => 'page',
				'status'          => AuditUrlStatus::COMPLETED,
				'failure_code'    => '',
				'failure_message' => '',
				'started_at'      => '2026-08-31 12:00:00',
				'completed_at'    => '2026-08-31 12:01:00',
			)
		);
		$this->url_hash   = hash( 'sha256', 'https://example.com/reviewed/' );
		$this->finding_id = $this->findings->create(
			array(
				'audit_id'                => $audit_id,
				'rule_id'                 => 'elavira.link.purpose_review',
				'rule_version'            => '1.0.0',
				'group_key'               => 'rule:link:generic',
				'result_type'             => RuleOutcome::POTENTIAL_ISSUE,
				'category'                => 'potential_issue',
				'priority'                => 'medium',
				'review_state'            => 'needs_review',
				'wcag_criterion'          => '2.4.4',
				'wcag_level'              => 'A',
				'wcag_principle'          => 'operable',
				'editorial_version'       => '1.0.0',
				'summary_snapshot'        => 'Review this link.',
				'affected_page_count'     => 1,
				'occurrence_count'        => 1,
				'active_occurrence_count' => 1,
			)
		);
		$target           = array(
			'tag_name' => 'a',
			'selector' => '#more',
			'locator'  => 'main/link/2',
		);
		$evidence         = array(
			'text_state'       => 'generic',
			'destination_kind' => 'internal',
		);
		$element          = array(
			'tag_name'            => 'a',
			'role'                => 'link',
			'has_accessible_name' => true,
		);
		$fingerprints     = $this->identity->fingerprints( $target, $evidence, $element );
		$this->occurrences->create(
			array(
				'finding_id'                    => $this->finding_id,
				'audit_url_id'                  => $url_id,
				'target'                        => $target,
				'evidence'                      => $evidence,
				'element'                       => $element,
				'target_fingerprint'            => $fingerprints['target'],
				'material_evidence_fingerprint' => $fingerprints['evidence'],
			)
		);
	}

	public function test_migration_installs_required_columns_table_and_is_idempotent() {
		$migration = new RulesetFoundationSchema( $this->database );
		$migration->up();
		$tables = new TableNames( $this->database->prefix );
		$this->assertArrayHasKey( $tables->reviewed_exceptions(), $this->database->tables );
		$this->assertStringContainsString( 'result_type varchar(32)', $this->database->schema_sql );
		$this->assertStringContainsString( 'target_fingerprint char(64)', $this->database->schema_sql );
		$this->assertStringContainsString( 'KEY exact_active', $this->database->schema_sql );
	}

	public function test_failed_ruleset_migration_does_not_advance_schema_version() {
		$GLOBALS['elavira_test_options'] = array( VersionManager::SCHEMA_VERSION_OPTION => '0.5.0' );
		$database                        = new FakeWpdb();
		$database->fail_table_creation   = true;
		$GLOBALS['wpdb']                 = $database;
		$runner                          = new MigrationRunner( new VersionManager(), array( new RulesetFoundationSchema( $database ) ) );
		try {
			$runner->run( '0.6.0' );
			$this->fail( 'Expected migration failure.' );
		} catch ( \RuntimeException $error ) {
			$this->assertSame( '0.5.0', get_option( VersionManager::SCHEMA_VERSION_OPTION ) );
		}
	}

	public function test_reviewed_not_issue_creates_auditable_exact_exception_and_history() {
		$this->service()->update( $this->finding_id, FindingStatus::REVIEWED_NOT_ISSUE, 'Acceptable in this context.', 7 );
		$this->assertSame( FindingStatus::REVIEWED_NOT_ISSUE, $this->findings->find( $this->finding_id )['current_status'] );
		$this->assertSame( array( FindingStatus::OPEN, FindingStatus::REVIEWED_NOT_ISSUE ), array_column( $this->history->find_by_finding( $this->finding_id ), 'status' ) );
		$exception = $this->exceptions->find_by_source_finding( $this->finding_id )[0];
		$this->assertNotNull( $this->exceptions->match( 'elavira.link.purpose_review', '1.0.0', $this->url_hash, $exception['target_fingerprint'], $exception['material_evidence_fingerprint'] ) );
		$this->assertNull( $this->exceptions->match( 'elavira.link.purpose_review', '2.0.0', $this->url_hash, $exception['target_fingerprint'], $exception['material_evidence_fingerprint'] ) );
		$this->assertNull( $this->exceptions->match( 'elavira.link.purpose_review', '1.0.0', hash( 'sha256', 'https://example.com/other/' ), $exception['target_fingerprint'], $exception['material_evidence_fingerprint'] ) );
		$this->assertNull( $this->exceptions->match( 'elavira.other.rule', '1.0.0', $this->url_hash, $exception['target_fingerprint'], $exception['material_evidence_fingerprint'] ) );
	}

	public function test_material_or_target_change_prevents_selector_only_suppression() {
		$this->service()->update( $this->finding_id, FindingStatus::REVIEWED_NOT_ISSUE, '', 7 );
		$exception        = $this->exceptions->find_by_source_finding( $this->finding_id )[0];
		$changed_evidence = $this->identity->fingerprints(
			array(
				'tag_name' => 'a',
				'selector' => '#more',
				'locator'  => 'main/link/2',
			),
			array( 'text_state' => 'ambiguous' ),
			array(
				'tag_name' => 'a',
				'role'     => 'link',
			)
		);
		$this->assertNull( $this->exceptions->match( 'elavira.link.purpose_review', '1.0.0', $this->url_hash, $exception['target_fingerprint'], $changed_evidence['evidence'] ) );
		$this->assertNull( $this->exceptions->match( 'elavira.link.purpose_review', '1.0.0', $this->url_hash, $changed_evidence['target'], $exception['material_evidence_fingerprint'] ) );
	}

	public function test_page_and_global_reset_deactivate_only_future_suppression() {
		$this->service()->update( $this->finding_id, FindingStatus::REVIEWED_NOT_ISSUE, '', 7 );
		$exception = $this->exceptions->find_by_source_finding( $this->finding_id )[0];
		$this->exceptions->reset_page( $this->url_hash, 7 );
		$this->assertNull( $this->exceptions->match( $exception['rule_id'], $exception['rule_version'], $this->url_hash, $exception['target_fingerprint'], $exception['material_evidence_fingerprint'] ) );
		$this->assertCount( 2, $this->history->find_by_finding( $this->finding_id ) );
		$this->assertNotEmpty( $this->findings->find( $this->finding_id ) );
		$this->exceptions->reset_all( 7 );
		$this->assertCount( 1, $this->exceptions->find_by_source_finding( $this->finding_id ) );
	}

	public function test_automatic_failure_cannot_be_reviewed_as_not_an_issue() {
		$finding             = &$this->database->tables[ ( new TableNames( 'wp_' ) )->findings() ][ $this->finding_id ];
		$finding['category'] = 'detected_failure';
		$this->expectException( InvalidArgumentException::class );
		$this->service()->update( $this->finding_id, FindingStatus::REVIEWED_NOT_ISSUE, '', 7 );
	}

	public function test_reviewed_structural_warning_matches_only_unchanged_evidence() {
		$finding                                     = &$this->database->tables[ ( new TableNames( 'wp_' ) )->findings() ][ $this->finding_id ];
		$finding['rule_id']                          = 'elavira.structure.missing_h1';
		$finding['category']                         = 'human_review';
		$finding['result_type']                      = RuleOutcome::HUMAN_REVIEW;
		$finding['review_state']                     = 'needs_review';
		$occurrence                                  = &$this->database->tables[ ( new TableNames( 'wp_' ) )->occurrences() ][1];
		$occurrence['evidence_json']                 = wp_json_encode( array( 'h1_count' => 0 ) );
		$occurrence['target_json']                   = wp_json_encode(
			array(
				'tag_name' => 'html',
				'selector' => 'html',
			)
		);
		$occurrence['element_json']                  = wp_json_encode( array( 'tag_name' => 'html' ) );
		$fingerprints                                = $this->identity->fingerprints( json_decode( $occurrence['target_json'], true ), json_decode( $occurrence['evidence_json'], true ), json_decode( $occurrence['element_json'], true ) );
		$occurrence['target_fingerprint']            = $fingerprints['target'];
		$occurrence['material_evidence_fingerprint'] = $fingerprints['evidence'];

		$this->service()->update( $this->finding_id, FindingStatus::REVIEWED_NOT_ISSUE, 'Reviewed structure.', 7 );
		$exception = $this->exceptions->find_by_source_finding( $this->finding_id )[0];
		$this->assertNotNull( $this->exceptions->match( $finding['rule_id'], $finding['rule_version'], $this->url_hash, $exception['target_fingerprint'], $exception['material_evidence_fingerprint'] ) );
		$changed = $this->identity->fingerprints( json_decode( $occurrence['target_json'], true ), array( 'h1_count' => 1 ), json_decode( $occurrence['element_json'], true ) );
		$this->assertNull( $this->exceptions->match( $finding['rule_id'], $finding['rule_version'], $this->url_hash, $changed['target'], $changed['evidence'] ) );
	}

	private function service() {
		return new UpdateFindingStatus( $this->findings, $this->history, $this->database, $this->occurrences, $this->urls, $this->exceptions, $this->identity );
	}
}
