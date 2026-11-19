<?php
/**
 * Application coordinator.
 *
 * @package Elavira
 */

namespace Elavira\Core;

use Elavira\Core\Contracts\Module;

/**
 * Registers explicitly supplied modules.
 */
final class Application {
	/**
	 * Registered modules.
	 *
	 * @var Module[]
	 */
	private $modules = array();

	/**
	 * Add a module to the application.
	 *
	 * @param Module $module Module to register.
	 * @return self
	 */
	public function add_module( Module $module ) {
		$this->modules[] = $module;

		return $this;
	}

	/**
	 * Register all application modules.
	 *
	 * @return void
	 */
	public function register() {
		foreach ( $this->modules as $module ) {
			$module->register();
		}
	}
}
