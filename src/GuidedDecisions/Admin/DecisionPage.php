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
use Elavira\GuidedDecisions\DecisionContext;
use Elavira\Learn\Admin\LearnPage;
use Elavira\Reports\Application\UpdateFindingStatus;
use InvalidArgumentException;
use Throwable;

/** Renders and processes the server-authoritative decision flow. */
final class DecisionPage {
	/** Dependencies. @var array */
	private $services;
	/** Bounded records loaded only after ownership authorization. @var array */
	private $evidence = array(
		'items' => array(),
		'total' => 0,
	);

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
			if ( ! $answers ) {
				$answers = $this->initial_answers( $decision );
				$state   = $this->state()->encode( $answers, $this->binding( $finding, $decision ) );
			}
			$result = $this->resolve( $decision, $answers );
		} catch ( InvalidArgumentException $error ) {
			$this->die_safe( __( 'This Guided Decision state is invalid. Restart the review from the finding.', 'elavira' ), 400 );
			return;
		}
		?>
		<div class="wrap elavira-reports elavira-decision">
		<?php $this->return_links( $finding ); ?>
		<h1><?php echo esc_html( $decision->get( 'title' ) ); ?></h1>
		<?php $this->notice(); ?>
		<p><?php echo esc_html( $decision->get( 'purpose' ) ); ?></p>
		<?php $this->recorded_evidence( $finding ); ?>
		<?php $this->learn_link( $decision ); ?>
		<p class="elavira-disclaimer"><?php echo esc_html__( 'Your answers guide this review. Elavira does not change your site or verify a fix when you complete it.', 'elavira' ); ?></p>
		<?php $this->answer_summary( $result['answers'] ); ?>
		<?php if ( $result['complete'] ) : ?>
			<?php $this->render_outcome( $finding, $decision, $state, $result ); ?>
		<?php else : ?>
			<?php $this->render_step( $finding, $decision, $state, $result ); ?>
		<?php endif; ?>
		</div>
		<?php
	}

	/** Link to background education without interrupting the decision flow. @param array $finding Finding. @return void */
	private function learn_link( $decision ) {
		foreach ( $decision->get( 'learn_topics' ) as $topic_id ) {
			$topic = $this->services['learn']->find( $topic_id );
			if ( $topic ) {
				/* translators: %s: Learn topic title. */
				echo '<p><a target="_blank" rel="noopener noreferrer" href="' . esc_url( LearnPage::url( $topic->get( 'id' ) ) ) . '">' . esc_html( sprintf( __( 'Why this matters: %s', 'elavira' ), $topic->get( 'title' ) ) ) . ' ' . esc_html__( '(opens in a new tab)', 'elavira' ) . '</a></p>';
			}
		}
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
			$result  = $this->resolve( $decision, $answers );
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
			$this->redirect( $finding['id'], $state, 'invalid_answer', $decision );
			return;
		}
		$this->redirect( $finding['id'], $this->state()->encode( $answers, $this->binding( $finding, $decision ) ), '', $decision );
	}

	/** Persist a completed decision and optional explicit workflow choice. @return void */
	public function complete() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Needed to select the finding-bound nonce action.
		$finding_id = isset( $_POST['finding'] ) ? absint( $_POST['finding'] ) : 0;
		check_admin_referer( 'elavira_decision_' . $finding_id );
		list( $finding, $decision ) = $this->context_from_post( Capability::MANAGE );
		$state                      = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		$status                     = isset( $_POST['workflow_status'] ) ? sanitize_key( wp_unslash( $_POST['workflow_status'] ) ) : '';
		$note                       = isset( $_POST['review_note'] ) ? substr( sanitize_textarea_field( wp_unslash( $_POST['review_note'] ) ), 0, 1000 ) : '';
		try {
			$answers = $this->state()->decode( $state, $this->binding( $finding, $decision ) );
			$result  = $this->resolve( $decision, $answers );
			if ( ! $finding['id'] || ! $result['complete'] || ! in_array( $status, array( '', FindingStatus::OPEN, FindingStatus::ADDRESSED, FindingStatus::ACCEPTED_LIMITATION, FindingStatus::NEEDS_REVIEW ), true ) ) {
				throw new InvalidArgumentException( 'Invalid completion request.' );
			}
			if ( '' !== $status && $status !== $finding['current_status'] ) {
				$this->services['status']->update( $finding['id'], $status, $note, get_current_user_id() );
			}
			$result['answers'][] = array(
				'question'        => __( 'Review note and workflow choice', 'elavira' ),
				'label'           => $note,
				'workflow_status' => $status,
				'previous_status' => $finding['current_status'],
			);
			$this->services['repository']->record(
				array(
					'finding_id'       => $finding['id'],
					'decision_id'      => $decision->get( 'id' ),
					'decision_version' => $decision->get( 'version' ),
					'answers'          => $result['answers'],
					'outcome_id'       => $result['outcome_id'],
					'recommendation'   => $result['outcome']['interpretation'] . "\n\n" . $result['outcome']['recommendation'] . "\n\n" . ( $result['outcome']['verification_guidance'] ?? '' ),
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
		if ( ! $finding_id ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only registered educational decision.
			return $this->manual_context( isset( $_GET['decision'] ) ? sanitize_text_field( wp_unslash( $_GET['decision'] ) ) : '' );
		}
		return $this->context( $finding_id );
	}

	/** Resolve and authorize POST context. @param string $capability Required capability. @return array */
	private function context_from_post( $capability ) {
		if ( ! is_user_logged_in() || ! current_user_can( $capability ) ) {
			$this->die_safe( __( 'You are not allowed to perform this Guided Decision action.', 'elavira' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller verifies the finding-bound nonce before resolving context.
		$finding_id = isset( $_POST['finding'] ) ? absint( $_POST['finding'] ) : 0;
		if ( ! $finding_id && Capability::VIEW_REPORTS === $capability ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the navigation nonce.
			return $this->manual_context( isset( $_POST['decision'] ) ? sanitize_text_field( wp_unslash( $_POST['decision'] ) ) : '' );
		}
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
		$this->evidence = isset( $this->services['occurrences'] ) ? $this->services['occurrences']->paginate_by_finding( $finding_id, 1, 3 ) : array(
			'items' => array(),
			'total' => 0,
		);
		return array( $finding, $decision );
	}

	/** Only registry-owned standalone educational flows may omit a finding. */
	private function manual_context( $id ) {
		foreach ( $this->services['registry']->all() as $decision ) {
			if ( $id === $decision->get( 'id' ) && ! $decision->get( 'rules' ) ) {
				$this->evidence = array(
					'items' => array(),
					'total' => 0,
				);
				return array(
					array(
						'id'       => 0,
						'audit_id' => 0,
					),
					$decision,
				);
			}
		}
		$this->die_safe( __( 'This educational decision is not available.', 'elavira' ), 404 );
	}

	private function initial_answers( $decision ) {
		return ( new DecisionContext() )->initial_answers( $decision, $this->evidence['items'], $this->evidence['total'] );
	}

	/** Label evidence-derived routing honestly in both the screen and saved record. */
	private function resolve( $decision, array $answers ) {
		$result  = $this->navigator()->resolve( $decision, $answers );
		$initial = $this->initial_answers( $decision );
		if ( $initial && isset( $result['answers'][0] ) && $initial[0] === $result['answers'][0]['answer'] ) {
			$result['answers'][0]['source']        = 'recorded_evidence';
			$result['answers'][0]['occurrence_id'] = (int) $this->evidence['items'][0]['id'];
			/* translators: %s: recorded link content type. */
			$result['answers'][0]['label'] = sprintf( __( '%s (from recorded evidence)', 'elavira' ), $result['answers'][0]['label'] );
		}
		return $result;
	}

	private function return_links( array $finding ) {
		if ( ! $finding['id'] ) {
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=elavira-learn#guided-decisions' ) ) . '">' . esc_html__( 'Back to Guided Decisions', 'elavira' ) . '</a></p>';
			return;
		}
		echo '<nav aria-label="' . esc_attr__( 'Return to audit', 'elavira' ) . '"><p><a href="' . esc_url( admin_url( 'admin.php?page=elavira-finding&finding=' . absint( $finding['id'] ) . '&audit=' . absint( $finding['audit_id'] ) ) ) . '">' . esc_html__( 'Back to finding', 'elavira' ) . '</a> · <a href="' . esc_url( admin_url( 'admin.php?page=elavira-report&audit=' . absint( $finding['audit_id'] ) ) ) . '">' . esc_html__( 'Back to report', 'elavira' ) . '</a></p></nav>';
	}

	private function recorded_evidence( array $finding ) {
		if ( ! $finding['id'] ) {
			echo '<p>' . esc_html__( 'This is an educational review. It is not attached to a finding and will not save a finding status.', 'elavira' ) . '</p>';
			return;
		}
		echo '<section class="elavira-decision-evidence"><h2>' . esc_html__( 'Recorded evidence', 'elavira' ) . '</h2>';
		if ( ! $this->evidence['items'] ) {
			echo '<p>' . esc_html__( 'No occurrence details are available for this finding. Use its saved summary and the audited page to locate the condition; missing evidence does not establish a failure.', 'elavira' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'These are audit snapshots, not live measurements or example content. Check the current page if it has changed. A simple name excerpt may omit ARIA naming relationships; inspect the full name when needed.', 'elavira' ) . '</p>';
			foreach ( $this->evidence['items'] as $occurrence ) {
				$presented = ( new DecisionContext() )->present( $occurrence, $finding['rule_id'] );
				echo '<h3>' . esc_html( $presented['heading'] ) . '</h3>';
				if ( ! empty( $occurrence['canonical_url'] ) ) {
					echo '<p>' . esc_html__( 'Audited page:', 'elavira' ) . ' ' . esc_html( $occurrence['canonical_url'] ) . '</p>';
				}
				echo '<dl>';
				foreach ( $presented['rows'] as $row ) {
					echo '<dt>' . esc_html( $row['label'] ) . '</dt><dd>' . esc_html( $row['value'] ) . '</dd>';
				}
				echo '</dl>';
			}
			if ( $this->evidence['total'] > 1 ) {
				echo '<p>' . esc_html__( 'This finding groups multiple occurrences. Only the first few are shown here. Check the full occurrence list in the finding and review each relevant case before changing the group status.', 'elavira' ) . '</p>';
			}
		}
		echo '</section>';
	}

	/** Render one question. @param array $finding Finding. @param object $decision Decision. @param string $state State. @param array $result Resolution. @return void */
	private function render_step( array $finding, $decision, $state, array $result ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="elavira-decision-form">
		<input type="hidden" name="decision" value="<?php echo esc_attr( $decision->get( 'id' ) ); ?>">
		<input type="hidden" name="action" value="elavira_decision_navigate"><input type="hidden" name="finding" value="<?php echo esc_attr( $finding['id'] ); ?>"><input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>"><?php wp_nonce_field( 'elavira_decision_' . absint( $finding['id'] ) ); ?>
		<fieldset aria-describedby="elavira-question-help"><legend><?php echo esc_html( $result['step']['question'] ); ?></legend>
		<p id="elavira-question-help"><?php echo esc_html( $result['step']['help'] ?? '' ); ?></p>
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
		<section aria-labelledby="elavira-decision-result"><h2 id="elavira-decision-result"><?php echo esc_html__( 'Decision result', 'elavira' ); ?></h2><p><?php echo esc_html( $result['outcome']['interpretation'] ); ?></p><h3><?php echo esc_html__( 'What to do next', 'elavira' ); ?></h3><p><?php echo esc_html( $result['outcome']['recommendation'] ); ?></p><h3><?php echo esc_html__( 'How to verify it', 'elavira' ); ?></h3><p><?php echo esc_html( $result['outcome']['verification_guidance'] ?? '' ); ?></p></section>
		<?php
		$this->return_links( $finding );
		if ( ! $finding['id'] || ! current_user_can( Capability::MANAGE ) ) {
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="elavira-decision-form"><input type="hidden" name="action" value="elavira_decision_complete"><input type="hidden" name="finding" value="<?php echo esc_attr( $finding['id'] ); ?>"><input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>"><?php wp_nonce_field( 'elavira_decision_' . absint( $finding['id'] ) ); ?>
		<fieldset><legend><?php echo esc_html__( 'Record this review', 'elavira' ); ?></legend><p><?php echo esc_html__( 'Choose whether to keep the current workflow status or explicitly record another permitted status. Verified is available only from a later audit.', 'elavira' ); ?></p>
		<label><input type="radio" name="workflow_status" value="" checked> <?php echo esc_html__( 'Keep current status', 'elavira' ); ?></label>
		<p><?php echo esc_html__( 'If your review finds no issue, save this decision with the current status, then use Reviewed — Not an Issue on the finding when that option is available. Review all grouped occurrences before applying that decision.', 'elavira' ); ?></p>
		<label><input type="radio" name="workflow_status" value="addressed"> <?php echo esc_html__( 'Mark Addressed — I report that remediation was performed; this is not verification.', 'elavira' ); ?></label>
		<label><input type="radio" name="workflow_status" value="accepted_limitation"> <?php echo esc_html__( 'Mark Accepted Limitation — records an intentional limitation; it does not remediate the issue or establish conformance.', 'elavira' ); ?></label>
		<label><input type="radio" name="workflow_status" value="needs_review"> <?php echo esc_html__( 'Keep or mark Needs Review', 'elavira' ); ?></label>
		<label><input type="radio" name="workflow_status" value="open"> <?php echo esc_html__( 'Keep or mark Open', 'elavira' ); ?></label></fieldset>
		<label for="elavira-review-note"><?php echo esc_html__( 'Review note (optional)', 'elavira' ); ?></label><textarea id="elavira-review-note" name="review_note" rows="4" maxlength="1000"></textarea>
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
	private function redirect( $finding_id, $state, $notice = '', $decision = null ) {
		$url = add_query_arg(
			array(
				'page'    => 'elavira-guided-decision',
				'finding' => absint( $finding_id ),
				'state'   => $state,
			),
			admin_url( 'admin.php' )
		);
		if ( ! $finding_id && $decision ) {
			$url = add_query_arg( 'decision', $decision->get( 'id' ), $url );
		}
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
