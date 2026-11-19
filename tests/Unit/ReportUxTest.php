<?php
/**
 * Workflow draft and historical generated-note regressions.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Reports\Admin\ActivityForm;
use Elavira\Reports\Application\ActivityNote;
use Elavira\Rules\CoreRuleGuidance;
use PHPUnit\Framework\TestCase;

final class ReportUxTest extends TestCase {

	public function test_actor_names_prefer_first_name_then_username_and_never_email() {
		$page   = ( new \ReflectionClass( \Elavira\Reports\Admin\ReportsPage::class ) )->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod( $page, 'actor_label' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$GLOBALS['elavira_test_users'][77] = (object) array(
			'first_name'   => ' Jane ',
			'user_login'   => 'reviewer',
			'display_name' => 'private@example.org',
		);
		$this->assertSame( 'Jane', $method->invoke( $page, 77 ) );
		$GLOBALS['elavira_test_users'][77]->first_name = '   ';
		$this->assertSame( 'reviewer', $method->invoke( $page, 77 ) );
		unset( $GLOBALS['elavira_test_users'][77] );
		$this->assertSame( 'User 77', $method->invoke( $page, 77 ) );
		$this->assertSame( 'Elavira audit', $method->invoke( $page, 0 ) );
	}

	public function test_legacy_prefix_requires_exact_persisted_provenance() {
		$decision = array(
			'decision_id'      => 'elavira.decision.heading_structure',
			'decision_version' => '2.0.0',
			'outcome_id'       => 'fix_hierarchy',
			'actor_user_id'    => 7,
			'completed_at'     => '2026-10-31 12:00:00',
			'answers_json'     => json_encode(
				array(
					array(
						'workflow_status' => 'needs_review',
						'label'           => 'review structure',
					),
				)
			),
		);
		$entry    = array(
			'actor_user_id' => 7,
			'created_at'    => $decision['completed_at'],
			'status'        => 'needs_review',
			'reason'        => 'elavira.decision.heading_structure 2.0.0 / fix_hierarchy: review structure',
		);
		$this->assertSame( 'review structure', ActivityNote::display( $entry, array( $decision ) ) );
		$this->assertSame( $entry['reason'], ActivityNote::display( $entry, array() ) );
		$decision['actor_user_id'] = 8;
		$this->assertSame( $entry['reason'], ActivityNote::display( $entry, array( $decision ) ) );
		$decision['actor_user_id'] = 7;
		$entry['reason']          .= ' user-added suffix';
		$this->assertSame( $entry['reason'], ActivityNote::display( $entry, array( $decision ) ) );
	}

	public function test_rejected_draft_is_private_bounded_and_escaped() {
		$GLOBALS['elavira_test_current_user_id'] = 7;
		ActivityForm::reject( 1, 2, 'needs_correction', '<script>test</script>' );
		$GLOBALS['elavira_test_current_user_id'] = 8;
		$this->assertSame( array(), ActivityForm::draft( 1, 2 ) );
		$GLOBALS['elavira_test_current_user_id'] = 7;
		$this->assertSame( array(), ActivityForm::draft( 1, 3 ) );
		$draft = ActivityForm::draft( 1, 2 );
		$this->assertSame( 'needs_correction', $draft['status'] );
		ob_start();
		ActivityForm::note( 'test-note', $draft['status'], $draft );
		$html = ob_get_clean();
		$this->assertStringContainsString( ' required', $html );
		$this->assertStringContainsString( 'aria-invalid="true"', $html );
		$this->assertStringContainsString( 'aria-describedby="test-note-help test-note-error"', $html );
		$this->assertStringContainsString( '&lt;script&gt;test&lt;/script&gt;', $html );
		$this->assertStringContainsString( 'role="alert"', $html );
		$this->assertSame( array(), ActivityForm::draft( 1, 2 ) );
	}

	public function test_empty_link_advice_is_explicitly_omitted_but_image_advice_is_approved() {
		$guidance = CoreRuleGuidance::all();
		$this->assertFalse( $guidance['elavira.links.missing_accessible_name@1.0.0']['wordpress_guidance_useful'] );
		$this->assertTrue( $guidance['elavira.image.alt_missing@1.0.0']['wordpress_guidance_useful'] );
	}
}
