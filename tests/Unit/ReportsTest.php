<?php
/**
 * Core Reports application tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Values\AuditScope;
use Elavira\Audit\Values\AuditStatus;
use Elavira\Core\Migrations\CoreAuditSchema;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\FindingStatusHistoryRepository;
use Elavira\Findings\Values\FindingStatus;
use Elavira\Reports\Application\HistoricalRuleResolver;
use Elavira\Reports\Application\UpdateFindingStatus;
use Elavira\Reports\Admin\ReportsPage;
use Elavira\Reports\Export\XlsxExporter;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Tests\Support\FakeWpdb;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReportsTest extends TestCase {
	private $database;
	private $findings;
	private $history;
	private $finding_id;

	protected function setUp(): void {
		$this->database  = new FakeWpdb();
		$GLOBALS['wpdb'] = $this->database;
		( new CoreAuditSchema( $this->database ) )->up();
		$audits           = new AuditRepository( $this->database );
		$audit_id         = $audits->create(
			array(
				'label'             => 'Report fixture',
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
		$this->findings   = new FindingRepository( $this->database );
		$this->history    = new FindingStatusHistoryRepository( $this->database );
		$this->finding_id = $this->findings->create(
			array(
				'audit_id'            => $audit_id,
				'rule_id'             => 'elavira.image.alt_missing',
				'rule_version'        => '1.0.0',
				'group_key'           => 'rule:alt',
				'category'            => 'detected_failure',
				'priority'            => 'high',
				'review_state'        => 'not_required',
				'wcag_criterion'      => '1.1.1',
				'wcag_level'          => 'A',
				'wcag_principle'      => 'perceivable',
				'editorial_version'   => '1.0.0',
				'summary_snapshot'    => 'Historical summary.',
				'affected_page_count' => 1,
				'occurrence_count'    => 1,
			)
		);
	}

	public function test_accepted_limitation_updates_finding_and_appends_history() {
		$service = new UpdateFindingStatus( $this->findings, $this->history );
		$service->update( $this->finding_id, FindingStatus::ACCEPTED_LIMITATION, 'Intentional documented constraint.', 7 );
		$this->assertSame( FindingStatus::ACCEPTED_LIMITATION, $this->findings->find( $this->finding_id )['current_status'] );
		$entries = $this->history->find_by_finding( $this->finding_id );
		$this->assertCount( 2, $entries );
		$this->assertSame( 'Intentional documented constraint.', $entries[1]['reason'] );
	}

	public function test_manual_verified_transition_is_rejected() {
		$this->expectException( InvalidArgumentException::class );
		( new UpdateFindingStatus( $this->findings, $this->history ) )->update( $this->finding_id, FindingStatus::VERIFIED, '', 7 );
	}

	public function test_historical_resolution_requires_exact_editorial_version() {
		$resolver = new HistoricalRuleResolver( CoreRuleRegistry::create() );
		$finding  = $this->findings->find( $this->finding_id );
		$this->assertTrue( $resolver->resolve( $finding )['available'] );
		$finding['editorial_version'] = '9.0.0';
		$result                       = $resolver->resolve( $finding );
		$this->assertFalse( $result['available'] );
		$this->assertSame( 'Historical summary.', $result['editorial']['summary'] );
	}

	public function test_spreadsheet_cells_neutralize_formula_prefixes() {
		$exporter = new XlsxExporter();
		foreach ( array( '=1+1', '+cmd', '-2+3', '@SUM(A1:A2)' ) as $value ) {
			$this->assertStringStartsWith( "'", $exporter->safe_cell( $value ) );
		}
		$this->assertSame( 'ordinary text', $exporter->safe_cell( 'ordinary text' ) );
	}

	public function test_unmapped_best_practice_has_non_conformance_report_label() {
		$page   = ( new \ReflectionClass( ReportsPage::class ) )->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod( ReportsPage::class, 'wcag_label' );
		$this->assertSame(
			'Best practice — no direct WCAG mapping',
			$method->invoke(
				$page,
				array(
					'wcag_criterion' => '',
					'wcag_level'     => '',
				)
			)
		);
	}
}
