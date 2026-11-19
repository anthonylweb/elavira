<?php
/**
 * Validated browser rule result DTO.
 *
 * @package Elavira
 */

namespace Elavira\Rules\Browser;

use Elavira\Rules\RuleDefinition;

/**
 * Contains only validated scanner evidence and its trusted registered rule.
 */
final class BrowserRuleResult {
	/** Trusted registered rule.
	 *
	 * @var RuleDefinition
	 */
	private $rule;

	/** Validated outcome.
	 *
	 * @var string
	 */
	private $outcome;

	/** Validated target metadata.
	 *
	 * @var array
	 */
	private $target;

	/** Validated evidence.
	 *
	 * @var array
	 */
	private $evidence;

	/** Validated element metadata.
	 *
	 * @var array
	 */
	private $element;

	/** Validated grouping inputs.
	 *
	 * @var array
	 */
	private $grouping;

	/**
	 * Constructor.
	 *
	 * @param RuleDefinition $rule     Trusted registered rule.
	 * @param string         $outcome  Validated outcome.
	 * @param array          $target   Validated target metadata.
	 * @param array          $evidence Validated rule-specific evidence.
	 * @param array          $element  Validated element metadata.
	 * @param array          $grouping Validated grouping inputs.
	 */
	public function __construct( RuleDefinition $rule, $outcome, array $target, array $evidence, array $element, array $grouping ) {
		$this->rule     = $rule;
		$this->outcome  = $outcome;
		$this->target   = $target;
		$this->evidence = $evidence;
		$this->element  = $element;
		$this->grouping = $grouping;
	}

	/** Get the trusted rule. @return RuleDefinition */
	public function rule() {
		return $this->rule;
	}

	/** Get the outcome. @return string */
	public function outcome() {
		return $this->outcome;
	}

	/** Get target metadata. @return array */
	public function target() {
		return $this->target;
	}

	/** Get evidence. @return array */
	public function evidence() {
		return $this->evidence;
	}

	/** Get element metadata. @return array */
	public function element() {
		return $this->element;
	}

	/** Get grouping inputs. @return array */
	public function grouping() {
		return $this->grouping;
	}
}
