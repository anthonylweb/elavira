<?php
/**
 * Accessible server-rendered Core Reports screens.
 *
 * @package Elavira
 */

namespace Elavira\Reports\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, Generic.Files.LineLength
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only query parameters are validated below; mutations verify nonces in their handlers.

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Audit\Values\AuditStatus;
use Elavira\Core\Capabilities\Capability;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\FindingStatusHistoryRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Findings\Persistence\ReviewedExceptionRepository;
use Elavira\Findings\Values\FindingStatus;
use Elavira\Findings\Values\Priority;
use Elavira\Findings\Values\ReviewState;
use Elavira\GuidedDecisions\DecisionRegistry;
use Elavira\GuidedDecisions\DecisionRepository;
use Elavira\Learn\Admin\LearnPage;
use Elavira\Learn\TopicRegistry;
use Elavira\Reports\Application\HistoricalRuleResolver;
use Elavira\Reports\Application\UpdateFindingStatus;
use Elavira\Reports\Export\XlsxExporter;
use Elavira\Statement\RecommendationRepository;
use Throwable;

/** Owns the Audits, report, and finding-detail admin routes. */
final class ReportsPage {

	/** Audit persistence.
	 *
	 * @var AuditRepository
	 */ private $audits;
	/** Audit URL persistence.
	 *
	 * @var AuditUrlRepository
	 */ private $urls;
	/** Finding persistence.
	 *
	 * @var FindingRepository
	 */ private $findings;
	/** Occurrence persistence.
	 *
	 * @var OccurrenceRepository
	 */ private $occurrences;
	/** Status-history persistence.
	 *
	 * @var FindingStatusHistoryRepository
	 */ private $history;
	/** Historical rule resolver.
	 *
	 * @var HistoricalRuleResolver
	 */ private $rules;
	/** Finding status service.
	 *
	 * @var UpdateFindingStatus
	 */ private $status;
	/** Workbook exporter.
	 *
	 * @var XlsxExporter
	 */ private $xlsx;
	/** Guided Decision registry.
	 *
	 * @var DecisionRegistry
	 */
	private $decisions;
	/** Completed Guided Decision history.
	 *
	 * @var DecisionRepository
	 */
	private $decision_history;
	/** Statement recommendations.
	 *
	 * @var RecommendationRepository
	 */
	private $statement_recommendations;
	/** Learn topics.
	 *
	 * @var TopicRegistry
	 */
	private $learn;
	/** Reviewed-exception persistence. @var ReviewedExceptionRepository|null */
	private $exceptions;

	/**
	 * Create the reports page controller.
	 *
	 * @param AuditRepository                $audits Audit persistence.
	 * @param AuditUrlRepository             $urls Audit URL persistence.
	 * @param FindingRepository              $findings Finding persistence.
	 * @param OccurrenceRepository           $occurrences Occurrence persistence.
	 * @param FindingStatusHistoryRepository $history Status-history persistence.
	 * @param HistoricalRuleResolver         $rules Historical rule resolver.
	 * @param UpdateFindingStatus            $status Finding status service.
	 * @param XlsxExporter                   $xlsx Workbook exporter.
	 * @param DecisionRegistry               $decisions Guided Decision registry.
	 * @param DecisionRepository             $decision_history Completed decision history.
	 * @param RecommendationRepository       $statement_recommendations Statement recommendations.
	 * @param TopicRegistry                  $learn Learn topics.
	 */
	public function __construct( AuditRepository $audits, AuditUrlRepository $urls, FindingRepository $findings, OccurrenceRepository $occurrences, FindingStatusHistoryRepository $history, HistoricalRuleResolver $rules, UpdateFindingStatus $status, XlsxExporter $xlsx, DecisionRegistry $decisions, DecisionRepository $decision_history, RecommendationRepository $statement_recommendations, TopicRegistry $learn, ?ReviewedExceptionRepository $exceptions = null ) {
		$this->audits                    = $audits;
		$this->urls                      = $urls;
		$this->findings                  = $findings;
		$this->occurrences               = $occurrences;
		$this->history                   = $history;
		$this->rules                     = $rules;
		$this->status                    = $status;
		$this->xlsx                      = $xlsx;
		$this->decisions                 = $decisions;
		$this->decision_history          = $decision_history;
		$this->statement_recommendations = $statement_recommendations;
		$this->learn                     = $learn;
		$this->exceptions                = $exceptions;
	}

	/** Register stable admin routes. @return void */
	public function register() {
		add_submenu_page( 'elavira-dashboard', __( 'Audits', 'elavira' ), __( 'Audits', 'elavira' ), Capability::VIEW_REPORTS, 'elavira-audits', array( $this, 'render_history' ), 10 );
		add_submenu_page( null, __( 'Elavira Audit Report', 'elavira' ), __( 'Audit Report', 'elavira' ), Capability::VIEW_REPORTS, 'elavira-report', array( $this, 'render_report' ) );
		add_submenu_page( null, __( 'Elavira Finding', 'elavira' ), __( 'Finding', 'elavira' ), Capability::VIEW_REPORTS, 'elavira-finding', array( $this, 'render_finding' ) );
	}

