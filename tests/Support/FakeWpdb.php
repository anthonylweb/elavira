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
	public $fail_next_insert    = false;
	private $transaction_tables = null;

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
		if ( $this->fail_next_insert ) {
			$this->fail_next_insert = false;
			return false;
		}

		if ( ! isset( $this->tables[ $table ] ) ) {
			$this->last_error = 'Missing table';
			return false;
		}
		if ( substr( $table, -13 ) === 'scan_batches' ) {
			foreach ( $this->tables[ $table ] as $row ) {
				if ( (int) $row['session_id'] === (int) $data['session_id'] && $row['batch_key'] === $data['batch_key'] ) {
					return false;
				}
			}
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
		if ( 'START TRANSACTION' === $query ) {
			$this->transaction_tables = $this->tables;
		} elseif ( 'ROLLBACK' === $query && null !== $this->transaction_tables ) {
			$this->tables             = $this->transaction_tables;
			$this->transaction_tables = null;
		} elseif ( 'COMMIT' === $query ) {
			$this->transaction_tables = null;
		}
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
		preg_match_all( '/(?:WHERE|AND)\s+([a-z_]+)\s*=\s*%[ds]/', $prepared['query'], $matches );
		foreach ( $this->tables[ $table ] ?? array() as $row ) {
			$matches_row = true;
			foreach ( $matches[1] as $index => $field ) {
				if ( ! array_key_exists( $field, $row ) || (string) $row[ $field ] !== (string) $prepared['args'][ $index ] ) {
					$matches_row = false;
				}
			}
			if ( $matches_row ) {
				return $row;
			}
		}
		return null;
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
