<?php
/**
 * Application tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Application;
use Elavira\Core\Contracts\Module;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase {
	public function test_registers_each_added_module() {
		$first  = new RecordingModule();
		$second = new RecordingModule();

		$application = new Application();
		$application->add_module( $first )->add_module( $second );
		$application->register();

		$this->assertSame( 1, $first->registrations );
		$this->assertSame( 1, $second->registrations );
	}
}

final class RecordingModule implements Module {
	public $registrations = 0;

	public function register() {
		++$this->registrations;
	}
}
