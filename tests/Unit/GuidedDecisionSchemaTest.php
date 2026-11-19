<?php
/**
 * Guided Decision schema tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Database\TableNames;
use Elavira\Core\Migrations\GuidedDecisionSchema;
use Elavira\GuidedDecisions\DecisionRepository;
use Elavira\Tests\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

final class GuidedDecisionSchemaTest extends TestCase {
	public function test_completed_decision_is_persisted_as_bounded_history() {
		$database        = new FakeWpdb();
		$GLOBALS['wpdb'] = $database;
		( new GuidedDecisionSchema( $database ) )->up();
		$tables = new TableNames( $database->prefix );
		$database->create_table( $tables->findings() );
		$database->insert( $tables->findings(), array() );
		$finding_id = $database->insert_id;
		$repository = new DecisionRepository( $database );
		$repository->record(
			array(
				'finding_id'       => $finding_id,
				'decision_id'      => 'elavira.decision.link_purpose',
				'decision_version' => '1.0.0',
				'answers'          => array(
					array(
						'step'   => 'purpose_clear',
						'answer' => 'yes',
					),
				),
				'outcome_id'       => 'context_sufficient',
				'recommendation'   => 'Review the context.',
				'actor_user_id'    => 7,
			)
		);
		$rows = $repository->find_by_finding( $finding_id );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'elavira.decision.link_purpose', $rows[0]['decision_id'] );
	}
}
