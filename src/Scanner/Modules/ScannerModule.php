<?php
/**
 * Core scanner module wiring.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Modules;

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Core\Contracts\Module;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Rules\Browser\BrowserEvidenceValidator;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Rules\Normalization\RuleResultNormalizer;
use Elavira\Scanner\Application\CompleteScan;
use Elavira\Scanner\Application\StartSinglePageScan;
use Elavira\Scanner\Application\SubmitScanBatch;
use Elavira\Scanner\Admin\ScanResultPage;
use Elavira\Scanner\Frontend\ScanPage;
use Elavira\Scanner\Http\ScanRestController;
use Elavira\Scanner\Persistence\ScanSessionRepository;
use Elavira\Scanner\Security\CanonicalUrl;
use Elavira\Scanner\Security\SessionGuard;

/** Registers only the Core single-page browser-scan hooks. */
final class ScannerModule implements Module {
	/** Register scan hooks. @return void */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'template_redirect', array( $this, 'protect_cache' ), 1 );
		add_action( 'admin_menu', array( $this, 'register_admin' ) );
	}

	/** Build and register REST routes when WordPress initializes REST. @return void */
	public function register_routes() {
		$database    = $GLOBALS['wpdb'];
		$rules       = CoreRuleRegistry::create();
		$sessions    = new ScanSessionRepository( $database );
		$guard       = new SessionGuard( $database, $sessions, $audits, $urls );
		$canonical   = new CanonicalUrl();
		$audits      = new AuditRepository( $database );
		$urls        = new AuditUrlRepository( $database );
		$findings    = new FindingRepository( $database );
		$occurrences = new OccurrenceRepository( $database );
		$start       = new StartSinglePageScan( $database, $audits, $urls, $sessions, $rules, $canonical );
		$submit      = new SubmitScanBatch( $database, $sessions, $guard, new BrowserEvidenceValidator( $rules ), new RuleResultNormalizer(), $findings, $occurrences );
		$complete    = new CompleteScan( $database, $sessions, $guard, $audits, $urls, $findings );
		$controller  = new ScanRestController( $start, $submit, $complete );
		$controller->register_routes();
	}

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

	/** Register the minimal completion destination. @return void */
	public function register_admin() {
		( new ScanResultPage( new AuditRepository( $GLOBALS['wpdb'] ), new FindingRepository( $GLOBALS['wpdb'] ) ) )->register();
	}
}
