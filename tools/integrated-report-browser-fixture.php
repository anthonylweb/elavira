<?php
/**
 * Browser-only integration fixture for report, decision, Learn, and activity UX.
 *
 * Run from the plugin root with: php -S 127.0.0.1:8767 -t .
 *
 * @package Elavira
 */

if ( 'cli-server' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

require dirname( __DIR__ ) . '/tests/bootstrap.php';

if ( ! function_exists( 'get_userdata' ) ) {
	/** Supply representative display names for local visual reflow checks.
	 *
	 * @param int $user_id Fixture user ID.
	 * @return object|false
	 */
	function get_userdata( $user_id ) {
		$names = array(
			101 => 'Anthony Accessibility Program Administrator With A Long Display Name',
			202 => 'User B — Theme Development and Quality Assurance Reviewer',
		);
		return isset( $names[ $user_id ] ) ? (object) array( 'display_name' => $names[ $user_id ] ) : false;
	}
}

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Core\Capabilities\Capability;
use Elavira\Core\Migrations\CoreAuditSchema;
use Elavira\Core\Migrations\GuidedDecisionSchema;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\FindingStatusHistoryRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Findings\Values\FindingStatus;
use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\GuidedDecisions\DecisionContext;
use Elavira\GuidedDecisions\DecisionNavigator;
use Elavira\GuidedDecisions\DecisionRepository;
use Elavira\GuidedDecisions\DecisionState;
use Elavira\GuidedDecisions\Admin\DecisionPage;
use Elavira\Learn\Admin\LearnPage;
use Elavira\Learn\CoreTopicRegistry;
use Elavira\Learn\WcagReference;
use Elavira\Reports\Admin\ReportsPage;
use Elavira\Reports\Application\UpdateFindingStatus;
use Elavira\Reports\Export\XlsxExporter;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Reports\Application\HistoricalRuleResolver;
use Elavira\Statement\RecommendationRepository;
use Elavira\Tests\Support\FakeWpdb;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Local read-only fixture controls.
$flow   = isset( $_GET['flow'] ) ? sanitize_key( wp_unslash( $_GET['flow'] ) ) : 'a';
$view   = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'finding';
$narrow = isset( $_GET['narrow'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['narrow'] ) );
// phpcs:enable WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $flow, array( 'a', 'b', 'c', 'd', 'e' ), true ) ) {
	$flow = 'a';
}

$database = new FakeWpdb();
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The isolated fixture installs its in-memory WordPress database test double.
$GLOBALS['wpdb'] = $database;
( new CoreAuditSchema( $database ) )->up();
( new GuidedDecisionSchema( $database ) )->up();
$GLOBALS['elavira_test_options']['date_format'] = 'Y-m-d';
$GLOBALS['elavira_test_options']['time_format'] = 'H:i';
$GLOBALS['elavira_test_capabilities']           = array(
	Capability::VIEW_REPORTS => true,
	Capability::MANAGE       => true,
	'read'                   => true,
);
$GLOBALS['elavira_test_current_user_id']        = 101;

$rules            = CoreRuleRegistry::create();
$decisions        = CoreDecisionRegistry::create();
$topics           = CoreTopicRegistry::create();
$audits           = new AuditRepository( $database );
$audit_urls       = new AuditUrlRepository( $database );
$findings         = new FindingRepository( $database );
$occurrences      = new OccurrenceRepository( $database );
$history          = new FindingStatusHistoryRepository( $database );
$decision_history = new DecisionRepository( $database );
$status_service   = new UpdateFindingStatus( $findings, $history );

$definitions = array(
	'a' => array( 'elavira.image.alt_missing', '1.0.0', 'Automatic Failure — missing image alternative', 'alternative_text', 'images_alternative_text' ),
	'b' => array( 'elavira.link.purpose_review', '2.0.0', 'Potential Issue — image-only Contact link', 'link_purpose', 'links_navigation' ),
	'c' => array( 'elavira.focus.obscured_review', '1.0.0', 'Human Review — overlay and focus visibility', 'focus_visibility', 'focus_focus_order' ),
	'd' => array( 'elavira.link.purpose_review', '1.0.0', 'Historical finding — older evidence contract', 'link_purpose', '' ),
	'e' => array( 'elavira.image.alt_missing', '1.0.0', 'Multi-user finding activity', 'alternative_text', 'images_alternative_text' ),
);
list( $rule_id, $rule_version, $audit_label, $decision_flow, $topic_id ) = $definitions[ $flow ];
$rule = $rules->get( $rule_id, $rule_version );
$wcag = $rule->wcag();
$audits->create(
	array(
		'label'             => $audit_label,
		'scope'             => 'single_page',
		'wcag_target'       => '2.2_AA',
		'status'            => 'completed',
		'owner_user_id'     => 101,
		'configuration'     => array(),
		'scanner_version'   => 'integration-fixture',
		'ruleset_version'   => 'integration-fixture',
		'editorial_version' => 'integration-fixture',
		'started_at'        => '2026-09-13 09:00:00',
		'completed_at'      => '2026-09-13 09:01:00',
	)
);
$url_id     = $audit_urls->create(
	array(
		'audit_id'      => 1,
		'canonical_url' => 'https://example.org/a/representative/very-long/audited/page/url/that-must-wrap-at-narrow-widths/?fixture=' . $flow,
		'status'        => 'completed',
	)
);
$finding_id = $findings->create(
	array(
		'audit_id'            => 1,
		'rule_id'             => $rule->id(),
		'rule_version'        => $rule->version(),
		'group_key'           => 'integration:' . $flow,
		'category'            => $rule->classification(),
		'priority'            => $rule->priority(),
		'review_state'        => 'detected_failure' === $rule->classification() ? 'not_required' : 'needs_review',
		'wcag_criterion'      => $wcag ? $wcag[0]->criterion() : '',
		'wcag_level'          => $wcag ? $wcag[0]->level() : '',
		'wcag_principle'      => $wcag ? $wcag[0]->principle() : '',
		'editorial_version'   => 'd' === $flow ? '0.9.0' : $rule->editorial_version(),
		'summary_snapshot'    => $rule->editorial()['summary'],
		'affected_page_count' => 1,
		'occurrence_count'    => 'd' === $flow ? 0 : ( 'a' === $flow ? 3 : 1 ),
	)
);

