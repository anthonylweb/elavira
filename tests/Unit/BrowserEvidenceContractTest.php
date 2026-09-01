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
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BrowserEvidenceContractTest extends TestCase {
	private $validator;
	private $normalizer;

	protected function setUp(): void {
		$this->validator  = new BrowserEvidenceValidator( CoreRuleRegistry::create() );
		$this->normalizer = new RuleResultNormalizer();
	}

	public function test_automatic_failure_uses_only_registered_trusted_metadata() {
		$normalized = $this->normalizer->normalize( $this->validator->validate( $this->image_payload() ) )->to_array();

		$this->assertTrue( $normalized['creates_finding'] );
		$this->assertSame( FindingClassification::DETECTED_FAILURE, $normalized['classification'] );
		$this->assertSame( Priority::HIGH, $normalized['priority'] );
		$this->assertSame( ReviewState::NOT_REQUIRED, $normalized['review_state'] );
		$this->assertSame( '1.1.1', $normalized['wcag_criterion'] );
		$this->assertSame( 'Images are missing alternative text attributes.', $normalized['editorial']['summary'] );
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
