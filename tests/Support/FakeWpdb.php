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
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
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
		if ( substr( $table, -17 ) === 'scan_observations' ) {
			foreach ( $this->tables[ $table ] as $row ) {
				if ( (int) $row['session_id'] === (int) $data['session_id'] && $row['batch_key'] === $data['batch_key'] && (int) $row['result_index'] === (int) $data['result_index'] ) {
					return false; }
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
		if ( is_array( $query ) ) {
			$sql  = $query['query'];
			$args = $query['args'];
			if ( 0 === strpos( $sql, 'DELETE FROM ' ) ) {
				$table = preg_match( '/DELETE FROM ([^\s]+)/', $sql, $match ) ? $match[1] : '';
				foreach ( $this->tables[ $table ] ?? array() as $id => $row ) {
					if ( (int) $row['session_id'] === (int) $args[0] ) {
						unset( $this->tables[ $table ][ $id ] ); }
				}
				return true;
			}
			if ( 0 === strpos( $sql, 'UPDATE ' ) && false !== strpos( $sql, 'reviewed_exceptions' ) ) {
				$table = preg_match( '/UPDATE ([^\s]+)/', $sql, $match ) ? $match[1] : '';
				$count = 0;
				foreach ( $this->tables[ $table ] ?? array() as $id => $row ) {
					$page_matches = true;
					preg_match_all( '/(?:WHERE|AND)\s+([a-z_]+)\s*=\s*%[ds]/', $sql, $fields );
					foreach ( $fields[1] as $index => $field ) {
						if ( (string) $row[ $field ] !== (string) $args[ $index + 2 ] ) {
							$page_matches = false; }
					}
					if ( 1 === (int) $row['active'] && $page_matches ) {
						$this->tables[ $table ][ $id ]['active']           = 0;
						$this->tables[ $table ][ $id ]['reset_at']         = $args[0];
						$this->tables[ $table ][ $id ]['reset_by_user_id'] = $args[1];
						++$count;
					}
				}
				return $count;
			}
			$query = $sql;
		}
		if ( preg_match( '/^ALTER TABLE ([^ ]+) ADD ([a-z_]+) /', $query, $column ) ) {
			$this->schema_sql .= "\n\t\t" . $column[2] . ' added';
		}
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
		if ( 0 === strpos( $prepared['query'], 'SHOW COLUMNS FROM' ) ) {
			$column = $prepared['args'][0];
			return false !== strpos( $this->schema_sql, "\n\t\t{$column} " ) ? $column : null;
		}

		$table = $this->table_from_query( $prepared['query'] );
		$field = preg_match( '/WHERE ([a-z_]+)\s*=\s*%[ds]/', $prepared['query'], $match ) ? $match[1] : 'id';
		$value = $prepared['args'][0];
		$rows  = array_filter(
			$this->tables[ $table ] ?? array(),
			static function ( $row ) use ( $field, $value ) {
				return isset( $row[ $field ] ) && (string) $row[ $field ] === (string) $value;
			}
		);
		if ( false !== stripos( $prepared['query'], 'COUNT(*)' ) ) {
			return count( $rows );
		}

		$row = reset( $rows );
		return $row ? $row['id'] : null;
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
		if ( false !== strpos( $prepared['query'], 'SELECT h.occurrence_id, h.reason' ) ) {
			$this->queries[] = $prepared['query'];
			$latest          = array();
			foreach ( $this->tables[ $table ] ?? array() as $row ) {
				$id = (int) ( $row['occurrence_id'] ?? 0 );
				if ( ! in_array( $id, $prepared['args'], true ) || '' === trim( $row['reason'] ) ) {
					continue;
				}
				$old = $latest[ $id ] ?? null;
				if ( ! $old || array( $row['created_at'], $row['id'] ) > array( $old['created_at'], $old['id'] ) ) {
					$latest[ $id ] = $row;
				}
			}
			return array_values( $latest );
		}
		if ( false !== strpos( $prepared['query'], 'WHERE f.audit_id = %d' ) ) {
			$joined = array();
			foreach ( $this->tables[ $table ] ?? array() as $row ) {
				$finding = $this->tables[ $this->prefix . 'elavira_findings' ][ $row['finding_id'] ] ?? null;
				if ( ! $finding || $value !== (int) $finding['audit_id'] ) {
					continue;
				}
				$row['rule_id']          = $finding['rule_id'];
				$row['summary_snapshot'] = $finding['summary_snapshot'];
				if ( isset( $row['audit_url_id'] ) ) {
					$row['canonical_url'] = $this->tables[ $this->prefix . 'elavira_audit_urls' ][ $row['audit_url_id'] ]['canonical_url'];
				}
				$row['exception_active'] = $this->tables[ $this->prefix . 'elavira_reviewed_exceptions' ][ $row['reviewed_exception_id'] ?? 0 ]['active'] ?? null;
				$joined[]                = $row;
			}
			return $joined;
		}
		if ( false !== strpos( $prepared['query'], 'WHERE f.audit_id < %d' ) ) {
			list( $audit, $rule, $version, $page, $target, $evidence ) = $prepared['args'];
			$rows = array();
			foreach ( $this->tables[ $table ] ?? array() as $row ) {
				$finding   = $this->tables[ $this->prefix . 'elavira_findings' ][ $row['finding_id'] ];
				$url       = $this->tables[ $this->prefix . 'elavira_audit_urls' ][ $row['audit_url_id'] ];
				$audit_row = $this->tables[ $this->prefix . 'elavira_audits' ][ $finding['audit_id'] ];
				if ( $finding['audit_id'] < $audit && $finding['rule_id'] === $rule && $finding['rule_version'] === $version && $url['canonical_url_hash'] === $page && $row['target_fingerprint'] === $target && $row['material_evidence_fingerprint'] === $evidence && 'completed' === $audit_row['status'] ) {
					$row['_audit'] = $finding['audit_id'];
					$rows[]        = $row; }
			}
			usort(
				$rows,
				static function ( $a, $b ) {
					$order = $b['_audit'] <=> $a['_audit'];
					return 0 !== $order ? $order : $b['id'] <=> $a['id'];
				}
			);
			return array_slice( $rows, 0, 2 );
		}
		$field = preg_match( '/WHERE (?:o\.)?([a-z_]+) = %d/', $prepared['query'], $match ) ? $match[1] : 'id';
		$rows  = array_filter(
			array_values( $this->tables[ $table ] ?? array() ),
			static function ( $row ) use ( $field, $value ) {
				return isset( $row[ $field ] ) && (int) $row[ $field ] === $value;
			}
		);

		if ( false !== strpos( $prepared['query'], 'SELECT o.*' ) && false !== strpos( $prepared['query'], 'u.canonical_url' ) ) {
			$rows = array_map(
				function ( $row ) {
					$row['canonical_url'] = $this->tables[ $this->prefix . 'elavira_audit_urls' ][ $row['audit_url_id'] ]['canonical_url'];
					return $row;
				},
				$rows
			);
		}
		usort(
			$rows,
			static function ( $left, $right ) {
				return (int) $left['id'] <=> (int) $right['id'];
			}
		);

		if ( false !== strpos( $prepared['query'], 'COUNT(*) AS finding_count' ) ) {
			$groups = array();
			foreach ( $rows as $row ) {
				if ( 'reviewed_not_issue' === $row['current_status'] || 0 === (int) $row['active_occurrence_count'] ) {
					continue;
				}
				$key = $row['priority'] . ':' . $row['current_status'] . ':' . $row['review_state'];
				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array(
						'priority'         => $row['priority'],
						'current_status'   => $row['current_status'],
						'review_state'     => $row['review_state'],
						'finding_count'    => 0,
						'occurrence_count' => 0,
					);
				}
				++$groups[ $key ]['finding_count'];
				$groups[ $key ]['occurrence_count'] += (int) $row['active_occurrence_count'];
			}
			return array_values( $groups );
		}

		return $rows;
	}

	private function table_from_query( $query ) {
		preg_match( '/FROM ([^\s]+)/', $query, $match );
		return $match[1];
	}
}
