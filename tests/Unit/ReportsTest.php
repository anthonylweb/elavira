<?php
/**
 * Core Reports application tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Values\AuditScope;
use Elavira\Audit\Values\AuditStatus;
use Elavira\Core\Migrations\CoreAuditSchema;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\FindingStatusHistoryRepository;
use Elavira\Findings\Values\FindingStatus;
use Elavira\Reports\Application\HistoricalRuleResolver;
use Elavira\Reports\Application\UpdateFindingStatus;
use Elavira\Reports\Admin\ReportsPage;
use Elavira\Reports\Export\XlsxExporter;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Tests\Support\FakeWpdb;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReportsTest extends TestCase {
	private $database;
	private $findings;
	private $history;
	private $finding_id;

	protected function setUp(): void {
		$this->database  = new FakeWpdb();
		$GLOBALS['wpdb'] = $this->database;
		( new CoreAuditSchema( $this->database ) )->up();
		$audits           = new AuditRepository( $this->database );
		$audit_id         = $audits->create(
			array(
				'label'             => 'Report fixture',
				'scope'             => AuditScope::SINGLE_PAGE,
				'wcag_target'       => '2.2_AA',
				'status'            => AuditStatus::COMPLETED,
				'owner_user_id'     => 7,
				'configuration'     => array(),
				'scanner_version'   => 'scanner-1',
				'ruleset_version'   => 'rules-1',
				'editorial_version' => 'editorial-1',
				'started_at'        => '2026-08-31 12:00:00',
				'completed_at'      => '2026-08-31 12:01:00',
			)
		);
		$this->findings   = new FindingRepository( $this->database );
		$this->history    = new FindingStatusHistoryRepository( $this->database );
		$this->finding_id = $this->findings->create(
			array(
				'audit_id'            => $audit_id,
				'rule_id'             => 'elavira.image.alt_missing',
				'rule_version'        => '1.0.0',
				'group_key'           => 'rule:alt',
				'category'            => 'detected_failure',
				'priority'            => 'high',
				'review_state'        => 'not_required',
				'wcag_criterion'      => '1.1.1',
				'wcag_level'          => 'A',
				'wcag_principle'      => 'perceivable',
				'editorial_version'   => '1.0.0',
				'summary_snapshot'    => 'Historical summary.',
				'affected_page_count' => 1,
				'occurrence_count'    => 1,
			)
		);
	}

	public function test_element_target_values_are_escaped_in_technical_details() {
		$method = new \ReflectionMethod( ReportsPage::class, 'structured' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true ); }
		ob_start();
		$method->invoke(
			$this->page(),
			'Target',
			json_encode(
				array(
					'element_id'      => '<script>alert(1)</script>',
					'element_classes' => '.elavira-qa-empty-link <svg/onload=alert(1)>',
					'selector'        => 'main > ul > li:nth-of-type(1) > a',
				)
			)
		);
		$html = ob_get_clean();
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<svg/', $html );
		$this->assertStringContainsString( 'Element ID', $html );
		$this->assertStringContainsString( 'Element classes', $html );
		$this->assertStringContainsString( '.elavira-qa-empty-link', $html );
		$this->assertStringContainsString( 'main &gt; ul &gt; li:nth-of-type(1) &gt; a', $html );
	}

	public function test_accepted_limitation_updates_finding_and_appends_history() {
		$service = new UpdateFindingStatus( $this->findings, $this->history );
		$service->update( $this->finding_id, FindingStatus::ACCEPTED_LIMITATION, 'Intentional documented constraint.', 7 );
		$this->assertSame( FindingStatus::ACCEPTED_LIMITATION, $this->findings->find( $this->finding_id )['current_status'] );
		$entries = $this->history->find_by_finding( $this->finding_id );
		$this->assertCount( 2, $entries );
		$this->assertSame( 'Intentional documented constraint.', $entries[1]['reason'] );
	}

	public function test_same_status_note_appends_activity_without_changing_status() {
		$GLOBALS['elavira_test_fired_actions'] = array();
		$service                               = new UpdateFindingStatus( $this->findings, $this->history );
		$first_id                              = $service->update( $this->finding_id, FindingStatus::OPEN, 'First review note.', 7 );
		$second_id                             = $service->update( $this->finding_id, FindingStatus::OPEN, 'Second review note.', 8 );

		$this->assertGreaterThan( 0, $first_id );
		$this->assertGreaterThan( $first_id, $second_id );
		$this->assertSame( FindingStatus::OPEN, $this->findings->find( $this->finding_id )['current_status'] );
		$entries = $this->history->find_by_finding( $this->finding_id );
		$this->assertCount( 3, $entries );
		$this->assertSame( array( 'First review note.', 'Second review note.' ), array_column( array_slice( $entries, 1 ), 'reason' ) );
		$this->assertSame( array( 7, 8 ), array_map( 'intval', array_column( array_slice( $entries, 1 ), 'actor_user_id' ) ) );
		$this->assertNotEmpty( $entries[1]['created_at'] );
		$this->assertCount( 2, $GLOBALS['elavira_test_fired_actions']['elavira_finding_activity_recorded'] );
		$this->assertArrayNotHasKey( 'elavira_finding_status_changed', $GLOBALS['elavira_test_fired_actions'] );
	}

	public function test_same_status_without_note_is_an_explicit_no_op() {
		$GLOBALS['elavira_test_fired_actions'] = array();
		$service                               = new UpdateFindingStatus( $this->findings, $this->history );
		$this->assertSame( 0, $service->update( $this->finding_id, FindingStatus::OPEN, '   ', 7 ) );
		$this->assertCount( 1, $this->history->find_by_finding( $this->finding_id ) );
		$this->assertSame( FindingStatus::OPEN, $this->findings->find( $this->finding_id )['current_status'] );
		$this->assertArrayNotHasKey( 'elavira_finding_activity_recorded', $GLOBALS['elavira_test_fired_actions'] );
	}

	public function test_grouped_correction_requires_note_and_retains_review_classification() {
		$service = new UpdateFindingStatus( $this->findings, $this->history );
		$before  = $this->findings->find( $this->finding_id );
		try {
			$service->update( $this->finding_id, FindingStatus::NEEDS_CORRECTION, '  ', 7 );
			$this->fail( 'Blank correction was accepted.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertSame( $before, $this->findings->find( $this->finding_id ) );
			$this->assertCount( 1, $this->history->find_by_finding( $this->finding_id ) );
		}
		$service->update( $this->finding_id, FindingStatus::NEEDS_CORRECTION, 'Supply the missing alternative.', 7 );
		$after = $this->findings->find( $this->finding_id );
		$this->assertSame( FindingStatus::NEEDS_CORRECTION, $after['current_status'] );
		$this->assertSame( $before['review_state'], $after['review_state'] );
		$this->assertCount( 2, $this->history->find_by_finding( $this->finding_id ) );
	}

	public function test_manual_verified_transition_is_rejected() {
		$this->expectException( InvalidArgumentException::class );
		( new UpdateFindingStatus( $this->findings, $this->history ) )->update( $this->finding_id, FindingStatus::VERIFIED, '', 7 );
	}

	public function test_historical_resolution_requires_exact_editorial_version() {
		$resolver = new HistoricalRuleResolver( CoreRuleRegistry::create() );
		$finding  = $this->findings->find( $this->finding_id );
		$this->assertTrue( $resolver->resolve( $finding )['available'] );
		$finding['editorial_version'] = '9.0.0';
		$result                       = $resolver->resolve( $finding );
		$this->assertFalse( $result['available'] );
		$this->assertSame( 'Historical summary.', $result['editorial']['summary'] );
	}

	public function test_spreadsheet_cells_neutralize_formula_prefixes() {
		$exporter = new XlsxExporter();
		foreach ( array( '=1+1', '+cmd', '-2+3', '@SUM(A1:A2)' ) as $value ) {
			$this->assertStringStartsWith( "'", $exporter->safe_cell( $value ) );
		}
		$this->assertSame( 'ordinary text', $exporter->safe_cell( 'ordinary text' ) );
	}

	private function page( $registry = null ) {
		return new ReportsPage(
			new AuditRepository( $this->database ),
			new \Elavira\Audit\Persistence\AuditUrlRepository( $this->database ),
			$this->findings,
			new \Elavira\Findings\Persistence\OccurrenceRepository( $this->database ),
			$this->history,
			new HistoricalRuleResolver( $registry ?? CoreRuleRegistry::create() ),
			new UpdateFindingStatus( $this->findings, $this->history ),
			new XlsxExporter(),
			\Elavira\GuidedDecisions\CoreDecisionRegistry::create(),
			new \Elavira\GuidedDecisions\DecisionRepository( $this->database ),
			new \Elavira\Statement\RecommendationRepository( $this->database ),
			\Elavira\Learn\CoreTopicRegistry::create()
		);
	}

	private function render( $page, $method ) {
		$GLOBALS['elavira_test_current_user_id'] = 7;
		$GLOBALS['elavira_test_capabilities']    = array(
			\Elavira\Core\Capabilities\Capability::VIEW_REPORTS => true,
			\Elavira\Core\Capabilities\Capability::MANAGE => true,
		);
		ob_start();
		try {
			$page->$method();
			return ob_get_contents();
		} finally {
			ob_end_clean();
			$_GET                                 = array();
			$GLOBALS['elavira_test_capabilities'] = array();
		}
	}

	public function test_stored_report_uses_injected_registry_count_and_preserves_navigation() {
		$_GET     = array( 'audit' => 1 );
		$registry = new \Elavira\Rules\RuleRegistry();
		$registry->register( CoreRuleRegistry::create()->get( 'elavira.image.alt_missing', '1.0.0' ) );
		$html = $this->render( $this->page( $registry ), 'render_report' );
		$this->assertStringContainsString( 'currently provides 1 automated accessibility rule.', $html );
		$this->assertStringContainsString( 'Older audits may use a different ruleset', $html );
		$this->assertStringContainsString( 'All audits', $html );
		$this->assertStringContainsString( 'Export XLSX', $html );
	}

	public function test_stored_finding_structure_and_workflow_render_without_optional_guidance() {
		$registry = new \Elavira\Rules\RuleRegistry();
		$registry->register(
			CoreRuleRegistry::create()->get( 'elavira.image.alt_missing', '1.0.0' )->with_editorial(
				array(
					'what_to_check'         => '',
					'verification_guidance' => '',
					'examples'              => array(),
				)
			)
		);
		$_GET = array( 'finding' => $this->finding_id );
		$html = $this->render( $this->page( $registry ), 'render_finding' );
		foreach ( array( 'What Elavira found', 'Where we found it', 'Why it matters', 'How to fix it', 'Technical details', 'Finding activity', 'Record finding activity', 'Save update', 'Back to audit report', 'Learn about' ) as $text ) {
			$this->assertStringContainsString( $text, $html );
		}
		$this->assertStringNotContainsString( '<h2>What to check</h2>', $html );
		$this->assertStringNotContainsString( '<h2>How to verify it</h2>', $html );
		$this->assertStringNotContainsString( '<h2>Examples</h2>', $html );
		$this->assertStringContainsString( '<details class="elavira-technical"><summary>Technical details</summary>', $html );
	}

	public function test_occurrences_remain_identifiable_and_evidence_is_disclosed_safely() {
		$urls        = new \Elavira\Audit\Persistence\AuditUrlRepository( $this->database );
		$url_id      = $urls->create(
			array(
				'audit_id'      => 1,
				'canonical_url' => 'https://example.org/page',
				'status'        => 'completed',
			)
		);
		$occurrences = new \Elavira\Findings\Persistence\OccurrenceRepository( $this->database );
		$occurrences->create(
			array(
				'finding_id'   => $this->finding_id,
				'audit_url_id' => $url_id,
				'target'       => array( 'selector' => '#image' ),
				'evidence'     => array( 'alt_excerpt' => '<script>alert(1)</script>' ),
				'element'      => array( 'tag_name' => 'img' ),
			)
		);
		$_GET = array( 'finding' => $this->finding_id );
		$html = $this->render( $this->page(), 'render_finding' );
		$this->assertStringContainsString( 'https://example.org/page', $html );
		$this->assertStringContainsString( 'Current alt text (excerpt)', $html );
		$this->assertStringContainsString( 'Technical details for this occurrence', $html );
		$this->assertStringContainsString( '#image', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_unavailable_historical_rule_uses_snapshot_without_new_guidance() {
		$_GET = array( 'finding' => $this->finding_id );
		$html = $this->render( $this->page( new \Elavira\Rules\RuleRegistry() ), 'render_finding' );
		$this->assertStringContainsString( 'Historical summary.', $html );
		$this->assertStringContainsString( 'exact historical guidance version is unavailable', $html );
		$this->assertStringNotContainsString( '<h2>How to fix it</h2>', $html );
		$this->assertStringNotContainsString( 'Learn about', $html );
		$this->assertStringContainsString( 'Save update', $html );
	}

	public function test_identification_values_are_escaped_and_destinations_are_not_links() {
		$urls        = new \Elavira\Audit\Persistence\AuditUrlRepository( $this->database );
		$url         = $urls->create(
			array(
				'audit_id'      => 1,
				'canonical_url' => 'https://example.org/audited-page/',
				'status'        => 'completed',
			)
		);
		$occurrences = new \Elavira\Findings\Persistence\OccurrenceRepository( $this->database );
		$occurrences->create(
			array(
				'finding_id'   => $this->finding_id,
				'audit_url_id' => $url,
				'element'      => array(
					'tag_name'          => 'a',
					'link_kind'         => 'image_only',
					'link_destination'  => 'https://example.org/contact/',
					'image_filename'    => 'envelope.svg',
					'image_alt_excerpt' => '<script>alt</script>',
					'link_name_excerpt' => '<script>name</script>',
					'text_excerpt'      => '<script>text</script>',
				),
				'evidence'     => array( 'alt_excerpt' => '<img onerror="alert(1)">' ),
			)
		);
		$_GET = array( 'finding' => $this->finding_id );
		$html = $this->render( $this->page(), 'render_finding' );
		$this->assertStringContainsString( 'Image link', $html );
		$this->assertStringContainsString( 'Audited page:', $html );
		$this->assertStringContainsString( 'Link destination (query and fragment omitted)', $html );
		$this->assertStringNotContainsString( 'href="https://example.org/contact/"', $html );
		foreach ( array( 'alt', 'name', 'text' ) as $value ) {
			$this->assertStringContainsString( '&lt;script&gt;' . $value . '&lt;/script&gt;', $html );
		}
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( '&lt;img', $html );
		$this->assertStringContainsString( 'Back to audit report', $html );
		$this->assertStringContainsString( 'Finding activity', $html );
		$this->assertStringContainsString( 'Learn about', $html );
	}

	public function test_rule_specific_optional_guidance_renders_in_order() {
		$rule                          = CoreRuleRegistry::create()->get( 'elavira.image.alt_missing', '1.0.0' );
		$property                      = new \ReflectionProperty( \Elavira\Rules\RuleDefinition::class, 'data' );
		$data                          = $property->getValue( $rule );
		$data['what_to_check']         = 'Check whether the image is a link.';
		$data['verification_guidance'] = 'Revisit the page and check its purpose.';
		$data['examples']              = array(
			array(
				'instead_of'  => '<img alt="arrow">',
				'consider'    => '<img alt="View pricing">',
				'explanation' => 'For a pricing link only.',
			),
		);
		$registry                      = new \Elavira\Rules\RuleRegistry();
		$registry->register( new \Elavira\Rules\RuleDefinition( $data ) );
		$_GET = array( 'finding' => $this->finding_id );
		$html = $this->render( $this->page( $registry ), 'render_finding' );
		$last = -1;
		foreach ( array( 'What Elavira found', 'Where we found it', 'Why it matters', 'What to check', 'How to fix it', 'Examples', 'How to verify it', 'Technical details' ) as $heading ) {
			$position = strpos( $html, $heading );
			$this->assertNotFalse( $position );
			$this->assertGreaterThan( $last, $position );
			$last = $position;
		}
		$this->assertStringContainsString( $data['what_to_check'], $html );
		$this->assertStringContainsString( $data['verification_guidance'], $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_guidance_and_evidence_escape_markup_and_omit_empty_content() {
		$page   = $this->page();
		$method = new \ReflectionMethod( ReportsPage::class, 'structured' );
		ob_start();
		$method->invoke(
			$page,
			'Evidence',
			wp_json_encode(
				array(
					'html'     => '<img src=x onerror=alert(1)>',
					'selector' => '#example',
				)
			)
		);
		$html = ob_get_clean();
		$this->assertStringContainsString( '&lt;img', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$method = new \ReflectionMethod( ReportsPage::class, 'examples' );
		ob_start();
		$method->invoke(
			$page,
			array(
				array(
					'instead_of'  => '<img alt="arrow">',
					'consider'    => '<img alt="View pricing">',
					'explanation' => 'For a pricing link.',
				),
			)
		);
		$html = ob_get_clean();
		$this->assertStringContainsString( 'not evidence captured from your page', $html );
		$this->assertStringContainsString( '&lt;img', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$method = new \ReflectionMethod( ReportsPage::class, 'section' );
		ob_start();
		$method->invoke( $page, 'Empty', '' );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_help_actions_require_existing_exact_mappings() {
		$page   = $this->page();
		$method = new \ReflectionMethod( ReportsPage::class, 'guided_decision_action' );
		foreach ( array(
			'2.0.0'  => true,
			'99.0.0' => false,
		) as $version => $expected ) {
			ob_start();
			$method->invoke(
				$page,
				array(
					'id'           => 1,
					'rule_id'      => 'elavira.link.purpose_review',
					'rule_version' => $version,
				)
			);
			$html = ob_get_clean();
			$this->assertSame( $expected, is_int( strpos( $html, 'Not sure?' ) ) );
		}
		$method = new \ReflectionMethod( ReportsPage::class, 'learn_action' );
		foreach ( array(
			'images_alternative_text' => true,
			'missing_topic'           => false,
			''                        => false,
		) as $topic => $expected ) {
			ob_start();
			$method->invoke(
				$page,
				array(
					'available' => true,
					'editorial' => array( 'learn_topic' => $topic ),
				)
			);
			$html = ob_get_clean();
			$this->assertSame( $expected, is_int( strpos( $html, 'Learn about' ) ) );
		}
	}

	public function test_every_current_rule_renders_guidance_examples_and_workflow() {
		$current = array();
		foreach ( CoreRuleRegistry::create()->all() as $rule ) {
			$current[ $rule->id() ] = $rule;
		}
		foreach ( $current as $rule ) {
			$copy                     = $rule->editorial();
			$row                      = &$this->database->tables['wp_elavira_findings'][ $this->finding_id ];
			$row['rule_id']           = $rule->id();
			$row['rule_version']      = $rule->version();
			$row['editorial_version'] = $rule->editorial_version();
			$row['category']          = $rule->classification();
			$row['review_state']      = 'automatic_failure' === $rule->evaluation_type() ? 'not_required' : 'needs_review';
			$_GET                     = array( 'finding' => $this->finding_id );
			$html                     = $this->render( $this->page(), 'render_finding' );
			foreach ( array( 'summary', 'why_it_matters', 'what_to_check', 'recommendation', 'verification_guidance', 'developer_guidance' ) as $field ) {
				$this->assertStringContainsString( esc_html( $copy[ $field ] ), $html, $rule->id() . ' ' . $field );
			}
			$this->assertSame( ! empty( $copy['examples'] ), false !== strpos( $html, '<h2>Examples</h2>' ), $rule->id() );
			foreach ( $copy['examples'] as $example ) {
				foreach ( $example as $value ) {
					$this->assertStringContainsString( esc_html( $value ), $html, $rule->id() );
				}
			}
			$this->assertStringContainsString( 'Finding activity', $html );
			$this->assertStringContainsString( 'Save update', $html );
			$this->assertStringNotContainsString( '<img ', $html );
			$decision = \Elavira\GuidedDecisions\CoreDecisionRegistry::create()->for_rule( $rule->id(), $rule->version() );
			$this->assertSame( (bool) $decision, is_int( strpos( $html, 'Not sure? Let&#039;s decide' ) ), $rule->id() );
		}
	}

	public function test_activity_timeline_distinguishes_status_changes_from_notes() {
		$GLOBALS['elavira_test_fired_actions'] = array();
		$service                               = new UpdateFindingStatus( $this->findings, $this->history );
		$service->update( $this->finding_id, FindingStatus::ADDRESSED, 'Implemented the content correction.', 7 );
		$service->update( $this->finding_id, FindingStatus::ADDRESSED, 'Queued for a fresh audit.', 8 );

		$_GET = array( 'finding' => $this->finding_id );
		$html = $this->render( $this->page(), 'render_finding' );
		$this->assertStringContainsString( 'Finding created', $html );
		$this->assertStringContainsString( 'Status changed', $html );
		$this->assertStringContainsString( 'Open → Addressed', $html );
		$this->assertStringContainsString( 'Note added', $html );
		$this->assertStringNotContainsString( 'Addressed → Addressed', $html );
		$this->assertStringContainsString( 'Implemented the content correction.', $html );
		$this->assertStringContainsString( 'Queued for a fresh audit.', $html );
		$this->assertStringContainsString( 'User 8', $html );
		$this->assertCount( 1, $GLOBALS['elavira_test_fired_actions']['elavira_finding_status_changed'] );
		$this->assertCount( 2, $GLOBALS['elavira_test_fired_actions']['elavira_finding_activity_recorded'] );
	}

	public function test_status_form_supports_note_only_activity_and_explains_verification() {
		$_GET = array( 'finding' => $this->finding_id );
		$html = $this->render( $this->page(), 'render_finding' );
		$this->assertStringContainsString( '<option value="open" selected=', $html );
		$this->assertStringContainsString( 'Activity note (optional)', $html );
		$this->assertStringContainsString( 'aria-describedby="elavira-status-reason-help"', $html );
		$this->assertStringContainsString( 'Addressed means remediation work was performed; it is not proof of resolution.', $html );
		$this->assertStringContainsString( 'Verified can only be established by a subsequent audit.', $html );
		$this->assertStringNotContainsString( '<option value="verified"', $html );
	}

	public function test_no_change_notice_is_clear_and_non_error() {
		$_GET = array(
			'finding'        => $this->finding_id,
			'elavira_notice' => 'no_changes',
		);
		$html = $this->render( $this->page(), 'render_finding' );
		$this->assertStringContainsString( 'notice notice-info', $html );
		$this->assertStringContainsString( 'No changes to save.', $html );
	}

	public function test_completed_guided_decision_is_visible_and_repeatable_from_finding() {
		( new \Elavira\Core\Migrations\GuidedDecisionSchema( $this->database ) )->up();
		$repository = new \Elavira\GuidedDecisions\DecisionRepository( $this->database );
		$repository->record(
			array(
				'finding_id'       => $this->finding_id,
				'decision_id'      => 'elavira.decision.alternative_text',
				'decision_version' => '2.0.0',
				'answers'          => array(
					array(
						'question'        => 'Review note and workflow choice',
						'label'           => 'Reviewed the image context.',
						'workflow_status' => '',
					),
				),
				'outcome_id'       => 'further_review',
				'recommendation'   => 'Continue human review.',
				'actor_user_id'    => 7,
			)
		);
		$_GET = array( 'finding' => $this->finding_id );
		$html = $this->render( $this->page(), 'render_finding' );
		$this->assertStringContainsString( 'A Guided Decision has already been recorded', $html );
		$this->assertStringContainsString( 'Run Guided Decision again', $html );
		$this->assertStringContainsString( 'Guided Decision activity', $html );
		$this->assertStringContainsString( 'Review decision details', $html );
		$this->assertStringContainsString( 'elavira.decision.alternative_text 2.0.0', $html );
		$this->assertSame( FindingStatus::OPEN, $this->findings->find( $this->finding_id )['current_status'] );
	}

	public function test_captured_page_links_open_separately_but_workflow_links_do_not() {
		$urls   = new \Elavira\Audit\Persistence\AuditUrlRepository( $this->database );
		$url_id = $urls->create(
			array(
				'audit_id'      => 1,
				'canonical_url' => 'https://example.org/captured/',
				'status'        => 'completed',
			)
		);
		( new \Elavira\Findings\Persistence\OccurrenceRepository( $this->database ) )->create(
			array(
				'finding_id'   => $this->finding_id,
				'audit_url_id' => $url_id,
				'element'      => array( 'tag_name' => 'img' ),
			)
		);
		$_GET = array( 'finding' => $this->finding_id );
		$html = $this->render( $this->page(), 'render_finding' );
		$this->assertMatchesRegularExpression( '/<a[^>]+aria-describedby="elavira-new-tab-[0-9]+"[^>]+href="https:\/\/example\.org\/captured\/">https:[^<]+<\/a> <span aria-hidden="true">\(opens in a new tab\)<\/span>/', $html );
		$this->assertMatchesRegularExpression( '/<a href="[^"]*page=elavira-report[^"]*">&larr; Back to audit report<\/a>/', $html );
		$this->assertStringNotContainsString( 'target="_blank" rel="noopener noreferrer" href="' . admin_url( 'admin.php?page=elavira-report' ), $html );
	}

	public function test_export_repositories_include_occurrence_and_history_join_fields() {
		$urls        = new \Elavira\Audit\Persistence\AuditUrlRepository( $this->database );
		$url         = $urls->create(
			array(
				'audit_id'      => 1,
				'canonical_url' => 'https://example.org/export-page/',
				'status'        => 'completed',
			)
		);
		$occurrences = new \Elavira\Findings\Persistence\OccurrenceRepository( $this->database );
		$occurrences->create(
			array(
				'finding_id'   => $this->finding_id,
				'audit_url_id' => $url,
				'element'      => array( 'tag_name' => 'img' ),
			)
		);
		$rows = $occurrences->find_by_audit( 1 );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'https://example.org/export-page/', $rows[0]['canonical_url'] );
		$this->assertSame( 'Historical summary.', $rows[0]['summary_snapshot'] );
		( new UpdateFindingStatus( $this->findings, $this->history ) )->update( $this->finding_id, FindingStatus::OPEN, 'Exported note-only activity.', 7 );
		$activity = $this->history->find_by_audit( 1 );
		$this->assertSame( 'Historical summary.', $activity[0]['summary_snapshot'] );
		$this->assertSame( 'Exported note-only activity.', $activity[1]['reason'] );
		$this->assertSame( array(), $occurrences->find_by_audit( 99 ) );
		$this->assertSame( array(), $this->history->find_by_audit( 99 ) );
	}

	public function test_overview_counts_stable_ids_once_across_historical_versions() {
		$registry = new \Elavira\Rules\RuleRegistry();
		$core     = CoreRuleRegistry::create();
		$registry->register( $core->get( 'elavira.document.language_missing', '1.0.0' ) );
		$registry->register( $core->get( 'elavira.document.language_missing', '2.0.0' ) );
		$registry->register( $core->get( 'elavira.image.alt_missing', '1.0.0' ) );
		$_GET = array( 'audit' => 1 );
		$html = $this->render( $this->page( $registry ), 'render_report' );
		$this->assertStringContainsString( 'currently provides 2 automated accessibility rules.', $html );
		$this->assertStringNotContainsString( '46-rule', $html );
	}

	public function test_result_explanations_are_actionable_and_preserve_certainty() {
		$page      = $this->page();
		$method    = new \ReflectionMethod( ReportsPage::class, 'result_description' );
		$automatic = $method->invoke( $page, 'automatic_failure' );
		$this->assertStringContainsString( 'enough objective evidence to confidently identify this as an accessibility failure', $automatic );
		$this->assertSame( $automatic, $method->invoke( $page, 'detected_failure' ) );
		$this->assertStringContainsString( 'may cause an accessibility problem', $method->invoke( $page, 'potential_issue' ) );
		$this->assertStringContainsString( 'before deciding whether it needs a change', $method->invoke( $page, 'potential_issue' ) );
		$this->assertStringContainsString( 'Elavira will explain what to look for', $method->invoke( $page, 'human_review' ) );
		$this->assertStringContainsString( 'saved summary and recorded evidence', $method->invoke( $page, 'unknown' ) );
		$_GET = array( 'finding' => $this->finding_id );
		$html = $this->render( $page, 'render_finding' );
		$this->assertStringContainsString( esc_html( $automatic ), $html );
		$this->assertStringNotContainsString( 'individual mapped accessibility failure', $html );
	}

	public function test_unmapped_best_practice_has_non_conformance_report_label() {
		$page   = ( new \ReflectionClass( ReportsPage::class ) )->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod( ReportsPage::class, 'wcag_label' );
		$this->assertSame(
			'Best practice — no direct WCAG mapping',
			$method->invoke(
				$page,
				array(
					'wcag_criterion' => '',
					'wcag_level'     => '',
				)
			)
		);
	}
}
