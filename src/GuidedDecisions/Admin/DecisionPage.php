<?php
/**
 * Accessible Guided Decision admin workflow.
 *
 * @package Elavira
 */

namespace Elavira\GuidedDecisions\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment -- Concise domain comments are intentional.

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Core\Capabilities\Capability;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Values\FindingStatus;
use Elavira\GuidedDecisions\DecisionNavigator;
use Elavira\GuidedDecisions\DecisionRegistry;
use Elavira\GuidedDecisions\DecisionRepository;
use Elavira\GuidedDecisions\DecisionState;
use Elavira\Reports\Application\UpdateFindingStatus;
use InvalidArgumentException;
use Throwable;

/** Renders and processes the server-authoritative decision flow. */
final class DecisionPage {
	/** Dependencies. @var array */
	private $services;

	/** Create the controller. @param array $services Named dependencies. */
	public function __construct( array $services ) {
		$this->services = $services;
	}

	/** Register the hidden admin route. @return void */
	public function register() {
		add_submenu_page( null, __( 'Guided Decision', 'elavira' ), __( 'Guided Decision', 'elavira' ), Capability::VIEW_REPORTS, 'elavira-guided-decision', array( $this, 'render' ) );
	}

	/** Render the current trusted step or outcome. @return void */
	public function render() {
		list( $finding, $decision ) = $this->context_from_get();
		try {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed read-only navigation state.
			$state   = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
			$answers = $this->state()->decode( $state, $this->binding( $finding, $decision ) );
			$result  = $this->navigator()->resolve( $decision, $answers );
		} catch ( InvalidArgumentException $error ) {
			$this->die_safe( __( 'This Guided Decision state is invalid. Restart the review from the finding.', 'elavira' ), 400 );
			return;
		}
		?>
		<div class="wrap elavira-reports elavira-decision">
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elavira-finding&finding=' . absint( $finding['id'] ) ) ); ?>">&larr; <?php echo esc_html__( 'Back to finding', 'elavira' ); ?></a></p>
		<h1><?php echo esc_html( $decision->get( 'title' ) ); ?></h1>
		<?php $this->notice(); ?>
		<p><?php echo esc_html( $decision->get( 'purpose' ) ); ?></p>
		<p class="elavira-disclaimer"><?php echo esc_html__( 'Elavira assists your judgment but cannot determine the final accessibility outcome automatically. Completing this review does not verify or remediate the finding.', 'elavira' ); ?></p>
		<?php $this->answer_summary( $result['answers'] ); ?>
		<?php if ( $result['complete'] ) : ?>
			<?php $this->render_outcome( $finding, $decision, $state, $result ); ?>
		<?php else : ?>
			<?php $this->render_step( $finding, $decision, $state, $result ); ?>
		<?php endif; ?>
		</div>
		<?php
	}

