<?php
/**
 * Application bootstrap.
 *
 * @package Elavira
 */

namespace Elavira\Core;

use Elavira\Core\Modules\CoreModule;
use Elavira\Admin\Modules\AdminModule;
use Elavira\GuidedDecisions\Modules\GuidedDecisionsModule;
use Elavira\Learn\Modules\LearnModule;
use Elavira\Reports\Modules\ReportsModule;
use Elavira\Scanner\Modules\ScannerModule;
use Elavira\Statement\Modules\StatementModule;
use Elavira\Toolbar\Modules\ToolbarModule;

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
		$application->add_module( new AdminModule() );
		$application->add_module( new ScannerModule() );
		$application->add_module( new ReportsModule() );
		$application->add_module( new GuidedDecisionsModule() );
		$application->add_module( new StatementModule() );
		$application->add_module( new LearnModule() );
		$application->add_module( new ToolbarModule() );
		$application->register();

		return $application;
	}
}
