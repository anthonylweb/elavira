<?php
/**
 * Browser evidence trust-boundary validation.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Browser;

// phpcs:disable Squiz.Commenting.FunctionComment

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Rules\Evidence\EvidenceSchema;
use Elavira\Rules\Evidence\FieldDefinition;
use Elavira\Rules\ContrastMath;
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
		$outcome  = PersistenceValidator::one_of( $payload['outcome'], RuleOutcome::accepted(), 'rule outcome' );
		$grouping = $payload['grouping'] ?? array();
		$outcome  = $this->canonical_outcome( $rule->evaluation_type(), $outcome );

		$this->validate_outcome( $rule->evaluation_type(), $outcome );

		$evidence = $rule->evidence_schema()->validate( $payload['evidence'] );
		if ( 0 === strpos( $rule->id(), 'elavira.images.' ) ) {
			$this->validate_image_evidence( $rule->id(), $evidence );
		}
		if ( 0 === strpos( $rule->id(), 'elavira.contrast.' ) && 'elavira.contrast.cannot_determine' !== $rule->id() ) {
			$required = 'elavira.contrast.text_insufficient' === $rule->id() ? 4500 : 3000;
			if ( $required !== $evidence['required_milli'] ) {
				throw new InvalidArgumentException( 'Contrast threshold does not match the trusted rule definition.' );
			}
			if ( isset( $evidence['font_size_milli'] ) ) {
				$size   = $evidence['font_size_milli'];
				$weight = $evidence['font_weight'];
				if ( $size < 1 || $size > 1000000 || $weight < 1 || $weight > 1000 ) {
					throw new InvalidArgumentException( 'Text contrast metrics are outside accepted bounds.' );
				}
				$is_large = $size >= 24000 || ( $size >= 18667 && $weight >= 700 );
				if ( ( 'elavira.contrast.large_text_insufficient' === $rule->id() ) !== $is_large ) {
					throw new InvalidArgumentException( 'Text contrast classification is inconsistent.' );
				}
			}
			$calculated = ( new ContrastMath() )->ratio_milli( $evidence['foreground'], $evidence['background'] );
			if ( abs( $calculated - $evidence['ratio_milli'] ) > 1 ) {
				throw new InvalidArgumentException( 'Contrast evidence is inconsistent.' );
			}
			$evidence['ratio_milli'] = $calculated;
			$outcome                 = $calculated < $evidence['required_milli'] ? RuleOutcome::AUTOMATIC_FAILURE : RuleOutcome::PASS;
		}

		return new BrowserRuleResult(
			$rule,
			$outcome,
			$this->target_schema->validate( $payload['target'] ),
			$evidence,
			$this->element_schema->validate( $payload['element'] ),
			$rule->grouping_schema()->validate( $grouping )
		);
	}

	/**
	 * Validate bounded image heuristics independently of browser conclusions.
	 *
	 * @throws InvalidArgumentException When image evidence conflicts with its contract.
	 */
	private function validate_image_evidence( $rule_id, array $evidence ) {
		if ( ! preg_match( '/^[0-9a-f]{8}$/', $evidence['alt_fingerprint'] ) ) {
			throw new InvalidArgumentException( 'Image evidence fingerprint is invalid.' );
		}
		if ( 'elavira.images.filename_alt' === $rule_id ) {
			$camera = (bool) preg_match( '/^(?:img|dsc)[_-]?\d{3,}\.(?:avif|gif|jpe?g|png|svg|webp)$/i', $evidence['alt_excerpt'] );
			$file   = (bool) preg_match( '~^[^\s\\/]{1,100}\.(?:avif|gif|jpe?g|png|svg|webp)$~i', $evidence['alt_excerpt'] );
			if ( ( 'camera_filename' === $evidence['pattern'] && ! $camera ) || ( 'image_extension' === $evidence['pattern'] && ( ! $file || $camera ) ) ) {
				throw new InvalidArgumentException( 'Filename-style alternative evidence is inconsistent.' );
			}
		}
		if ( 'elavira.images.long_alt_review' === $rule_id && ( $evidence['alt_length'] < 180 || $evidence['alt_length'] > 10000 || $evidence['word_count'] < 30 || $evidence['word_count'] > 10000 ) ) {
			throw new InvalidArgumentException( 'Extended alternative evidence is outside accepted review bounds.' );
		}
		if ( 'elavira.images.alt_quality_review' === $rule_id ) {
			$functional = in_array( $evidence['context'], array( 'functional_link', 'functional_control' ), true );
			if ( ( 'functional_context' === $evidence['review_reason'] ) !== $functional || ( 'caption_context' === $evidence['review_reason'] ) !== ( 'figure_caption' === $evidence['context'] ) ) {
				throw new InvalidArgumentException( 'Image review context is inconsistent.' );
			}
		}
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
		if ( in_array( $outcome, array( RuleOutcome::PASS, RuleOutcome::NOT_EVALUATED ), true ) ) {
			return;
		}

		if ( EvaluationType::AUTOMATIC_FAILURE === $evaluation && RuleOutcome::AUTOMATIC_FAILURE !== $outcome ) {
			throw new InvalidArgumentException( 'Automatic rules require failure or pass outcomes.' );
		}

		if ( EvaluationType::POTENTIAL_ISSUE === $evaluation && RuleOutcome::POTENTIAL_ISSUE !== $outcome ) {
			throw new InvalidArgumentException( 'Potential-issue rules require a potential-issue outcome.' );
		}

		if ( EvaluationType::HUMAN_REVIEW === $evaluation && RuleOutcome::HUMAN_REVIEW !== $outcome ) {
			throw new InvalidArgumentException( 'Review rules cannot claim a definitive failure.' );
		}
	}

	/** Translate only the two documented pre-expansion transport values. */
	private function canonical_outcome( $evaluation, $outcome ) {
		if ( RuleOutcome::LEGACY_FAILURE === $outcome ) {
			return RuleOutcome::AUTOMATIC_FAILURE;
		}
		if ( RuleOutcome::LEGACY_REVIEW_REQUIRED === $outcome ) {
			return EvaluationType::POTENTIAL_ISSUE === $evaluation ? RuleOutcome::POTENTIAL_ISSUE : RuleOutcome::HUMAN_REVIEW;
		}
		return $outcome;
	}
}
