<?php
/**
 * Core Dashboard.
 *
 * @package Elavira
 */

namespace Elavira\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.WP.I18n.MissingTranslatorsComment -- Compact integration rendering remains documented at the class boundary.

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Core\Capabilities\Capability;
use Elavira\Core\Database\TableNames;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\GuidedDecisions\CoreDecisionRegistry;
use Elavira\Statement\StatementRepository;
use Elavira\Toolbar\ToolbarSettings;

/** Presents bounded factual summaries of existing Core data. */
final class DashboardPage {
	/** Database. @var object */
	private $database;
	/** Construct. @param object $database Database. */
	public function __construct( $database ) {
		$this->database = $database; }

	/** Register menu. @return void */
	public function register() {
		add_menu_page( __( 'Elavira Dashboard', 'elavira' ), __( 'Elavira', 'elavira' ), Capability::VIEW_REPORTS, 'elavira-dashboard', array( $this, 'render' ), 'dashicons-universal-access-alt', 58 );
		add_submenu_page( 'elavira-dashboard', __( 'Elavira Dashboard', 'elavira' ), __( 'Dashboard', 'elavira' ), Capability::VIEW_REPORTS, 'elavira-dashboard', array( $this, 'render' ), 0 );
	}

	/** Render dashboard. @return void */
	public function render() {
		$this->authorize();
		$audits    = ( new AuditRepository( $this->database ) )->paginate( get_current_user_id(), 1, 5 );
		$latest    = $audits['items'][0] ?? null;
		$summary   = $latest ? ( new FindingRepository( $this->database ) )->summarize_by_audit( $latest['id'] ) : array();
		$counts    = $this->finding_counts( $summary );
		$statement = ( new StatementRepository( $this->database ) )->find();
		$pending   = $this->pending_recommendations( $statement );
		$toolbar   = ToolbarSettings::get();
		?>
		<div class="wrap elavira-admin"><h1><?php echo esc_html__( 'Elavira Dashboard', 'elavira' ); ?></h1><p><?php echo esc_html__( 'What needs your attention right now. Plugin findings are not an accessibility score or proof of conformance.', 'elavira' ); ?></p>
		<?php if ( ! $latest ) : ?>
		<section><h2><?php echo esc_html__( 'Start with a rendered page audit', 'elavira' ); ?></h2><p><?php echo esc_html__( 'A Core audit checks one public page in your browser and creates a report of automated findings and items needing human review.', 'elavira' ); ?></p>
			<?php
			if ( current_user_can( Capability::RUN_AUDITS ) ) :
				?>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-run-audit' ) ); ?>"><?php echo esc_html__( 'Run Your First Audit', 'elavira' ); ?></a></p><?php endif; ?></section>
		<?php else : ?>
		<section><h2><?php echo esc_html__( 'Latest audit', 'elavira' ); ?></h2><dl class="elavira-dashboard-grid"><div><dt><?php echo esc_html__( 'Page', 'elavira' ); ?></dt><dd><?php echo esc_html( $latest['canonical_url'] ); ?></dd></div><div><dt><?php echo esc_html__( 'Date', 'elavira' ); ?></dt><dd><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $latest['created_at'] ) ); ?></dd></div><div><dt><?php echo esc_html__( 'Scope', 'elavira' ); ?></dt><dd><?php echo esc_html__( 'Single page', 'elavira' ); ?></dd></div><div><dt><?php echo esc_html__( 'Pages scanned', 'elavira' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $latest['url_count'] ) ); ?></dd></div><div><dt><?php echo esc_html__( 'WCAG target', 'elavira' ); ?></dt><dd><?php echo esc_html( str_replace( '_', ' Level ', $latest['wcag_target'] ) ); ?></dd></div><div><dt><?php echo esc_html__( 'Status', 'elavira' ); ?></dt><dd><?php echo esc_html( ucfirst( $latest['status'] ) ); ?></dd></div></dl><p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-report&audit=' . absint( $latest['id'] ) ) ); ?>"><?php echo esc_html__( 'View Latest Report', 'elavira' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-audits' ) ); ?>"><?php echo esc_html__( 'View Audit History', 'elavira' ); ?></a>
			<?php
			if ( current_user_can( Capability::RUN_AUDITS ) ) :
				?>
			<a class="button" href="<?php echo esc_url( add_query_arg( 'url', $latest['canonical_url'], admin_url( 'admin.php?page=elavira-run-audit' ) ) ); ?>"><?php echo esc_html__( 'Run New Audit', 'elavira' ); ?></a><?php endif; ?></p></section>
		<section><h2><?php echo esc_html__( 'Latest-audit findings', 'elavira' ); ?></h2><p><?php echo esc_html__( 'These counts describe grouped findings in the latest audit only.', 'elavira' ); ?></p><?php $this->counts( $counts ); ?></section>
		<?php endif; ?>
		<section><h2><?php echo esc_html__( 'Accessibility Statement', 'elavira' ); ?></h2><p><?php echo esc_html( ! $statement ? __( 'No statement created', 'elavira' ) : ( 'published' === $statement['status'] ? __( 'Published', 'elavira' ) : __( 'Draft exists', 'elavira' ) ) ); ?>
		<?php
		if ( $pending ) :
			?>
			— <?php echo esc_html( sprintf( _n( '%d pending recommendation', '%d pending recommendations', $pending, 'elavira' ), $pending ) ); ?><?php endif; ?></p><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-statement' ) ); ?>"><?php echo esc_html( ! $statement ? __( 'Create Statement', 'elavira' ) : __( 'Review or edit Statement', 'elavira' ) ); ?></a>
			<?php
			if ( $statement && 'published' === $statement['status'] && $statement['post_id'] ) :
				?>
			· <a href="<?php echo esc_url( get_permalink( $statement['post_id'] ) ); ?>"><?php echo esc_html__( 'View published Statement', 'elavira' ); ?></a><?php endif; ?></p></section>
		<section><h2><?php echo esc_html__( 'Guided Decisions', 'elavira' ); ?></h2><p><?php echo esc_html( sprintf( __( '%d Phase 1 decisions are available. Finding-bound decisions are completed from compatible reports; manual reviews remain available through Learn.', 'elavira' ), count( CoreDecisionRegistry::create()->all() ) ) ); ?></p><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-learn#guided-decisions' ) ); ?>"><?php echo esc_html__( 'Browse Guided Decisions', 'elavira' ); ?></a></p></section>
		<section><h2><?php echo esc_html__( 'Visitor Toolbar', 'elavira' ); ?></h2><p><?php echo esc_html( $toolbar['enabled'] ? __( 'Enabled', 'elavira' ) : __( 'Disabled', 'elavira' ) ); ?> — <?php echo esc_html( sprintf( __( '%d visitor preferences configured', 'elavira' ), count( array_filter( $toolbar['controls'] ) ) ) ); ?></p><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-toolbar' ) ); ?>"><?php echo esc_html__( 'Configure Toolbar', 'elavira' ); ?></a></p></section>
		<section><h2><?php echo esc_html__( 'Learn', 'elavira' ); ?></h2><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-learn' ) ); ?>"><?php echo esc_html__( 'Browse Learn and the WCAG Reference', 'elavira' ); ?></a></p></section>
		<?php
		if ( $latest ) :
			?>
			<section><h2><?php echo esc_html__( 'Recent audits', 'elavira' ); ?></h2><ol>
			<?php
			foreach ( $audits['items'] as $audit ) :
				?>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-report&audit=' . absint( $audit['id'] ) ) ); ?>"><?php echo esc_html( $audit['label'] ); ?></a> — <?php echo esc_html( mysql2date( get_option( 'date_format' ), $audit['created_at'] ) ); ?></li><?php endforeach; ?></ol></section><?php endif; ?></div>
		<?php
	}

	/** Normalize latest-audit aggregate rows. @param array $rows Rows. @return array */
	private function finding_counts( array $rows ) {
		$counts = array(
			'open'                => 0,
			'addressed'           => 0,
			'accepted_limitation' => 0,
			'needs_review'        => 0,
			'high'                => 0,
			'medium'              => 0,
			'low'                 => 0,
		);
		foreach ( $rows as $row ) {
			$count = (int) $row['finding_count'];
			if ( isset( $counts[ $row['current_status'] ] ) ) {
				$counts[ $row['current_status'] ] += $count; }
			if ( 'needs_review' === $row['review_state'] ) {
				$counts['needs_review'] += $count; }
			if ( isset( $counts[ $row['priority'] ] ) ) {
				$counts[ $row['priority'] ] += $count; }
		}
		return $counts;
	}

	/** Render finding counts. @param array $counts Counts. @return void */
	private function counts( array $counts ) {
		echo '<dl class="elavira-dashboard-grid">';
		foreach ( array(
			'open'                => __( 'Open', 'elavira' ),
			'needs_review'        => __( 'Needs Review (human review)', 'elavira' ),
			'addressed'           => __( 'Addressed', 'elavira' ),
			'accepted_limitation' => __( 'Accepted Limitation', 'elavira' ),
			'high'                => __( 'High priority', 'elavira' ),
			'medium'              => __( 'Medium priority', 'elavira' ),
			'low'                 => __( 'Low priority', 'elavira' ),
		) as $key => $label ) {
			echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( number_format_i18n( $counts[ $key ] ) ) . '</dd></div>';
		} echo '</dl>'; }

	/** Pending recommendations for the current statement. @param array|null $statement Statement. @return int */
	private function pending_recommendations( $statement ) {
		$tables = new TableNames( $this->database->prefix );
		$id     = $statement ? absint( $statement['id'] ) : 0;
		return (int) $this->database->get_var( $this->database->prepare( "SELECT COUNT(*) FROM {$tables->statement_recommendations()} WHERE state = %s AND statement_id IN (0, %d)", 'pending', $id ) );
	}

	/** Require report access. @return void */
	private function authorize() {
		if ( ! current_user_can( Capability::VIEW_REPORTS ) ) {
			wp_die( esc_html__( 'You are not allowed to view the Elavira Dashboard.', 'elavira' ), '', array( 'response' => 403 ) ); } }
}