if ( 'd' !== $flow ) {
	$count = 'a' === $flow ? 3 : 1;
	for ( $index = 1; $index <= $count; ++$index ) {
		$element  = array( 'tag_name' => 'img' );
		$evidence = array();
		if ( 'b' === $flow ) {
			$element  = array(
				'tag_name'          => 'a',
				'link_kind'         => 'image_only',
				'image_filename'    => 'envelope-contact-icon-with-a-long-file-name.svg',
				'image_alt_excerpt' => 'Envelope icon',
				'link_destination'  => 'https://example.org/contact/customer-support/?department=accessibility',
			);
			$evidence = array(
				'label_kind'       => 'generic',
				'destination_kind' => 'internal',
			);
		} elseif ( 'c' === $flow ) {
			$element  = array(
				'tag_name'     => 'aside',
				'text_excerpt' => 'A persistent cookie preference overlay with representative long content.',
			);
			$evidence = array(
				'position' => 'fixed',
				'boundary' => 'bottom',
				'coverage' => 'half',
			);
		}
		$occurrences->create(
			array(
				'finding_id'   => $finding_id,
				'audit_url_id' => $url_id,
				'target'       => array( 'selector' => '#main-content .component-with-an-intentionally-long-selector:nth-child(' . $index . ')' ),
				'element'      => $element,
				'evidence'     => $evidence,
			)
		);
	}
}

if ( 'a' === $flow ) {
	$status_service->update( 1, FindingStatus::OPEN, 'Located all three image occurrences and assigned the content correction before re-auditing and manually reviewing context.', 101 );
	$status_service->update( 1, FindingStatus::ADDRESSED, 'Alternative text was added where informative and an intentionally empty alternative was used only for decoration.', 101 );
} elseif ( 'b' === $flow ) {
	$status_service->update( 1, FindingStatus::NEEDS_REVIEW, 'Needs design-system review before changing the shared image-only Contact link.', 101 );
	$status_service->update( 1, FindingStatus::NEEDS_REVIEW, 'Confirmed the accessible name describes appearance rather than the Contact destination. Developer review is still needed.', 202 );
} elseif ( 'c' === $flow ) {
	$status_service->update( 1, FindingStatus::OPEN, 'Keyboard review at 400% zoom was inconclusive; the user-controlled status remains Open pending viewport testing.', 101 );
} elseif ( 'd' === $flow ) {
	$status_service->update( 1, FindingStatus::OPEN, 'Historical record reviewed without inventing missing occurrence evidence.', 101 );
} else {
	$status_service->update( 1, FindingStatus::NEEDS_REVIEW, 'Need to check this against the navigation design.', 101 );
	$status_service->update( 1, FindingStatus::NEEDS_REVIEW, 'Confirmed this is generated by the theme. Developer review needed.', 202 );
	$status_service->update( 1, FindingStatus::ADDRESSED, 'Updated theme markup and completed the relevant manual checks; a subsequent audit is still required for Verified.', 101 );
}

if ( in_array( $flow, array( 'b', 'c' ), true ) ) {
	$decision = $decisions->for_rule( $rule->id(), $rule->version() );
	if ( $decision ) {
		$decision_history->record(
			array(
				'finding_id'       => 1,
				'decision_id'      => $decision->get( 'id' ),
				'decision_version' => $decision->get( 'version' ),
				'answers'          => array(
					array(
						'question' => 'Representative integrated review',
						'label'    => 'Context still requires human judgment.',
					),
					array(
						'question'        => 'Review note and workflow choice',
						'label'           => 'Recorded without automatic resolution.',
						'workflow_status' => '',
						'previous_status' => $findings->find( 1 )['current_status'],
					),
				),
				'outcome_id'       => 'further_review',
				'recommendation'   => 'Continue the documented manual review, make any necessary change, then re-audit without treating absence of a finding as complete WCAG conformance.',
				'actor_user_id'    => 202,
			)
		);
	}
}

