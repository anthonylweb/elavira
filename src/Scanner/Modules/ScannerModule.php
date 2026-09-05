<?php
/**
 * Core scanner module wiring.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Modules;

// phpcs:disable Squiz.Commenting.FunctionComment -- Compact module wiring is documented at the class boundary.

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Core\Contracts\Module;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Findings\Persistence\ReviewedExceptionRepository;
use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\Rules\Browser\BrowserEvidenceValidator;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Rules\Normalization\RuleResultNormalizer;
use Elavira\Rules\Grouping\SupersessionResolver;
use Elavira\Scanner\Application\CompleteScan;
use Elavira\Scanner\Application\StartSinglePageScan;
use Elavira\Scanner\Application\SubmitScanBatch;
use Elavira\Scanner\Admin\RunAuditPage;
use Elavira\Scanner\Admin\ContextualAuditLinks;
use Elavira\Scanner\Frontend\ScanPage;
use Elavira\Scanner\Http\ScanRestController;
use Elavira\Scanner\Persistence\ScanSessionRepository;
use Elavira\Scanner\Security\CanonicalUrl;
use Elavira\Scanner\Security\SessionGuard;

/** Registers only the Core single-page browser-scan hooks. */
final class ScannerModule implements Module {
	/** Shared initiation service.
	 *
	 * @var StartSinglePageScan|null
	 */ private $start;
	/** Admin launcher.
	 *
	 * @var RunAuditPage|null
	 */ private $run_page;
	/** Register scan hooks. @return void */
	public function register() {
		$context = new ContextualAuditLinks();
		add_action( 'admin_menu', array( $this, 'register_admin' ) );
		add_action( 'admin_post_elavira_run_audit', array( $this, 'launch' ) );
		add_action( 'admin_bar_menu', array( $context, 'admin_bar' ), 90 );
		add_action( 'post_submitbox_misc_actions', array( $context, 'editor' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'template_redirect', array( $this, 'protect_cache' ), 1 );
	}
	/** Register launcher. */ public function register_admin() {
		$this->run_page()->register(); }
	/** Handle launcher. */ public function launch() {
		$this->run_page()->launch(); }

	/** Build and register REST routes when WordPress initializes REST. @return void */
	public function register_routes() {
		$database    = $GLOBALS['wpdb'];
		$rules       = CoreRuleRegistry::create();
		$sessions    = new ScanSessionRepository( $database );
		$audits      = new AuditRepository( $database );
		$urls        = new AuditUrlRepository( $database );
		$guard       = new SessionGuard( $database, $sessions, $audits, $urls );
		$canonical   = new CanonicalUrl();
		$findings    = new FindingRepository( $database );
		$occurrences = new OccurrenceRepository( $database );
		$start       = $this->start_service();
		$identity    = new ReviewedConditionIdentity();
		$submit      = new SubmitScanBatch( $database, $sessions, $guard, new BrowserEvidenceValidator( $rules ), new RuleResultNormalizer(), $findings, $occurrences, $audits, $urls, new ReviewedExceptionRepository( $database ), $identity, new SupersessionResolver( $rules, $identity ) );
		$complete    = new CompleteScan( $database, $sessions, $guard, $audits, $urls, $findings );
		$controller  = new ScanRestController( $start, $submit, $complete );
		$controller->register_routes();
	}
	/** Shared initiation service. @return StartSinglePageScan */
	private function start_service() {
		if ( null === $this->start ) {
			$database    = $GLOBALS['wpdb'];
			$this->start = new StartSinglePageScan( $database, new AuditRepository( $database ), new AuditUrlRepository( $database ), new ScanSessionRepository( $database ), CoreRuleRegistry::create(), new CanonicalUrl() ); }
		return $this->start;
	}
	/** Lazy launcher. @return RunAuditPage */
	private function run_page() {
		if ( null === $this->run_page ) {
			$this->run_page = new RunAuditPage( $this->start_service() );
		} return $this->run_page; }

	/** Conditionally load the public scanner. @return void */
	public function enqueue() {
		$sessions = new ScanSessionRepository( $GLOBALS['wpdb'] );
		( new ScanPage( new SessionGuard( $GLOBALS['wpdb'], $sessions, new AuditRepository( $GLOBALS['wpdb'] ), new AuditUrlRepository( $GLOBALS['wpdb'] ) ), new CanonicalUrl() ) )->enqueue();
	}

	/** Prevent valid credential-bearing pages from entering shared caches. @return void */
	public function protect_cache() {
		$sessions = new ScanSessionRepository( $GLOBALS['wpdb'] );
		( new ScanPage( new SessionGuard( $GLOBALS['wpdb'], $sessions, new AuditRepository( $GLOBALS['wpdb'] ), new AuditUrlRepository( $GLOBALS['wpdb'] ) ), new CanonicalUrl() ) )->protect_cache();
	}
}
