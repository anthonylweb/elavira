<?php
/**
 * Learn topic definition.
 *
 * @package Elavira
 */

namespace Elavira\Learn;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment -- Concise immutable model comments are intentional.

use InvalidArgumentException;

/** Immutable, server-owned educational content. */
final class TopicDefinition {
	/** Topic data. @var array */
	private $data;

	/** Validate a topic definition. @param array $data Topic data. */
	public function __construct( array $data ) {
		$data     = array_merge(
			array(
				'group'    => '',
				'examples' => array(),
			),
			$data
		);
		$required = array( 'id', 'version', 'title', 'summary', 'category', 'principle', 'wcag', 'sections', 'wordpress_guidance', 'developer_guidance', 'related_rules', 'related_topics', 'resources', 'keywords' );
		if ( array() !== array_diff( $required, array_keys( $data ) ) || ! preg_match( '/^[a-z][a-z0-9_]*$/', (string) ( $data['id'] ?? '' ) ) ) {
			throw new InvalidArgumentException( 'Invalid Learn topic definition.' );
		}
		foreach ( array( 'version', 'title', 'summary', 'category', 'principle' ) as $field ) {
			if ( '' === trim( (string) $data[ $field ] ) ) {
				throw new InvalidArgumentException( 'Learn topic metadata cannot be empty.' );
			}
		}
		foreach ( array( 'wcag', 'sections', 'wordpress_guidance', 'developer_guidance', 'related_rules', 'related_topics', 'resources', 'keywords', 'examples' ) as $field ) {
			if ( ! is_array( $data[ $field ] ) ) {
				throw new InvalidArgumentException( 'Learn topic collections must be arrays.' );
			}
		}
		$this->data = $data;
	}

	/** Get a field. @param string $key Field. @return mixed */
	public function get( $key ) {
		return $this->data[ $key ] ?? null;
	}
}
