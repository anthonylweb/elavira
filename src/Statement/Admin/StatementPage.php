<?php
/**
 * Accessible Statement administration.
 *
 * @package Elavira
 */

namespace Elavira\Statement\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Controller methods centralize nonce validation before reading bounded form data.

use Elavira\Core\Capabilities\Capability;
use Elavira\Learn\Admin\LearnPage;
use Elavira\Statement\RecommendationRepository;
use Elavira\Statement\StatementFields;
use Elavira\Statement\StatementRenderer;
use Elavira\Statement\StatementRepository;
use Throwable;

/** Provides structured editing, review, preview, and explicit publishing. */
final class StatementPage {
	/** Dependencies. @var array */
	private $services;

	/** Create controller. @param array $services Services. */
	public function __construct( array $services ) {
		$this->services = $services;
	}

	/** Register Statement screens. @return void */
	public function register() {
		add_submenu_page( 'elavira-dashboard', __( 'Accessibility Statement', 'elavira' ), __( 'Statement', 'elavira' ), Capability::MANAGE_STATEMENT, 'elavira-statement', array( $this, 'render' ), 20 );
		add_submenu_page( null, __( 'Preview Accessibility Statement', 'elavira' ), __( 'Statement Preview', 'elavira' ), Capability::MANAGE_STATEMENT, 'elavira-statement-preview', array( $this, 'preview' ) );
	}

