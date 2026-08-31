<?php
/**
 * Versioned migration runner.
 *
 * @package Elavira
 */

namespace Elavira\Core\Migrations;

use Elavira\Core\Version\VersionManager;

/**
 * Applies pending migrations in version order.
 */
final class MigrationRunner {
	/**
	 * Version manager.
	 *
	 * @var VersionManager
	 */
	private $versions;

	/**
	 * Registered migrations.
	 *
	 * @var Migration[]
	 */
	private $migrations;

	/**
	 * Constructor.
	 *
	 * @param VersionManager $versions   Version manager.
	 * @param Migration[]    $migrations Registered migrations.
	 */
	public function __construct( VersionManager $versions, array $migrations = array() ) {
		$this->versions   = $versions;
		$this->migrations = $migrations;
	}

	/**
	 * Run pending migrations and record the target schema version.
	 *
	 * @param string $target_version Target schema version.
	 * @return void
	 */
	public function run( $target_version ) {
		$current_version = $this->versions->schema_version();
		$migrations      = $this->migrations;

		usort(
			$migrations,
			static function ( Migration $left, Migration $right ) {
				return version_compare( $left->version(), $right->version() );
			}
		);

		foreach ( $migrations as $migration ) {
			if ( version_compare( $migration->version(), $current_version, '<=' ) ) {
				continue;
			}

			if ( version_compare( $migration->version(), $target_version, '>' ) ) {
				continue;
			}

			$migration->up();
			$this->versions->set_schema_version( $migration->version() );
			$current_version = $migration->version();
		}

		if ( version_compare( $current_version, $target_version, '<' ) ) {
			$this->versions->set_schema_version( $target_version );
		}
	}
}
