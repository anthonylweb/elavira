<?php
/**
 * Statement schema and persistence tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Database\TableNames;
use Elavira\Core\Migrations\StatementSchema;
use Elavira\Statement\RecommendationRepository;
use Elavira\Statement\RecommendationGenerator;
use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\Statement\StatementFields;
use Elavira\Statement\StatementRepository;
use Elavira\Tests\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

final class StatementSchemaTest extends TestCase {
	private $database;
	private $tables;

	protected function setUp(): void {
		$this->database  = new FakeWpdb();
		$GLOBALS['wpdb'] = $this->database;
		$this->tables    = new TableNames( $this->database->prefix );
		( new StatementSchema( $this->database ) )->up();
		$this->database->create_table( $this->tables->audits() );
		$this->database->create_table( $this->tables->findings() );
	}

	public function test_draft_creation_and_publish_revision_keep_a_published_snapshot() {
		$repository = new StatementRepository( $this->database );
		$id         = $repository->create( StatementFields::defaults(), 7 );
		$draft      = $repository->find();
		$this->assertSame( 'draft', $draft['status'] );
		$this->assertSame( '', $draft['published_fields_json'] );
		$this->assertNull( $repository->find_published_by_post( 22 ) );
		$repository->publish( $id, 22, StatementFields::defaults(), 7, '2026-08-31' );
		$published = $repository->find();
		$this->assertSame( 'published', $published['status'] );
		$this->assertCount( 1, $repository->revisions( $id ) );
		$changed               = StatementFields::defaults();
		$changed['commitment'] = 'Unpublished draft edit';
		$published_snapshot    = $published['published_fields_json'];
		$repository->save_draft( $id, $changed, '2026-08-31' );
		$this->assertSame( $published_snapshot, $repository->find()['published_fields_json'] );
	}

	public function test_accepted_limitation_generates_one_traceable_recommendation() {
		$this->database->create_table( $this->tables->audit_urls() );
		$this->database->create_table( $this->tables->guided_decisions() );
		$this->database->insert( $this->tables->audits(), array() );
		$audit_id = $this->database->insert_id;
		$this->database->insert(
			$this->tables->findings(),
			array(
				'audit_id'         => $audit_id,
				'rule_id'          => 'elavira.image.alt_missing',
				'rule_version'     => '1.0.0',
				'group_key'        => 'image',
				'current_status'   => 'accepted_limitation',
				'category'         => 'detected_failure',
				'summary_snapshot' => 'An image lacks alternative text.',
			)
		);
		$repository = new RecommendationRepository( $this->database );
		$generator  = new RecommendationGenerator( $this->database, $repository, CoreDecisionRegistry::create() );
		$generator->generate( $audit_id );
		$generator->generate( $audit_id );
		$this->assertCount( 1, $this->database->tables[ $this->tables->statement_recommendations() ] );
		$row = reset( $this->database->tables[ $this->tables->statement_recommendations() ] );
		$this->assertSame( $audit_id, $row['audit_id'] );
		$this->assertSame( 1, $row['finding_id'] );
	}

	public function test_reviewed_not_an_issue_does_not_generate_a_statement_recommendation() {
		$this->database->create_table( $this->tables->audit_urls() );
		$this->database->create_table( $this->tables->guided_decisions() );
		$this->database->insert( $this->tables->audits(), array() );
		$audit_id = $this->database->insert_id;
		$this->database->insert(
			$this->tables->findings(),
			array(
				'audit_id'         => $audit_id,
				'rule_id'          => 'elavira.link.purpose_review',
				'rule_version'     => '1.0.0',
				'group_key'        => 'link',
				'current_status'   => 'reviewed_not_issue',
				'category'         => 'potential_issue',
				'summary_snapshot' => 'The link was acceptable in context.',
			)
		);
		$this->database->insert(
			$this->tables->guided_decisions(),
			array(
				'finding_id'       => 1,
				'decision_id'      => 'elavira.decision.link_purpose',
				'decision_version' => '1.0.0',
				'outcome_id'       => 'remediation_likely',
			)
		);
		( new RecommendationGenerator( $this->database, new RecommendationRepository( $this->database ), CoreDecisionRegistry::create() ) )->generate( $audit_id );
		$this->assertCount( 0, $this->database->tables[ $this->tables->statement_recommendations() ] );
	}

	public function test_unmapped_best_practice_does_not_generate_a_statement_recommendation() {
		$this->database->create_table( $this->tables->audit_urls() );
		$this->database->create_table( $this->tables->guided_decisions() );
		$this->database->insert( $this->tables->audits(), array() );
		$audit_id = $this->database->insert_id;
		$this->database->insert(
			$this->tables->findings(),
			array(
				'audit_id'         => $audit_id,
				'rule_id'          => 'elavira.aria.redundant_role',
				'rule_version'     => '1.0.0',
				'group_key'        => 'redundant-role',
				'current_status'   => 'accepted_limitation',
				'category'         => 'potential_issue',
				'wcag_criterion'   => '',
				'summary_snapshot' => 'A native element repeats its implicit role.',
			)
		);
		( new RecommendationGenerator( $this->database, new RecommendationRepository( $this->database ), CoreDecisionRegistry::create() ) )->generate( $audit_id );
		$this->assertCount( 0, $this->database->tables[ $this->tables->statement_recommendations() ] );
	}

	public function test_compatible_guided_decision_can_generate_human_review_recommendation() {
		$this->database->create_table( $this->tables->audit_urls() );
		$this->database->create_table( $this->tables->guided_decisions() );
		$this->database->insert( $this->tables->audits(), array() );
		$audit_id = $this->database->insert_id;
		$this->database->insert(
			$this->tables->findings(),
			array(
				'audit_id'         => $audit_id,
				'rule_id'          => 'elavira.link.purpose_review',
				'rule_version'     => '1.0.0',
				'group_key'        => 'link',
				'current_status'   => 'open',
				'category'         => 'human_review',
				'summary_snapshot' => 'Link purpose needs review.',
			)
		);
		$this->database->insert(
			$this->tables->guided_decisions(),
			array(
				'finding_id'       => 1,
				'decision_id'      => 'elavira.decision.link_purpose',
				'decision_version' => '1.0.0',
				'outcome_id'       => 'context_sufficient',
			)
		);
		( new RecommendationGenerator( $this->database, new RecommendationRepository( $this->database ), CoreDecisionRegistry::create() ) )->generate( $audit_id );
		$row = reset( $this->database->tables[ $this->tables->statement_recommendations() ] );
		$this->assertSame( 'human_review', $row['kind'] );
		$this->assertSame( 1, $row['guided_decision_id'] );
	}

	public function test_image_review_requires_compatible_human_decision_before_statement_recommendation() {
		$this->database->create_table( $this->tables->audit_urls() );
		$this->database->create_table( $this->tables->guided_decisions() );
		$this->database->insert( $this->tables->audits(), array() );
		$audit_id = $this->database->insert_id;
		$this->database->insert(
			$this->tables->findings(),
			array(
				'audit_id'         => $audit_id,
				'rule_id'          => 'elavira.images.alt_quality_review',
				'rule_version'     => '1.0.0',
				'group_key'        => 'image-review',
				'current_status'   => 'needs_review',
				'category'         => 'human_review',
				'wcag_criterion'   => '1.1.1',
				'summary_snapshot' => 'Review this text alternative in context.',
			)
		);
		$repository = new RecommendationRepository( $this->database );
		$generator  = new RecommendationGenerator( $this->database, $repository, CoreDecisionRegistry::create() );
		$generator->generate( $audit_id );
		$this->assertCount( 0, $this->database->tables[ $this->tables->statement_recommendations() ] );
		$this->database->insert(
			$this->tables->guided_decisions(),
			array(
				'finding_id'       => 1,
				'decision_id'      => 'elavira.decision.alternative_text',
				'decision_version' => '1.0.0',
				'outcome_id'       => 'further_review',
			)
		);
		$generator->generate( $audit_id );
		$this->assertSame( 'human_review', reset( $this->database->tables[ $this->tables->statement_recommendations() ] )['kind'] );
	}

	public function test_zero_findings_and_incompatible_decisions_create_no_recommendation() {
		$this->database->create_table( $this->tables->audit_urls() );
		$this->database->create_table( $this->tables->guided_decisions() );
		$this->database->insert( $this->tables->audits(), array() );
		$audit_id  = $this->database->insert_id;
		$generator = new RecommendationGenerator( $this->database, new RecommendationRepository( $this->database ), CoreDecisionRegistry::create() );
		$generator->generate( $audit_id );
		$this->assertCount( 0, $this->database->tables[ $this->tables->statement_recommendations() ] );
		$this->database->insert(
			$this->tables->findings(),
			array(
				'audit_id'         => $audit_id,
				'rule_id'          => 'elavira.link.purpose_review',
				'rule_version'     => '9.0.0',
				'group_key'        => 'link',
				'current_status'   => 'open',
				'category'         => 'human_review',
				'summary_snapshot' => 'Historical link review.',
			)
		);
		$this->database->insert(
			$this->tables->guided_decisions(),
			array(
				'finding_id'       => 1,
				'decision_id'      => 'elavira.decision.link_purpose',
				'decision_version' => '1.0.0',
				'outcome_id'       => 'context_sufficient',
			)
		);
		$generator->generate( $audit_id );
		$this->assertCount( 0, $this->database->tables[ $this->tables->statement_recommendations() ] );
	}

	public function test_recommendation_is_deduplicated_and_review_state_is_retained() {
		$this->database->insert( $this->tables->audits(), array() );
		$audit_id = $this->database->insert_id;
		$this->database->insert( $this->tables->findings(), array() );
		$finding_id = $this->database->insert_id;
		$repository = new RecommendationRepository( $this->database );
		$data       = array(
			'audit_id'           => $audit_id,
			'finding_id'         => $finding_id,
			'source_fingerprint' => str_repeat( 'a', 64 ),
			'kind'               => 'known_limitation',
			'suggested_text'     => 'Suggested limitation.',
		);
		$first      = $repository->create_once( $data );
		$this->assertSame( $first, $repository->create_once( $data ) );
		$repository->review( $first, 1, 'dismissed', '', 'already_covered' );
		$this->assertSame( 'dismissed', $repository->find( $first )['state'] );
		$this->assertSame( 'already_covered', $repository->find( $first )['dismissal_reason'] );
	}
}