	/** Load scoped admin styles. @return void */
	public function enqueue() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selection.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( in_array( $page, array( 'elavira-statement', 'elavira-statement-preview' ), true ) ) {
			wp_enqueue_style( 'elavira-statement', ELAVIRA_URL . 'assets/css/statement.css', array(), ELAVIRA_VERSION );
		}
		if ( 'elavira-statement' === $page ) {
			wp_enqueue_script( 'elavira-statement-help', ELAVIRA_URL . 'assets/js/statement-help.js', array(), ELAVIRA_VERSION, true );
		}
	}

	/** Render editor or first-use state. @return void */
	public function render() {
		$this->authorize();
		$statement = $this->services['statements']->find();
		?>
		<div class="wrap elavira-statement-admin"><h1><?php echo esc_html__( 'Accessibility Statement', 'elavira' ); ?></h1><?php $this->notice(); ?>
		<p class="elavira-statement-learn"><?php echo esc_html__( 'Need help getting started?', 'elavira' ); ?> <a href="<?php echo esc_url( LearnPage::url( 'creating_accessibility_statement' ) ); ?>"><?php echo esc_html__( 'Creating an Accessibility Statement', 'elavira' ); ?></a></p>
		<?php if ( ! $statement ) : ?>
			<p><?php echo esc_html__( 'Create a structured draft to review before anything is published.', 'elavira' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="elavira_statement_create"><?php wp_nonce_field( 'elavira_statement_create' ); ?><button class="button button-primary"><?php echo esc_html__( 'Create Statement', 'elavira' ); ?></button></form>
			<h2><?php echo esc_html__( 'Connect an existing page', 'elavira' ); ?></h2><p><?php echo esc_html__( 'Connecting imports its plain text into a draft. The existing public page is not replaced until you explicitly publish.', 'elavira' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="elavira_statement_connect"><?php wp_nonce_field( 'elavira_statement_connect' ); ?><label for="elavira-page-id"><?php echo esc_html__( 'WordPress page ID', 'elavira' ); ?></label><input id="elavira-page-id" name="post_id" type="number" min="1" required><button class="button"><?php echo esc_html__( 'Connect page as draft', 'elavira' ); ?></button></form>
			<?php
		else :
			$this->editor( $statement );
endif;
		?>
		</div>
		<?php
	}

	/** Create default draft. @return void */
	public function create() {
		$this->authorize_post( 'elavira_statement_create' );
		try {
			$this->services['statements']->create( StatementFields::defaults(), get_current_user_id() );
			$this->redirect( 'created' );
		} catch ( Throwable $error ) {
			$this->redirect( 'failed' );
		}
	}

	/** Connect an existing WordPress page without changing it. @return void */
	public function connect() {
		$this->authorize_post( 'elavira_statement_connect' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = get_post( $post_id );
		if ( ! $post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			$this->redirect( 'invalid_page' );
		}
		try {
			$fields                           = StatementFields::defaults();
			$fields['title']                  = sanitize_text_field( $post->post_title );
			$fields['additional_information'] = sanitize_textarea_field( wp_strip_all_tags( $post->post_content ) );
			$id                               = $this->services['statements']->create( $fields, get_current_user_id() );
			$this->services['statements']->associate_post( $id, $post_id );
			$this->redirect( 'connected' );
		} catch ( Throwable $error ) {
			$this->redirect( 'failed' );
		}
	}

	/** Save a draft or explicitly publish it. @return void */
	public function save() {
		$this->authorize_post( 'elavira_statement_save' );
		$statement = $this->services['statements']->find();
		$operation = isset( $_POST['statement_operation'] ) ? sanitize_key( wp_unslash( $_POST['statement_operation'] ) ) : '';
		try {
			if ( ! $statement || ! in_array( $operation, array( 'draft', 'publish' ), true ) ) {
				throw new \RuntimeException( 'Invalid Statement request.' );
			}
			$fields   = StatementFields::sanitize( isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array() );
			$reviewed = isset( $_POST['last_reviewed'] ) ? sanitize_text_field( wp_unslash( $_POST['last_reviewed'] ) ) : '';
			if ( 'draft' === $operation ) {
				$this->services['statements']->save_draft( $statement['id'], $fields, $reviewed );
				$this->redirect( 'draft_saved' );
			}
			$post_id = $this->publish_page( $statement, $fields );
			$this->services['statements']->publish( $statement['id'], $post_id, $fields, get_current_user_id(), $reviewed );
			$this->redirect( 'published' );
		} catch ( Throwable $error ) {
			$this->redirect( 'publish_forbidden' === $error->getMessage() ? 'publish_forbidden' : 'invalid_fields' );
		}
	}

	/** Accept/edit or dismiss a recommendation without publishing. @return void */
	public function review_recommendation() {
		$this->authorize_post( 'elavira_statement_recommendation' );
		$statement = $this->services['statements']->find();
		$id        = isset( $_POST['recommendation_id'] ) ? absint( $_POST['recommendation_id'] ) : 0;
		$state     = isset( $_POST['recommendation_action'] ) ? sanitize_key( wp_unslash( $_POST['recommendation_action'] ) ) : '';
		$text      = isset( $_POST['approved_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['approved_text'] ) ) : '';
		$reason    = isset( $_POST['dismissal_reason'] ) ? sanitize_key( wp_unslash( $_POST['dismissal_reason'] ) ) : '';
		$record    = $this->services['recommendations']->find( $id );
		try {
			if ( ! $statement || ! $record || 'pending' !== $record['state'] ) {
				throw new \RuntimeException( 'Invalid recommendation.' );
			}
			if ( 'accepted' === $state ) {
				$fields                      = StatementRepository::fields( $statement );
				$fields['known_limitations'] = trim( $fields['known_limitations'] . "\n\n" . $text );
				$this->services['statements']->save_draft( $statement['id'], StatementFields::sanitize( $fields ), $statement['last_reviewed'] );
			}
			$this->services['recommendations']->review( $id, $statement['id'], $state, $text, $reason );
			$this->redirect( 'recommendation_reviewed' );
		} catch ( Throwable $error ) {
			$this->redirect( 'recommendation_failed' );
		}
	}

	/** Render authenticated draft preview. @return void */
	public function preview() {
		$this->authorize();
		$statement = $this->services['statements']->find();
		if ( ! $statement ) {
			wp_die( esc_html__( 'No Statement draft is available.', 'elavira' ), '', array( 'response' => 404 ) );
		}
		echo '<div class="wrap elavira-statement-preview"><p><a href="' . esc_url( admin_url( 'admin.php?page=elavira-statement' ) ) . '">&larr; ' . esc_html__( 'Back to editor', 'elavira' ) . '</a></p><h1>' . esc_html( StatementRepository::fields( $statement )['title'] ) . '</h1>';
		echo wp_kses_post( $this->services['renderer']->render( StatementRepository::fields( $statement ), $statement ) );
		echo '</div>';
	}

	/** Render structured editor and recommendations. @param array $statement Statement. @return void */
	private function editor( array $statement ) {
		$fields = StatementRepository::fields( $statement );
		$url    = $statement['post_id'] ? get_permalink( $statement['post_id'] ) : '';
		?>
		<p><strong><?php echo esc_html__( 'Status:', 'elavira' ); ?></strong> <?php echo esc_html( ucfirst( $statement['status'] ) ); ?>
		<?php
		if ( $url && 'published' === $statement['status'] ) :
			?>
			· <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'View public statement', 'elavira' ); ?></a><?php endif; ?></p>
		<p><?php echo esc_html__( 'Saving a draft never changes the currently published statement. Publishing is always explicit. Elavira must remain active to render the public statement. Deactivation or deletion retains its page and structured records, but the public statement body is unavailable. Before deactivation, copy the approved statement into a standalone WordPress page if it must remain public.', 'elavira' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="elavira-statement-form"><input type="hidden" name="action" value="elavira_statement_save"><?php wp_nonce_field( 'elavira_statement_save' ); ?>
		<?php
		$this->text( 'title', __( 'Statement title', 'elavira' ), $fields, false, true );
		$this->text( 'organization', __( 'Organization or site name', 'elavira' ), $fields, false, true );
		$this->text( 'commitment', __( 'Accessibility commitment', 'elavira' ), $fields );
		?>
		<fieldset><legend><?php echo esc_html__( 'Conformance information', 'elavira' ); ?></legend><p><?php echo esc_html__( 'Keep the target, plugin findings, and organization-stated conformance distinct. Plugin findings are incomplete and do not establish conformance.', 'elavira' ); ?></p>
		<?php
		$this->select(
			'wcag_target',
			__( 'WCAG Target', 'elavira' ),
			array(
				'2.2_A'  => __( 'WCAG 2.2 Level A', 'elavira' ),
				'2.2_AA' => __( 'WCAG 2.2 Level AA', 'elavira' ),
			),
			$fields
		);
		$this->select(
			'plugin_findings_status',
			__( 'Plugin Findings', 'elavira' ),
			array(
				'not_assessed'           => __( 'Not assessed', 'elavira' ),
				'unresolved_findings'    => __( 'Unresolved plugin findings exist', 'elavira' ),
				'no_findings_identified' => __( 'No findings identified in completed scans', 'elavira' ),
			),
			$fields
		);
		$this->select(
			'stated_conformance',
			__( "Organization's Stated Conformance", 'elavira' ),
			array(
				'not_stated'           => __( 'Not stated', 'elavira' ),
				'partially_conformant' => __( 'Partially conformant', 'elavira' ),
				'fully_conformant'     => __( 'Fully conformant', 'elavira' ),
				'non_conformant'       => __( 'Not conformant', 'elavira' ),
			),
			$fields
		);
		?>
		<p class="notice notice-warning inline"><?php echo esc_html__( 'A strong conformance claim is the organization’s assertion. Review unresolved findings and manual testing before publishing it.', 'elavira' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-audits' ) ); ?>"><?php echo esc_html__( 'Review audit findings', 'elavira' ); ?></a></p></fieldset>
		<?php
		$this->text( 'known_limitations', __( 'Known limitations', 'elavira' ), $fields );
		$this->text( 'feedback', __( 'Feedback and contact introduction', 'elavira' ), $fields );
		$this->text( 'contact_email', __( 'Contact email', 'elavira' ), $fields, false );
		$this->text( 'contact_url', __( 'Contact form URL', 'elavira' ), $fields, false );
		$this->text( 'contact_phone', __( 'Phone or other contact method', 'elavira' ), $fields, false );
		$this->text( 'compatibility', __( 'Compatibility', 'elavira' ), $fields );
		$this->text( 'assessment_approach', __( 'Assessment approach', 'elavira' ), $fields );
		$this->text( 'additional_information', __( 'Additional information', 'elavira' ), $fields );
		?>
		<label for="elavira-last-reviewed"><?php echo esc_html__( 'Last reviewed date', 'elavira' ); ?></label><input id="elavira-last-reviewed" name="last_reviewed" type="date" value="<?php echo esc_attr( $statement['last_reviewed'] ); ?>"><p class="description"><?php echo esc_html__( 'Change this only when the organization actually reviews the statement.', 'elavira' ); ?></p>
		<p><button class="button" name="statement_operation" value="draft"><?php echo esc_html__( 'Save Draft', 'elavira' ); ?></button> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-statement-preview' ) ); ?>"><?php echo esc_html__( 'Preview Draft', 'elavira' ); ?></a> <button class="button button-primary" name="statement_operation" value="publish"><?php echo esc_html__( 'Publish Changes', 'elavira' ); ?></button></p></form>
		<?php
		$this->recommendations( $statement );
		$this->revisions( $statement );
	}

	/** Render recommendation review forms. @param array $statement Statement. @return void */
	private function recommendations( array $statement ) {
		$records = $this->services['recommendations']->find_for_statement( $statement['id'] );
		echo '<section><h2>' . esc_html__( 'Statement recommendations', 'elavira' ) . '</h2><p>' . esc_html__( 'Recommendations are advisory. Accepting one changes only the draft and never publishes it.', 'elavira' ) . '</p>';
		if ( ! $records ) {
			echo '<p>' . esc_html__( 'No recommendations require review.', 'elavira' ) . '</p></section>';
			return; }
		foreach ( $records as $record ) {
			echo '<article class="elavira-recommendation"><h3>' . esc_html( $record['summary_snapshot'] ) . '</h3><p>' . esc_html( $record['suggested_text'] ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=elavira-finding&finding=' . absint( $record['finding_id'] ) ) ) . '">' . esc_html__( 'Review source finding', 'elavira' ) . '</a> · <a href="' . esc_url( admin_url( 'admin.php?page=elavira-report&audit=' . absint( $record['audit_id'] ) ) ) . '">' . esc_html__( 'Review source audit', 'elavira' ) . '</a></p>';
			if ( 'pending' === $record['state'] ) {
				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="elavira_statement_recommendation"><input type="hidden" name="recommendation_id" value="<?php echo esc_attr( $record['id'] ); ?>"><?php wp_nonce_field( 'elavira_statement_recommendation' ); ?><label for="elavira-approved-<?php echo esc_attr( $record['id'] ); ?>"><?php echo esc_html__( 'Editable public wording', 'elavira' ); ?></label><textarea id="elavira-approved-<?php echo esc_attr( $record['id'] ); ?>" name="approved_text" rows="4"><?php echo esc_textarea( $record['suggested_text'] ); ?></textarea><label for="elavira-dismiss-<?php echo esc_attr( $record['id'] ); ?>"><?php echo esc_html__( 'Dismissal reason', 'elavira' ); ?></label><select id="elavira-dismiss-<?php echo esc_attr( $record['id'] ); ?>" name="dismissal_reason"><option value="not_a_limitation"><?php echo esc_html__( 'Not a limitation', 'elavira' ); ?></option><option value="already_addressed"><?php echo esc_html__( 'Already addressed', 'elavira' ); ?></option><option value="already_covered"><?php echo esc_html__( 'Already covered', 'elavira' ); ?></option><option value="do_not_include"><?php echo esc_html__( 'Do not include', 'elavira' ); ?></option><option value="other"><?php echo esc_html__( 'Other', 'elavira' ); ?></option></select><p><button class="button button-primary" name="recommendation_action" value="accepted"><?php echo esc_html__( 'Add edited wording to draft', 'elavira' ); ?></button> <button class="button" name="recommendation_action" value="dismissed" formnovalidate><?php echo esc_html__( 'Dismiss recommendation', 'elavira' ); ?></button></p></form>
				<?php
			} else {
				echo '<p><strong>' . esc_html__( 'Reviewed:', 'elavira' ) . '</strong> ' . esc_html( ucfirst( $record['state'] ) ) . '</p>'; }
			echo '</article>';
		}
		echo '</section>';
	}

	/** Render revision history. @param array $statement Statement. @return void */
	private function revisions( array $statement ) {
		$rows = $this->services['statements']->revisions( $statement['id'] );
		echo '<section><h2>' . esc_html__( 'Published history', 'elavira' ) . '</h2>';
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No published revisions yet.', 'elavira' ) . '</p></section>';
			return; }
		echo '<ol>';
		foreach ( $rows as $row ) {
			$fields = json_decode( $row['fields_json'], true );
			echo '<li><details><summary>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['published_at'] ) ) . '</summary><p><strong>' . esc_html__( 'Statement title:', 'elavira' ) . '</strong> ' . esc_html( is_array( $fields ) ? ( $fields['title'] ?? '' ) : '' ) . '</p></details></li>';
		} echo '</ol></section>';
	}

	/** Render input. @param string $key Key. @param string $label Label. @param array $fields Fields. @param bool $area Textarea. @param bool $required Required. @return void */
	private function text( $key, $label, array $fields, $area = true, $required = false ) {
		$hints = array(
			'known_limitations'      => __( 'Describe known barriers, the tasks they affect, and any workarounds. Include improvement plans and dates only when they are realistic.', 'elavira' ),
			'feedback'               => __( 'Invite people to report barriers or request another way to access content. Explain which contact methods you monitor and when they can expect a response.', 'elavira' ),
			'compatibility'          => __( 'List browser and assistive technology combinations you have actually tested, including versions where known. Be clear about gaps in testing.', 'elavira' ),
			'assessment_approach'    => __( 'Say who assessed the site, when, which pages and tasks they checked, and how. Explain testing limits; automated checks alone do not establish WCAG conformance.', 'elavira' ),
			'additional_information' => __( 'Include useful details such as alternative access arrangements and who maintains this statement. Say when it will be reviewed again.', 'elavira' ),
		);
		if ( isset( $hints[ $key ] ) ) {
			echo '<div class="elavira-field-help">';
		}
		echo '<label for="elavira-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		if ( isset( $hints[ $key ] ) ) {
			/* translators: %s: Statement field label. */
			$name = sprintf( __( 'Help for %s', 'elavira' ), $label );
			echo '<button type="button" class="elavira-help-toggle" hidden aria-label="' . esc_attr( $name ) . '" aria-expanded="false" aria-controls="elavira-help-' . esc_attr( $key ) . '" aria-describedby="elavira-help-' . esc_attr( $key ) . '"><span aria-hidden="true">i</span></button><p class="elavira-field-hint" id="elavira-help-' . esc_attr( $key ) . '">' . esc_html( $hints[ $key ] ) . '</p></div>';
		}
		if ( $area ) {
			echo '<textarea id="elavira-' . esc_attr( $key ) . '" name="fields[' . esc_attr( $key ) . ']" rows="5"' . ( $required ? ' required' : '' ) . '>' . esc_textarea( $fields[ $key ] ?? '' ) . '</textarea>';
		} else {
			$types = array(
				'contact_email' => 'email',
				'contact_url'   => 'url',
				'contact_phone' => 'tel',
			);
			echo '<input type="' . esc_attr( $types[ $key ] ?? 'text' ) . '" id="elavira-' . esc_attr( $key ) . '" name="fields[' . esc_attr( $key ) . ']" value="' . esc_attr( $fields[ $key ] ?? '' ) . '"' . ( $required ? ' required' : '' ) . '>'; } }
	/** Render select. @param string $key Key. @param string $label Label. @param array $options Options. @param array $fields Fields. @return void */
	private function select( $key, $label, array $options, array $fields ) {
		echo '<label for="elavira-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><select id="elavira-' . esc_attr( $key ) . '" name="fields[' . esc_attr( $key ) . ']">';
		foreach ( $options as $value => $text ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $fields[ $key ] ?? '', $value, false ) . '>' . esc_html( $text ) . '</option>';
		} echo '</select>'; }
	/** Create/update associated page. @param array $statement Statement. @param array $fields Fields. @return int */
	private function publish_page( array $statement, array $fields ) {
		if ( ! current_user_can( 'publish_pages' ) || ! current_user_can( 'edit_pages' ) || ( $statement['post_id'] && ! current_user_can( 'edit_post', absint( $statement['post_id'] ) ) ) ) {
			throw new \RuntimeException( 'publish_forbidden' );
		}
		$data = array(
			'post_title'   => $fields['title'],
			'post_content' => '<!-- Elavira renders this Accessibility Statement from approved structured fields. -->',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);
		if ( $statement['post_id'] ) {
			$data['ID'] = absint( $statement['post_id'] );
		} $id = wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( 'WordPress could not publish the Statement page.' );
		} return (int) $id; }
	/** Associate redirect marker. @param string $notice Notice. @return void */
	private function redirect( $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'elavira-statement',
					'elavira_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit; }
	/** Require capability. @return void */
	private function authorize() {
		if ( ! current_user_can( Capability::MANAGE_STATEMENT ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the Accessibility Statement.', 'elavira' ), '', array( 'response' => 403 ) ); } }
	/** Require capability and nonce. @param string $action Nonce action. @return void */
	private function authorize_post( $action ) {
		$this->authorize();
		check_admin_referer( $action ); }
	/** Render feedback. @return void */
	private function notice() {
		$messages = array(
			'created'                 => __( 'Statement draft created.', 'elavira' ),
			'connected'               => __( 'Existing page connected as a draft.', 'elavira' ),
			'draft_saved'             => __( 'Statement draft saved. Published content was not changed.', 'elavira' ),
			'published'               => __( 'Accessibility Statement published.', 'elavira' ),
			'recommendation_reviewed' => __( 'Recommendation reviewed. Publish explicitly when the draft is ready.', 'elavira' ),
			'invalid_page'            => __( 'Choose an editable WordPress page.', 'elavira' ),
			'publish_forbidden'       => __( 'Publishing requires permission to edit and publish pages, including permission to edit the connected page.', 'elavira' ),
			'invalid_fields'          => __( 'Correct the invalid Statement fields and try again.', 'elavira' ),
			'recommendation_failed'   => __( 'The recommendation could not be reviewed.', 'elavira' ),
			'failed'                  => __( 'The Statement could not be created.', 'elavira' ),
		);
		/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect feedback only. */
		$key = isset( $_GET['elavira_notice'] ) ? sanitize_key( wp_unslash( $_GET['elavira_notice'] ) ) : '';
		if ( isset( $messages[ $key ] ) ) {
			echo '<div class="notice ' . ( in_array( $key, array( 'invalid_page', 'publish_forbidden', 'invalid_fields', 'recommendation_failed', 'failed' ), true ) ? 'notice-error' : 'notice-success' ) . ' inline"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}
}
