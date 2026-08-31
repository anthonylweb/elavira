<?php
/**
 * Module contract.
 *
 * @package Elavira
 */

namespace Elavira\Core\Contracts;

/**
 * Registers one cohesive group of WordPress hooks.
 */
interface Module {
	/**
	 * Register the module with WordPress.
	 *
	 * @return void
	 */
	public function register();
}
