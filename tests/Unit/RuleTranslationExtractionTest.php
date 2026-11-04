<?php
/**
 * Translation extraction regressions for generated rule metadata.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RuleTranslationExtractionTest extends TestCase {
	public function test_round_metadata_strings_have_literal_gettext_entries() {
		foreach ( array( 'RoundThreeRuleDefinitions', 'RoundFourRuleDefinitions', 'RoundFiveRuleDefinitions', 'RoundSixRuleDefinitions' ) as $name ) {
			$source  = file_get_contents( dirname( __DIR__, 2 ) . '/src/Rules/' . $name . '.php' );
			$tokens  = array_values(
				array_filter(
					token_get_all( $source ),
					static function ( $token ) {
						return ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
					}
				)
			);
			$strings = array();
			foreach ( $tokens as $index => $token ) {
				if ( is_array( $token ) && T_STRING === $token[0] && '__' === $token[1] ) {
					$this->assertSame( '(', $tokens[ $index + 1 ] );
					$this->assertSame( T_CONSTANT_ENCAPSED_STRING, $tokens[ $index + 2 ][0], $name );
					$this->assertSame( ',', $tokens[ $index + 3 ], $name );
					$this->assertSame( "'elavira'", $tokens[ $index + 4 ][1] );
					$strings[] = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), substr( $tokens[ $index + 2 ][1], 1, -1 ) );
				}
			}
			$class = '\\Elavira\\Rules\\' . $name;
			foreach ( $class::all() as $rule ) {
				foreach ( $rule->editorial() as $key => $value ) {
					if ( in_array( $key, array( 'title', 'description', 'summary', 'why_it_matters', 'recommendation', 'wordpress_guidance', 'developer_guidance' ), true ) ) {
						$this->assertContains( $value, $strings, $name . ': ' . $key );
					}
				}
			}
		}
	}
}
