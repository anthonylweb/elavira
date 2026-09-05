<?php
/**
 * Phase 1 ruleset foundation persistence.
 *
 * @package Elavira
 */

namespace Elavira\Core\Migrations;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment, Generic.Commenting.DocComment.MissingShort, Generic.Files.LineLength

use Elavira\Core\Database\TableNames;
use RuntimeException;

/** Adds reviewed exceptions and auditable observation metadata without deleting history. */
final class RulesetFoundationSchema implements Migration {
	/** @var object */
	private $database;

	/** @param object $database WordPress database object. */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/** @return string */
	public function version() {
		return '0.6.0';
	}

	/** @return void */
	public function up() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		$tables  = new TableNames( $this->database->prefix );
		$charset = $this->database->get_charset_collate();
		$sql     = "CREATE TABLE {$tables->findings()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		audit_id bigint(20) unsigned NOT NULL,
		rule_id varchar(191) NOT NULL,
		rule_version varchar(64) NOT NULL,
		group_key varchar(191) NOT NULL,
		result_type varchar(32) NOT NULL DEFAULT 'automatic_failure',
		category varchar(32) NOT NULL,
		priority varchar(16) NOT NULL,
		review_state varchar(32) NOT NULL,
		current_status varchar(32) NOT NULL,
		wcag_criterion varchar(32) NOT NULL DEFAULT '',
		wcag_level varchar(8) NOT NULL DEFAULT '',
		wcag_principle varchar(32) NOT NULL DEFAULT '',
		editorial_version varchar(64) NOT NULL,
		summary_snapshot text NOT NULL,
		affected_page_count bigint(20) unsigned NOT NULL DEFAULT 0,
		occurrence_count bigint(20) unsigned NOT NULL DEFAULT 0,
		active_occurrence_count bigint(20) unsigned NOT NULL DEFAULT 0,
		reviewed_occurrence_count bigint(20) unsigned NOT NULL DEFAULT 0,
		diagnostic_occurrence_count bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY audit_rule_group (audit_id, rule_id, group_key),
		KEY audit_status (audit_id, current_status),
		KEY audit_active (audit_id, active_occurrence_count),
		KEY rule_group (rule_id, group_key)
	) {$charset};

	CREATE TABLE {$tables->occurrences()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		finding_id bigint(20) unsigned NOT NULL,
		audit_url_id bigint(20) unsigned NOT NULL,
		target_json text NOT NULL,
		evidence_json text NOT NULL,
		element_json text NULL,
		target_fingerprint char(64) NOT NULL DEFAULT '',
		material_evidence_fingerprint char(64) NOT NULL DEFAULT '',
		reviewed_exception_id bigint(20) unsigned NOT NULL DEFAULT 0,
		superseded_by_occurrence_id bigint(20) unsigned NOT NULL DEFAULT 0,
		diagnostic_json text NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY finding_id (finding_id),
		KEY audit_url_id (audit_url_id),
		KEY finding_url (finding_id, audit_url_id),
		KEY target_fingerprint (audit_url_id, target_fingerprint),
		KEY reviewed_exception_id (reviewed_exception_id)
	) {$charset};

	CREATE TABLE {$tables->reviewed_exceptions()} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		source_finding_id bigint(20) unsigned NOT NULL,
		source_occurrence_id bigint(20) unsigned NOT NULL,
		rule_id varchar(191) NOT NULL,
		rule_version varchar(64) NOT NULL,
		canonical_url_hash char(64) NOT NULL,
		target_fingerprint char(64) NOT NULL,
		material_evidence_fingerprint char(64) NOT NULL,
		actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		active tinyint(1) unsigned NOT NULL DEFAULT 1,
		created_at datetime NOT NULL,
		reset_at datetime NULL,
		reset_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY exact_active (rule_id, rule_version, canonical_url_hash, target_fingerprint, material_evidence_fingerprint, active),
		KEY page_active (canonical_url_hash, active),
		KEY source_finding (source_finding_id)
	) {$charset};";

		$this->database->last_error = '';
		dbDelta( $sql );
		$reviewed_table = $this->database->get_var( $this->database->prepare( 'SHOW TABLES LIKE %s', $tables->reviewed_exceptions() ) );
		$result_column  = $this->database->get_var( $this->database->prepare( "SHOW COLUMNS FROM {$tables->findings()} LIKE %s", 'result_type' ) );
		$target_column  = $this->database->get_var( $this->database->prepare( "SHOW COLUMNS FROM {$tables->occurrences()} LIKE %s", 'target_fingerprint' ) );
		if ( '' !== $this->database->last_error || $tables->reviewed_exceptions() !== $reviewed_table || 'result_type' !== $result_column || 'target_fingerprint' !== $target_column ) {
			throw new RuntimeException( 'Elavira could not install the ruleset foundation schema.' );
		}
		$this->database->query( "UPDATE {$tables->findings()} SET result_type = 'automatic_failure' WHERE category = 'detected_failure'" );
		$this->database->query( "UPDATE {$tables->findings()} SET result_type = 'potential_issue' WHERE category = 'potential_issue'" );
		$this->database->query( "UPDATE {$tables->findings()} SET result_type = 'human_review' WHERE category = 'human_review'" );
		$this->database->query( "UPDATE {$tables->findings()} f SET active_occurrence_count = (SELECT COUNT(*) FROM {$tables->occurrences()} o WHERE o.finding_id = f.id AND o.reviewed_exception_id = 0 AND o.superseded_by_occurrence_id = 0), reviewed_occurrence_count = (SELECT COUNT(*) FROM {$tables->occurrences()} o WHERE o.finding_id = f.id AND o.reviewed_exception_id > 0), diagnostic_occurrence_count = (SELECT COUNT(*) FROM {$tables->occurrences()} o WHERE o.finding_id = f.id AND o.superseded_by_occurrence_id > 0)" );
	}
}
