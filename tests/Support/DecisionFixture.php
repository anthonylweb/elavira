<?php
/**
 * Isolated persisted finding and real decision controller for UX tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Support;

use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\GuidedDecisions\Admin\DecisionPage;
use Elavira\GuidedDecisions\DecisionNavigator;
use Elavira\GuidedDecisions\DecisionState;
use Elavira\GuidedDecisions\DecisionRepository;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Learn\CoreTopicRegistry;
use Elavira\Core\Capabilities\Capability;
use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Findings\Persistence\FindingStatusHistoryRepository;
use Elavira\Reports\Application\UpdateFindingStatus;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment -- Test fixture.

/** Uses repository records rather than hand-authored output HTML. */
final class DecisionFixture {
	public $database;
	public $page;
	public $decision;
	public $finding;
	public $repository;

	public function __construct( $id, array $element = array(), array $evidence = array(), $historical = false ) {
		$this->database  = new FakeWpdb();
		$GLOBALS['wpdb'] = $this->database;
		( new \Elavira\Core\Migrations\CoreAuditSchema( $this->database ) )->up();
		( new \Elavira\Core\Migrations\GuidedDecisionSchema( $this->database ) )->up();
		$audits = new AuditRepository( $this->database );
		$audits->create(
			array(
				'label'             => 'Decision fixture',
				'scope'             => 'single_page',
				'wcag_target'       => '2.2_AA',
				'status'            => 'completed',
				'owner_user_id'     => 7,
				'configuration'     => array(),
				'scanner_version'   => 'fixture',
				'ruleset_version'   => 'fixture',
				'editorial_version' => 'fixture',
			)
		);
		$registry = CoreDecisionRegistry::create();
		foreach ( $registry->all() as $definition ) {
			if ( 'elavira.decision.' . $id === $definition->get( 'id' ) ) {
				$this->decision = $definition;
			}
		}
		$findings    = new FindingRepository( $this->database );
		$occurrences = new OccurrenceRepository( $this->database );
		$rules       = $this->decision->get( 'rules' );
		if ( $rules ) {
			$supported = $rules[0];
			$rule      = CoreRuleRegistry::create()->get( $supported['id'], $supported['version'] );
			$findings->create(
				array(
					'audit_id'            => 1,
					'rule_id'             => $rule->id(),
					'rule_version'        => $rule->version(),
					'group_key'           => 'fixture',
					'category'            => $rule->classification(),
					'priority'            => $rule->priority(),
					'review_state'        => 'needs_review',
					'editorial_version'   => $rule->editorial_version(),
					'summary_snapshot'    => $rule->editorial()['summary'],
					'affected_page_count' => 1,
					'occurrence_count'    => 1,
				)
			);
			$this->finding = $findings->find( 1 );
			if ( ! $historical ) {
				$urls = new AuditUrlRepository( $this->database );
				$urls->create(
					array(
						'audit_id'      => 1,
						'canonical_url' => 'https://example.org/review/',
						'status'        => 'completed',
					)
				);
				$occurrences->create(
					array(
						'finding_id'   => 1,
						'audit_url_id' => 1,
						'target'       => array( 'selector' => '#review-target' ),
						'element'      => $element,
						'evidence'     => $evidence,
					)
				);
			}
		} else {
			$this->finding = array(
				'id'       => 0,
				'audit_id' => 0,
			);
		}
		$this->repository = new DecisionRepository( $this->database );
		$this->page       = new DecisionPage(
			array(
				'audits'      => $audits,
				'findings'    => $findings,
				'occurrences' => $occurrences,
				'registry'    => $registry,
				'navigator'   => new DecisionNavigator(),
				'state'       => new DecisionState(),
				'repository'  => $this->repository,
				'status'      => new UpdateFindingStatus( $findings, new FindingStatusHistoryRepository( $this->database ) ),
				'rules'       => CoreRuleRegistry::create(),
				'learn'       => CoreTopicRegistry::create(),
			)
		);
	}

	public function state( array $answers ) {
		return ( new DecisionState() )->encode( $answers, $this->finding['id'] . '|' . $this->decision->get( 'id' ) . '|' . $this->decision->get( 'version' ) );
	}

	public function render( array $answers = array() ) {
		$GLOBALS['elavira_test_current_user_id'] = 7;
		$GLOBALS['elavira_test_capabilities']    = array(
			Capability::VIEW_REPORTS => true,
			Capability::MANAGE       => true,
		);
		$_GET                                    = array(
			'finding'  => $this->finding['id'],
			'decision' => $this->decision->get( 'id' ),
			'state'    => $this->state( $answers ),
		);
		ob_start();
		try {
			$this->page->render();
			return ob_get_contents();
		} finally {
			ob_end_clean();
			$_GET = array();
		}
	}
}