	/** Process answer, back, and restart requests without persistence. @return void */
	public function navigate() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller verifies the finding-bound nonce before resolving context.
		$finding_id = isset( $_POST['finding'] ) ? absint( $_POST['finding'] ) : 0;
		check_admin_referer( 'elavira_decision_' . $finding_id );
		list( $finding, $decision ) = $this->context_from_post( Capability::VIEW_REPORTS );
		$state                      = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		$operation                  = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		try {
			$answers = $this->state()->decode( $state, $this->binding( $finding, $decision ) );
			$result  = $this->navigator()->resolve( $decision, $answers );
			if ( 'answer' === $operation && ! $result['complete'] ) {
				$answer    = isset( $_POST['answer'] ) ? sanitize_key( wp_unslash( $_POST['answer'] ) ) : '';
				$answers[] = $answer;
				$this->navigator()->resolve( $decision, $answers );
			} elseif ( 'back' === $operation ) {
				array_pop( $answers );
			} elseif ( 'restart' === $operation ) {
				$answers = array();
			} else {
				throw new InvalidArgumentException( 'Invalid navigation operation.' );
			}
		} catch ( InvalidArgumentException $error ) {
			$this->redirect( $finding['id'], '', 'invalid_answer' );
			return;
		}
		$this->redirect( $finding['id'], $this->state()->encode( $answers, $this->binding( $finding, $decision ) ) );
	}

	/** Persist a completed decision and optional explicit workflow choice. @return void */
	public function complete() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Needed to select the finding-bound nonce action.
		$finding_id = isset( $_POST['finding'] ) ? absint( $_POST['finding'] ) : 0;
		check_admin_referer( 'elavira_decision_' . $finding_id );
		list( $finding, $decision ) = $this->context_from_post( Capability::MANAGE );
		$state                      = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		$status                     = isset( $_POST['workflow_status'] ) ? sanitize_key( wp_unslash( $_POST['workflow_status'] ) ) : '';
		try {
			$answers = $this->state()->decode( $state, $this->binding( $finding, $decision ) );
			$result  = $this->navigator()->resolve( $decision, $answers );
			if ( ! $result['complete'] || ! in_array( $status, array( '', FindingStatus::OPEN, FindingStatus::ADDRESSED, FindingStatus::ACCEPTED_LIMITATION, FindingStatus::NEEDS_REVIEW ), true ) ) {
				throw new InvalidArgumentException( 'Invalid completion request.' );
			}
			if ( '' !== $status && $status !== $finding['current_status'] ) {
				$this->services['status']->update( $finding['id'], $status, __( 'Recorded after completing a Guided Decision.', 'elavira' ), get_current_user_id() );
			}
			$this->services['repository']->record(
				array(
					'finding_id'       => $finding['id'],
					'decision_id'      => $decision->get( 'id' ),
					'decision_version' => $decision->get( 'version' ),
					'answers'          => $result['answers'],
					'outcome_id'       => $result['outcome_id'],
					'recommendation'   => $result['outcome']['recommendation'],
					'actor_user_id'    => get_current_user_id(),
				)
			);
		} catch ( Throwable $error ) {
			$this->redirect( $finding['id'], $state, 'completion_failed' );
			return;
		}
		wp_safe_redirect( add_query_arg( 'elavira_notice', 'decision_completed', admin_url( 'admin.php?page=elavira-finding&finding=' . absint( $finding['id'] ) ) ) );
		exit;
	}

	/** Resolve and authorize GET context. @return array */
	private function context_from_get() {
		if ( ! current_user_can( Capability::VIEW_REPORTS ) ) {
			$this->die_safe( __( 'You are not allowed to review this finding.', 'elavira' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only finding lookup.
		$finding_id = isset( $_GET['finding'] ) ? absint( $_GET['finding'] ) : 0;
		return $this->context( $finding_id );
	}

	/** Resolve and authorize POST context. @param string $capability Required capability. @return array */
	private function context_from_post( $capability ) {
		if ( ! is_user_logged_in() || ! current_user_can( $capability ) ) {
			$this->die_safe( __( 'You are not allowed to perform this Guided Decision action.', 'elavira' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller verifies the finding-bound nonce before resolving context.
		$finding_id = isset( $_POST['finding'] ) ? absint( $_POST['finding'] ) : 0;
		return $this->context( $finding_id );
	}

	/** Resolve owned finding and compatible decision. @param int $finding_id Finding ID. @return array */
	private function context( $finding_id ) {
		$finding  = $this->services['findings']->find( $finding_id );
		$audit    = $finding ? $this->services['audits']->find( $finding['audit_id'] ) : null;
		$decision = $finding ? $this->services['registry']->for_rule( $finding['rule_id'], $finding['rule_version'] ) : null;
		if ( ! $finding || ! $audit || ! $decision || get_current_user_id() !== (int) $audit['owner_user_id'] ) {
			$this->die_safe( __( 'A compatible Guided Decision is not available for this finding.', 'elavira' ), 404 );
		}
		return array( $finding, $decision );
	}

	/** Render one question. @param array $finding Finding. @param object $decision Decision. @param string $state State. @param array $result Resolution. @return void */
	private function render_step( array $finding, $decision, $state, array $result ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="elavira-decision-form">
		<input type="hidden" name="action" value="elavira_decision_navigate"><input type="hidden" name="finding" value="<?php echo esc_attr( $finding['id'] ); ?>"><input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>"><?php wp_nonce_field( 'elavira_decision_' . absint( $finding['id'] ) ); ?>
		<fieldset><legend><?php echo esc_html( $result['step']['question'] ); ?></legend>
		<?php
		if ( 'information' === $result['step']['type'] ) :
			?>
			<p><?php echo esc_html( $result['step']['content'] ?? '' ); ?></p><input type="hidden" name="answer" value="<?php echo esc_attr( $result['step']['options'][0]['id'] ); ?>">
			<?php
		else :
			?>
			<p><?php echo esc_html__( 'Choose the answer that best reflects the rendered page and its context.', 'elavira' ); ?></p>
			<?php
			foreach ( $result['step']['options'] as $option ) :
				?>
				<label><input type="radio" name="answer" value="<?php echo esc_attr( $option['id'] ); ?>" required> <?php echo esc_html( $option['label'] ); ?></label><?php endforeach; ?>
		<?php endif; ?>
		</fieldset><p><button class="button button-primary" name="operation" value="answer"><?php echo esc_html__( 'Continue', 'elavira' ); ?></button> 
		<?php
		if ( $result['answers'] ) :
			?>
			<button class="button" name="operation" value="back" formnovalidate><?php echo esc_html__( 'Back', 'elavira' ); ?></button><?php endif; ?> <button class="button" name="operation" value="restart" formnovalidate><?php echo esc_html__( 'Restart', 'elavira' ); ?></button></p></form>
		<?php
	}

	/** Render final interpretation and explicit workflow choices. @param array $finding Finding. @param object $decision Decision. @param string $state State. @param array $result Result. @return void */
	private function render_outcome( array $finding, $decision, $state, array $result ) {
		?>
		<section aria-labelledby="elavira-decision-result"><h2 id="elavira-decision-result"><?php echo esc_html__( 'Decision result', 'elavira' ); ?></h2><p><strong><?php echo esc_html__( 'Based on your answers, the recommended approach is:', 'elavira' ); ?></strong> <?php echo esc_html( $result['outcome']['interpretation'] ); ?></p><p><?php echo esc_html( $result['outcome']['recommendation'] ); ?></p></section>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="elavira-decision-form"><input type="hidden" name="action" value="elavira_decision_complete"><input type="hidden" name="finding" value="<?php echo esc_attr( $finding['id'] ); ?>"><input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>"><?php wp_nonce_field( 'elavira_decision_' . absint( $finding['id'] ) ); ?>
		<fieldset><legend><?php echo esc_html__( 'Record this review', 'elavira' ); ?></legend><p><?php echo esc_html__( 'Choose whether to keep the current workflow status or explicitly record another permitted status. Verified is available only from a later audit.', 'elavira' ); ?></p>
		<label><input type="radio" name="workflow_status" value="" checked> <?php echo esc_html__( 'Keep current status', 'elavira' ); ?></label>
		<label><input type="radio" name="workflow_status" value="addressed"> <?php echo esc_html__( 'Mark Addressed — I report that remediation was performed; this is not verification.', 'elavira' ); ?></label>
		<label><input type="radio" name="workflow_status" value="accepted_limitation"> <?php echo esc_html__( 'Mark Accepted Limitation — records an intentional limitation; it does not remediate the issue or establish conformance.', 'elavira' ); ?></label>
		<label><input type="radio" name="workflow_status" value="needs_review"> <?php echo esc_html__( 'Keep or mark Needs Review', 'elavira' ); ?></label>
		<label><input type="radio" name="workflow_status" value="open"> <?php echo esc_html__( 'Keep or mark Open', 'elavira' ); ?></label></fieldset>
		<p><button class="button button-primary" type="submit"><?php echo esc_html__( 'Save decision', 'elavira' ); ?></button></p></form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="elavira_decision_navigate"><input type="hidden" name="finding" value="<?php echo esc_attr( $finding['id'] ); ?>"><input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>"><?php wp_nonce_field( 'elavira_decision_' . absint( $finding['id'] ) ); ?><button class="button" name="operation" value="back"><?php echo esc_html__( 'Back', 'elavira' ); ?></button> <button class="button" name="operation" value="restart"><?php echo esc_html__( 'Restart', 'elavira' ); ?></button></form>
		<?php
	}

	/** Render prior answers. @param array $answers Answer summaries. @return void */
	private function answer_summary( array $answers ) {
		if ( ! $answers ) {
			return; }
		echo '<section><h2>' . esc_html__( 'Your answers', 'elavira' ) . '</h2><dl class="elavira-decision-answers">';
		foreach ( $answers as $answer ) {
			echo '<div><dt>' . esc_html( $answer['question'] ) . '</dt><dd>' . esc_html( $answer['label'] ) . '</dd></div>';
		}
		echo '</dl></section>';
	}

	/** Redirect to trusted decision state. @param int $finding_id Finding ID. @param string $state State. @param string $notice Notice. @return void */
	private function redirect( $finding_id, $state, $notice = '' ) {
		$url = add_query_arg(
			array(
				'page'    => 'elavira-guided-decision',
				'finding' => absint( $finding_id ),
				'state'   => $state,
			),
			admin_url( 'admin.php' )
		);
		if ( $notice ) {
			$url = add_query_arg( 'elavira_notice', $notice, $url ); }
		wp_safe_redirect( $url );
		exit;
	}

	/** Return the state codec. @return DecisionState */
	private function state() {
		return $this->services['state']; }
	/** Return the navigator. @return DecisionNavigator */
	private function navigator() {
		return $this->services['navigator']; }
	/** Bind state to one finding and semantic decision. @param array $finding Finding. @param object $decision Decision. @return string */
	private function binding( array $finding, $decision ) {
		return absint( $finding['id'] ) . '|' . $decision->get( 'id' ) . '|' . $decision->get( 'version' );
	}
	/** Render safe feedback after rejected POST input. @return void */
	private function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only feedback marker after a nonce-protected action.
		$notice = isset( $_GET['elavira_notice'] ) ? sanitize_key( wp_unslash( $_GET['elavira_notice'] ) ) : '';
		if ( 'invalid_answer' === $notice ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Choose one of the available answers and try again.', 'elavira' ) . '</p></div>';
		} elseif ( 'completion_failed' === $notice ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The completed decision could not be saved. Your answers are unchanged.', 'elavira' ) . '</p></div>';
		}
	}
	/** Stop with a safe message. @param string $message Message. @param int $response Status. @return void */
	private function die_safe( $message, $response ) {
		wp_die( esc_html( $message ), '', array( 'response' => absint( $response ) ) ); }
}
