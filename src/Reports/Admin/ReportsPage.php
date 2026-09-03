<?php
/**
 * Accessible server-rendered Core Reports screens.
 *
 * @package Elavira
 */

namespace Elavira\Reports\Admin;

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Audit\Values\AuditStatus;
use Elavira\Core\Capabilities\Capability;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\FindingStatusHistoryRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
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
	public function __construct( AuditRepository $audits, AuditUrlRepository $urls, FindingRepository $findings, OccurrenceRepository $occurrences, FindingStatusHistoryRepository $history, HistoricalRuleResolver $rules, UpdateFindingStatus $status, XlsxExporter $xlsx, DecisionRegistry $decisions, DecisionRepository $decision_history, RecommendationRepository $statement_recommendations, TopicRegistry $learn ) {
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
	}

	/** Register stable admin routes. @return void */
	public function register() {
		add_menu_page( __( 'Elavira Audits', 'elavira' ), __( 'Elavira', 'elavira' ), Capability::VIEW_REPORTS, 'elavira-audits', array( $this, 'render_history' ), 'dashicons-universal-access-alt', 58 );
		add_submenu_page( 'elavira-audits', __( 'Audits', 'elavira' ), __( 'Audits', 'elavira' ), Capability::VIEW_REPORTS, 'elavira-audits', array( $this, 'render_history' ) );
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
		<form method="get" class="elavira-filters"><input type="hidden" name="page" value="elavira-audits">
		<label for="elavira-audit-status"><?php echo esc_html__( 'Audit status', 'elavira' ); ?></label><select id="elavira-audit-status" name="status"><option value=""><?php echo esc_html__( 'All statuses', 'elavira' ); ?></option><?php $this->options( AuditStatus::all(), $status ); ?></select>
		<label for="elavira-wcag-target"><?php echo esc_html__( 'WCAG target', 'elavira' ); ?></label><select id="elavira-wcag-target" name="wcag_target"><option value=""><?php echo esc_html__( 'All targets', 'elavira' ); ?></option><?php $this->options( array( '2.2_A', '2.2_AA' ), $target ); ?></select>
		<button class="button" type="submit"><?php echo esc_html__( 'Filter audits', 'elavira' ); ?></button></form>
		<?php
		if ( empty( $result['items'] ) ) :
			?>
			<div class="notice notice-info inline"><p><?php echo esc_html__( 'No audits match these filters. Run a single-page scan from a supported page to create a report.', 'elavira' ); ?></p></div>
			<?php
		else :
			?>
			<table class="widefat striped elavira-responsive-table"><caption class="screen-reader-text"><?php echo esc_html__( 'Historical accessibility audits', 'elavira' ); ?></caption><thead><tr><th scope="col"><?php echo esc_html__( 'Audit', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Page scanned', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Scope', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'WCAG target', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Status', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Plugin findings', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Date', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Action', 'elavira' ); ?></th></tr></thead><tbody>
			<?php
			foreach ( $result['items'] as $audit ) :
				?>
				<tr><th scope="row" data-label="<?php echo esc_attr__( 'Audit', 'elavira' ); ?>"><?php echo esc_html( $audit['label'] ); ?></th><td data-label="<?php echo esc_attr__( 'Page scanned', 'elavira' ); ?>"><?php echo esc_html( $audit['canonical_url'] ? $audit['canonical_url'] : __( 'Unavailable', 'elavira' ) ); ?></td><td data-label="<?php echo esc_attr__( 'Scope', 'elavira' ); ?>"><?php echo esc_html__( 'Single page', 'elavira' ); ?></td><td data-label="<?php echo esc_attr__( 'WCAG target', 'elavira' ); ?>"><?php echo esc_html( $this->target_label( $audit['wcag_target'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Status', 'elavira' ); ?>"><span class="elavira-label"><?php echo esc_html( $this->label( $audit['status'] ) ); ?></span></td><td data-label="<?php echo esc_attr__( 'Plugin findings', 'elavira' ); ?>"><?php echo esc_html( number_format_i18n( $audit['finding_count'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Date', 'elavira' ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $audit['created_at'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Action', 'elavira' ); ?>"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-report&audit=' . absint( $audit['id'] ) ) ); ?>"><?php echo esc_html__( 'View report', 'elavira' ); ?></a></td></tr>
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
		);
		$page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
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
		$result  = $this->findings->paginate_by_audit( $audit['id'], $filters, $page, 20 );
		$summary = $this->summarize_findings( $this->findings->summarize_by_audit( $audit['id'] ) );
		?>
		<div class="wrap elavira-reports"><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-audits' ) ); ?>">&larr; <?php echo esc_html__( 'All audits', 'elavira' ); ?></a></p><h1><?php echo esc_html__( 'Audit Report', 'elavira' ); ?></h1><h2><?php echo esc_html( $audit['label'] ); ?></h2>
		<?php
		if ( $this->statement_recommendations->pending_for_audit( $audit['id'] ) ) :
			?>
			<div class="notice notice-info inline"><p><?php echo esc_html__( 'This audit identified findings that may affect your Accessibility Statement.', 'elavira' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-statement' ) ); ?>"><?php echo esc_html__( 'Review statement recommendations', 'elavira' ); ?></a></p></div><?php endif; ?>
		<div class="elavira-summary" aria-label="<?php echo esc_attr__( 'Audit summary', 'elavira' ); ?>"><dl><div><dt><?php echo esc_html__( 'Page scanned', 'elavira' ); ?></dt><dd><?php echo isset( $urls[0] ) ? '<a href="' . esc_url( $urls[0]['canonical_url'] ) . '">' . esc_html( $urls[0]['canonical_url'] ) . '</a>' : esc_html__( 'Unavailable', 'elavira' ); ?></dd></div><div><dt><?php echo esc_html__( 'Status', 'elavira' ); ?></dt><dd><?php echo esc_html( $this->label( $audit['status'] ) ); ?></dd></div><div><dt><?php echo esc_html__( 'WCAG target', 'elavira' ); ?></dt><dd><?php echo esc_html( $this->target_label( $audit['wcag_target'] ) ); ?></dd></div><div><dt><?php echo esc_html__( 'Scanner / ruleset', 'elavira' ); ?></dt><dd><code><?php echo esc_html( $audit['scanner_version'] . ' / ' . $audit['ruleset_version'] ); ?></code></dd></div></dl></div>
		<h3><?php echo esc_html__( 'Finding summary', 'elavira' ); ?></h3><ul class="elavira-finding-meta"><li><strong><?php echo esc_html__( 'Grouped findings:', 'elavira' ); ?></strong> <?php echo esc_html( number_format_i18n( $summary['findings'] ) ); ?></li><li><strong><?php echo esc_html__( 'Occurrences:', 'elavira' ); ?></strong> <?php echo esc_html( number_format_i18n( $summary['occurrences'] ) ); ?></li><li><strong><?php echo esc_html__( 'High priority:', 'elavira' ); ?></strong> <?php echo esc_html( number_format_i18n( $summary['high'] ) ); ?></li><li><strong><?php echo esc_html__( 'Needs review:', 'elavira' ); ?></strong> <?php echo esc_html( number_format_i18n( $summary['needs_review'] ) ); ?></li><li><strong><?php echo esc_html__( 'Open:', 'elavira' ); ?></strong> <?php echo esc_html( number_format_i18n( $summary['open'] ) ); ?></li></ul>
		<p class="elavira-disclaimer"><strong><?php echo esc_html__( 'Plugin findings, not an accessibility score:', 'elavira' ); ?></strong> <?php echo esc_html__( 'Priority is an internal organizational recommendation, not an official WCAG severity or conformance grade. Automated testing is incomplete.', 'elavira' ); ?> <a href="<?php echo esc_url( LearnPage::url( 'understanding_audit_results' ) ); ?>"><?php echo esc_html__( 'Learn how to interpret audit results', 'elavira' ); ?></a>.</p>
		<?php
		if ( AuditStatus::COMPLETED !== $audit['status'] ) :
			?>
			<div class="notice notice-warning inline"><p><?php echo esc_html__( 'This audit did not complete. Any partial data must not be treated as a complete report.', 'elavira' ); ?></p></div>
			<?php
		else :
			?>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=elavira_export_xlsx&audit=' . absint( $audit['id'] ) ), 'elavira_export_audit_' . absint( $audit['id'] ) ) ); ?>"><?php echo esc_html__( 'Export XLSX', 'elavira' ); ?></a></p>
			<?php
			$this->finding_filters( $audit['id'], $filters ); if ( 0 === $result['total'] ) :
				?>
				<div class="notice notice-info inline"><p><?php echo esc_html__( 'Elavira did not identify any findings with the rules included in this audit, or none match the current filters. Automated checks do not evaluate every accessibility requirement.', 'elavira' ); ?></p></div>
				<?php
			else :
				$this->finding_table( $audit['id'], $result['items'] );
				$this->pagination( $page, $result['pages'] );
			endif;
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
		$history     = $this->history->find_by_finding( $finding_id );
		$resolved    = $this->rules->resolve( $finding );
		$editorial   = $resolved['editorial'];
		?>
		<div class="wrap elavira-reports"><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-report&audit=' . absint( $audit['id'] ) ) ); ?>">&larr; <?php echo esc_html__( 'Back to audit report', 'elavira' ); ?></a></p><h1><?php echo esc_html( $editorial['title'] ); ?></h1>
		<?php $this->status_notice(); ?>
		<ul class="elavira-finding-meta"><li><strong><?php echo esc_html__( 'Workflow status:', 'elavira' ); ?></strong> <?php echo esc_html( $this->label( $finding['current_status'] ) ); ?></li><li><strong><?php echo esc_html__( 'Priority:', 'elavira' ); ?></strong> <?php echo esc_html( $this->label( $finding['priority'] ) ); ?></li><li><strong><?php echo esc_html__( 'Review classification:', 'elavira' ); ?></strong> <?php echo esc_html( $this->label( $finding['review_state'] ) ); ?></li><li><strong><?php echo esc_html__( 'WCAG:', 'elavira' ); ?></strong> <?php echo esc_html( $finding['wcag_criterion'] . ' Level ' . $finding['wcag_level'] . ' — ' . $this->label( $finding['wcag_principle'] ) ); ?></li></ul>
		<?php
		if ( ! $resolved['available'] ) :
			?>
			<div class="notice notice-warning inline"><p><?php echo esc_html__( 'The exact historical guidance version is unavailable. The stored audit-time summary is shown without substituting current guidance.', 'elavira' ); ?></p></div>
			<?php
		endif;
		?>
		<?php
		if ( ReviewState::NEEDS_REVIEW === $finding['review_state'] ) :
			?>
			<div class="notice notice-info inline"><p><?php echo esc_html__( 'Human review is required. Automated scanning cannot determine the final accessibility outcome for this finding.', 'elavira' ); ?></p></div>
			<?php
		endif;
		?>
		<?php $this->guided_decision_action( $finding ); ?>
		<?php $this->learn_action( $resolved ); ?>
		<?php $this->guided_decision_history( $finding_id ); ?>
		<?php
		$this->section( __( 'What Elavira found', 'elavira' ), $editorial['summary'] ?? $finding['summary_snapshot'] );
		if ( $resolved['available'] ) {
			$this->section( __( 'Why it matters', 'elavira' ), $editorial['why_it_matters'] );
			$this->section( __( 'Recommendation', 'elavira' ), $editorial['recommendation'] );
			$this->section( __( 'How to address this in WordPress', 'elavira' ), $editorial['wordpress_guidance'] );
			$this->section( __( 'Developer guidance', 'elavira' ), $editorial['developer_guidance'] );
		}
		?>
		<?php
		if ( current_user_can( Capability::MANAGE ) ) {
			$this->status_form( $finding );
		}
		?>
		<h2><?php echo esc_html__( 'Occurrences captured at audit time', 'elavira' ); ?></h2><p><?php echo esc_html__( 'Selectors and element details describe evidence captured during the audit and may no longer match the current page.', 'elavira' ); ?></p>
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
				?>
	<li><h3><?php /* translators: %d: occurrence record ID. */ echo esc_html( sprintf( __( 'Occurrence %d', 'elavira' ), absint( $occurrence['id'] ) ) ); ?></h3><p><strong><?php echo esc_html__( 'Captured page:', 'elavira' ); ?></strong> <a href="<?php echo esc_url( $occurrence['canonical_url'] ); ?>"><?php echo esc_html( $occurrence['canonical_url'] ); ?></a></p>
				<?php
				$this->structured( __( 'Target', 'elavira' ), $occurrence['target_json'] );
				$this->structured( __( 'Evidence', 'elavira' ), $occurrence['evidence_json'] );
				$this->structured( __( 'Element metadata', 'elavira' ), $occurrence['element_json'] );
				?>
	</li>
				<?php
			endforeach;
			?>
			</ol>
			<?php
			$this->pagination( $page, $occurrences['pages'] );
		endif;
		?>
		<h2><?php echo esc_html__( 'Status history', 'elavira' ); ?></h2><table class="widefat striped elavira-responsive-table"><thead><tr><th scope="col"><?php echo esc_html__( 'Date', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Status', 'elavira' ); ?></th><th scope="col"><?php echo esc_html__( 'Reason', 'elavira' ); ?></th></tr></thead><tbody>
		<?php
		foreach ( $history as $entry ) :
			?>
			<tr><td data-label="<?php echo esc_attr__( 'Date', 'elavira' ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['created_at'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Status', 'elavira' ); ?>"><?php echo esc_html( $this->label( $entry['status'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Reason', 'elavira' ); ?>"><?php echo esc_html( $entry['reason'] ? $entry['reason'] : __( 'No reason recorded.', 'elavira' ) ); ?></td></tr>
			<?php
			endforeach;
		?>
			</tbody></table></div>
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
			echo '<p><a class="button" href="' . esc_url( LearnPage::url( $id ) ) . '">' . esc_html( sprintf( __( 'Learn about %s', 'elavira' ), $topic->get( 'title' ) ) ) . '</a></p>';
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
			$this->status->update( $finding_id, $status, $reason, get_current_user_id() );
			$notice = 'updated';
		} catch ( Throwable $error ) {
			$notice = 'rejected';
		}
		wp_safe_redirect( add_query_arg( 'elavira_notice', $notice, admin_url( 'admin.php?page=elavira-finding&finding=' . $finding_id ) ) );
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
		$summary     = array( array( __( 'Field', 'elavira' ), __( 'Value', 'elavira' ) ), array( __( 'Audit', 'elavira' ), $audit['label'] ), array( __( 'Scope', 'elavira' ), __( 'Single page', 'elavira' ) ), array( __( 'WCAG target', 'elavira' ), $this->target_label( $audit['wcag_target'] ) ), array( __( 'Status', 'elavira' ), $this->label( $audit['status'] ) ), array( __( 'Started', 'elavira' ), $audit['started_at'] ), array( __( 'Completed', 'elavira' ), $audit['completed_at'] ), array( __( 'Grouped plugin findings', 'elavira' ), count( $findings ) ), array( __( 'Page URL', 'elavira' ), $urls[0]['canonical_url'] ?? '' ), array( __( 'Important limitation', 'elavira' ), __( 'Plugin findings are not an accessibility score or proof of conformance. Automated testing is incomplete.', 'elavira' ) ) );
		$issues      = array( array( 'Status', 'Priority', 'Issue', 'Classification', 'Review state', 'WCAG criterion', 'WCAG level', 'Occurrences', 'Affected pages', 'Recommendation', 'WordPress guidance', 'Developer guidance' ) );
		foreach ( $findings as $finding ) {
			$resolved  = $this->rules->resolve( $finding );
			$editorial = $resolved['editorial'];
			$issues[]  = array( $this->label( $finding['current_status'] ), $this->label( $finding['priority'] ), $editorial['title'], $this->label( $finding['category'] ), $this->label( $finding['review_state'] ), $finding['wcag_criterion'], $finding['wcag_level'], $finding['occurrence_count'], $finding['affected_page_count'], $editorial['recommendation'] ?? __( 'Exact historical recommendation unavailable.', 'elavira' ), $editorial['wordpress_guidance'] ?? '', $editorial['developer_guidance'] ?? '' );
		}
		$affected = array( array( 'Finding', 'Captured URL', 'Target', 'Evidence', 'Element metadata' ) );
		foreach ( $occurrences as $occurrence ) {
			$affected[] = array( $occurrence['summary_snapshot'], $occurrence['canonical_url'], $occurrence['target_json'], $occurrence['evidence_json'], $occurrence['element_json'] );
		}
		$priority    = array( array( 'Priority', 'Meaning' ), array( 'High', 'Likely to create a significant accessibility barrier and generally should be investigated first.' ), array( 'Medium', 'Meaningful accessibility concern that should be addressed, with less immediate impact than High.' ), array( 'Low', 'Lower-impact accessibility concern or best-practice issue.' ), array( 'Disclaimer', 'Priority is an internal organizational recommendation, not an official WCAG severity or conformance grading system.' ) );
		$status_rows = array( array( 'Finding', 'Status', 'Reason', 'Actor user ID', 'Source audit ID', 'Date' ) );
		foreach ( $history as $entry ) {
			$status_rows[] = array( $entry['summary_snapshot'], $this->label( $entry['status'] ), $entry['reason'], $entry['actor_user_id'], $entry['source_audit_id'], $entry['created_at'] );
		}
		try {
			$file = $this->xlsx->create(
				array(
					'Audit Summary'  => $summary,
					'Issues'         => $issues,
					'Affected URLs'  => $affected,
					'Priority Guide' => $priority,
					'Status History' => $status_rows,
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
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}
	/** Create a WCAG target label.
	 *
	 * @param string $value Target.
	 * @return string
	 */
	private function target_label( $value ) {
		return '2.2_AA' === $value ? __( 'WCAG 2.2 Level AA', 'elavira' ) : __( 'WCAG 2.2 Level A', 'elavira' );
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
		echo '<section><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $content ) . '</p></section>';
	}
	/** Render structured evidence without emitting captured markup.
	 *
	 * @param string $title Title.
	 * @param string $json JSON data.
	 */
	private function structured( $title, $json ) {
		$values = json_decode( $json, true );
		echo '<div class="elavira-evidence"><h4>' . esc_html( $title ) . '</h4><dl>';
		if ( is_array( $values ) ) {
			foreach ( $values as $key => $value ) {
				if ( is_scalar( $value ) ) {
					echo '<div><dt>' . esc_html( $this->label( $key ) ) . '</dt><dd><code>' . esc_html( (string) $value ) . '</code></dd></div>';
				}
			}
		} echo '</dl></div>';
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
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Finding status updated.', 'elavira' ) . '</p></div>';
		} elseif ( 'rejected' === $notice ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The requested status change could not be saved.', 'elavira' ) . '</p></div>';
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
		$url = admin_url( 'admin.php?page=elavira-guided-decision&finding=' . absint( $finding['id'] ) );
		echo '<section><h2>' . esc_html__( 'Guided Decision', 'elavira' ) . '</h2><p>' . esc_html__( 'Review this finding through structured questions that support, but do not replace, your judgment.', 'elavira' ) . '</p><p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Review this finding', 'elavira' ) . '</a></p></section>';
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
		echo '<section><h2>' . esc_html__( 'Guided Decision history', 'elavira' ) . '</h2><ol class="elavira-decision-history">';
		foreach ( $records as $record ) {
			echo '<li><p><strong>' . esc_html( $record['decision_id'] . ' ' . $record['decision_version'] ) . '</strong> — ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $record['completed_at'] ) ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Outcome:', 'elavira' ) . '</strong> ' . esc_html( $this->label( $record['outcome_id'] ) ) . '</p><p>' . esc_html( $record['recommendation'] ) . '</p>';
			$answers = json_decode( $record['answers_json'], true );
			if ( is_array( $answers ) ) {
				echo '<dl class="elavira-decision-answers">';
				foreach ( $answers as $answer ) {
					if ( isset( $answer['question'], $answer['label'] ) ) {
						echo '<div><dt>' . esc_html( $answer['question'] ) . '</dt><dd>' . esc_html( $answer['label'] ) . '</dd></div>';
					}
				}
				echo '</dl>';
			}
			echo '</li>';
		}
		echo '</ol></section>';
	}

	/** Render finding filters.
	 *
	 * @param int   $audit_id Audit ID.
	 * @param array $filters Filters.
	 */
	private function finding_filters( $audit_id, array $filters ) {
		?>
		<form method="get" class="elavira-filters"><input type="hidden" name="page" value="elavira-report"><input type="hidden" name="audit" value="<?php echo esc_attr( $audit_id ); ?>"><label for="elavira-search"><?php echo esc_html__( 'Search findings', 'elavira' ); ?></label><input id="elavira-search" name="s" type="search" value="<?php echo esc_attr( $filters['search'] ); ?>"><label for="elavira-priority"><?php echo esc_html__( 'Priority', 'elavira' ); ?></label><select id="elavira-priority" name="priority"><option value=""><?php echo esc_html__( 'All priorities', 'elavira' ); ?></option><?php $this->options( Priority::all(), $filters['priority'] ); ?></select><label for="elavira-finding-status"><?php echo esc_html__( 'Workflow status', 'elavira' ); ?></label><select id="elavira-finding-status" name="finding_status"><option value=""><?php echo esc_html__( 'All statuses', 'elavira' ); ?></option><?php $this->options( FindingStatus::all(), $filters['current_status'] ); ?></select><label for="elavira-review-state"><?php echo esc_html__( 'Review state', 'elavira' ); ?></label><select id="elavira-review-state" name="review_state"><option value=""><?php echo esc_html__( 'All review states', 'elavira' ); ?></option><?php $this->options( ReviewState::all(), $filters['review_state'] ); ?></select><label for="elavira-wcag-level"><?php echo esc_html__( 'WCAG level', 'elavira' ); ?></label><select id="elavira-wcag-level" name="wcag_level"><option value=""><?php echo esc_html__( 'All levels', 'elavira' ); ?></option><?php $this->options( array( 'A', 'AA', 'AAA' ), $filters['wcag_level'] ); ?></select><button class="button" type="submit"><?php echo esc_html__( 'Filter findings', 'elavira' ); ?></button></form>
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
	<tr><th scope="row" data-label="<?php echo esc_attr__( 'Issue', 'elavira' ); ?>"><?php echo esc_html( $resolved['editorial']['title'] ); ?><span class="description"><?php echo esc_html( $finding['summary_snapshot'] ); ?></span></th><td data-label="<?php echo esc_attr__( 'Priority', 'elavira' ); ?>"><?php echo esc_html( $this->label( $finding['priority'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Workflow status', 'elavira' ); ?>"><?php echo esc_html( $this->label( $finding['current_status'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Review state', 'elavira' ); ?>"><?php echo esc_html( $this->label( $finding['review_state'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'Occurrences', 'elavira' ); ?>"><?php echo esc_html( number_format_i18n( $finding['occurrence_count'] ) ); ?></td><td data-label="<?php echo esc_attr__( 'WCAG', 'elavira' ); ?>"><?php echo esc_html( $finding['wcag_criterion'] . ' ' . $finding['wcag_level'] ); ?></td><td data-label="<?php echo esc_attr__( 'Action', 'elavira' ); ?>"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-finding&finding=' . absint( $finding['id'] ) . '&audit=' . absint( $audit_id ) ) ); ?>"><?php echo esc_html__( 'View finding', 'elavira' ); ?></a></td></tr>
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
		?>
		<section><h2><?php echo esc_html__( 'Update workflow status', 'elavira' ); ?></h2><p><?php echo esc_html__( 'Status changes record workflow decisions and do not modify the website. Verified can only be established by a subsequent audit.', 'elavira' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="elavira_update_finding"><input type="hidden" name="finding" value="<?php echo esc_attr( $finding['id'] ); ?>"><?php wp_nonce_field( 'elavira_update_finding_' . absint( $finding['id'] ) ); ?><label for="elavira-new-status"><?php echo esc_html__( 'New status', 'elavira' ); ?></label><select id="elavira-new-status" name="finding_status" required><option value=""><?php echo esc_html__( 'Choose a status', 'elavira' ); ?></option><?php $this->options( array( FindingStatus::OPEN, FindingStatus::ADDRESSED, FindingStatus::ACCEPTED_LIMITATION, FindingStatus::NEEDS_REVIEW ), '' ); ?></select><label for="elavira-status-reason"><?php echo esc_html__( 'Reason or note (optional)', 'elavira' ); ?></label><textarea id="elavira-status-reason" name="reason" rows="4" maxlength="1000"></textarea><p class="description"><?php echo esc_html__( 'Accepted Limitation records an intentional decision. It does not fix the issue or establish conformance.', 'elavira' ); ?></p><button class="button button-primary" type="submit"><?php echo esc_html__( 'Save status', 'elavira' ); ?></button></form></section>
		<?php
	}
}
