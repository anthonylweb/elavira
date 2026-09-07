<?php
/**
 * Durable normalized scan-observation staging.
 *
 * @package Elavira
 */

namespace Elavira\Scanner\Persistence;

// phpcs:disable Squiz.Commenting.FunctionComment -- Compact repository operations are documented at the class boundary.

use Elavira\Core\Database\PersistenceValidator;
use Elavira\Core\Database\Repository;

/** Stores validated observations until explicit whole-page finalization. */
final class StagedObservationRepository extends Repository {
	/** Stage one normalized batch inside the caller transaction. @return void */
	public function stage_batch( $session_id, $batch_key, array $results ) {
		foreach ( array_values( $results ) as $index => $result ) {
			$this->insert(
				$this->tables->scan_observations(),
				array(
					'session_id'      => absint( $session_id ),
					'batch_key'       => PersistenceValidator::identifier( $batch_key, 'batch key' ),
					'result_index'    => absint( $index ),
					'normalized_json' => PersistenceValidator::json( $result, 32768, 'normalized observation' ),
					'created_at'      => $this->now(),
				),
				array( '%d', '%s', '%d', '%s', '%s' )
			);
		}
	}

	/** Return all normalized observations in deterministic receipt order. @return array[] */
	public function all_for_session( $session_id ) {
		$rows = $this->database->get_results( $this->database->prepare( "SELECT * FROM {$this->tables->scan_observations()} WHERE session_id = %d ORDER BY id ASC", absint( $session_id ) ), ARRAY_A );
		return array_map(
			static function ( array $row ) {
				return json_decode( $row['normalized_json'], true );
			},
			$rows
		);
	}

	/** Remove terminal-session staging and receipts. @return void */
	public function cleanup( $session_id ) {
		$this->database->query( $this->database->prepare( "DELETE FROM {$this->tables->scan_observations()} WHERE session_id = %d", absint( $session_id ) ) );
		$this->database->query( $this->database->prepare( "DELETE FROM {$this->tables->scan_batches()} WHERE session_id = %d", absint( $session_id ) ) );
	}

	/** Remove bounded staging for expired interrupted sessions. @return void */
	public function cleanup_expired() {
		$rows = $this->database->get_results( $this->database->prepare( "SELECT id FROM {$this->tables->scan_sessions()} WHERE expires_at < %s AND state <> %s LIMIT 100", current_time( 'mysql', true ), 'completed' ), ARRAY_A );
		foreach ( $rows as $row ) {
			$this->cleanup( $row['id'] ); }
	}
}
