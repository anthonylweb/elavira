<?php
/**
 * Guided Decision registry.
 *
 * @package Elavira
 */

namespace Elavira\GuidedDecisions;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment -- Concise registry comments are intentional.

use InvalidArgumentException;

/** Owns exact decision identities and rule compatibility lookup. */
final class DecisionRegistry {
	/** Registered definitions. @var array */
	private $definitions = array();

	/** Register one definition. @param DecisionDefinition $definition Definition. @return void */
	public function register( DecisionDefinition $definition ) {
		$key = $definition->get( 'id' ) . '@' . $definition->get( 'version' );
		if ( isset( $this->definitions[ $key ] ) ) {
			throw new InvalidArgumentException( 'Duplicate Guided Decision definition.' );
		}
		$this->definitions[ $key ] = $definition;
	}

	/** Resolve an exact definition. @param string $id Decision ID. @param string $version Version. @return DecisionDefinition */
	public function get( $id, $version ) {
		$key = $id . '@' . $version;
		if ( ! isset( $this->definitions[ $key ] ) ) {
			throw new InvalidArgumentException( 'Guided Decision is unavailable.' );
		}
		return $this->definitions[ $key ];
	}

	/** Resolve the compatible decision for a historical finding. @param string $rule_id Rule ID. @param string $rule_version Rule version. @return DecisionDefinition|null */
	public function for_rule( $rule_id, $rule_version ) {
		foreach ( $this->definitions as $definition ) {
			if ( $definition->supports( $rule_id, $rule_version ) ) {
				return $definition;
			}
		}
		return null;
	}

	/** Return every actually registered decision. @return DecisionDefinition[] */
	public function all() {
		return array_values( $this->definitions );
	}
}
