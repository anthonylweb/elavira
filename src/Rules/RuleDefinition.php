<?php
/**
 * Accessibility rule definition.
 *
 * @package Elavira
 */

namespace Elavira\Rules;

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Findings\Values\Priority;
use Elavira\Rules\Evidence\EvidenceSchema;
use Elavira\Rules\Grouping\GroupingStrategy;
use Elavira\Rules\Values\EvaluationType;
use Elavira\Rules\Values\FindingClassification;
use InvalidArgumentException;

/**
 * Immutable trusted rule metadata and its browser evidence contract.
 */
final class RuleDefinition {
	/** Trusted rule data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param array $data Trusted rule definition.
	 * @throws InvalidArgumentException When metadata or contracts are invalid.
	 */
	public function __construct( array $data ) {
		$required = array(
			'id',
			'version',
			'editorial_version',
			'title',
			'description',
			'evaluation_type',
			'classification',
			'priority',
			'wcag',
			'summary',
			'why_it_matters',
			'recommendation',
			'wordpress_guidance',
			'developer_guidance',
			'evidence_schema',
			'grouping_schema',
			'grouping_strategy',
		);

		if ( array() !== array_diff( $required, array_keys( $data ) ) ) {
			throw new InvalidArgumentException( 'Rule definition is incomplete.' );
		}

		if ( ! preg_match( '/^elavira\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', (string) $data['id'] ) ) {
			throw new InvalidArgumentException( 'Invalid Elavira rule ID.' );
		}

		PersistenceValidator::identifier( $data['version'], 'rule version' );
		PersistenceValidator::identifier( $data['editorial_version'], 'editorial version' );
		PersistenceValidator::one_of( $data['evaluation_type'], EvaluationType::all(), 'evaluation type' );
		PersistenceValidator::one_of( $data['classification'], FindingClassification::all(), 'finding classification' );
		PersistenceValidator::one_of( $data['priority'], Priority::all(), 'priority' );
		$this->validate_classification( $data['evaluation_type'], $data['classification'] );

		if ( array() === $data['wcag'] ) {
			throw new InvalidArgumentException( 'A rule requires at least one WCAG mapping.' );
		}

		foreach ( $data['wcag'] as $mapping ) {
			if ( ! $mapping instanceof WcagMapping ) {
				throw new InvalidArgumentException( 'Invalid rule WCAG mapping.' );
			}
		}

		foreach ( array( 'title', 'description', 'summary', 'why_it_matters', 'recommendation', 'wordpress_guidance', 'developer_guidance' ) as $field ) {
			if ( '' === PersistenceValidator::text( $data[ $field ], 4096, $field, true ) ) {
				throw new InvalidArgumentException( 'Rule editorial content cannot be empty.' );
			}
		}

		if ( ! $data['evidence_schema'] instanceof EvidenceSchema || ! $data['grouping_schema'] instanceof EvidenceSchema ) {
			throw new InvalidArgumentException( 'Rule evidence schemas are invalid.' );
		}

		if ( ! $data['grouping_strategy'] instanceof GroupingStrategy ) {
			throw new InvalidArgumentException( 'Rule grouping strategy is invalid.' );
		}

		foreach ( array( 'learn_topic', 'guided_decision' ) as $optional ) {
			if ( isset( $data[ $optional ] ) && null !== $data[ $optional ] ) {
				PersistenceValidator::identifier( $data[ $optional ], $optional );
			}
		}

		$this->data = $data;
	}

	/** Get the rule ID. @return string */
	public function id() {
		return $this->data['id'];
	}

	/** Get the scanner contract version. @return string */
	public function version() {
		return $this->data['version'];
	}

	/** Get the editorial version. @return string */
	public function editorial_version() {
		return $this->data['editorial_version'];
	}

	/** Get the evaluation type. @return string */
	public function evaluation_type() {
		return $this->data['evaluation_type'];
	}

	/** Get the finding classification. @return string */
	public function classification() {
		return $this->data['classification'];
	}

	/** Get the default priority. @return string */
	public function priority() {
		return $this->data['priority'];
	}

	/** Get WCAG mappings. @return WcagMapping[] */
	public function wcag() {
		return $this->data['wcag'];
	}

	/** Get the evidence schema. @return EvidenceSchema */
	public function evidence_schema() {
		return $this->data['evidence_schema'];
	}

	/** Get the grouping-input schema. @return EvidenceSchema */
	public function grouping_schema() {
		return $this->data['grouping_schema'];
	}

	/** Get the grouping strategy. @return GroupingStrategy */
	public function grouping_strategy() {
		return $this->data['grouping_strategy'];
	}

	/**
	 * Return trusted editorial metadata.
	 *
	 * @return array
	 */
	public function editorial() {
		return array(
			'title'              => $this->data['title'],
			'description'        => $this->data['description'],
			'summary'            => $this->data['summary'],
			'why_it_matters'     => $this->data['why_it_matters'],
			'recommendation'     => $this->data['recommendation'],
			'wordpress_guidance' => $this->data['wordpress_guidance'],
			'developer_guidance' => $this->data['developer_guidance'],
			'learn_topic'        => $this->data['learn_topic'] ?? null,
			'guided_decision'    => $this->data['guided_decision'] ?? null,
		);
	}

	/**
	 * Validate that certainty and classification convey the same semantics.
	 *
	 * @param string $evaluation_type Evaluation type.
	 * @param string $classification  Finding classification.
	 * @return void
	 * @throws InvalidArgumentException When the pair is inconsistent.
	 */
	private function validate_classification( $evaluation_type, $classification ) {
		$pairs = array(
			EvaluationType::AUTOMATIC_FAILURE => FindingClassification::DETECTED_FAILURE,
			EvaluationType::POTENTIAL_ISSUE   => FindingClassification::POTENTIAL_ISSUE,
			EvaluationType::HUMAN_REVIEW      => FindingClassification::HUMAN_REVIEW,
		);

		if ( $pairs[ $evaluation_type ] !== $classification ) {
			throw new InvalidArgumentException( 'Rule evaluation and finding classification conflict.' );
		}
	}
}
