<?php
/**
 * Guided Decision definition.
 *
 * @package Elavira
 */

namespace Elavira\GuidedDecisions;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment -- Concise domain comments are intentional.

use InvalidArgumentException;

/** Immutable, server-owned decision graph. */
final class DecisionDefinition {
	/** Trusted definition data. @var array */
	private $data;

	/**
	 * Validate and create a definition.
	 *
	 * @param array $data Definition data.
	 * @throws InvalidArgumentException When the graph is invalid.
	 */
	public function __construct( array $data ) {
		$data['learn_topics'] = $data['learn_topics'] ?? array();
		$required             = array( 'id', 'version', 'editorial_version', 'title', 'purpose', 'rules', 'start', 'steps', 'outcomes' );
		if ( array() !== array_diff( $required, array_keys( $data ) ) ) {
			throw new InvalidArgumentException( 'Guided Decision definition is incomplete.' );
		}
		if ( ! preg_match( '/^elavira\.decision\.[a-z][a-z0-9_]*$/', $data['id'] ) || ! preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+$/', $data['version'] ) ) {
			throw new InvalidArgumentException( 'Guided Decision identity is invalid.' );
		}
		if ( ! isset( $data['steps'][ $data['start'] ] ) || ( empty( $data['rules'] ) && empty( $data['learn_topics'] ) ) || empty( $data['outcomes'] ) ) {
			throw new InvalidArgumentException( 'Guided Decision graph is incomplete.' );
		}
		foreach ( $data['learn_topics'] as $topic_id ) {
			if ( ! is_string( $topic_id ) || ! preg_match( '/^[a-z][a-z0-9_]*$/', $topic_id ) ) {
				throw new InvalidArgumentException( 'Guided Decision Learn topic is invalid.' );
			}
		}
		$this->validate_graph( $data );
		$this->data = $data;
	}

	/** Return an identity or metadata field. @param string $key Field. @return mixed */
	public function get( $key ) {
		return $this->data[ $key ] ?? null;
	}

	/** Return whether this decision supports a stored rule. @param string $rule_id Rule ID. @param string $rule_version Rule version. @return bool */
	public function supports( $rule_id, $rule_version ) {
		foreach ( $this->data['rules'] as $rule ) {
			if ( $rule_id === $rule['id'] && $rule_version === $rule['version'] ) {
				return true;
			}
		}
		return false;
	}

	/** Resolve a step. @param string $step_id Step ID. @return array */
	public function step( $step_id ) {
		if ( ! isset( $this->data['steps'][ $step_id ] ) ) {
			throw new InvalidArgumentException( 'Unknown Guided Decision step.' );
		}
		return $this->data['steps'][ $step_id ];
	}

	/** Resolve an outcome. @param string $outcome_id Outcome ID. @return array */
	public function outcome( $outcome_id ) {
		if ( ! isset( $this->data['outcomes'][ $outcome_id ] ) ) {
			throw new InvalidArgumentException( 'Unknown Guided Decision outcome.' );
		}
		return $this->data['outcomes'][ $outcome_id ];
	}

	/** Validate deterministic targets and reject cycles.
	 *
	 * @param array $data Definition data.
	 * @return void
	 * @throws InvalidArgumentException When a target or path is invalid.
	 */
	private function validate_graph( array $data ) {
		foreach ( $data['steps'] as $step_id => $step ) {
			if ( ! in_array( $step['type'] ?? '', array( 'yes_no', 'single_choice', 'information' ), true ) ) {
				throw new InvalidArgumentException( 'Unsupported Guided Decision step type.' );
			}
			$options = $step['options'] ?? array();
			if ( 'information' !== $step['type'] && empty( $options ) ) {
				throw new InvalidArgumentException( 'A question requires answer options.' );
			}
			if ( 'information' === $step['type'] && 1 !== count( $options ) ) {
				throw new InvalidArgumentException( 'An informational step requires one deterministic continuation.' );
			}
			foreach ( $options as $option ) {
				$target = $option['target'] ?? '';
				if ( ! isset( $data['steps'][ $target ] ) && ! isset( $data['outcomes'][ $target ] ) ) {
					throw new InvalidArgumentException( 'Guided Decision branch target is invalid.' );
				}
			}
		}
		$reachable_steps    = array();
		$reachable_outcomes = array();
		$this->walk( $data['start'], $data, array(), $reachable_steps, $reachable_outcomes );
		if ( count( $reachable_steps ) !== count( $data['steps'] ) || count( $reachable_outcomes ) !== count( $data['outcomes'] ) ) {
			throw new InvalidArgumentException( 'Guided Decision graph contains an unreachable step or outcome.' );
		}
	}

	/** Walk a graph path to detect cycles and collect reachable nodes. @param string $step_id Step ID. @param array $data Definition. @param array $path Current path. @param array $steps Reachable steps. @param array $outcomes Reachable outcomes. @return void */
	private function walk( $step_id, array $data, array $path, array &$steps, array &$outcomes ) {
		if ( isset( $path[ $step_id ] ) ) {
			throw new InvalidArgumentException( 'Guided Decision graph contains a cycle.' );
		}
		$steps[ $step_id ] = true;
		$path[ $step_id ]  = true;
		foreach ( $data['steps'][ $step_id ]['options'] ?? array() as $option ) {
			if ( isset( $data['steps'][ $option['target'] ] ) ) {
				$this->walk( $option['target'], $data, $path, $steps, $outcomes );
			} else {
				$outcomes[ $option['target'] ] = true;
			}
		}
	}
}
