<?php
/**
 * In-memory WordPress database test double.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Support;

/**
 * Implements the small wpdb surface used by foundation persistence tests.
 */
final class FakeWpdb {
	public $prefix              = 'wp_';
	public $last_error          = '';
	public $insert_id           = 0;
	public $schema_sql          = '';
	public $tables              = array();
	public $queries             = array();
	public $fail_table_creation = false;

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	public function create_table( $table ) {
		if ( $this->fail_table_creation ) {
			return;
		}

		if ( ! isset( $this->tables[ $table ] ) ) {
			$this->tables[ $table ] = array();
		}
	}

	public function prepare( $query, ...$args ) {
		return array(
			'query' => $query,
			'args'  => $args,
		);
	}

	public function insert( $table, array $data, array $formats = array() ) {
		unset( $formats );

		if ( ! isset( $this->tables[ $table ] ) ) {
			$this->last_error = 'Missing table';
			return false;
		}

		$this->insert_id                            = count( $this->tables[ $table ] ) + 1;
		$data['id']                                 = $this->insert_id;
		$this->tables[ $table ][ $this->insert_id ] = $data;

		return 1;
	}

	public function update( $table, array $data, array $where, array $formats = array(), array $where_formats = array() ) {
		unset( $formats, $where_formats );
		$id = (int) $where['id'];

		if ( ! isset( $this->tables[ $table ][ $id ] ) ) {
			return false;
		}

		$this->tables[ $table ][ $id ] = array_merge( $this->tables[ $table ][ $id ], $data );

		return 1;
	}

	public function query( $query ) {
		$this->queries[] = $query;
		return true;
	}

	public function get_var( $prepared ) {
		if ( 0 === strpos( $prepared['query'], 'SHOW TABLES LIKE' ) ) {
			$table = $prepared['args'][0];
			return isset( $this->tables[ $table ] ) ? $table : null;
		}

		$table = $this->table_from_query( $prepared['query'] );
		$id    = (int) $prepared['args'][0];

		return isset( $this->tables[ $table ][ $id ] ) ? $id : null;
	}

	public function get_row( $prepared, $output = null ) {
		unset( $output );
		$table = $this->table_from_query( $prepared['query'] );
		$id    = (int) $prepared['args'][0];

		return $this->tables[ $table ][ $id ] ?? null;
	}

	public function get_results( $prepared, $output = null ) {
		unset( $output );
		$table = $this->table_from_query( $prepared['query'] );
		$value = (int) $prepared['args'][0];
		$field = preg_match( '/WHERE ([a-z_]+) = %d/', $prepared['query'], $match ) ? $match[1] : 'id';
		$rows  = array_filter(
			array_values( $this->tables[ $table ] ?? array() ),
			static function ( $row ) use ( $field, $value ) {
				return isset( $row[ $field ] ) && (int) $row[ $field ] === $value;
			}
		);

		usort(
			$rows,
			static function ( $left, $right ) {
				return (int) $left['id'] <=> (int) $right['id'];
			}
		);

		return $rows;
	}

	private function table_from_query( $query ) {
		preg_match( '/FROM ([^\s]+)/', $query, $match );
		return $match[1];
	}
}
