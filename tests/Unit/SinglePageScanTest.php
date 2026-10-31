<?php
/**
 * Core single-page scan service tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Audit\Persistence\AuditRepository;
use Elavira\Audit\Persistence\AuditUrlRepository;
use Elavira\Core\Capabilities\Capability;
use Elavira\Core\Database\TableNames;
use Elavira\Core\Migrations\CoreAuditSchema;
use Elavira\Core\Migrations\CoreScanSessionSchema;
use Elavira\Core\Migrations\ScanObservationSchema;
use Elavira\Findings\Persistence\FindingRepository;
use Elavira\Findings\Persistence\OccurrenceRepository;
use Elavira\Rules\Browser\BrowserEvidenceValidator;
use Elavira\Rules\CoreRuleRegistry;
use Elavira\Rules\Normalization\RuleResultNormalizer;
use Elavira\Rules\Grouping\SupersessionResolver;
use Elavira\Findings\Identity\ReviewedConditionIdentity;
use Elavira\Scanner\Application\CompleteScan;
use Elavira\Scanner\Application\StartSinglePageScan;
use Elavira\Scanner\Application\SubmitScanBatch;
use Elavira\Scanner\Persistence\ScanSessionRepository;
use Elavira\Scanner\Persistence\StagedObservationRepository;
use Elavira\Scanner\Security\CanonicalUrl;
use Elavira\Scanner\Security\SessionGuard;
use Elavira\Tests\Support\FakeWpdb;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SinglePageScanTest extends TestCase {
	private $database;
	private $sessions;
	private $start;
	private $submit;
	private $complete;
	private $guard;
	private $findings;
	private $audits;
	private $urls;
	private $occurrences;

	protected function setUp(): void {
		$this->database  = new FakeWpdb();
		$GLOBALS['wpdb'] = $this->database;
		( new CoreAuditSchema( $this->database ) )->up();
		( new CoreScanSessionSchema( $this->database ) )->up();
		( new \Elavira\Core\Migrations\RulesetFoundationSchema( $this->database ) )->up();
		( new ScanObservationSchema( $this->database ) )->up();
		$GLOBALS['elavira_test_current_user_id'] = 7;
		$GLOBALS['elavira_test_capabilities']    = array( Capability::RUN_AUDITS => true );
		$GLOBALS['elavira_test_url_objects']     = array();
		$this->audits                            = new AuditRepository( $this->database );
		$this->urls                              = new AuditUrlRepository( $this->database );
		$this->sessions                          = new ScanSessionRepository( $this->database );
		$this->guard                             = new SessionGuard( $this->database, $this->sessions, $this->audits, $this->urls );
		$rules                                   = CoreRuleRegistry::create();
		$this->findings                          = new FindingRepository( $this->database );
		$this->start                             = new StartSinglePageScan( $this->database, $this->audits, $this->urls, $this->sessions, $rules, new CanonicalUrl() );
		$occurrences                             = new OccurrenceRepository( $this->database );
		$this->occurrences                       = $occurrences;
		$staged                                  = new StagedObservationRepository( $this->database );
		$identity                                = new ReviewedConditionIdentity();
		$supersession                            = new SupersessionResolver( $rules, $identity );
		$this->submit                            = new SubmitScanBatch( $this->database, $this->sessions, $this->guard, new BrowserEvidenceValidator( $rules ), new RuleResultNormalizer(), $this->findings, $occurrences, $this->audits, $this->urls, null, $identity, $supersession, $staged );
		$this->complete                          = new CompleteScan( $this->database, $this->sessions, $this->guard, $this->audits, $this->urls, $this->findings, $occurrences, new \Elavira\Findings\Persistence\ReviewedExceptionRepository( $this->database ), $identity, $supersession, $staged );
	}

	public function test_creates_unpredictable_owner_bound_session_and_version_snapshot() {
		$first  = $this->token( $this->start->start( 'https://example.com/article/?b=2&a=1' ) );
		$second = $this->token( $this->start->start( 'https://example.com/article/' ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $first );
		$this->assertNotSame( $first, $second );
		$session = $this->sessions->find_by_token( $first );
		$this->assertSame( 7, (int) $session['owner_user_id'] );
		$this->assertSame( 'core-rules-1.6.0', $session['ruleset_version'] );
		$allowed = json_decode( $session['allowed_rules_json'], true );
		$this->assertSame( '2.0.0', $allowed['elavira.link.purpose_review'] );
		$this->assertSame( '2.0.0', $allowed['elavira.heading.structure_review'] );
		$this->assertSame( '2.0.0', $allowed['elavira.document.language_missing'] );
		$this->assertSame( '2.0.0', $allowed['elavira.document.invalid_language'] );
		$this->assertSame( '2.0.0', $allowed['elavira.links.identical_name_different_destination'] );
		$this->assertSame( '2.0.0', $allowed['elavira.form.accessible_name_missing'] );
		$this->assertSame( 'https://example.com/article/?a=1&b=2', $session['canonical_url'] );
	}

	public function test_rejects_missing_capability_cross_site_and_internal_targets() {
		$GLOBALS['elavira_test_capabilities'] = array();
		try {
			$this->start->start( 'https://example.com/' );
			$this->fail();
		} catch ( RuntimeException $error ) {
			$this->assertNotEmpty( $error->getMessage() ); }
		$GLOBALS['elavira_test_capabilities'][ Capability::RUN_AUDITS ] = true;
		foreach ( array( 'https://other.example/', 'ftp://example.com/file', 'https://example.com/wp-admin/', 'https://example.com/wp-login.php', 'https://example.com/wp-json/', 'https://example.com/wp-cron.php', 'https://example.com/xmlrpc.php' ) as $url ) {
			try {
				$this->start->start( $url );
				$this->fail();
			} catch ( InvalidArgumentException $error ) {
				$this->assertNotEmpty( $error->getMessage() ); }
		}
	}

	public function test_per_audit_target_override_is_validated_and_snapshotted() {
		$token   = $this->token( $this->start->start( 'https://example.com/page/', '2.2_A' ) );
		$session = $this->sessions->find_by_token( $token );
		$audit   = $this->audits->find( $session['audit_id'] );
		$this->assertSame( '2.2_A', $session['wcag_target'] );
		$this->assertSame( '2.2_A', $audit['wcag_target'] );
		$this->assertStringStartsWith( 'Single-page audit', $audit['label'] );
		$this->expectException( InvalidArgumentException::class );
		$this->start->start( 'https://example.com/page/', '2.2_AAA' );
	}

	public function test_rejects_a_known_private_wordpress_object() {
		$GLOBALS['elavira_test_url_objects']['https://example.com/private/'] = array(
			'id'     => 42,
			'status' => 'private',
		);
		$this->expectException( InvalidArgumentException::class );
		$this->start->start( 'https://example.com/private/' );
	}

	public function test_enforces_ownership_expiration_and_page_binding() {
		$token = $this->token( $this->start->start( 'https://example.com/page/' ) );
		$this->expectException( RuntimeException::class );
		$this->guard->authorize( $token, 8, 'https://example.com/page/' );
	}

	public function test_expired_session_is_rejected_and_marked_expired() {
		$token   = $this->token( $this->start->start( 'https://example.com/page/' ) );
		$session = $this->sessions->find_by_token( $token );
		$table   = ( new TableNames( $this->database->prefix ) )->scan_sessions();
		$this->database->tables[ $table ][ $session['id'] ]['expires_at'] = '2020-01-01 00:00:00';
		try {
			$this->guard->authorize( $token, 7 );
			$this->fail();
		} catch ( RuntimeException $error ) {
			$this->assertNotEmpty( $error->getMessage() ); }
		$this->assertSame( 'expired', $this->sessions->find_by_token( $token )['state'] );
	}

	public function test_persists_grouped_findings_and_deduplicates_retried_batch() {
		$token   = $this->token( $this->start->start( 'https://example.com/page/' ) );
		$payload = $this->image_result();
		$first   = $this->submit->submit( $token, 7, array( $payload, $payload ) );
		$second  = $this->submit->submit( $token, 7, array( $payload, $payload ) );
		$this->assertFalse( $first['duplicate'] );
		$this->assertTrue( $second['duplicate'] );
		$session = $this->sessions->find_by_token( $token );
		$this->assertCount( 0, $this->findings->find_by_audit( $session['audit_id'] ) );
		$this->complete->complete( $token, 7 );
		$findings = $this->findings->find_by_audit( $session['audit_id'] );
		$this->assertCount( 1, $findings );
		$this->assertSame( 2, (int) $findings[0]['occurrence_count'] );
		$this->assertSame( 1, (int) $findings[0]['affected_page_count'] );
	}

	public function test_rejects_rule_version_mismatch_and_batch_limit() {
		$token                  = $this->token( $this->start->start( 'https://example.com/' ) );
		$result                 = $this->image_result();
		$result['rule_version'] = '9.0.0';
		try {
			$this->submit->submit( $token, 7, array( $result ) );
			$this->fail();
		} catch ( InvalidArgumentException $error ) {
			$this->assertNotEmpty( $error->getMessage() ); }
		$this->expectException( InvalidArgumentException::class );
		$this->submit->submit( $token, 7, array_fill( 0, 51, $this->image_result() ) );
	}

	public function test_total_result_limit_fails_session_without_silent_truncation() {
		$token   = $this->token( $this->start->start( 'https://example.com/' ) );
		$session = $this->sessions->find_by_token( $token );
		$this->sessions->update( $session['id'], array( 'total_results' => 999 ) );
		try {
			$this->submit->submit( $token, 7, array( $this->image_result(), $this->image_result() ) );
			$this->fail();
		} catch ( RuntimeException $error ) {
			$this->assertNotEmpty( $error->getMessage() ); }
		$this->assertSame( 'failed', $this->sessions->find_by_token( $token )['state'] );
		$this->assertSame( 'result_limit_exceeded', $this->sessions->find_by_token( $token )['failure_code'] );
		$session = $this->sessions->find_by_token( $token );
		$this->assertSame( 'failed', $this->audits->find( $session['audit_id'] )['status'] );
		$this->assertSame( 'failed', $this->urls->find_by_audit( $session['audit_id'] )[0]['status'] );
	}

	public function test_partial_failure_rolls_back_and_retry_does_not_inflate_counts() {
		$token                            = $this->token( $this->start->start( 'https://example.com/' ) );
		$this->database->fail_next_insert = true;
		try {
			$this->submit->submit( $token, 7, array( $this->image_result() ) );
			$this->fail();
		} catch ( RuntimeException $error ) {
			$this->assertNotEmpty( $error->getMessage() ); }
		$this->submit->submit( $token, 7, array( $this->image_result() ) );
		$session = $this->sessions->find_by_token( $token );
		$this->complete->complete( $token, 7 );
		$this->assertSame( 1, (int) $this->findings->find_by_audit( $session['audit_id'] )[0]['occurrence_count'] );
	}

	public function test_completion_is_explicit_idempotent_and_blocks_more_submissions() {
		$token = $this->token( $this->start->start( 'https://example.com/' ) );
		$this->submit->submit( $token, 7, array( $this->image_result() ) );
		$this->assertFalse( $this->complete->complete( $token, 7 )['duplicate'] );
		$this->assertTrue( $this->complete->complete( $token, 7 )['duplicate'] );
		$this->expectException( RuntimeException::class );
		$this->submit->submit( $token, 7, array( $this->image_result() ) );
	}

	public function test_cross_batch_supersession_occurs_only_at_completion() {
		$token  = $this->token( $this->start->start( 'https://example.com/' ) );
		$target = array(
			'selector' => 'main input#email',
			'tag_name' => 'input',
		);
		$this->submit->submit(
			$token,
			7,
			array(
				array(
					'rule_id'      => 'elavira.aria.broken_id_reference',
					'rule_version' => '1.0.0',
					'outcome'      => 'potential_issue',
					'target'       => $target,
					'evidence'     => array(
						'attribute_name'  => 'aria-labelledby',
						'reference_state' => 'missing',
						'token_count'     => 1,
					),
					'element'      => array( 'tag_name' => 'input' ),
					'grouping'     => array(),
				),
			)
		);
		$this->submit->submit(
			$token,
			7,
			array(
				array(
					'rule_id'      => 'elavira.form.accessible_name_missing',
					'rule_version' => '2.0.0',
					'outcome'      => 'automatic_failure',
					'target'       => $target,
					'evidence'     => array(
						'control_type' => 'input',
						'name_state'   => 'missing',
					),
					'element'      => array(
						'tag_name'            => 'input',
						'input_type'          => 'text',
						'has_accessible_name' => false,
					),
					'grouping'     => array(),
				),
			)
		);
		$session = $this->sessions->find_by_token( $token );
		$this->assertCount( 0, $this->findings->find_by_audit( $session['audit_id'] ) );
		$this->complete->complete( $token, 7 );
		$findings = $this->findings->find_by_audit( $session['audit_id'] );
		$this->assertCount( 1, $findings );
		$this->assertSame( 'elavira.form.accessible_name_missing', $findings[0]['rule_id'] );
		$occurrences = $this->occurrences->find_by_finding( $findings[0]['id'] );
		$this->assertStringContainsString( 'elavira.aria.broken_id_reference', $occurrences[0]['diagnostic_json'] );
	}

	public function test_image_supersession_uses_complete_staged_page_results() {
		$token  = $this->token( $this->start->start( 'https://example.com/' ) );
		$target = array(
			'selector' => 'main img#hero',
			'tag_name' => 'img',
		);
		$this->submit->submit( $token, 7, array( $this->image_review_result( 'elavira.images.alt_quality_review', 'human_review', $target ) ) );
		$filename = $this->image_review_result( 'elavira.images.filename_alt', 'potential_issue', $target );
		unset( $filename['evidence']['review_reason'] );
		$filename['evidence']['pattern'] = 'image_extension';
		$this->submit->submit( $token, 7, array( $filename ) );
		$session = $this->sessions->find_by_token( $token );
		$this->assertCount( 0, $this->findings->find_by_audit( $session['audit_id'] ) );
		$this->complete->complete( $token, 7 );
		$findings = $this->findings->find_by_audit( $session['audit_id'] );
		$this->assertCount( 1, $findings );
		$this->assertSame( 'elavira.images.filename_alt', $findings[0]['rule_id'] );
		$this->assertStringContainsString( 'elavira.images.alt_quality_review', $this->occurrences->find_by_finding( $findings[0]['id'] )[0]['diagnostic_json'] );
	}

	public function test_finalization_retry_rolls_back_partial_findings() {
		$token = $this->token( $this->start->start( 'https://example.com/' ) );
		$this->submit->submit( $token, 7, array( $this->image_result() ) );
		$this->database->fail_next_insert = true;
		try {
			$this->complete->complete( $token, 7 );
			$this->fail();
		} catch ( RuntimeException $error ) {
			$this->assertNotEmpty( $error->getMessage() ); }
		$session = $this->sessions->find_by_token( $token );
		$this->assertCount( 0, $this->findings->find_by_audit( $session['audit_id'] ) );
		$this->assertFalse( $this->complete->complete( $token, 7 )['duplicate'] );
		$this->assertCount( 1, $this->findings->find_by_audit( $session['audit_id'] ) );
	}


	public function test_occurrence_workflow_across_audits_and_reset() {
		$target                      = array(
			'identity_version' => 2,
			'element_id'       => 'hero',
			'selector'         => 'main img#hero',
			'tag_name'         => 'img',
		);
		$one                         = $this->image_review_result( 'elavira.images.alt_quality_review', 'human_review', $target );
		$two                         = $one;
		$two['target']['element_id'] = 'footer';
		$two['target']['selector']   = 'footer img#footer';
		$scan                        = function ( $results ) {
			$token = $this->token( $this->start->start( 'https://example.com/' ) );
			$this->submit->submit( $token, 7, $results );
			$completed = $this->complete->complete( $token, 7 );
			return $this->occurrences->find_by_audit( $completed['audit_id'] );
		};
		$rows                        = $scan( array( $one, $two ) );
		$this->assertCount( 2, $rows );
		$review = $this->occurrences->reviews();
		$review->update( $rows[0]['finding_id'], $rows[0]['id'], 'reviewed_not_issue', 'Context reviewed', 7 );
		$review->update( $rows[1]['finding_id'], $rows[1]['id'], 'addressed', 'Work performed', 7 );
		$one['target']['selector'] = 'section:nth-of-type(2)>img#hero';
		$rows                      = $scan( array( $one, $two ) );
		$this->assertSame( array( 'reviewed_not_issue', 'addressed' ), array_column( $rows, 'current_status' ) );
		$this->assertGreaterThan( 0, $rows[0]['previous_occurrence_id'] );
		$this->assertGreaterThan( 0, $rows[0]['reviewed_exception_id'] );
		$this->assertCount( 1, $scan( array( $two ) ) ); // Absent accepted occurrences are not resurrected.
		( new \Elavira\Findings\Persistence\ReviewedExceptionRepository( $this->database ) )->reset_all( 7 );
		$rows = $scan( array( $one, $two ) );
		$this->assertSame( 'open', $rows[0]['current_status'] );
		$this->assertSame( 'addressed', $rows[1]['current_status'] );
	}

	public function test_ambiguous_current_matches_do_not_inherit_acceptance() {
		$one   = $this->image_review_result(
			'elavira.images.alt_quality_review',
			'human_review',
			array(
				'identity_version' => 2,
				'element_id'       => 'duplicate',
				'selector'         => 'main img',
				'tag_name'         => 'img',
			)
		);
		$token = $this->token( $this->start->start( 'https://example.com/' ) );
		$this->submit->submit( $token, 7, array( $one ) );
		$audit = $this->complete->complete( $token, 7 );
		$row   = $this->occurrences->find_by_audit( $audit['audit_id'] )[0];
		$this->occurrences->reviews()->update( $row['finding_id'], $row['id'], 'reviewed_not_issue', '', 7 );
		$two                       = $one;
		$two['target']['selector'] = 'footer img';
		$token                     = $this->token( $this->start->start( 'https://example.com/' ) );
		$this->submit->submit( $token, 7, array( $one, $two ) );
		$audit = $this->complete->complete( $token, 7 );
		$rows  = $this->occurrences->find_by_audit( $audit['audit_id'] );
		$this->assertSame( array( 'open', 'open' ), array_column( $rows, 'current_status' ) );
	}

	public function test_missing_url_does_not_create_audit_graph() {
		$GLOBALS['elavira_test_http_responses'] = array( array( 'response' => array( 'code' => 404 ) ) );
		try {
			$this->start->start( 'https://example.com/missing' );
			$this->fail( 'Expected missing-page rejection' ); } catch ( RuntimeException $error ) {
			$this->assertSame( 'page_missing', $error->getMessage() ); }
			$this->assertCount( 0, $this->database->tables['wp_elavira_audits'] );
	}

	private function token( array $started ) {
		parse_str( wp_parse_url( $started['scan_url'], PHP_URL_QUERY ), $query );
		return $query['elavira_scan']; }
	private function image_result() {
		return array(
			'rule_id'      => 'elavira.image.alt_missing',
			'rule_version' => '1.0.0',
			'outcome'      => 'failure',
			'target'       => array(
				'selector' => 'main img',
				'tag_name' => 'img',
			),
			'evidence'     => array( 'alt_state' => 'missing' ),
			'element'      => array( 'tag_name' => 'img' ),
			'grouping'     => array(),
		); }

	private function image_review_result( $rule_id, $outcome, array $target ) {
		return array(
			'rule_id'      => $rule_id,
			'rule_version' => '1.0.0',
			'outcome'      => $outcome,
			'target'       => $target,
			'evidence'     => array(
				'alt_excerpt'     => 'hero-banner.png',
				'alt_fingerprint' => '1234abcd',
				'context'         => 'functional_link',
				'review_reason'   => 'functional_context',
			),
			'element'      => array( 'tag_name' => 'img' ),
			'grouping'     => array(),
		);
	}
}