	/**
	 * Load scoped report styles only.
	 *
	 * @param string $hook_suffix Screen hook.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		unset( $hook_suffix );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selection.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( in_array( $page, array( 'elavira-audits', 'elavira-report', 'elavira-finding', 'elavira-guided-decision' ), true ) ) {
			wp_enqueue_script( 'elavira-reports', ELAVIRA_URL . 'assets/js/reports.js', array(), ELAVIRA_VERSION, true );
			wp_enqueue_style( 'elavira-reports', ELAVIRA_URL . 'assets/css/reports.css', array(), ELAVIRA_VERSION );
		}
	}

	/** Render paginated audit history. @return void */
	public function render_history() {
		$this->authorize_view();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$target = isset( $_GET['wcag_target'] ) ? sanitize_text_field( wp_unslash( $_GET['wcag_target'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		if ( '' !== $status && ! in_array( $status, AuditStatus::all(), true ) ) {
			$status = '';
		}
		if ( '' !== $target && ! in_array( $target, array( '2.2_A', '2.2_AA' ), true ) ) {
			$target = '';
		}
		$result = $this->audits->paginate( get_current_user_id(), $page, 20, $status, $target );
		?>
		<div class="wrap elavira-reports"><h1><?php echo esc_html__( 'Accessibility Audits', 'elavira' ); ?></h1>
		<p><?php echo esc_html__( 'Historical reports from your rendered single-page audits. Automated checks do not evaluate every accessibility requirement.', 'elavira' ); ?></p>
		<?php
		if ( current_user_can( Capability::RUN_AUDITS ) ) :
			?>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-run-audit' ) ); ?>"><?php echo esc_html__( 'Run Audit', 'elavira' ); ?></a></p><?php endif; ?>
		<form method="get" class="elavira-filters"><input type="hidden" name="page" value="elavira-audits">
		<div class="elavira-filter-field"><label for="elavira-audit-status"><?php echo esc_html__( 'Audit status', 'elavira' ); ?></label><select id="elavira-audit-status" name="status"><option value=""><?php echo esc_html__( 'All statuses', 'elavira' ); ?></option><?php $this->options( AuditStatus::all(), $status ); ?></select></div>
		<div class="elavira-filter-field"><label for="elavira-wcag-target"><?php echo esc_html__( 'WCAG target', 'elavira' ); ?></label><select id="elavira-wcag-target" name="wcag_target"><option value=""><?php echo esc_html__( 'All targets', 'elavira' ); ?></option><?php $this->options( array( '2.2_A', '2.2_AA' ), $target ); ?></select></div>
		<button class="button" type="submit"><?php echo esc_html__( 'Filter audits', 'elavira' ); ?></button></form>
		<?php
		if ( empty( $result['items'] ) ) :
			?>
			<div class="notice notice-info inline"><p><?php echo esc_html__( 'No audits match these filters. Run a single-page scan from a supported page to create a report.', 'elavira' ); ?></p></div>
			<?php
		else :
			?>
			<table class="widefat striped elavira-responsive-table"><caption class="screen-reader-text"><?php echo esc_html__( 'Historical accessibility audits', 'elavira' ); ?></caption><thead><tr><th scope="col"><?php echo esc_html__( 'Audit', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Page scanned', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Scope', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'WCAG target', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Status', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Rule groups detected (all statuses)', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Date', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Action', 'elavira' ); ?></th></tr></thead><tbody>
			<?php
			foreach ( $result['items'] as $audit ) :
				?>
				<tr><th scope="row" data-label="<?php echo esc_attr__( 'Audit', 'elavira' ); ?>"><?php echo esc_html( $audit['label'] ); ?></th><td data-label="<?php echo esc_attr__( 'Page scanned', 'elavira' ); ?>"><?php echo esc_html( $audit['canonical_url'] ? $audit['canonical_url'] : __( 'Unavailable', 'elavira' ) ); ?></td><td data-label="<?php echo esc_attr__( 'Scope', 'elavira' ); ?>"><?php echo esc_html__( 'Single page', 'elavira' ); ?></td><td data-label="<?php echo esc_attr__( 'WCAG target', 'elavira' ); ?>"><?php echo esc_html( $this->target_label( $audit['wcag_target'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Status', 'elavira' ); ?>"><span class="elavira-label"><?php echo esc_html( $this->label( $audit['status'] ) ); ?></span></td><td data-label="<?php echo esc_attr__( 'Rule groups detected (all statuses)', 'elavira' ); ?>"><?php echo esc_html( number_format_i18n( $audit['finding_count'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Date', 'elavira' ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $audit['created_at'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Action', 'elavira' ); ?>"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-report&audit=' . absint( $audit['id'] ) ) ); ?>"><?php echo esc_html__( 'View report', 'elavira' ); ?></a></td></tr>
				<?php
			endforeach;
			?>
		</tbody></table>
			<?php
			$this->pagination( $page, $result['pages'] );
		endif;
		?>
		</div>
		<?php
	}

	/** Render an individual audit. @return void */
	public function render_report() {
		$this->authorize_view();
		$audit = $this->requested_audit();
		if ( ! $audit ) {
			$this->not_found( __( 'This audit report is not available.', 'elavira' ) );
			return;
		}
		$urls = $this->urls->find_by_audit( $audit['id'] );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters and pagination.
		$filters = array(
			'priority'       => isset( $_GET['priority'] ) ? sanitize_key( wp_unslash( $_GET['priority'] ) ) : '',
			'current_status' => isset( $_GET['finding_status'] ) ? sanitize_key( wp_unslash( $_GET['finding_status'] ) ) : '',
			'review_state'   => isset( $_GET['review_state'] ) ? sanitize_key( wp_unslash( $_GET['review_state'] ) ) : '',
			'wcag_level'     => isset( $_GET['wcag_level'] ) ? sanitize_text_field( wp_unslash( $_GET['wcag_level'] ) ) : '',
			'search'         => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'visibility'     => isset( $_GET['visibility'] ) ? sanitize_key( wp_unslash( $_GET['visibility'] ) ) : 'active',
		);
		$page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Remaining query parameters are read-only and individually validated.
		foreach ( array(
			'priority'       => Priority::all(),
			'current_status' => FindingStatus::all(),
			'review_state'   => ReviewState::all(),
			'wcag_level'     => array( 'A', 'AA', 'AAA' ),
		) as $key => $allowed ) {
			if ( '' !== $filters[ $key ] && ! in_array( $filters[ $key ], $allowed, true ) ) {
				$filters[ $key ] = '';
			}
		}
		if ( ! in_array( $filters['visibility'], array( 'active', 'reviewed', 'all' ), true ) ) {
			$filters['visibility'] = 'active';
		}

		$report_findings = $this->findings->find_by_audit( $audit['id'] );
		$sections        = \Elavira\Reports\Application\OccurrenceReport::build( $report_findings, $this->occurrences->find_by_audit( $audit['id'] ) );
		?>
		<div class="wrap elavira-reports"><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-audits' ) ); ?>">&larr; <?php echo esc_html__( 'All audits', 'elavira' ); ?></a></p><h1><?php echo esc_html__( 'Audit Report', 'elavira' ); ?></h1><h2><?php echo esc_html( $audit['label'] ); ?></h2>
		<?php
		$count = $this->rules->current_rule_count();
		/* translators: %s: number of currently registered Core browser rules, excluding historical versions. */
		echo '<p>' . esc_html( sprintf( _n( 'Elavira Core currently provides %s automated accessibility rule.', 'Elavira Core currently provides %s automated accessibility rules.', $count, 'elavira' ), number_format_i18n( $count ) ) ) . ' ' . esc_html__( 'This report shows what the rules included in this audit found on the page: detected failures, potential issues, and items that need a person to review. Older audits may use a different ruleset, shown below.', 'elavira' ) . '</p>';
		echo '<p>' . esc_html__( 'Automated testing cannot determine whether every part of a page is accessible, so some findings ask you to make a decision. Elavira provides guidance on what to look for and, when available, questions to help you decide what needs to change.', 'elavira' ) . '</p>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only feedback after a nonce-protected action.
		if ( isset( $_GET['elavira_notice'] ) && 'exceptions_reset' === sanitize_key( wp_unslash( $_GET['elavira_notice'] ) ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Reviewed exceptions reset. Historical reports and decisions were preserved.', 'elavira' ) . '</p></div>';
		}
		if ( $this->statement_recommendations->pending_for_audit( $audit['id'] ) ) :
			?>
			<div class="notice notice-info inline"><p><?php echo esc_html__( 'This audit identified findings that may affect your Accessibility Statement.', 'elavira' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-statement' ) ); ?>"><?php echo esc_html__( 'Review statement recommendations', 'elavira' ); ?></a></p></div><?php endif; ?>
		<div class="elavira-summary" aria-label="<?php echo esc_attr__( 'Audit summary', 'elavira' ); ?>"><dl><div><dt><?php echo esc_html__( 'Page scanned', 'elavira' ); ?></dt><dd><?php echo isset( $urls[0] ) ? wp_kses_post( $this->captured_page_link( $urls[0]['canonical_url'] ) ) : esc_html__( 'Unavailable', 'elavira' ); ?></dd></div><div><dt><?php echo esc_html__( 'Status', 'elavira' ); ?></dt><dd><?php echo esc_html( $this->label( $audit['status'] ) ); ?></dd></div><div><dt><?php echo esc_html__( 'WCAG target', 'elavira' ); ?></dt><dd><?php echo esc_html( $this->target_label( $audit['wcag_target'] ) ); ?></dd></div><div><dt><?php echo esc_html__( 'Scanner / ruleset', 'elavira' ); ?></dt><dd><code><?php echo esc_html( $audit['scanner_version'] . ' / ' . $audit['ruleset_version'] ); ?></code></dd></div></dl></div>
		<?php OccurrenceReviewView::summary( $sections, count( $report_findings ) ); ?><p><?php echo esc_html__( 'Summary counts include the entire audit. Filters narrow the occurrence entries displayed below.', 'elavira' ); ?></p>
		<p class="elavira-disclaimer"><strong><?php echo esc_html__( 'Plugin findings, not an accessibility score:', 'elavira' ); ?></strong> <?php echo esc_html__( 'Priority is an internal organizational recommendation, not an official WCAG severity or conformance grade. Automated testing is incomplete.', 'elavira' ); ?> <?php echo self::new_tab_link( LearnPage::url( 'understanding_audit_results' ), __( 'Learn how to interpret audit results', 'elavira' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes components. ?>.</p>
		<?php
		$this->reset_controls( $audit, $urls );
		if ( AuditStatus::COMPLETED !== $audit['status'] ) :
			?>
			<div class="notice notice-warning inline"><p><?php echo esc_html__( 'This audit did not complete. Any partial data must not be treated as a complete report.', 'elavira' ); ?></p></div>
			<?php
		else :
			?>
			<?php
			$this->finding_filters( $audit['id'], $filters );
			OccurrenceReviewView::sections( \Elavira\Reports\Application\OccurrenceReport::filter( $sections, $filters ), $this->rules, $this->occurrences->reviews() );
			$this->reset_controls( $audit, $urls );
		endif;
		?>
		</div>
		<?php
	}

	/** Render grouped finding detail and occurrences. @return void */
	public function render_finding() {
		$this->authorize_view();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only identifier.
		$finding_id = isset( $_GET['finding'] ) ? absint( $_GET['finding'] ) : 0;
		$finding    = $this->findings->find( $finding_id );
		$audit      = $finding ? $this->audits->find( $finding['audit_id'] ) : null;
		if ( ! $finding || ! $audit || get_current_user_id() !== (int) $audit['owner_user_id'] ) {
			$this->not_found( __( 'This finding is not available.', 'elavira' ) );
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page        = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$occurrences = $this->occurrences->paginate_by_finding( $finding_id, $page, 25 );
		$selected_id = isset( $_GET['occurrence'] ) ? absint( $_GET['occurrence'] ) : 0;
		if ( $selected_id ) {
			$selected = $this->occurrences->find( $selected_id );
			if ( ! $selected || (int) $selected['finding_id'] !== (int) $finding_id ) {
				$this->not_found( __( 'This occurrence is not available.', 'elavira' ) );
				return; }
			$url                       = $this->urls->find( $selected['audit_url_id'] );
			$selected['canonical_url'] = $url['canonical_url'];
			$finding['current_status'] = \Elavira\Findings\OccurrenceReview::status( $selected, $finding );
			$occurrences               = array(
				'items' => array( $selected ),
				'pages' => 1,
				'total' => 1,
			);
		}

		$notes = $this->occurrences->reviews()->latest_notes( array_column( $occurrences['items'], 'id' ) );
		foreach ( $occurrences['items'] as &$item ) {
			$item['latest_note'] = $notes[ $item['id'] ] ?? '';
		}
		unset( $item );
		$history   = $this->history->find_by_finding( $finding_id );
		$resolved  = $this->rules->resolve( $finding );
		$editorial = $resolved['editorial'];
		?>
		<div class="wrap elavira-reports"><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-report&audit=' . absint( $audit['id'] ) ) ); ?>">&larr; <?php echo esc_html__( 'Back to audit report', 'elavira' ); ?></a></p><h1><?php echo esc_html( $editorial['title'] ); ?></h1>
		<?php $this->status_notice(); ?>
		<?php if ( 'structural_warning' === ( $editorial['presentation'] ?? '' ) ) : ?>
			<div class="elavira-structural-warning" role="note"><strong><?php echo esc_html__( 'Structural Warning', 'elavira' ); ?></strong><p><?php echo esc_html( $editorial['summary'] ?? $finding['summary_snapshot'] ); ?></p><p><strong><?php echo esc_html__( 'Human Review — this check needs your judgement.', 'elavira' ); ?></strong> <?php echo esc_html__( 'The automated finding alone does not establish an accessibility failure.', 'elavira' ); ?></p></div>
		<?php endif; ?>
		<ul class="elavira-finding-meta"><li><strong><?php echo esc_html__( 'Workflow status:', 'elavira' ); ?></strong> <?php echo esc_html( $this->label( $finding['current_status'] ) ); ?></li><li><strong><?php echo esc_html__( 'Priority:', 'elavira' ); ?></strong> <?php echo esc_html( $this->label( $finding['priority'] ) ); ?></li><li><strong><?php echo esc_html__( 'Review classification:', 'elavira' ); ?></strong> <?php echo esc_html( $this->label( $finding['review_state'] ) ); ?></li><li><strong><?php echo esc_html__( 'Standards context:', 'elavira' ); ?></strong> <?php echo esc_html( $this->wcag_label( $finding ) ); ?></li></ul>
		<?php
		if ( ! $resolved['available'] ) :
			?>
			<div class="notice notice-warning inline"><p><?php echo esc_html__( 'The exact historical guidance version is unavailable. The stored audit-time summary is shown without substituting current guidance.', 'elavira' ); ?></p></div>
			<?php
		endif;
		?>
		<?php
		if ( ReviewState::NEEDS_REVIEW === $finding['review_state'] && 'structural_warning' !== ( $editorial['presentation'] ?? '' ) ) :
			?>
			<div class="notice notice-info inline"><p><strong><?php echo esc_html__( 'Human Review — this check needs your judgement.', 'elavira' ); ?></strong> <?php echo esc_html__( 'Automated scanning cannot determine the final accessibility outcome for this finding.', 'elavira' ); ?></p></div>
			<?php
		endif;
		?>
		<?php
		if ( 'structural_warning' !== ( $editorial['presentation'] ?? '' ) && ReviewState::NEEDS_REVIEW !== $finding['review_state'] ) {
			$result_type = $finding['result_type'] ?? $finding['category'];
			if ( 'human_review' === $result_type ) {
				echo '<section><h2>' . esc_html__( 'Rule result', 'elavira' ) . '</h2><p><strong>' . esc_html__( 'Human Review — this check needs your judgement.', 'elavira' ) . '</strong> ' . esc_html( $this->result_description( $result_type ) ) . '</p></section>';
			} else {
				$this->section( __( 'Rule result', 'elavira' ), $this->result_description( $result_type ) );
			}
		}
		$this->section( __( 'What Elavira found', 'elavira' ), $editorial['summary'] ?? $finding['summary_snapshot'] );
		?>
		<h2><?php echo esc_html__( 'Where we found it', 'elavira' ); ?></h2>
		<?php
		if ( empty( $occurrences['items'] ) ) :
			?>
			<p><?php echo esc_html__( 'No occurrence evidence is available.', 'elavira' ); ?></p>
			<?php
		else :
			?>
			<ol class="elavira-occurrences">
			<?php
			foreach ( $occurrences['items'] as $occurrence ) :
				$identification = ( new OccurrencePresenter() )->present( $occurrence, $finding['rule_id'] );
				?>
	<li><h3><?php echo esc_html( $identification['heading'] ); ?></h3><p><strong><?php echo esc_html__( 'Audited page:', 'elavira' ); ?></strong> <?php echo $this->captured_page_link( $occurrence['canonical_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes every component. ?></p>
				<?php
				if ( $identification['rows'] ) {
					echo '<dl class="elavira-identification">';
					foreach ( $identification['rows'] as $row ) {
						echo '<div><dt>' . esc_html( $row['label'] ) . '</dt><dd>' . esc_html( $row['value'] ) . '</dd></div>';
					}
					echo '</dl>';
				}
				$trigger = \Elavira\Learn\AriaGuidance::trigger( $finding['rule_id'], (array) json_decode( $occurrence['evidence_json'], true ) );
				if ( $trigger ) {
					echo '<p><strong>' . esc_html__( 'Why this occurrence was reported:', 'elavira' ) . '</strong> ' . esc_html( $trigger ) . '</p>'; }
				echo '<details class="elavira-technical"><summary>' . esc_html__( 'Technical details for this occurrence', 'elavira' ) . '</summary>';
				$target = (array) json_decode( $occurrence['target_json'], true );
				if ( ! empty( $target['element_classes'] ) ) {
					$target['element_classes'] = '.' . implode( ' .', preg_split( '/\s+/', trim( $target['element_classes'] ) ) ); }
				$this->structured( __( 'Target (classes may identify multiple elements)', 'elavira' ), wp_json_encode( $target ) );
				$this->structured( __( 'Evidence', 'elavira' ), $occurrence['evidence_json'] );
				$this->structured( __( 'Element metadata', 'elavira' ), wp_json_encode( $identification['technical_element'] ) );
				$this->diagnostics( $occurrence['diagnostic_json'] ?? '' );
				echo '</details>';
				OccurrenceReviewView::form( $finding, $occurrence );
				if ( ! empty( $occurrence['previous_occurrence_id'] ) ) {
					$prior = $this->occurrences->find( $occurrence['previous_occurrence_id'] );
					if ( $prior ) {
						echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=elavira-finding&finding=' . absint( $prior['finding_id'] ) . '&occurrence=' . absint( $prior['id'] ) ) ) . '">' . esc_html__( 'Review the previous occurrence and decision history', 'elavira' ) . '</a></p>'; }
				}

				$this->activity_history( $this->occurrences->reviews()->history( $occurrence['id'] ), true );
				?>
	</li>
				<?php
			endforeach;
			?>
			</ol>
			<?php
			$this->pagination( $page, $occurrences['pages'] );
		endif;
		$this->section( __( 'Why it matters', 'elavira' ), $editorial['why_it_matters'] ?? '' );
		$this->section( __( 'What to check', 'elavira' ), $editorial['what_to_check'] ?? '' );
		$this->guided_decision_action( $finding );
		$this->learn_action( $resolved );
		if ( \Elavira\Learn\AriaGuidance::reference( $finding['rule_id'] ) ) {
			$url = add_query_arg(
				array(
					'rule'    => $finding['rule_id'],
					'version' => $finding['rule_version'],
				),
				LearnPage::url( 'aria_accessible_controls' )
			);
			echo '<p><a href="' . esc_url( $url ) . '#aria-rule-context">' . esc_html__( 'Learn why this ARIA rule was triggered and review its WAI-ARIA guidance', 'elavira' ) . '</a></p>';
		}
		$this->section( __( 'How to fix it', 'elavira' ), $editorial['recommendation'] ?? '' );
		$this->wordpress_guidance( $editorial );
		$this->examples( $editorial['examples'] ?? array() );
		$this->section( __( 'How to verify it', 'elavira' ), $editorial['verification_guidance'] ?? '' );
		echo '<details class="elavira-technical"><summary>' . esc_html__( 'Technical details', 'elavira' ) . '</summary><dl><dt>' . esc_html__( 'Rule ID and version', 'elavira' ) . '</dt><dd><code>' . esc_html( $finding['rule_id'] . ' / ' . $finding['rule_version'] ) . '</code></dd><dt>' . esc_html__( 'WCAG reference', 'elavira' ) . '</dt><dd>' . esc_html( $this->wcag_label( $finding ) ) . '</dd></dl>';
		$this->section( __( 'Developer guidance', 'elavira' ), $editorial['developer_guidance'] ?? '' );
		echo '</details>';
		if ( empty( $occurrences['items'] ) && current_user_can( Capability::MANAGE ) ) {
			$this->status_form( $finding ); }
		echo '<p>' . esc_html__( 'Earlier grouped activity is retained below. Review each occurrence independently above.', 'elavira' ) . '</p>';
		$this->guided_decision_history( $finding_id );
		?>
		<?php $this->activity_history( $history ); ?>
		</div>
			<?php
	}

	/** Render contextual education only for an exact historical rule definition.
	 *
	 * @param array $resolved Rule resolution.
	 * @return void
	 */
	private function learn_action( array $resolved ) {
		$id    = $resolved['available'] ? ( $resolved['editorial']['learn_topic'] ?? '' ) : '';
		$topic = $id ? $this->learn->find( $id ) : null;
		if ( $topic ) {
			/* translators: %s: Learn topic title. */
			echo '<p>' . self::new_tab_link( LearnPage::url( $id ), sprintf( __( 'Learn about %s', 'elavira' ), $topic->get( 'title' ) ), 'button' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes components.
		}
	}

	/** Handle a nonce-protected status update. @return void */
	public function update_status() {
		if ( ! is_user_logged_in() || ! current_user_can( Capability::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to update findings.', 'elavira' ), '', array( 'response' => 403 ) );
		}
		$finding_id = isset( $_POST['finding'] ) ? absint( $_POST['finding'] ) : 0;
		check_admin_referer( 'elavira_update_finding_' . $finding_id );
		$finding = $this->findings->find( $finding_id );
		$audit   = $finding ? $this->audits->find( $finding['audit_id'] ) : null;
		if ( ! $finding || ! $audit || get_current_user_id() !== (int) $audit['owner_user_id'] ) {
			wp_die( esc_html__( 'This finding is not available.', 'elavira' ), '', array( 'response' => 404 ) );
		}
		$status = isset( $_POST['finding_status'] ) ? sanitize_key( wp_unslash( $_POST['finding_status'] ) ) : '';
		$reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		try {
			$occurrence_id = isset( $_POST['occurrence'] ) ? absint( $_POST['occurrence'] ) : 0;
			$history_id    = $occurrence_id ? $this->occurrences->reviews()->update( $finding_id, $occurrence_id, $status, $reason, get_current_user_id() ) : $this->status->update( $finding_id, $status, $reason, get_current_user_id() );
			$notice        = $history_id ? 'updated' : 'no_changes';
		} catch ( Throwable $error ) {
			$notice = 'rejected';
			ActivityForm::reject( $finding_id, $occurrence_id, $status, $reason );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'elavira_notice' => $notice,
					'occurrence'     => isset( $_POST['occurrence'] ) ? absint( $_POST['occurrence'] ) : 0,
				),
				admin_url( 'admin.php?page=elavira-finding&finding=' . $finding_id )
			) . '#elavira-status-notice'
		);
		exit;
	}

	/** Handle a nonce-protected reviewed-exception reset. @return void */
	public function reset_exceptions() {
		if ( ! is_user_logged_in() || ! current_user_can( Capability::MANAGE ) || ! $this->exceptions ) {
			wp_die( esc_html__( 'You are not allowed to reset reviewed exceptions.', 'elavira' ), '', array( 'response' => 403 ) );
		}
		$scope    = isset( $_POST['reset_scope'] ) ? sanitize_key( wp_unslash( $_POST['reset_scope'] ) ) : '';
		$audit_id = isset( $_POST['audit'] ) ? absint( $_POST['audit'] ) : 0;
		check_admin_referer( 'elavira_reset_reviewed_exceptions_' . $scope . '_' . $audit_id );
		if ( 'page' === $scope ) {
			$audit = $this->audits->find( $audit_id );
			$urls  = $audit ? $this->urls->find_by_audit( $audit_id ) : array();
			if ( ! $audit || get_current_user_id() !== (int) $audit['owner_user_id'] || empty( $urls[0]['canonical_url_hash'] ) ) {
				wp_die( esc_html__( 'This audited page is not available.', 'elavira' ), '', array( 'response' => 404 ) );
			}
			$this->exceptions->reset_page( $urls[0]['canonical_url_hash'], get_current_user_id() );
		} elseif ( 'all' === $scope ) {
			$this->exceptions->reset_all( get_current_user_id() );
		} else {
			wp_die( esc_html__( 'Choose a valid reset scope.', 'elavira' ), '', array( 'response' => 400 ) );
		}
		wp_safe_redirect( add_query_arg( 'elavira_notice', 'exceptions_reset', admin_url( 'admin.php?page=elavira-report&audit=' . $audit_id ) ) );
		exit;
	}

	/** Stream an authorized completed-audit workbook. @return void */
	public function export() {
		$this->authorize_view();
		$audit_id = isset( $_GET['audit'] ) ? absint( $_GET['audit'] ) : 0;
		check_admin_referer( 'elavira_export_audit_' . $audit_id );
		$audit = $this->audits->find( $audit_id );
		if ( ! $audit || get_current_user_id() !== (int) $audit['owner_user_id'] || AuditStatus::COMPLETED !== $audit['status'] ) {
			wp_die( esc_html__( 'This completed audit is not available for export.', 'elavira' ), '', array( 'response' => 404 ) );
		}
		$urls        = $this->urls->find_by_audit( $audit_id );
		$findings    = $this->findings->find_by_audit( $audit_id );
		$occurrences = $this->occurrences->find_by_audit( $audit_id );
		$history     = $this->history->find_by_audit( $audit_id );
		$sections    = \Elavira\Reports\Application\OccurrenceReport::build( $findings, $occurrences );
		$summary     = array( array( __( 'Field', 'elavira' ), __( 'Value', 'elavira' ) ), array( __( 'Audit', 'elavira' ), $audit['label'] ), array( __( 'Scope', 'elavira' ), __( 'Single page', 'elavira' ) ), array( __( 'WCAG target', 'elavira' ), $this->target_label( $audit['wcag_target'] ) ), array( __( 'Status', 'elavira' ), $this->label( $audit['status'] ) ), array( __( 'Started', 'elavira' ), $audit['started_at'] ), array( __( 'Completed', 'elavira' ), $audit['completed_at'] ), array( __( 'Grouped plugin findings', 'elavira' ), count( $findings ) ), array( __( 'Page URL', 'elavira' ), $urls[0]['canonical_url'] ?? '' ), array( __( 'Important limitation', 'elavira' ), __( 'Plugin findings are not an accessibility score or proof of conformance. Automated testing is incomplete.', 'elavira' ) ) );
		foreach ( array(
			'active'    => __( 'Active occurrences', 'elavira' ),
			'addressed' => __( 'Addressed occurrences', 'elavira' ),
			'accepted'  => __( 'Accepted occurrences', 'elavira' ),
			'verified'  => __( 'Verified occurrences', 'elavira' ),
		) as $key => $label ) {
			$summary[] = array( $label, count( $sections[ $key ] ) ); }
		$summary[] = array( __( 'Needs Correction occurrences (included in active)', 'elavira' ), \Elavira\Reports\Application\OccurrenceReport::correction_count( $sections ) );
		$summary[] = array( __( 'Total detected occurrences', 'elavira' ), array_sum( array_map( 'count', $sections ) ) );
		$issues    = array( array( __( 'Status', 'elavira' ), __( 'Priority', 'elavira' ), __( 'Issue', 'elavira' ), __( 'Classification', 'elavira' ), __( 'Review state', 'elavira' ), __( 'WCAG criterion', 'elavira' ), __( 'WCAG level', 'elavira' ), __( 'Occurrences', 'elavira' ), __( 'Affected pages', 'elavira' ), __( 'Recommendation', 'elavira' ), __( 'WordPress guidance', 'elavira' ), __( 'Developer guidance', 'elavira' ) ) );
		foreach ( $findings as $finding ) {
			$resolved  = $this->rules->resolve( $finding );
			$editorial = $resolved['editorial'];
			$issues[]  = array( $this->label( $finding['current_status'] ), $this->label( $finding['priority'] ), $editorial['title'], $this->label( $finding['category'] ), $this->label( $finding['review_state'] ), $finding['wcag_criterion'], $finding['wcag_level'], $finding['occurrence_count'], $finding['affected_page_count'], $editorial['recommendation'] ?? __( 'Exact historical recommendation unavailable.', 'elavira' ), ! empty( $editorial['wordpress_guidance_useful'] ) ? $editorial['wordpress_guidance'] : '', $editorial['developer_guidance'] ?? '' );
		}
		$affected = array( array( __( 'Occurrence ID', 'elavira' ), __( 'Occurrence status', 'elavira' ), __( 'Finding', 'elavira' ), __( 'Captured URL', 'elavira' ), __( 'Target', 'elavira' ), __( 'Evidence', 'elavira' ), __( 'Element metadata', 'elavira' ), __( 'Diagnostic causes', 'elavira' ) ) );
		foreach ( $occurrences as $occurrence ) {
			$occurrence_finding = $this->findings->find( $occurrence['finding_id'] );
			$affected[]         = array( $occurrence['id'], OccurrenceReviewView::label( \Elavira\Findings\OccurrenceReview::status( $occurrence, $occurrence_finding ) ), $occurrence['summary_snapshot'], $occurrence['canonical_url'], $occurrence['target_json'], $occurrence['evidence_json'], $occurrence['element_json'], $occurrence['diagnostic_json'] ?? '' );
		}
		$priority    = array( array( __( 'Priority', 'elavira' ), __( 'Meaning', 'elavira' ) ), array( __( 'High', 'elavira' ), __( 'Likely to create a significant accessibility barrier and generally should be investigated first.', 'elavira' ) ), array( __( 'Medium', 'elavira' ), __( 'Meaningful accessibility concern that should be addressed, with less immediate impact than High.', 'elavira' ) ), array( __( 'Low', 'elavira' ), __( 'Lower-impact accessibility concern or best-practice issue.', 'elavira' ) ), array( __( 'Disclaimer', 'elavira' ), __( 'Priority is an internal organizational recommendation, not an official WCAG severity or conformance grading system.', 'elavira' ) ) );
		$status_rows = array( array( __( 'Finding', 'elavira' ), __( 'Resulting status', 'elavira' ), __( 'Activity note', 'elavira' ), __( 'Actor user ID', 'elavira' ), __( 'Source audit ID', 'elavira' ), __( 'Date', 'elavira' ) ) );
		foreach ( $history as $entry ) {
			$status_rows[] = array( $entry['summary_snapshot'], $this->label( $entry['status'] ), \Elavira\Reports\Application\ActivityNote::display( $entry, $this->decision_history->find_by_finding( $entry['finding_id'] ) ), $entry['actor_user_id'], $entry['source_audit_id'], $entry['created_at'] );
		}
		try {
			$file = $this->xlsx->create(
				array(
					__( 'Audit Summary', 'elavira' )    => $summary,
					__( 'Issues', 'elavira' )           => $issues,
					__( 'Affected URLs', 'elavira' )    => $affected,
					__( 'Priority Guide', 'elavira' )   => $priority,
					__( 'Finding Activity', 'elavira' ) => $status_rows,
				)
			);
		} catch ( Throwable $error ) {
			wp_die( esc_html( $error->getMessage() ), '', array( 'response' => 500 ) );
		}
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="elavira-audit-' . $audit_id . '.xlsx"' );
		header( 'Content-Length: ' . filesize( $file ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams a generated local workbook to the authorized user.
		readfile( $file );
		wp_delete_file( $file );
		exit;
	}

	/** Require report-view permission. */
	private function authorize_view() {
		if ( ! current_user_can( Capability::VIEW_REPORTS ) ) {
			wp_die( esc_html__( 'You are not allowed to view audit reports.', 'elavira' ), '', array( 'response' => 403 ) );
		}
	}
	/** Return the requested audit when owned by the current user. */
	private function requested_audit() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only identifier.
		$id    = isset( $_GET['audit'] ) ? absint( $_GET['audit'] ) : 0;
		$audit = $this->audits->find( $id );
		return $audit && get_current_user_id() === (int) $audit['owner_user_id'] ? $audit : null;
	}
	/** Render a safe unavailable-report message.
	 *
	 * @param string $message Message.
	 */
	private function not_found( $message ) {
		echo '<div class="wrap elavira-reports"><h1>' . esc_html__( 'Report unavailable', 'elavira' ) . '</h1><div class="notice notice-error inline"><p>' . esc_html( $message ) . '</p></div></div>';
	}
	/** Create a human-readable enum label.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function label( $value ) {
		$target_labels = array(
			'element_id'      => __( 'Element ID', 'elavira' ),
			'element_classes' => __( 'Element classes', 'elavira' ),
			'selector'        => __( 'Hierarchical selector', 'elavira' ),
		);
		if ( isset( $target_labels[ $value ] ) ) {
			return $target_labels[ $value ]; }
		if ( in_array( $value, FindingStatus::all(), true ) ) {
			return OccurrenceReviewView::label( $value );
		}
		if ( FindingStatus::REVIEWED_NOT_ISSUE === $value ) {
			return __( 'Reviewed — Not an Issue', 'elavira' );
		}
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	/** Return WCAG metadata without manufacturing a conformance association. @return string */
	private function wcag_label( array $finding ) {
		if ( empty( $finding['wcag_criterion'] ) ) {
			return __( 'Best practice — no direct WCAG mapping', 'elavira' );
		}
		return $finding['wcag_criterion'] . ' Level ' . $finding['wcag_level'] . ' — ' . $this->label( $finding['wcag_principle'] );
	}
	/** Create a WCAG target label.
	 *
	 * @param string $value Target.
	 * @return string
	 */
	private function target_label( $value ) {
		return '2.2_AA' === $value ? __( 'WCAG 2.2 Level AA', 'elavira' ) : __( 'WCAG 2.2 Level A', 'elavira' );
	}
	/** Explain scanner result without implying criterion or page conformance. @return string */
	private function result_description( $value ) {
		$descriptions = array(
			'automatic_failure' => __( 'Automatic Failure — Elavira has enough objective evidence to confidently identify this as an accessibility failure. The guidance below explains what to change and how to check the result.', 'elavira' ),
			'potential_issue'   => __( 'Potential Issue — Elavira found something that may cause an accessibility problem. Check it in context using the guidance below before deciding whether it needs a change.', 'elavira' ),
			'human_review'      => __( 'Elavira will explain what to look for so you can decide whether there is an accessibility problem and what to do next.', 'elavira' ),
			'detected_failure'  => __( 'Automatic Failure — Elavira has enough objective evidence to confidently identify this as an accessibility failure. The guidance below explains what to change and how to check the result.', 'elavira' ),
		);
		return $descriptions[ $value ] ?? __( 'Elavira cannot show the result explanation for this older finding. Use the saved summary and recorded evidence to review what was found.', 'elavira' );
	}
	/** Render select options.
	 *
	 * @param array  $values Values.
	 * @param string $selected Selected value.
	 */
	private function options( array $values, $selected ) {
		foreach ( $values as $value ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $this->label( $value ) ) . '</option>';
		}
	}
	/** Render pagination.
	 *
	 * @param int $page Current page.
	 * @param int $pages Total pages.
	 */
	private function pagination( $page, $pages ) {
		if ( $pages > 1 ) {
			echo '<nav class="elavira-pagination" aria-label="' . esc_attr__( 'Pagination', 'elavira' ) . '">' . wp_kses_post(
				paginate_links(
					array(
						'current' => $page,
						'total'   => $pages,
						'type'    => 'list',
					)
				)
			) . '</nav>';
		}
	}
	/** Render a guidance section.
	 *
	 * @param string $title Title.
	 * @param string $content Content.
	 */
	private function section( $title, $content ) {
		if ( '' === trim( $content ) ) {
			return;
		}
		echo '<section><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $content ) . '</p></section>';
	}

	/** Render only explicitly approved advice; formatting uses translated editorial labels. */
	private function wordpress_guidance( array $editorial ) {
		if ( empty( $editorial['wordpress_guidance_useful'] ) || empty( $editorial['wordpress_guidance'] ) ) {
			return;
		}
		$content = esc_html( $editorial['wordpress_guidance'] );
		foreach ( array( __( 'Content fix:', 'elavira' ), __( 'Style fix:', 'elavira' ), __( 'Code fix:', 'elavira' ), __( 'Content/style fix:', 'elavira' ) ) as $label ) {
			$content = str_replace( esc_html( $label ), '<strong>' . esc_html( $label ) . '</strong>', $content );
		}
		echo '<section class="elavira-wordpress-guidance"><h2>' . esc_html__( 'Potential Ways to Address in WordPress', 'elavira' ) . '</h2><p>' . esc_html__( 'These are potential approaches. The appropriate solution depends on your builder, theme, plugins, and custom code.', 'elavira' ) . '</p><p>' . wp_kses_post( $content ) . '</p></section>';
	}

	/** Render editorial comparisons separately from captured evidence. */
	private function examples( array $examples ) {
		if ( ! $examples ) {
			return;
		}
		echo '<section class="elavira-examples"><h2>' . esc_html__( 'Examples', 'elavira' ) . '</h2><p>' . esc_html__( 'These examples are guidance, not evidence captured from your page. Choose an approach that fits the purpose and context of your content.', 'elavira' ) . '</p>';
		foreach ( $examples as $example ) {
			echo '<div><p><strong>' . esc_html__( 'Instead of:', 'elavira' ) . '</strong></p><pre><code>' . esc_html( $example['instead_of'] ) . '</code></pre><p><strong>' . esc_html__( 'Consider:', 'elavira' ) . '</strong></p><pre><code>' . esc_html( $example['consider'] ) . '</code></pre><p>' . esc_html( $example['explanation'] ) . '</p></div>';
		}
		echo '</section>';
	}

	/** Render structured evidence without emitting captured markup.
	 *
	 * @param string $title Title.
	 * @param string $json JSON data.
	 */
	private function structured( $title, $json ) {
		$values = json_decode( $json, true );
		if ( ! is_array( $values ) || ! array_filter( $values, 'is_scalar' ) ) {
			return;
		}
		echo '<div class="elavira-evidence"><h4>' . esc_html( $title ) . '</h4><dl>';
		if ( is_array( $values ) ) {
			foreach ( $values as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$display = (string) $value;
					if ( in_array( $key, array( 'ratio_milli', 'required_milli' ), true ) && is_numeric( $value ) ) {
						$display = number_format_i18n( (int) $value / 1000, 3 ) . ':1';
					} elseif ( 'font_size_milli' === $key && is_numeric( $value ) ) {
						$display = number_format_i18n( (int) $value / 1000, 3 ) . 'px';
					}
					echo '<div><dt>' . esc_html( $this->label( $key ) ) . '</dt><dd><code>' . esc_html( $display ) . '</code></dd></div>';
				}
			}
		} echo '</dl></div>';
	}

	/** Render superseded observations as diagnostic causes, not duplicate findings. @return void */
	private function diagnostics( $json ) {
		$items = json_decode( (string) $json, true );
		if ( ! is_array( $items ) || array() === $items ) {
			return; }
		echo '<div class="elavira-diagnostics"><h4>' . esc_html__( 'Diagnostic causes retained', 'elavira' ) . '</h4><p>' . esc_html__( 'These lower-level observations help explain the primary finding and are not separate active findings.', 'elavira' ) . '</p><ol>';
		foreach ( $items as $item ) {
			echo '<li><strong>' . esc_html( ( $item['rule_id'] ?? '' ) . ' ' . ( $item['rule_version'] ?? '' ) ) . '</strong>';
			$this->structured( __( 'Diagnostic evidence', 'elavira' ), wp_json_encode( $item['evidence'] ?? array() ) );
			echo '</li>';
		}
		echo '</ol></div>';
	}

	/** Reduce grouped database counts into the report summary.
	 *
	 * @param array $rows Aggregate rows.
	 * @return array
	 */
	private function summarize_findings( array $rows ) {
		$summary = array(
			'findings'     => 0,
			'occurrences'  => 0,
			'high'         => 0,
			'needs_review' => 0,
			'open'         => 0,
		);
		foreach ( $rows as $row ) {
			$count                   = absint( $row['finding_count'] );
			$summary['findings']    += $count;
			$summary['occurrences'] += absint( $row['occurrence_count'] );
			if ( Priority::HIGH === $row['priority'] ) {
				$summary['high'] += $count;
			}
			if ( ReviewState::NEEDS_REVIEW === $row['review_state'] ) {
				$summary['needs_review'] += $count;
			}
			if ( FindingStatus::OPEN === $row['current_status'] ) {
				$summary['open'] += $count;
			}
		}
		return $summary;
	}

	/** Render workflow update feedback from the safe redirect marker. */
	private function status_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only feedback marker after a nonce-protected action.
		$notice = isset( $_GET['elavira_notice'] ) ? sanitize_key( wp_unslash( $_GET['elavira_notice'] ) ) : '';
		if ( 'decision_completed' === $notice ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Guided Decision recorded.', 'elavira' ) . '</p></div>';
		} elseif ( 'updated' === $notice ) {
			echo '<div id="elavira-status-notice" tabindex="-1" role="status" class="notice notice-success inline"><p>' . esc_html__( 'Finding activity saved.', 'elavira' ) . '</p></div>';
		} elseif ( 'no_changes' === $notice ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'No changes to save. Add an activity note or choose a different status.', 'elavira' ) . '</p></div>';
		} elseif ( 'rejected' === $notice ) {
			echo '<div id="elavira-status-notice" tabindex="-1" role="alert" class="notice notice-error inline"><p>' . esc_html__( 'The finding update could not be saved. Review the status and note, then try again.', 'elavira' ) . '</p></div>';
		}
	}

	/** Render a link only for an exactly compatible Guided Decision.
	 *
	 * @param array $finding Finding.
	 * @return void
	 */
	private function guided_decision_action( array $finding ) {
		$decision = $this->decisions->for_rule( $finding['rule_id'], $finding['rule_version'] );
		if ( ! $decision ) {
			return; }
		$url       = admin_url( 'admin.php?page=elavira-guided-decision&finding=' . absint( $finding['id'] ) );
		$completed = $this->decision_history->find_by_finding( $finding['id'] );
		echo '<section><h2>' . esc_html__( 'Guided Decision', 'elavira' ) . '</h2>';
		if ( $completed ) {
			echo '<p>' . esc_html__( 'A Guided Decision has already been recorded for this finding. Review it below or run the questions again if the page or your judgment has changed.', 'elavira' ) . '</p><p><a href="#elavira-guided-decision-history">' . esc_html__( 'Review recorded Guided Decisions', 'elavira' ) . '</a></p>';
		} else {
			echo '<p>' . esc_html__( 'Answer a few questions and Elavira will help you determine whether this needs to be fixed.', 'elavira' ) . '</p>';
		}
		echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html( $completed ? __( 'Run Guided Decision again', 'elavira' ) : __( "Not sure? Let's decide", 'elavira' ) ) . '</a></p></section>';
	}

	/** Render bounded completed decision history for this finding.
	 *
	 * @param int $finding_id Finding ID.
	 * @return void
	 */
	private function guided_decision_history( $finding_id ) {
		$records = $this->decision_history->find_by_finding( $finding_id );
		if ( ! $records ) {
			return;
		}
		echo '<section id="elavira-guided-decision-history"><h2>' . esc_html__( 'Guided Decision activity', 'elavira' ) . '</h2><p>' . esc_html__( 'Completing a decision records the review. It changes workflow status only when the reviewer explicitly chooses a different status.', 'elavira' ) . '</p><ol class="elavira-decision-history">';
		foreach ( $records as $record ) {
			try {
				$definition = $this->decisions->get( $record['decision_id'], $record['decision_version'] );
				$title      = $definition->get( 'title' );
			} catch ( \InvalidArgumentException $error ) {
				$title = __( 'Historical Guided Decision', 'elavira' );
			}
			echo '<li><p><strong>' . esc_html( $title ) . '</strong></p><p>' . esc_html( $this->actor_label( $record['actor_user_id'] ) ) . ' — ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $record['completed_at'] ) ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Outcome:', 'elavira' ) . '</strong> ' . esc_html( $this->label( $record['outcome_id'] ) ) . '</p><p>' . esc_html( $record['recommendation'] ) . '</p>';
			$answers = json_decode( $record['answers_json'], true );
			if ( is_array( $answers ) ) {
				echo '<details class="elavira-technical"><summary>' . esc_html__( 'Review decision details', 'elavira' ) . '</summary><dl class="elavira-decision-answers"><div><dt>' . esc_html__( 'Decision contract', 'elavira' ) . '</dt><dd><code>' . esc_html( $record['decision_id'] . ' ' . $record['decision_version'] ) . '</code></dd></div>';
				foreach ( $answers as $answer ) {
					if ( array_key_exists( 'workflow_status', $answer ) ) {
						echo '<div><dt>' . esc_html__( 'Workflow choice', 'elavira' ) . '</dt><dd>' . esc_html( $answer['workflow_status'] ? $this->label( $answer['workflow_status'] ) : __( 'Keep current status', 'elavira' ) ) . '</dd></div>';
					}
					if ( isset( $answer['question'], $answer['label'] ) ) {
						echo '<div><dt>' . esc_html( $answer['question'] ) . '</dt><dd>' . esc_html( $answer['label'] ) . '</dd></div>';
					}
				}
				echo '</dl></details>';
			}
			echo '</li>';
		}
		echo '</ol></section>';
	}

	/** Render chronological status changes and note-only activity. @param array[] $history Activity rows. */
	private function activity_history( array $history, $occurrence = false ) {
		echo '<section><h2>' . esc_html( $occurrence ? __( 'Occurrence review history', 'elavira' ) : __( 'Finding activity', 'elavira' ) ) . '</h2><p>' . esc_html__( 'Activity records status changes and notes. A note can be added without changing the current status.', 'elavira' ) . '</p>';
		if ( ! $history ) {
			echo '<p>' . esc_html__( 'No finding activity is available for this historical record.', 'elavira' ) . '</p></section>';
			return;
		}
		echo '<div class="elavira-activity-history">';
		$previous_status = '';
		$decisions       = $this->decision_history->find_by_finding( $history[0]['finding_id'] );
		foreach ( $history as $entry ) {
			$status_changed = '' !== $previous_status && $previous_status !== $entry['status'];
			$is_created     = '' === $previous_status;
			$title          = $is_created ? ( $occurrence ? __( 'Review recorded', 'elavira' ) : __( 'Finding created', 'elavira' ) ) : ( $status_changed ? __( 'Status changed', 'elavira' ) : __( 'Note added', 'elavira' ) );
			echo '<div class="elavira-activity-card"><h3>' . esc_html( $title ) . '</h3><dl>';
			if ( $status_changed ) {
				echo '<div><dt>' . esc_html__( 'Status', 'elavira' ) . '</dt><dd>' . esc_html( $this->label( $previous_status ) . ' → ' . $this->label( $entry['status'] ) ) . '</dd></div>';
			} else {
				echo '<div><dt>' . esc_html__( 'Status', 'elavira' ) . '</dt><dd>' . esc_html( $this->label( $entry['status'] ) ) . '</dd></div>';
			}
			echo '<div><dt>' . esc_html__( 'Recorded by', 'elavira' ) . '</dt><dd>' . esc_html( $this->actor_label( $entry['actor_user_id'] ) ) . '</dd></div><div><dt>' . esc_html__( 'Date', 'elavira' ) . '</dt><dd>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['created_at'] ) ) . '</dd></div>';
			if ( '' !== trim( (string) $entry['reason'] ) ) {
				echo '<div><dt>' . esc_html__( 'Activity note', 'elavira' ) . '</dt><dd>' . esc_html( \Elavira\Reports\Application\ActivityNote::display( $entry, $decisions ) ) . '</dd></div>';
			}
			echo '</dl></div>';
			$previous_status = $entry['status'];
		}
		echo '</div></section>';
	}

	/** Resolve a display name while retaining a safe historical user-ID fallback. @param int $actor_id User ID. @return string */
	private function actor_label( $actor_id ) {
		$actor_id = absint( $actor_id );
		if ( 0 === $actor_id ) {
			return __( 'Elavira audit', 'elavira' );
		}
		$user = function_exists( 'get_userdata' ) ? get_userdata( $actor_id ) : null;
		if ( $user ) {
			$first = trim( (string) ( $user->first_name ?? '' ) );
			$login = trim( (string) ( $user->user_login ?? '' ) );
			if ( '' !== $first || '' !== $login ) {
				return '' !== $first ? $first : $login;
			}
		}
		/* translators: %d: Historical WordPress user ID. */
		return sprintf( __( 'User %d', 'elavira' ), $actor_id );
	}

	/** Render a validated captured page URL without replacing the current workflow tab. @param string $url Captured canonical URL. @return string */
	private function captured_page_link( $url ) {
		return self::new_tab_link( $url, $url );
	}

	/** Escaped new-tab link with a single accessible description. */
	public static function new_tab_link( $url, $label, $css_class = '' ) {
		static $sequence = 0;
		$id              = 'elavira-new-tab-' . ( ++$sequence );
		return '<a class="' . esc_attr( $css_class ) . '" target="_blank" rel="noopener noreferrer" aria-describedby="' . esc_attr( $id ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> <span aria-hidden="true">' . esc_html__( '(opens in a new tab)', 'elavira' ) . '</span><span class="screen-reader-text" id="' . esc_attr( $id ) . '">' . esc_html__( '(opens in a new tab)', 'elavira' ) . '</span>';
	}

	/** Render deliberate reset controls with consequences in visible text. */
	private function reset_controls( array $audit, array $urls ) {
		echo '<section class="elavira-report-actions"><h3>' . esc_html__( 'Report actions', 'elavira' ) . '</h3>';
		$can_reset = current_user_can( Capability::MANAGE ) && $this->exceptions;
		if ( $can_reset ) {
			echo '<p>' . esc_html__( 'Resetting removes prior reviewed exceptions for future audits. Existing audit reports and finding history will not be deleted. Future audits will evaluate those conditions again.', 'elavira' ) . '</p>';
		}
		echo '<div class="elavira-action-row">';
		if ( $can_reset ) {
			foreach ( array(
				'page' => __( 'Reset for this page', 'elavira' ),
				'all'  => __( 'Reset all reviewed exceptions', 'elavira' ),
			) as $scope => $label ) {
				if ( 'page' === $scope && ! $urls ) {
					continue;
				}
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="elavira_reset_reviewed_exceptions"><input type="hidden" name="reset_scope" value="' . esc_attr( $scope ) . '"><input type="hidden" name="audit" value="' . esc_attr( $audit['id'] ) . '"><input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( 'elavira_reset_reviewed_exceptions_' . $scope . '_' . absint( $audit['id'] ) ) ) . '"><button class="button" type="submit">' . esc_html( $label ) . '</button></form>';
			}
		}
		if ( AuditStatus::COMPLETED === $audit['status'] ) {
			echo '<a class="button button-primary elavira-export" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=elavira_export_xlsx&audit=' . absint( $audit['id'] ) ), 'elavira_export_audit_' . absint( $audit['id'] ) ) ) . '">' . esc_html__( 'Export XLSX', 'elavira' ) . '</a>';
		}
		echo '</div></section>';
	}

	/** Render finding filters.
	 *
	 * @param int   $audit_id Audit ID.
	 * @param array $filters Filters.
	 */
	private function finding_filters( $audit_id, array $filters ) {
		?>
		<form method="get" class="elavira-filters"><input type="hidden" name="page" value="elavira-report"><input type="hidden" name="audit" value="<?php echo esc_attr( $audit_id ); ?>"><div class="elavira-filter-field"><label for="elavira-search"><?php echo esc_html__( 'Search findings', 'elavira' ); ?></label><input id="elavira-search" name="s" type="search" value="<?php echo esc_attr( $filters['search'] ); ?>"></div><div class="elavira-filter-field"><label for="elavira-priority"><?php echo esc_html__( 'Priority', 'elavira' ); ?></label><select id="elavira-priority" name="priority"><option value=""><?php echo esc_html__( 'All priorities', 'elavira' ); ?></option><?php $this->options( Priority::all(), $filters['priority'] ); ?></select></div><div class="elavira-filter-field"><label for="elavira-finding-status"><?php echo esc_html__( 'Workflow status', 'elavira' ); ?></label><select id="elavira-finding-status" name="finding_status"><option value=""><?php echo esc_html__( 'All statuses', 'elavira' ); ?></option><?php $this->options( FindingStatus::all(), $filters['current_status'] ); ?></select></div><div class="elavira-filter-field"><label for="elavira-review-state"><?php echo esc_html__( 'Review state', 'elavira' ); ?></label><select id="elavira-review-state" name="review_state"><option value=""><?php echo esc_html__( 'All review states', 'elavira' ); ?></option><?php $this->options( ReviewState::all(), $filters['review_state'] ); ?></select></div><div class="elavira-filter-field"><label for="elavira-wcag-level"><?php echo esc_html__( 'WCAG level', 'elavira' ); ?></label><select id="elavira-wcag-level" name="wcag_level"><option value=""><?php echo esc_html__( 'All levels', 'elavira' ); ?></option><?php $this->options( array( 'A', 'AA', 'AAA' ), $filters['wcag_level'] ); ?></select></div><button class="button" type="submit"><?php echo esc_html__( 'Filter findings', 'elavira' ); ?></button></form>
		<?php
	}
	/** Render grouped findings.
	 *
	 * @param int   $audit_id Audit ID.
	 * @param array $items Findings.
	 */
	private function finding_table( $audit_id, array $items ) {
		?>
		<table class="widefat striped elavira-responsive-table"><caption class="screen-reader-text"><?php echo esc_html__( 'Grouped plugin findings', 'elavira' ); ?></caption><thead><tr><th scope="col"><?php echo esc_html__( 'Issue', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Priority', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Workflow status', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Review state', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Occurrences', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'WCAG', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Action', 'elavira' ); ?></th></tr></thead><tbody>
		<?php
		foreach ( $items as $finding ) :
			$resolved = $this->rules->resolve( $finding );
			?>
	<tr><th scope="row" data-label="<?php echo esc_attr__( 'Issue', 'elavira' ); ?>"><strong><?php echo esc_html( $resolved['editorial']['title'] ); ?></strong>
			<?php
			if ( 'structural_warning' === ( $resolved['editorial']['presentation'] ?? '' ) ) :
				?>
		<span class="elavira-structural-warning-label"><?php echo esc_html__( 'Structural Warning', 'elavira' ); ?></span><?php endif; ?><span class="description"><?php echo esc_html( $finding['summary_snapshot'] ); ?></span></th><td data-label="<?php echo esc_attr__( 'Priority', 'elavira' ); ?>"><?php echo esc_html( $this->label( $finding['priority'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Workflow status', 'elavira' ); ?>"><?php echo esc_html( $this->label( $finding['current_status'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Review state', 'elavira' ); ?>"><?php echo esc_html( $this->label( $finding['review_state'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Occurrences', 'elavira' ); ?>"><?php echo esc_html( number_format_i18n( $finding['occurrence_count'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'WCAG', 'elavira' ); ?>"><?php echo esc_html( $this->wcag_label( $finding ) ); ?></td><td data-label="<?php echo esc_attr__( 'Action', 'elavira' ); ?>"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-finding&finding=' . absint( $finding['id'] ) . '&audit=' . absint( $audit_id ) ) ); ?>"><?php echo esc_html__( 'View finding', 'elavira' ); ?></a></td></tr>
			<?php
		endforeach;
		?>
		</tbody></table>
		<?php
	}
	/** Render the workflow status form.
	 *
	 * @param array $finding Finding record.
	 */
	private function status_form( array $finding ) {
		$statuses        = array_unique( array_merge( array( $finding['current_status'], FindingStatus::OPEN, FindingStatus::ADDRESSED, FindingStatus::ACCEPTED_LIMITATION, FindingStatus::NEEDS_REVIEW, FindingStatus::NEEDS_CORRECTION ), in_array( $finding['category'], array( 'potential_issue', 'human_review' ), true ) ? array( FindingStatus::REVIEWED_NOT_ISSUE ) : array() ) );
		$draft           = ActivityForm::draft( $finding['id'], 0 );
		$selected_status = $draft['status'] ?? $finding['current_status'];
		?>
		<section><h2><?php echo esc_html__( 'Record finding activity', 'elavira' ); ?></h2><p id="elavira-activity-help"><?php echo esc_html__( 'Change the workflow status, add a note about what you checked or changed, or do both. A note can be saved while keeping the current status. This does not modify the website.', 'elavira' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" aria-describedby="elavira-activity-help"><input type="hidden" name="action" value="elavira_update_finding"><input type="hidden" name="finding" value="<?php echo esc_attr( $finding['id'] ); ?>"><?php wp_nonce_field( 'elavira_update_finding_' . absint( $finding['id'] ) ); ?><label for="elavira-new-status"><?php echo esc_html__( 'Status', 'elavira' ); ?></label><select id="elavira-new-status" name="finding_status" required><?php $this->options( $statuses, $selected_status ); ?></select><?php ActivityForm::note( 'elavira-status-reason', $selected_status, $draft, true ); ?><p class="description"><?php echo esc_html__( 'Addressed means remediation work was performed; it is not proof of resolution. Verified can only be established by a subsequent audit. Reviewed — Not an Issue is available for eligible review findings and is different from retaining a known issue as an Accepted Limitation.', 'elavira' ); ?></p><button class="button button-primary" type="submit"><?php echo esc_html__( 'Save update', 'elavira' ); ?></button></form></section>
		<?php
	}
}
