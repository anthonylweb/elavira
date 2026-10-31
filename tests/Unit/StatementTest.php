<?php
/**
 * Accessibility Statement tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Statement\StatementFields;
use Elavira\Statement\StatementRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StatementTest extends TestCase {
	public function test_defaults_are_a_draft_template_without_conformance_claim() {
		$fields = StatementFields::defaults();
		$this->assertSame( 'not_assessed', $fields['plugin_findings_status'] );
		$this->assertSame( 'not_stated', $fields['stated_conformance'] );
	}

	public function test_structured_fields_strip_unsafe_markup() {
		$fields = StatementFields::sanitize( array_merge( StatementFields::defaults(), array( 'commitment' => '<script>alert(1)</script><b>Commitment</b>' ) ) );
		$this->assertSame( 'alert(1)Commitment', $fields['commitment'] );
		$this->assertStringNotContainsString( '<script', $fields['commitment'] );
	}

	public function test_invalid_contact_url_is_rejected() {
		$this->expectException( InvalidArgumentException::class );
		StatementFields::sanitize( array_merge( StatementFields::defaults(), array( 'contact_url' => 'javascript:alert(1)' ) ) );
	}

	public function test_invalid_email_is_rejected() {
		$this->expectException( InvalidArgumentException::class );
		StatementFields::sanitize( array_merge( StatementFields::defaults(), array( 'contact_email' => 'not-an-email' ) ) );
	}

	public function test_public_renderer_qualifies_plugin_findings() {
		$fields                  = StatementFields::defaults();
		$fields['feedback']      = 'Contact us about a barrier.';
		$fields['contact_email'] = 'accessibility@example.test';
		$html                    = ( new StatementRenderer() )->render( $fields );
		$this->assertStringContainsString( 'Conformance Status — Based on Plugin Findings', $html );
		$this->assertStringContainsString( 'do not constitute a complete determination', $html );
		$this->assertStringContainsString( $fields['organization'], $html );
		$this->assertMatchesRegularExpression( '/<section><h2>Feedback and Contact<\/h2>.*mailto:/s', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_admin_state_changes_are_capability_and_nonce_protected() {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Statement/Admin/StatementPage.php' );
		$this->assertStringContainsString( 'Capability::MANAGE_STATEMENT', $source );
		$this->assertStringContainsString( 'check_admin_referer( $action )', $source );
	}
}
