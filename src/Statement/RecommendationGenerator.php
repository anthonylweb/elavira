<?php
/**
 * Advisory Statement recommendation generation.
 *
 * @package Elavira
 */

namespace Elavira\Statement;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment -- Concise domain comments are intentional.

use Elavira\Findings\Values\FindingStatus;
use Elavira\GuidedDecisions\DecisionRegistry;

/** Converts trusted completed-audit context into reviewable suggestions only. */
final class RecommendationGenerator {
	/** Dependencies. @var object */
	private $database;
	/** Recommendation persistence. @var RecommendationRepository */
	private $recommendations;
	/** Decision registry. @var DecisionRegistry */
	private $decisions;

	/** Create generator. @param object $database Database. @param RecommendationRepository $recommendations Repository. @param DecisionRegistry $decisions Registry. */
	public function __construct( $database, RecommendationRepository $recommendations, DecisionRegistry $decisions ) {
		$this->database        = $database;
		$this->recommendations = $recommendations;
		$this->decisions       = $decisions;
	}

	/** Generate idempotent recommendations for a completed audit. @param int $audit_id Audit. @return int */
	public function generate( $audit_id ) {
		$tables = new \Elavira\Core\Database\TableNames( $this->database->prefix );
		$rows   = $this->database->get_results( $this->database->prepare( "SELECT f.*, MIN(u.canonical_url) AS canonical_url FROM {$tables->findings()} f LEFT JOIN {$tables->audit_urls()} u ON u.audit_id=f.audit_id WHERE f.audit_id=%d GROUP BY f.id LIMIT 1000", absint( $audit_id ) ), ARRAY_A );
		$count  = 0;
		foreach ( $rows as $finding ) {
			if ( FindingStatus::REVIEWED_NOT_ISSUE === $finding['current_status'] || ( array_key_exists( 'wcag_criterion', $finding ) && '' === $finding['wcag_criterion'] ) ) {
				continue;
			}
			$decision = $this->latest_compatible_decision( $tables, $finding );
			if ( FindingStatus::ACCEPTED_LIMITATION !== $finding['current_status'] && 'detected_failure' !== $finding['category'] && ! $decision ) {
				continue;
			}
			$kind = ( $decision && 'needs_review' === $decision['recommended_status'] ) ? 'human_review' : 'known_limitation';
			/* translators: %s: the stored audit-time finding summary. */
			$text        = 'human_review' === $kind ? sprintf( __( '%s Further human review is still required before the organization makes a public accessibility assertion.', 'elavira' ), $finding['summary_snapshot'] ) : sprintf( __( '%s This limitation may affect some people using the website. The organization is reviewing appropriate remediation.', 'elavira' ), $finding['summary_snapshot'] );
			$fingerprint = hash( 'sha256', implode( '|', array( $finding['rule_id'], $finding['rule_version'], $finding['group_key'], $finding['canonical_url'] ?? '', $finding['current_status'], $decision['decision_version'] ?? '', $decision['outcome_id'] ?? '' ) ) );
			$this->recommendations->create_once(
				array(
					'audit_id'           => $audit_id,
					'finding_id'         => $finding['id'],
					'guided_decision_id' => $decision['id'] ?? 0,
					'source_fingerprint' => $fingerprint,
					'kind'               => $kind,
					'suggested_text'     => $text,
				)
			);
			++$count;
		}
		return $count;
	}

	/** Return latest completion only when its registered semantic decision supports the finding. @param object $tables Tables. @param array $finding Finding. @return array|null */
	private function latest_compatible_decision( $tables, array $finding ) {
		$row = $this->database->get_row( $this->database->prepare( "SELECT * FROM {$tables->guided_decisions()} WHERE finding_id=%d ORDER BY id DESC LIMIT 1", absint( $finding['id'] ) ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		try {
			$definition = $this->decisions->get( $row['decision_id'], $row['decision_version'] );
			if ( ! $definition->supports( $finding['rule_id'], $finding['rule_version'] ) ) {
				return null;
			}
			$row['recommended_status'] = $definition->outcome( $row['outcome_id'] )['status'];
			return $row;
		} catch ( \InvalidArgumentException $error ) {
			return null;
		}
	}
}
