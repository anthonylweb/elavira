<?php
/**
 * Server-authoritative Guided Decision navigation.
 *
 * @package Elavira
 */

namespace Elavira\GuidedDecisions;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag -- Concise domain comments are intentional.

use InvalidArgumentException;

/** Replays validated answers through a trusted graph. */
final class DecisionNavigator {
	/** Resolve the current step or final outcome. @param DecisionDefinition $decision Definition. @param array $answers Ordered answers. @return array */
	public function resolve( DecisionDefinition $decision, array $answers ) {
		$step_id  = $decision->get( 'start' );
		$accepted = array();
		foreach ( $answers as $answer ) {
			$step       = $decision->step( $step_id );
			$option     = $this->option( $step, $answer );
			$accepted[] = array(
				'step'     => $step_id,
				'answer'   => $answer,
				'question' => $step['question'],
				'label'    => $option['label'],
			);
			if ( $this->is_outcome( $decision, $option['target'] ) ) {
				if ( count( $accepted ) !== count( $answers ) ) {
					throw new InvalidArgumentException( 'Answers continue beyond the decision outcome.' );
				}
				return array(
					'complete'   => true,
					'answers'    => $accepted,
					'outcome_id' => $option['target'],
					'outcome'    => $decision->outcome( $option['target'] ),
				);
			}
			$step_id = $option['target'];
		}
		return array(
			'complete' => false,
			'answers'  => $accepted,
			'step_id'  => $step_id,
			'step'     => $decision->step( $step_id ),
		);
	}

	/** Find a valid answer option. @param array $step Step. @param string $answer Answer ID. @return array */
	private function option( array $step, $answer ) {
		foreach ( $step['options'] as $option ) {
			if ( $answer === $option['id'] ) {
				return $option;
			}
		}
		throw new InvalidArgumentException( 'That answer is not available for this question.' );
	}

	/** Determine whether a target is an outcome. @param DecisionDefinition $decision Definition. @param string $target Target. @return bool */
	private function is_outcome( DecisionDefinition $decision, $target ) {
		try {
			$decision->outcome( $target );
			return true;
		} catch ( InvalidArgumentException $error ) {
			return false;
		}
	}
}
