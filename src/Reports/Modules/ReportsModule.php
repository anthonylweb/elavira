<?php
/**
 * Core Reports module wiring.
 *
 * @package Elavira
 */

namespace Elavira\Reports\Modules;

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Core\Contracts\Module;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\FindingStatusHistoryRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Findings\Persistence\ReviewedExceptionRepository;
use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\GuidedDecisions\DecisionRepository;
use Elavira\Learn\CoreTopicRegistry;
use Elavira\Reports\Admin\ReportsPage;
use Elavira\Reports\Application\HistoricalRuleResolver;
use Elavira\Reports\Application\UpdateFindingStatus;
use Elavira\Reports\Export\XlsxExporter;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Statement\RecommendationRepository;

/** Registers reports independently from scanner execution. */
final class ReportsModule implements Module {
	/** Reports page controller.
	 *
	 * @var ReportsPage|null
	 */
	private $page;

	/** Register Reports hooks. @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_admin' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_elavira_update_finding', array( $this, 'update_status' ) );
		add_action( 'admin_post_elavira_export_xlsx', array( $this, 'export' ) );
		add_action( 'admin_post_elavira_reset_reviewed_exceptions', array( $this, 'reset_exceptions' ) );
	}

	/** Register menu routes. @return void */
	public function register_admin() {
		$this->page()->register(); }
	/** Load scoped assets.
	 *
	 * @param string $hook_suffix Screen hook.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		$this->page()->enqueue( $hook_suffix ); }
	/** Handle finding status updates. @return void */
	public function update_status() {
		$this->page()->update_status(); }
	/** Stream an XLSX export. @return void */
	public function export() {
		$this->page()->export(); }
	/** Handle reviewed-exception reset. @return void */
	public function reset_exceptions() {
		$this->page()->reset_exceptions(); }

	/** Build the page once per request. @return ReportsPage */
	private function page() {
		if ( null === $this->page ) {
			$database    = $GLOBALS['wpdb'];
			$audits      = new AuditRepository( $database );
			$urls        = new AuditUrlRepository( $database );
			$findings    = new FindingRepository( $database );
			$occurrences = new OccurrenceRepository( $database );
			$history     = new FindingStatusHistoryRepository( $database );
			$exceptions  = new ReviewedExceptionRepository( $database );
			$this->page  = new ReportsPage( $audits, $urls, $findings, $occurrences, $history, new HistoricalRuleResolver( CoreRuleRegistry::create() ), new UpdateFindingStatus( $findings, $history, $database, $occurrences, $urls, $exceptions, new ReviewedConditionIdentity() ), new XlsxExporter(), CoreDecisionRegistry::create(), new DecisionRepository( $database ), new RecommendationRepository( $database ), CoreTopicRegistry::create(), $exceptions );
		}
		return $this->page;
	}
}
