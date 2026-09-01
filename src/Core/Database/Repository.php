<?php
/**
 * Shared repository mechanics.
 *
 * @package Elavira
 */

namespace Elavira\Core\Database;

use InvalidArgumentException;
use RuntimeException;

/**
 * Provides safe insertion and parent-existence checks to narrow repositories.
 */
abstract class Repository {
	/**
	 * WordPress database object.
	 *
	 * @var object
	 */
	protected $database;

	/**
	 * Elavira table names.
	 *
	 * @var TableNames
	 */
	protected $tables;

	/**
	 * Constructor.
	 *
	 * @param object $database WordPress database object.
	 */
	public function __construct( $database ) {
		$this->database = $database;
		$this->tables   = new TableNames( $database->prefix );
	}

	/**
	 * Require a parent record.
	 *
	 * @param string $table Parent table.
	 * @param int    $id    Parent ID.
	 * @param string $name  Parent name for errors.
	 * @return int
	 * @throws InvalidArgumentException When the parent ID is invalid or absent.
	 */
	protected function require_parent( $table, $id, $name ) {
		unset( $name );
		$id = absint( $id );

		if ( 0 === $id ) {
			throw new InvalidArgumentException( 'A referenced parent ID is invalid.' );
		}

		$found = $this->database->get_var(
			$this->database->prepare( "SELECT id FROM {$table} WHERE id = %d", $id )
		);

		if ( null === $found ) {
			throw new InvalidArgumentException( 'A referenced parent record does not exist.' );
		}

		return $id;
	}

	/**
	 * Insert a row or fail explicitly.
	 *
	 * @param string   $table   Table name.
	 * @param array    $data    Row data.
	 * @param string[] $formats WordPress insert formats.
	 * @return int
	 * @throws RuntimeException When WordPress cannot insert the record.
	 */
	protected function insert( $table, array $data, array $formats ) {
		$result = $this->database->insert( $table, $data, $formats );

		if ( false === $result ) {
			throw new RuntimeException( 'Elavira could not persist the requested record.' );
		}

		return (int) $this->database->insert_id;
	}

	/**
	 * Return a UTC database timestamp.
	 *
	 * @return string
	 */
	protected function now() {
		return current_time( 'mysql', true );
	}
}
