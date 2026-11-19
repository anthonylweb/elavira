<?php
/**
 * Feature availability tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Core\Features\CoreFeatureAvailability;
use Elavira\Core\Features\Feature;
use PHPUnit\Framework\TestCase;

final class CoreFeatureAvailabilityTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['elavira_test_filters'] = array();
	}

	public function test_core_features_are_available_without_premium() {
		$availability = new CoreFeatureAvailability();

		foreach ( Feature::core() as $feature ) {
			$this->assertTrue( $availability->is_available( $feature ) );
		}
	}

	public function test_premium_features_default_to_unavailable() {
		$availability = new CoreFeatureAvailability();

		foreach ( Feature::premium() as $feature ) {
			$this->assertFalse( $availability->is_available( $feature ) );
		}
	}

	public function test_site_wide_execution_is_not_available_in_core() {
		$availability = new CoreFeatureAvailability();

		$this->assertFalse( $availability->is_available( Feature::SITE_WIDE_AUDITS ) );
		$this->assertFalse( $availability->is_available( Feature::BATCH_AUDITS ) );
		$this->assertFalse( $availability->is_available( Feature::REMOTE_BROWSER_WORKERS ) );
		$this->assertFalse( $availability->is_available( Feature::PERSISTENT_AUDIT_QUEUE ) );
	}

	public function test_unknown_features_are_not_exposed_to_extensions() {
		$GLOBALS['elavira_test_filters']['elavira_feature_available'] = static function () {
			return true;
		};

		$this->assertFalse( ( new CoreFeatureAvailability() )->is_available( 'unknown' ) );
	}

	public function test_recognized_feature_can_be_enabled_at_the_boundary() {
		$GLOBALS['elavira_test_filters']['elavira_feature_available'] = static function ( $available, $feature ) {
			return Feature::DEVELOPER_DIAGNOSTICS === $feature ? true : $available;
		};

		$this->assertTrue( ( new CoreFeatureAvailability() )->is_available( Feature::DEVELOPER_DIAGNOSTICS ) );
	}
}