$reports       = new ReportsPage( $audits, $audit_urls, $findings, $occurrences, $history, new HistoricalRuleResolver( 'd' === $flow ? new \Elavira\Rules\RuleRegistry() : $rules ), $status_service, new XlsxExporter(), $decisions, $decision_history, new RecommendationRepository( $database ), $topics );
$decision_page = new DecisionPage(
	array(
		'audits'      => $audits,
		'findings'    => $findings,
		'occurrences' => $occurrences,
		'registry'    => $decisions,
		'navigator'   => new DecisionNavigator(),
		'state'       => new DecisionState(),
		'repository'  => $decision_history,
		'status'      => $status_service,
		'rules'       => $rules,
		'learn'       => $topics,
	)
);
$learn_page    = new LearnPage( $topics, $decisions, $rules, new WcagReference( $topics, $rules ) );

$_GET['finding'] = 1;
ob_start();
if ( 'report' === $view ) {
	$_GET['audit'] = 1;
	$reports->render_report();
} elseif ( 'decision' === $view && $decision_flow ) {
	$definition       = $decisions->for_rule( $rule->id(), $rule->version() );
	$_GET['decision'] = $definition->get( 'id' );
	$decision_page->render();
} elseif ( 'learn' === $view && $topic_id ) {
	$_GET['topic'] = $topic_id;
	$learn_page->detail();
} else {
	$reports->render_finding();
}
$content = ob_get_clean();

$base    = '/tools/integrated-report-browser-fixture.php?flow=' . rawurlencode( $flow ) . ( $narrow ? '&narrow=1' : '' );
$content = str_replace( 'https://example.com/wp-admin/admin.php?page=elavira-report&amp;audit=1', $base . '&view=report', $content );
$content = str_replace( 'https://example.com/wp-admin/admin.php?page=elavira-report&audit=1', $base . '&view=report', $content );
$content = str_replace( 'https://example.com/wp-admin/admin.php?page=elavira-finding&amp;finding=1&amp;audit=1', $base . '&view=finding', $content );
$content = str_replace( 'https://example.com/wp-admin/admin.php?page=elavira-finding&finding=1&audit=1', $base . '&view=finding', $content );
$content = str_replace( 'https://example.com/wp-admin/admin.php?page=elavira-finding&amp;finding=1', $base . '&view=finding', $content );
$content = str_replace( 'https://example.com/wp-admin/admin.php?page=elavira-finding&finding=1', $base . '&view=finding', $content );
if ( $decision_flow ) {
	$content = preg_replace( '#https://example\.com/wp-admin/admin\.php\?page=elavira-guided-decision(?:&amp;|&)finding=1[^"<]*#', $base . '&view=decision', $content );
}
if ( $topic_id ) {
	$content = preg_replace( '#https://example\.com/wp-admin/admin\.php\?page=elavira-learn-topic(?:&amp;|&)topic=[a-z0-9_]+#', $base . '&view=learn', $content );
}

header( 'Content-Type: text/html; charset=UTF-8' );
// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone local browser fixture.
?><!doctype html>
<html lang="en"<?php echo $narrow ? ' class="elavira-narrow"' : ''; ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Integrated Audit Report Workflow Fixture</title>
	<link rel="stylesheet" href="/assets/css/reports.css">
	<link rel="stylesheet" href="/assets/css/learn.css">
	<style>html.elavira-narrow{width:320px;max-width:320px}body{box-sizing:border-box;margin:0;padding:0 8px;background:#f0f0f1;font:16px/1.5 system-ui,sans-serif}html.elavira-narrow body{width:320px;max-width:320px}.wrap{margin:0 auto}.fixture-nav{padding:.75rem;background:#fff;border-block-end:1px solid #c3c4c7}.fixture-nav ul{display:flex;flex-wrap:wrap;gap:.5rem 1rem;margin:0;padding:0;list-style:none}button,input,select,textarea{font:inherit}</style>
</head>
<body>
	<nav class="fixture-nav" aria-label="Integration fixture flows"><ul>
	<?php
	foreach ( array( 'a', 'b', 'c', 'd', 'e' ) as $item ) :
		?>
		<li><a href="/tools/integrated-report-browser-fixture.php?flow=<?php echo esc_attr( $item ); ?><?php echo $narrow ? '&amp;narrow=1' : ''; ?>">Flow <?php echo esc_html( strtoupper( $item ) ); ?></a></li><?php endforeach; ?></ul></nav>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Actual production controllers escape their output. ?>
	<p id="fixture-layout-metrics" class="fixture-nav">Measuring fixture layout…</p>
	<script>
		(function () {
			var root = document.body;
			var metrics = document.getElementById('fixture-layout-metrics');
			metrics.textContent = 'Fixture layout: ' + root.clientWidth + 'px client width; ' + root.scrollWidth + 'px document scroll width; horizontal overflow ' + (root.scrollWidth > root.clientWidth ? 'detected' : 'not detected') + '.';
		}());
	</script>
</body>
</html>
