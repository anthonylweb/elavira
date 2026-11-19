<?php
/**
 * Learn module wiring.
 *
 * @package Elavira
 */

namespace Elavira\Learn\Modules;

// phpcs:disable Squiz.Commenting.VariableComment -- The single lazy dependency is documented inline.

use Elavira\Core\Contracts\Module;
use Elavira\Learn\Admin\LearnPage;
use Elavira\Learn\CoreTopicRegistry;
use Elavira\Learn\WcagReference;
use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\Rules\CoreRuleRegistry;

/** Registers bundled Learn independently from scanning and persistence. */
final class LearnModule implements Module {
	/** Page. @var LearnPage|null */
	private $page;

	/** Register hooks. @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_admin' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/** Register routes. @return void */
	public function register_admin() {
		add_submenu_page( 'elavira-dashboard', __( 'Elavira Learn', 'elavira' ), __( 'Learn', 'elavira' ), 'read', 'elavira-learn', array( $this, 'landing' ), 40 );
		add_submenu_page( null, __( 'Elavira Learn Topic', 'elavira' ), __( 'Learn Topic', 'elavira' ), 'read', 'elavira-learn-topic', array( $this, 'detail' ) );
	}

	/** Load scoped assets. @return void */
	public function enqueue() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only route selection.
		$route = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( in_array( $route, array( 'elavira-learn', 'elavira-learn-topic' ), true ) ) {
			$this->page()->enqueue();
		}
	}

	/** Render the landing route lazily. @return void */
	public function landing() {
		$this->page()->landing();
	}

	/** Render a topic route lazily. @return void */
	public function detail() {
		$this->page()->detail();
	}

	/** Build page once. @return LearnPage */
	private function page() {
		if ( null === $this->page ) {
			$topics     = CoreTopicRegistry::create();
			$rules      = CoreRuleRegistry::create();
			$this->page = new LearnPage( $topics, CoreDecisionRegistry::create(), $rules, new WcagReference( $topics, $rules ) );
		}
		return $this->page;
	}
}
