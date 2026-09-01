<?php
/**
 * Browser evidence trust-boundary validation.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Browser;

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Rules\Evidence\EvidenceSchema;
use Elavira\Rules\Evidence\FieldDefinition;
use Elavira\Rules\RuleRegistry;
use Elavira\Rules\Values\EvaluationType;
use Elavira\Rules\Values\RuleOutcome;
use InvalidArgumentException;

/**
 * Rejects malformed or authority-claiming browser payloads before normalization.
 */
final class BrowserEvidenceValidator {
	const MAX_RESULT_BYTES = 32768;

	/** Trusted rule registry.
	 *
	 * @var RuleRegistry
	 */
	private $registry;

	/** Target metadata schema.
	 *
	 * @var EvidenceSchema
	 */
	private $target_schema;

	/** Element metadata schema.
	 *
	 * @var EvidenceSchema
	 */
	private $element_schema;

	/**
	 * Constructor.
	 *
	 * @param RuleRegistry $registry Trusted rule registry.
	 */
	public function __construct( RuleRegistry $registry ) {
		$this->registry       = $registry;
		$this->target_schema  = new EvidenceSchema(
			array(
				'selector'   => new FieldDefinition( FieldDefinition::TYPE_STRING, false, 512 ),
				'locator'    => new FieldDefinition( FieldDefinition::TYPE_STRING, false, 512 ),
				'tag_name'   => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 32 ),
				'element_id' => new FieldDefinition( FieldDefinition::TYPE_STRING, false, 191 ),
			),
			PersistenceValidator::TARGET_MAX_BYTES
		);
		$this->element_schema = new EvidenceSchema(
			array(
				'tag_name'            => new FieldDefinition( FieldDefinition::TYPE_STRING, true, 32 ),
				'role'                => new FieldDefinition( FieldDefinition::TYPE_STRING, false, 64 ),
				'input_type'          => new FieldDefinition( FieldDefinition::TYPE_STRING, false, 32 ),
				'has_accessible_name' => new FieldDefinition( FieldDefinition::TYPE_BOOLEAN ),
			),
			PersistenceValidator::ELEMENT_MAX_BYTES
		);
	}

	/**
	 * Validate one browser result against its exact registered rule version.
	 *
	 * @param mixed $payload Browser-submitted associative array.
	 * @return BrowserRuleResult
	 * @throws InvalidArgumentException When the result is malformed, unknown, or unauthorized by the contract.
	 */
	public function validate( $payload ) {
		if ( ! is_array( $payload ) ) {
			throw new InvalidArgumentException( 'Browser evidence must be a structured object.' );
		}

		$allowed  = array( 'rule_id', 'rule_version', 'outcome', 'target', 'evidence', 'element', 'grouping' );
		$required = array( 'rule_id', 'rule_version', 'outcome', 'target', 'evidence', 'element' );

		if ( array() !== array_diff( array_keys( $payload ), $allowed ) ) {
			throw new InvalidArgumentException( 'Browser evidence contains an unauthorized field.' );
		}

		if ( array() !== array_diff( $required, array_keys( $payload ) ) ) {
			throw new InvalidArgumentException( 'Browser evidence is incomplete.' );
		}

		PersistenceValidator::json( $payload, self::MAX_RESULT_BYTES, 'browser result' );

		if ( ! is_string( $payload['rule_id'] ) || ! is_string( $payload['rule_version'] ) ) {
			throw new InvalidArgumentException( 'Browser rule identity is invalid.' );
		}

		$rule     = $this->registry->get( $payload['rule_id'], $payload['rule_version'] );
		$outcome  = PersistenceValidator::one_of( $payload['outcome'], RuleOutcome::all(), 'rule outcome' );
		$grouping = $payload['grouping'] ?? array();

		$this->validate_outcome( $rule->evaluation_type(), $outcome );

		return new BrowserRuleResult(
			$rule,
			$outcome,
			$this->target_schema->validate( $payload['target'] ),
			$rule->evidence_schema()->validate( $payload['evidence'] ),
			$this->element_schema->validate( $payload['element'] ),
			$rule->grouping_schema()->validate( $grouping )
		);
	}

	/**
	 * Prevent review-only checks from claiming definitive failures.
	 *
	 * @param string $evaluation Evaluation type.
	 * @param string $outcome    Submitted outcome.
	 * @return void
	 * @throws InvalidArgumentException When the outcome conflicts with evaluation certainty.
	 */
	private function validate_outcome( $evaluation, $outcome ) {
		if ( RuleOutcome::PASS === $outcome ) {
			return;
		}

		if ( EvaluationType::AUTOMATIC_FAILURE === $evaluation && RuleOutcome::FAILURE !== $outcome ) {
			throw new InvalidArgumentException( 'Automatic rules require failure or pass outcomes.' );
		}

		if ( EvaluationType::AUTOMATIC_FAILURE !== $evaluation && RuleOutcome::REVIEW_REQUIRED !== $outcome ) {
			throw new InvalidArgumentException( 'Review rules cannot claim a definitive failure.' );
		}
	}
}
