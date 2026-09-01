<?php
/**
 * Application bootstrap.
 *
 * @package Elavira
 */

namespace Elavira\Core;

use Elavira\Core\Modules\CoreModule;
use Elavira\Scanner\Modules\ScannerModule;

/**
 * Builds the root application from its modules.
 */
final class Bootstrap {
	/**
	 * Build and register the application.
	 *
	 * @return Application
	 */
	public static function boot() {
		$application = new Application();
		$application->add_module( new CoreModule() );
		$application->add_module( new ScannerModule() );
		$application->register();

		return $application;
	}
}
