<?php
/**
 * Migration contract.
 *
 * @package Elavira
 */

namespace Elavira\Core\Migrations;

/**
 * Represents one idempotent schema migration.
 */
interface Migration {
	/**
	 * Schema version introduced by the migration.
	 *
	 * @return string
	 */
	public function version();

	/**
	 * Apply the migration.
	 *
	 * @return void
	 */
	public function up();
}
