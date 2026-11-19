<?php
/**
 * Structured Statement persistence.
 *
 * @package Elavira
 */

namespace Elavira\Statement;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag -- Concise repository comments are intentional.

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Core\Database\Repository;
use RuntimeException;

/** Persists one site-owned Statement draft and immutable published revisions. */
final class StatementRepository extends Repository {
	/** Find the site Statement. @return array|null */
	public function find() {
		return $this->database->get_row( $this->database->prepare( "SELECT * FROM {$this->tables->statements()} WHERE id > %d ORDER BY id ASC LIMIT 1", 0 ), ARRAY_A );
	}

	/** Find a published Statement by associated post. @param int $post_id Post ID. @return array|null */
	public function find_published_by_post( $post_id ) {
		return $this->database->get_row( $this->database->prepare( "SELECT * FROM {$this->tables->statements()} WHERE post_id = %d AND status = %s", absint( $post_id ), 'published' ), ARRAY_A );
	}

	/** Create the initial user-reviewed draft. @param array $fields Fields. @param int $owner Owner. @return int */
	public function create( array $fields, $owner ) {
		if ( $this->find() ) {
			throw new RuntimeException( 'An Accessibility Statement already exists.' );
		}
		$now = $this->now();
		return $this->insert(
			$this->tables->statements(),
			array(
				'post_id'               => 0,
				'status'                => 'draft',
				'fields_json'           => PersistenceValidator::json( $fields, 65535, 'Statement fields' ),
				'published_fields_json' => '',
				'owner_user_id'         => absint( $owner ),
				'last_reviewed'         => null,
				'published_at'          => null,
				'created_at'            => $now,
				'updated_at'            => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/** Save a draft without changing the published snapshot. @param int $id ID. @param array $fields Fields. @param string|null $reviewed Review date. @return void */
	public function save_draft( $id, array $fields, $reviewed ) {
		$this->update(
			$id,
			array(
				'fields_json'   => PersistenceValidator::json( $fields, 65535, 'Statement fields' ),
				'last_reviewed' => self::review_date( $reviewed ),
				'updated_at'    => $this->now(),
			)
		);
	}

	/** Associate an existing page while retaining draft state. @param int $id Statement ID. @param int $post_id Page ID. @return void */
	public function associate_post( $id, $post_id ) {
		$this->update(
			$id,
			array(
				'post_id'    => absint( $post_id ),
				'updated_at' => $this->now(),
			)
		);
	}

	/** Mark published after WordPress page creation/update. @param int $id ID. @param int $post_id Post ID. @param array $fields Fields. @param int $actor Actor. @param string|null $reviewed Review date. @return void */
	public function publish( $id, $post_id, array $fields, $actor, $reviewed ) {
		$now  = $this->now();
		$json = PersistenceValidator::json( $fields, 65535, 'Statement fields' );
		$this->database->query( 'START TRANSACTION' );
		try {
			$this->update(
				$id,
				array(
					'post_id'               => absint( $post_id ),
					'status'                => 'published',
					'fields_json'           => $json,
					'published_fields_json' => $json,
					'last_reviewed'         => self::review_date( $reviewed ),
					'published_at'          => $now,
					'updated_at'            => $now,
				)
			);
			$this->insert(
				$this->tables->statement_revisions(),
				array(
					'statement_id'  => absint( $id ),
					'fields_json'   => $json,
					'actor_user_id' => absint( $actor ),
					'published_at'  => $now,
				),
				array( '%d', '%s', '%d', '%s' )
			);
			$this->database->query( 'COMMIT' );
		} catch ( \Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			throw $error;
		}
	}

	/** Return bounded revision history. @param int $id Statement ID. @return array */
	public function revisions( $id ) {
		return $this->database->get_results( $this->database->prepare( "SELECT * FROM {$this->tables->statement_revisions()} WHERE statement_id = %d ORDER BY id DESC LIMIT 100", absint( $id ) ), ARRAY_A );
	}

	/** Decode structured fields. @param array $statement Statement. @return array */
	public static function fields( array $statement ) {
		$fields = json_decode( $statement['fields_json'], true );
		return is_array( $fields ) ? $fields : array();
	}

	/** Decode the current published snapshot. @param array $statement Statement. @return array */
	public static function published_fields( array $statement ) {
		$fields = json_decode( $statement['published_fields_json'] ?? '', true );
		return is_array( $fields ) ? $fields : array();
	}

	/** Update a statement. @param int $id ID. @param array $data Data. @return void */
	private function update( $id, array $data ) {
		if ( false === $this->database->update( $this->tables->statements(), $data, array( 'id' => absint( $id ) ) ) ) {
			throw new RuntimeException( 'Elavira could not save the Accessibility Statement.' );
		}
	}

	/** Validate optional review date. @param string|null $date Date. @return string|null */
	private static function review_date( $date ) {
		if ( null === $date || '' === $date ) {
			return null;
		}
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
			throw new RuntimeException( 'The review date is invalid.' );
		}
		return $date;
	}
}
