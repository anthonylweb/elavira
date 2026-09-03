<?php
/**
 * Statement module wiring.
 *
 * @package Elavira
 */

namespace Elavira\Statement\Modules;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment -- Concise module delegation comments are intentional.

use Elavira\Core\Contracts\Module;
use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\Statement\Admin\StatementPage;
use Elavira\Statement\FrontendStatement;
use Elavira\Statement\RecommendationGenerator;
use Elavira\Statement\RecommendationRepository;
use Elavira\Statement\StatementRenderer;
use Elavira\Statement\StatementRepository;
use Throwable;

/** Registers Core Statement behavior without global frontend assets. */
final class StatementModule implements Module {
	/** Admin controller. @var StatementPage|null */
	private $page;
	/** Frontend integration. @var FrontendStatement|null */
	private $frontend;

	/** Register hooks. @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_admin' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_elavira_statement_create', array( $this, 'create' ) );
		add_action( 'admin_post_elavira_statement_connect', array( $this, 'connect' ) );
		add_action( 'admin_post_elavira_statement_save', array( $this, 'save' ) );
		add_action( 'admin_post_elavira_statement_recommendation', array( $this, 'review' ) );
		add_action( 'elavira_audit_completed', array( $this, 'audit_completed' ) );
		add_action( 'elavira_finding_status_changed', array( $this, 'finding_changed' ), 10, 3 );
		add_filter( 'the_content', array( $this, 'content' ), 20 );
	}

	/** Delegate hooks. @return void */
	public function register_admin() {
		$this->page()->register(); }
	/** Load scoped styles. @return void */
	public function enqueue() {
		$this->page()->enqueue(); }
	/** Create. @return void */
	public function create() {
		$this->page()->create(); }
	/** Connect. @return void */
	public function connect() {
		$this->page()->connect(); }
	/** Save. @return void */
	public function save() {
		$this->page()->save(); }
	/** Review recommendation. @return void */
	public function review() {
		$this->page()->review_recommendation(); }
	/** Render associated published page content. @param string $content Content. @return string */
	public function content( $content ) {
		return $this->frontend()->filter_content( $content ); }

	/** Generate advisory recommendations without breaking audit completion. @param int $audit_id Audit ID. @return void */
	public function audit_completed( $audit_id ) {
		try {
			$database = $GLOBALS['wpdb'];
			( new RecommendationGenerator( $database, new RecommendationRepository( $database ), CoreDecisionRegistry::create() ) )->generate( $audit_id );
		} catch ( Throwable $error ) {
			// Audit completion remains authoritative if advisory recommendation generation fails.
			unset( $error );
		}
	}

	/** Reconsider recommendations after a meaningful finding workflow change. @param int $audit_id Audit ID. @return void */
	public function finding_changed( $audit_id ) {
		$this->audit_completed( $audit_id );
	}

	/** Build admin page. @return StatementPage */
	private function page() {
		if ( null === $this->page ) {
			$database   = $GLOBALS['wpdb'];
			$this->page = new StatementPage(
				array(
					'statements'      => new StatementRepository( $database ),
					'recommendations' => new RecommendationRepository( $database ),
					'renderer'        => new StatementRenderer(),
				)
			);
		}
		return $this->page;
	}

	/** Build frontend integration. @return FrontendStatement */
	private function frontend() {
		if ( null === $this->frontend ) {
			$this->frontend = new FrontendStatement( new StatementRepository( $GLOBALS['wpdb'] ), new StatementRenderer() );
		}
		return $this->frontend;
	}
}
