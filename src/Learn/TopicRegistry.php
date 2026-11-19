<?php
/**
 * Learn topic registry.
 *
 * @package Elavira
 */

namespace Elavira\Learn;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment -- Concise registry comments are intentional.

use InvalidArgumentException;

/** Stable in-memory registry for bundled Learn content. */
final class TopicRegistry {
	/** Topics keyed by stable ID. @var TopicDefinition[] */
	private $topics = array();

	/** Register one topic. @param TopicDefinition $topic Topic. @return void */
	public function register( TopicDefinition $topic ) {
		$id = $topic->get( 'id' );
		if ( isset( $this->topics[ $id ] ) ) {
			throw new InvalidArgumentException( 'Duplicate Learn topic ID.' );
		}
		$this->topics[ $id ] = $topic;
	}

	/** Find a topic without throwing. @param string $id Stable ID. @return TopicDefinition|null */
	public function find( $id ) {
		return $this->topics[ (string) $id ] ?? null;
	}

	/** Return all topics. @return TopicDefinition[] */
	public function all() {
		return array_values( $this->topics );
	}

	/** Search trusted summary metadata. @param string $term Search term. @param string $category Category. @return TopicDefinition[] */
	public function search( $term = '', $category = '' ) {
		$term = strtolower( substr( trim( (string) $term ), 0, 100 ) );
		return array_values(
			array_filter(
				$this->topics,
				function ( TopicDefinition $topic ) use ( $term, $category ) {
					if ( '' !== $category && $category !== $topic->get( 'category' ) ) {
						return false;
					}
					$haystack = implode( ' ', array_merge( array( $topic->get( 'title' ), $topic->get( 'summary' ), $topic->get( 'category' ) ), $topic->get( 'keywords' ) ) );
					return '' === $term || false !== strpos( strtolower( $haystack ), $term );
				}
			)
		);
	}
}
