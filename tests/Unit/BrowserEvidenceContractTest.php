<?php
/**
 * Browser evidence contract tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Findings\Values\Priority;
use Elavira\Findings\Values\ReviewState;
use Elavira\Rules\Browser\BrowserEvidenceValidator;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Rules\Normalization\RuleResultNormalizer;
use Elavira\Rules\Values\FindingClassification;
use Elavira\Rules\Values\RuleOutcome;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BrowserEvidenceContractTest extends TestCase {
	private $validator;
	private $normalizer;

	protected function setUp(): void {
		$this->validator  = new BrowserEvidenceValidator( CoreRuleRegistry::create() );
		$this->normalizer = new RuleResultNormalizer();
	}

	public function test_actual_element_identification_is_bounded_and_preserved_as_text() {
		$payload                               = $this->image_payload();
		$payload['target']['element_id']       = 'actual<id>"';
		$payload['target']['element_classes']  = 'elavira-qa-empty-link <svg/onload=alert(1)>';
		$payload['target']['identity_version'] = 2;
		$result                                = $this->validator->validate( $payload );
		$this->assertSame( $payload['target']['element_id'], $result->target()['element_id'] );
		$this->assertSame( $payload['target']['element_classes'], $result->target()['element_classes'] );
		$payload['target']['element_classes'] = str_repeat( 'x', 2049 );
		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function test_expanded_rule_result_vocabulary_is_complete() {
		$this->assertSame( array( RuleOutcome::PASS, RuleOutcome::AUTOMATIC_FAILURE, RuleOutcome::POTENTIAL_ISSUE, RuleOutcome::HUMAN_REVIEW, RuleOutcome::NOT_EVALUATED ), RuleOutcome::all() );
	}

	public function test_optional_identification_survives_normalization_without_changing_outcome() {
		$payload             = $this->image_payload();
		$payload['element'] += array(
			'image_filename'   => 'banner.png',
			'image_alt_state'  => 'missing',
			'link_destination' => 'https://example.org/contact/',
		);
		$result              = $this->normalizer->normalize( $this->validator->validate( $payload ) )->to_array();
		ksort( $payload['element'] );
		ksort( $result['element'] );
		$this->assertSame( $payload['element'], $result['element'] );
		$this->assertSame( RuleOutcome::AUTOMATIC_FAILURE, $result['outcome'] );
	}

	public function test_identification_rejects_sensitive_or_unsafe_destinations() {
		foreach ( array( 'javascript:alert(1)', 'https://example.org/?token=secret', 'https://name:secret@example.org/path', 'https://example.org/#private' ) as $url ) {
			$payload                                = $this->image_payload();
			$payload['element']['link_destination'] = $url;
			try {
				$this->validator->validate( $payload );
				$this->fail( 'Unsafe identification destination accepted.' );
			} catch ( InvalidArgumentException $error ) {
				$this->assertStringContainsString( 'minimized HTTP URLs', $error->getMessage() );
			}
		}
	}

	public function test_identification_size_and_unknown_fields_remain_bounded() {
		$payload                                 = $this->image_payload();
		$payload['element']['image_alt_excerpt'] = str_repeat( 'x', 1025 );
		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function test_automatic_failure_uses_only_registered_trusted_metadata() {
		$normalized = $this->normalizer->normalize( $this->validator->validate( $this->image_payload() ) )->to_array();

		$this->assertTrue( $normalized['creates_finding'] );
		$this->assertSame( FindingClassification::DETECTED_FAILURE, $normalized['classification'] );
		$this->assertSame( RuleOutcome::AUTOMATIC_FAILURE, $normalized['outcome'] );
		$this->assertSame( Priority::HIGH, $normalized['priority'] );
		$this->assertSame( ReviewState::NOT_REQUIRED, $normalized['review_state'] );
		$this->assertSame( '1.1.1', $normalized['wcag_criterion'] );
		$this->assertSame( 'Elavira found an image without an alt attribute, the place where HTML supplies its text alternative.', $normalized['editorial']['summary'] );
	}

	public function test_review_rule_normalizes_as_review_not_failure_or_severity() {
		$payload    = array(
			'rule_id'      => 'elavira.link.purpose_review',
			'rule_version' => '1.0.0',
			'outcome'      => 'review_required',
			'target'       => array(
				'tag_name' => 'a',
				'selector' => 'main a:nth-of-type(2)',
			),
			'evidence'     => array(
				'text_state'       => 'generic',
				'destination_kind' => 'internal',
			),
			'element'      => array(
				'tag_name'            => 'a',
				'role'                => 'link',
				'has_accessible_name' => true,
			),
			'grouping'     => array( 'text_state' => 'generic' ),
		);
		$normalized = $this->normalizer->normalize( $this->validator->validate( $payload ) )->to_array();

		$this->assertSame( FindingClassification::POTENTIAL_ISSUE, $normalized['classification'] );
		$this->assertSame( RuleOutcome::POTENTIAL_ISSUE, $normalized['outcome'] );
		$this->assertSame( ReviewState::NEEDS_REVIEW, $normalized['review_state'] );
		$this->assertSame( Priority::MEDIUM, $normalized['priority'] );
	}

	public function test_server_generates_grouping_key_from_validated_inputs() {
		$payload = array(
			'rule_id'      => 'elavira.form.accessible_name_missing',
			'rule_version' => '1.0.0',
			'outcome'      => 'failure',
			'target'       => array(
				'tag_name' => 'input',
				'selector' => '#email',
			),
			'evidence'     => array(
				'control_type'          => 'input',
				'accessible_name_state' => 'missing',
			),
			'element'      => array(
				'tag_name'            => 'input',
				'input_type'          => 'email',
				'has_accessible_name' => false,
			),
			'grouping'     => array( 'control_type' => 'input' ),
		);
		$result  = $this->normalizer->normalize( $this->validator->validate( $payload ) );

		$this->assertStringStartsWith( 'rule:elavira.form.accessible_name_missing:', $result->grouping_key() );
		$this->assertLessThanOrEqual( 191, strlen( $result->grouping_key() ) );
	}

	public function test_pass_result_does_not_create_a_finding() {
		$payload            = $this->image_payload();
		$payload['outcome'] = 'pass';
		$result             = $this->normalizer->normalize( $this->validator->validate( $payload ) );

		$this->assertFalse( $result->creates_finding() );
		$this->assertNull( $result->grouping_key() );
	}

	public function test_not_evaluated_is_valid_and_does_not_create_a_finding() {
		$payload            = $this->image_payload();
		$payload['outcome'] = RuleOutcome::NOT_EVALUATED;
		$result             = $this->normalizer->normalize( $this->validator->validate( $payload ) );
		$this->assertFalse( $result->creates_finding() );
		$this->assertSame( RuleOutcome::NOT_EVALUATED, $result->to_array()['outcome'] );
	}

	public function test_canonical_outcome_must_match_registered_evaluation_type() {
		$payload            = $this->image_payload();
		$payload['outcome'] = RuleOutcome::HUMAN_REVIEW;
		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	/**
	 * Provide unsupported rule identities.
	 *
	 * @dataProvider invalid_identity_provider
	 * @param string $rule_id Rule ID.
	 * @param string $version Rule version.
	 */
	public function test_rejects_unknown_rule_or_version( $rule_id, $version ) {
		$payload                 = $this->image_payload();
		$payload['rule_id']      = $rule_id;
		$payload['rule_version'] = $version;

		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function invalid_identity_provider() {
		return array(
			'unknown ID'      => array( 'elavira.unknown.rule', '1.0.0' ),
			'unknown version' => array( 'elavira.image.alt_missing', '9.0.0' ),
		);
	}

	/**
	 * Provide browser attempts to assert trusted metadata.
	 *
	 * @dataProvider unauthorized_metadata_provider
	 * @param string $field Metadata field.
	 * @param mixed  $value Metadata value.
	 */
	public function test_rejects_browser_submitted_trusted_metadata( $field, $value ) {
		$payload           = $this->image_payload();
		$payload[ $field ] = $value;

		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function unauthorized_metadata_provider() {
		return array(
			'priority'       => array( 'priority', 'low' ),
			'WCAG mapping'   => array( 'wcag', array( 'criterion' => '9.9.9' ) ),
			'recommendation' => array( 'recommendation', 'Trust me' ),
			'group key'      => array( 'group_key', 'browser-controlled' ),
		);
	}

	public function test_rejects_rule_specific_unknown_or_invalid_evidence() {
		$payload                     = $this->image_payload();
		$payload['evidence']['role'] = 'presentation';

		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function test_rejects_sensitive_evidence_recursively() {
		$payload                         = $this->image_payload();
		$payload['evidence']['password'] = 'secret';

		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function test_rejects_oversized_payload() {
		$payload                       = $this->image_payload();
		$payload['target']['selector'] = str_repeat( 'x', BrowserEvidenceValidator::MAX_RESULT_BYTES );

		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function test_review_rule_cannot_claim_definitive_failure() {
		$payload = array(
			'rule_id'      => 'elavira.heading.structure_review',
			'rule_version' => '1.0.0',
			'outcome'      => 'failure',
			'target'       => array(
				'tag_name' => 'h3',
				'selector' => 'main h3',
			),
			'evidence'     => array(
				'current_level'  => 3,
				'previous_level' => 1,
				'issue'          => 'skipped_level',
			),
			'element'      => array( 'tag_name' => 'h3' ),
			'grouping'     => array( 'issue' => 'skipped_level' ),
		);

		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function test_server_recalculates_contrast_and_controls_threshold() {
		$payload = $this->contrast_payload();
		$result  = $this->validator->validate( $payload );
		$this->assertSame( RuleOutcome::AUTOMATIC_FAILURE, $result->outcome() );
		$payload['evidence']['required_milli'] = 3000;
		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function test_server_rejects_tampered_contrast_ratio_and_size_classification() {
		$payload                            = $this->contrast_payload();
		$payload['evidence']['ratio_milli'] = 9000;
		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function test_server_rejects_browser_selected_large_text_classification() {
		$payload                               = $this->contrast_payload();
		$payload['rule_id']                    = 'elavira.contrast.large_text_insufficient';
		$payload['evidence']['required_milli'] = 3000;
		$payload['evidence']['text_size']      = 'large';
		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function test_image_filename_evidence_is_server_checked() {
		$payload                        = $this->image_review_payload( 'elavira.images.filename_alt', 'potential_issue' );
		$payload['evidence']['pattern'] = 'image_extension';
		$result                         = $this->validator->validate( $payload );
		$this->assertSame( RuleOutcome::POTENTIAL_ISSUE, $result->outcome() );
		$payload['evidence']['alt_excerpt'] = 'A team meeting';
		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function test_long_alt_metrics_are_bounded_by_server() {
		$payload                              = $this->image_review_payload( 'elavira.images.long_alt_review', 'human_review' );
		$payload['evidence']['alt_length']    = 179;
		$payload['evidence']['word_count']    = 40;
		$payload['evidence']['review_reason'] = 'extended_multi_sentence';
		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	public function test_image_browser_cannot_author_quality_conclusion() {
		$payload                              = $this->image_review_payload( 'elavira.images.alt_quality_review', 'human_review' );
		$payload['evidence']['review_reason'] = 'functional_context';
		$payload['evidence']['context']       = 'functional_link';
		$payload['evidence']['alt_is_bad']    = true;
		$this->expectException( InvalidArgumentException::class );
		$this->validator->validate( $payload );
	}

	private function contrast_payload() {
		return array(
			'rule_id'      => 'elavira.contrast.text_insufficient',
			'rule_version' => '1.0.0',
			'outcome'      => 'automatic_failure',
			'target'       => array(
				'tag_name' => 'p',
				'selector' => '#copy',
			),
			'evidence'     => array(
				'foreground'      => '#777777',
				'background'      => '#ffffff',
				'ratio_milli'     => 4478,
				'required_milli'  => 4500,
				'text_size'       => 'normal',
				'font_size_milli' => 16000,
				'font_weight'     => 400,
				'determination'   => 'opaque',
				'rendered_state'  => 'current',
			),
			'element'      => array( 'tag_name' => 'p' ),
			'grouping'     => array(),
		);
	}

	private function image_review_payload( $rule_id, $outcome ) {
		return array(
			'rule_id'      => $rule_id,
			'rule_version' => '1.0.0',
			'outcome'      => $outcome,
			'target'       => array(
				'tag_name' => 'img',
				'selector' => '#hero',
			),
			'evidence'     => array(
				'alt_excerpt'     => 'hero-banner.png',
				'alt_fingerprint' => '1234abcd',
				'context'         => 'standalone',
			),
			'element'      => array( 'tag_name' => 'img' ),
			'grouping'     => array(),
		);
	}

	private function image_payload() {
		return array(
			'rule_id'      => 'elavira.image.alt_missing',
			'rule_version' => '1.0.0',
			'outcome'      => 'failure',
			'target'       => array(
				'tag_name' => 'img',
				'selector' => 'main img:first-child',
			),
			'evidence'     => array( 'alt_state' => 'missing' ),
			'element'      => array(
				'tag_name'            => 'img',
				'has_accessible_name' => false,
			),
			'grouping'     => array(),
		);
	}
}
