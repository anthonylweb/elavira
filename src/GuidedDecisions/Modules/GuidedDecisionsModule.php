<?php
/**
 * Guided Decisions module wiring.
 *
 * @package Elavira
 */

namespace Elavira\GuidedDecisions\Modules;

// phpcs:disable Squiz.Commenting.VariableComment -- The single property is documented inline.

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Core\Contracts\Module;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\FindingStatusHistoryRepository;
use Elavira\GuidedDecisions\Admin\DecisionPage;
use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\GuidedDecisions\DecisionNavigator;
use Elavira\GuidedDecisions\DecisionRepository;
use Elavira\GuidedDecisions\DecisionState;
use Elavira\Learn\CoreTopicRegistry;
use Elavira\Reports\Application\UpdateFindingStatus;
use Elavira\Rules\CoreRuleRegistry;

/** Registers the isolated Core Guided Decision routes. */
final class GuidedDecisionsModule implements Module {
	/** Page controller. @var DecisionPage|null */
	private $page;

	/** Register hooks. @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_admin' ) );
		add_action( 'admin_post_elavira_decision_navigate', array( $this, 'navigate' ) );
		add_action( 'admin_post_elavira_decision_complete', array( $this, 'complete' ) );
	}

	/** Register the admin page. @return void */
	public function register_admin() {
		$this->page()->register(); }
	/** Process navigation. @return void */
	public function navigate() {
		$this->page()->navigate(); }
	/** Process completion. @return void */
	public function complete() {
		$this->page()->complete(); }

	/** Build the page once. @return DecisionPage */
	private function page() {
		if ( null === $this->page ) {
			$database   = $GLOBALS['wpdb'];
			$findings   = new FindingRepository( $database );
			$history    = new FindingStatusHistoryRepository( $database );
			$this->page = new DecisionPage(
				array(
					'audits'      => new AuditRepository( $database ),
					'findings'    => $findings,
					'occurrences' => new \Elavira\Findings\Persistence\OccurrenceRepository( $database ),
					'registry'    => CoreDecisionRegistry::create(),
					'navigator'   => new DecisionNavigator(),
					'state'       => new DecisionState(),
					'repository'  => new DecisionRepository( $database ),
					'status'      => new UpdateFindingStatus( $findings, $history ),
					'rules'       => CoreRuleRegistry::create(),
					'learn'       => CoreTopicRegistry::create(),
				)
			);
		}
		return $this->page;
	}
}
