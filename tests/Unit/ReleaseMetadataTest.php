<?php
/**
 * Release metadata consistency tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReleaseMetadataTest extends TestCase {
	public function test_release_version_is_consistent() {
		$root      = dirname( __DIR__, 2 );
		$bootstrap = file_get_contents( $root . '/elavira.php' );
		$readme    = file_get_contents( $root . '/readme.txt' );

		$this->assertMatchesRegularExpression( '/^ \* Version:\s+0\.2\.6$/m', $bootstrap );
		$this->assertStringContainsString( "define( 'ELAVIRA_VERSION', '0.2.6' );", $bootstrap );
		$this->assertMatchesRegularExpression( '/^Stable tag: 0\.2\.6$/m', $readme );
	}

	public function test_wordpress_org_metadata_matches_runtime_requirements() {
		$root      = dirname( __DIR__, 2 );
		$bootstrap = file_get_contents( $root . '/elavira.php' );
		$readme    = file_get_contents( $root . '/readme.txt' );
		$composer  = json_decode( file_get_contents( $root . '/composer.json' ), true );

		$this->assertStringContainsString( 'Requires at least: 6.4', $bootstrap );
		$this->assertStringContainsString( 'Requires PHP:      7.4', $bootstrap );
		$this->assertStringContainsString( 'Text Domain:       elavira', $bootstrap );
		$this->assertStringContainsString( 'License:           GPL-2.0-or-later', $bootstrap );
		$this->assertMatchesRegularExpression( '/^Requires at least: 6\.4$/m', $readme );
		$this->assertMatchesRegularExpression( '/^Tested up to: 7\.1$/m', $readme );
		$this->assertMatchesRegularExpression( '/^Requires PHP: 7\.4$/m', $readme );
		$this->assertMatchesRegularExpression( '/^License: GPL-2\.0-or-later$/m', $readme );
		$this->assertSame( 'GPL-2.0-or-later', $composer['license'] );
	}

	public function test_content_contract_versions_are_independent_of_plugin_release() {
		$versions = file_get_contents( dirname( __DIR__, 2 ) . '/src/Scanner/Versions.php' );

		$this->assertMatchesRegularExpression( "/const SCANNER\\s+= 'core-browser-1.5.2';/", $versions );
		$this->assertMatchesRegularExpression( "/const RULESET\\s+= 'core-rules-1.6.0';/", $versions );
		$this->assertStringContainsString( "const EDITORIAL = 'core-editorial-1.5.0';", $versions );
	}
}
