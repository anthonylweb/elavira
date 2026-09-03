<?php
/**
 * Application bootstrap.
 *
 * @package Elavira
 */

namespace Elavira\Core;

use Elavira\Core\Modules\CoreModule;
use Elavira\GuidedDecisions\Modules\GuidedDecisionsModule;
use Elavira\Learn\Modules\LearnModule;
use Elavira\Reports\Modules\ReportsModule;
use Elavira\Scanner\Modules\ScannerModule;
use Elavira\Statement\Modules\StatementModule;

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
		$application->add_module( new ReportsModule() );
		$application->add_module( new GuidedDecisionsModule() );
		$application->add_module( new StatementModule() );
		$application->add_module( new LearnModule() );
		$application->register();

		return $application;
	}
}
