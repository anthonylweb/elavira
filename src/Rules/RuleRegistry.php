<?php
/**
 * Accessibility Rule Registry.
 *
 * @package Elavira
 */

namespace Elavira\Rules;

// phpcs:disable Squiz.Commenting.FunctionComment

use InvalidArgumentException;

/**
 * Central collision-safe registry keyed by stable rule ID and version.
 */
final class RuleRegistry {
	/** Registered rules by ID and version.
	 *
	 * @var RuleDefinition[]
	 */
	private $rules = array();

	/**
	 * Register a trusted rule definition.
	 *
	 * @param RuleDefinition $rule Rule definition.
	 * @return void
	 * @throws InvalidArgumentException When the ID/version pair already exists.
	 */
	public function register( RuleDefinition $rule ) {
		$key = $this->key( $rule->id(), $rule->version() );

		if ( isset( $this->rules[ $key ] ) ) {
			throw new InvalidArgumentException( 'Duplicate Elavira rule ID and version.' );
		}

		$this->rules[ $key ] = $rule;
	}

	/**
	 * Resolve an exact historical rule version.
	 *
	 * @param string $id      Stable rule ID.
	 * @param string $version Exact rule version.
	 * @return RuleDefinition
	 * @throws InvalidArgumentException When the rule or version is unsupported.
	 */
	public function get( $id, $version ) {
		$key = $this->key( $id, $version );

		if ( ! isset( $this->rules[ $key ] ) ) {
			throw new InvalidArgumentException( 'Unknown or unsupported Elavira rule version.' );
		}

		return $this->rules[ $key ];
	}

	/** Return all registered rule versions.
	 *
	 * @return RuleDefinition[]
	 */
	public function all() {
		return array_values( $this->rules );
	}

	/** Determine declared precedence for exact registered rule versions. @return bool */
	public function supersedes( $primary_id, $primary_version, $diagnostic_id ) {
		return in_array( $diagnostic_id, $this->get( $primary_id, $primary_version )->supersedes(), true );
	}

	/** Build the registry key.
	 *
	 * @param string $id      Rule ID.
	 * @param string $version Rule version.
	 * @return string
	 */
	private function key( $id, $version ) {
		return (string) $id . '@' . (string) $version;
	}
}
