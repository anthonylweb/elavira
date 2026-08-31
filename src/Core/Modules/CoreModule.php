<?php
/**
 * Core module.
 *
 * @package Elavira
 */

namespace Elavira\Core\Modules;

use Elavira\Core\Contracts\Module;
use Elavira\Core\Lifecycle\UpgradeManager;

/**
 * Registers foundation-level hooks only.
 */
final class CoreModule implements Module {
	/**
	 * Register foundation hooks.
	 *
	 * WordPress automatically loads translations for hosted plugins. No manual
	 * translation-loading hook is needed for this foundation.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'plugins_loaded', array( new UpgradeManager(), 'maybe_upgrade' ), 5 );
	}
}
