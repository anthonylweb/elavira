<?php
/**
 * Visitor Toolbar module.
 *
 * @package Elavira
 */

namespace Elavira\Toolbar\Modules;

use Elavira\Core\Contracts\Module;
use Elavira\Statement\StatementRepository;
use Elavira\Toolbar\Admin\ToolbarPage;
use Elavira\Toolbar\FrontendToolbar;

/** Registers the isolated Core visitor-toolbar hooks. */
final class ToolbarModule implements Module {
	/**
	 * Admin page.
	 *
	 * @var ToolbarPage|null
	 */
	private $page;
	/**
	 * Frontend integration.
	 *
	 * @var FrontendToolbar|null
	 */
	private $frontend;

	/** Register hooks. @return void */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_admin' ) );
		add_action( 'admin_post_elavira_toolbar_save', array( $this, 'save' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ) );
	}

	/** Register admin page. @return void */
	public function register_admin() {
		$this->page()->register(); }
	/** Save settings. @return void */
	public function save() {
		$this->page()->save(); }
	/** Enqueue frontend assets. @return void */
	public function enqueue() {
		$this->frontend()->enqueue(); }
	/** Render frontend toolbar. @return void */
	public function render() {
		$this->frontend()->render(); }

	/** Get admin page. @return ToolbarPage */
	private function page() {
		if ( null === $this->page ) {
			$this->page = new ToolbarPage(); }
		return $this->page;
	}

	/** Get frontend integration. @return FrontendToolbar */
	private function frontend() {
		if ( null === $this->frontend ) {
			$this->frontend = new FrontendToolbar( new StatementRepository( $GLOBALS['wpdb'] ) ); }
		return $this->frontend;
	}
}
