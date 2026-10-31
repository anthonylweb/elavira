<?php
/**
 * CLI-only report-controller fixtures, using isolated test repositories.
 * Usage: php tools/render-editorial-fixtures.php ABSOLUTE_OUTPUT_DIRECTORY
 * Add "export" as the final argument to stream a real report workbook there.
 */
if ( PHP_SAPI !== 'cli' ) { exit; }
require dirname( __DIR__ ) . '/tests/bootstrap.php';
$output = realpath( $argv[1] ?? '' );
if ( ! $output || ! is_dir( $output ) ) { throw new RuntimeException( 'Supply an existing output directory.' ); }
$cases = array( array( 'missing-alt', 'elavira.image.alt_missing', '1.0.0', array( 'tag_name' => 'img', 'image_filename' => 'banner.png', 'image_alt_state' => 'missing' ), array( 'alt_state' => 'missing' ) ), array( 'decorative-review', 'elavira.images.alt_quality_review', '1.0.0', array( 'tag_name' => 'img', 'link_kind' => 'mixed_or_unknown', 'image_filename' => 'arrow.svg', 'image_alt_state' => 'present', 'image_alt_excerpt' => 'Arrow pointing right', 'text_excerpt' => 'View pricing', 'link_destination' => 'https://example.org/pricing/' ), array( 'alt_excerpt' => 'Arrow pointing right', 'alt_fingerprint' => 'abcd1234', 'context' => 'functional_link', 'review_reason' => 'functional_context' ) ), array( 'image-only-link', 'elavira.images.alt_quality_review', '1.0.0', array( 'tag_name' => 'img', 'link_kind' => 'image_only', 'image_filename' => 'envelope.svg', 'image_alt_state' => 'present', 'image_alt_excerpt' => 'Envelope icon', 'link_name_excerpt' => 'Envelope icon', 'link_destination' => 'https://example.org/contact/' ), array( 'alt_excerpt' => 'Envelope icon', 'alt_fingerprint' => 'abcd1234', 'context' => 'functional_link', 'review_reason' => 'functional_context' ) ), array( 'meaningful-image-link', 'elavira.images.alt_quality_review', '1.0.0', array( 'tag_name' => 'img', 'link_kind' => 'image_only', 'image_filename' => 'envelope.svg', 'image_alt_state' => 'present', 'image_alt_excerpt' => 'Contact us', 'link_name_excerpt' => 'Contact us', 'link_destination' => 'https://example.org/contact/' ), array( 'alt_excerpt' => 'Contact us', 'alt_fingerprint' => 'abcd1234', 'context' => 'functional_link', 'review_reason' => 'functional_context' ) ), array( 'text-link', 'elavira.link.purpose_review', '2.0.0', array( 'tag_name' => 'a', 'link_kind' => 'text', 'text_excerpt' => 'Learn more', 'link_name_excerpt' => 'Learn more', 'link_destination' => 'https://example.org/services/', 'context_excerpt' => 'Explore our services for small businesses. Learn more' ), array( 'label_kind' => 'learn_more', 'destination_kind' => 'internal' ) ), array( 'form-name', 'elavira.form.accessible_name_missing', '2.0.0', array( 'tag_name' => 'input', 'input_type' => 'email', 'field_name' => 'contact_email' ), array( 'control_type' => 'email', 'name_state' => 'missing' ) ), array( 'heading', 'elavira.structure.heading_level_jump', '1.0.0', array( 'tag_name' => 'h4', 'text_excerpt' => 'Delivery times' ), array( 'previous_level' => 2, 'current_level' => 4 ) ), array( 'language', 'elavira.document.language_missing', '2.0.0', array( 'tag_name' => 'html' ), array( 'language_state' => 'missing' ) ), array( 'aria', 'elavira.aria.invalid_value', '1.0.0', array( 'tag_name' => 'button', 'text_excerpt' => 'Delivery options' ), array( 'attribute_name' => 'aria-expanded', 'value_reason' => 'invalid_boolean' ) ), array( 'keyboard-focus', 'elavira.focus.possible_hidden_focus', '1.0.0', array( 'tag_name' => 'button', 'text_excerpt' => 'Save preferences' ), array( 'style_pattern' => 'outline_removed_no_alternative', 'style_source' => 'author_stylesheet' ) ), array( 'contrast', 'elavira.contrast.text_insufficient', '1.0.0', array( 'tag_name' => 'p' ), array( 'foreground' => '#999999', 'background' => '#ffffff', 'ratio_milli' => 2849, 'required_milli' => 4500, 'text_size' => 'normal', 'font_size_milli' => 16000, 'font_weight' => 400, 'determination' => 'opaque', 'rendered_state' => 'current' ) ) );
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/admin.css' ) . file_get_contents( dirname( __DIR__ ) . '/assets/css/reports.css' );
foreach ( $cases as $case ) {
    list( $name, $id, $version, $element, $evidence ) = $case;
    $test = new Elavira\Tests\Unit\ReportsTest();
    ( new ReflectionMethod( $test, 'setUp' ) )->invoke( $test );
    $database = $GLOBALS['wpdb'];
    $rule = Elavira\Rules\CoreRuleRegistry::create()->get( $id, $version );
    $rule->evidence_schema()->validate( $evidence );
    $finding = &$database->tables['wp_elavira_findings'][1];
    $finding['rule_id'] = $id;
    $finding['rule_version'] = $version;
    $finding['editorial_version'] = $rule->editorial_version();
    $finding['category'] = $rule->classification();
    $finding['result_type'] = $rule->evaluation_type();
    $finding['review_state'] = 'automatic_failure' === $rule->evaluation_type() ? 'not_required' : 'needs_review';
    $finding['summary_snapshot'] = $rule->editorial()['summary'];
    $finding['priority'] = $rule->priority();
    $mapping = $rule->wcag()[0]->to_array();
    foreach ( $mapping as $key => $value ) { $finding['wcag_' . $key] = $value; }
    $urls = new Elavira\Audit\Persistence\AuditUrlRepository( $database );
    $url = $urls->create( array( 'audit_id' => 1, 'canonical_url' => 'https://example.org/audited-page/', 'status' => 'completed' ) );
    $occurrences = new Elavira\Findings\Persistence\OccurrenceRepository( $database );
    $occurrences->create( array( 'finding_id' => 1, 'audit_url_id' => $url, 'target' => array( 'selector' => '#target', 'tag_name' => $element['tag_name'] ), 'element' => $element, 'evidence' => $evidence ) );
    $page = ( new ReflectionMethod( $test, 'page' ) )->invoke( $test );
    $_GET = array( 'finding' => 1 );
    $html = ( new ReflectionMethod( $test, 'render' ) )->invoke( $test, $page, 'render_finding' );
    file_put_contents( $output . '/editorial-' . $name . '.html', '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Editorial fixture: ' . $name . '</title><style>body{font:16px/1.5 sans-serif;margin:20px;color:#1d2327;background:#f0f0f1}a{color:#135e96}button,input,select,textarea{font:inherit}' . $css . '</style></head><body><main>' . $html . '</main></body></html>' );
}
if ( ( $argv[2] ?? '' ) === 'export' ) {
	// All shims remain confined to this CLI fixture process.
	$GLOBALS['elavira_export_fixture_output'] = $output;
	function wp_tempnam( $name ) { return tempnam( $GLOBALS['elavira_export_fixture_output'], 'elavira-qa-' ); }
	function check_admin_referer( $action ) { if ( 'elavira_export_audit_1' !== $action ) { throw new RuntimeException( 'Unexpected nonce action.' ); } }
	function nocache_headers() {}
	function wp_delete_file( $file ) { unlink( $file ); }
	function wp_die( $message ) { throw new RuntimeException( (string) $message ); }
    $GLOBALS['elavira_test_current_user_id'] = 7;
    $GLOBALS['elavira_test_capabilities'] = array( Elavira\Core\Capabilities\Capability::VIEW_REPORTS => true );
    $_GET = array( 'audit' => 1 );
    ob_start( static function ( $bytes ) use ( $output ) { file_put_contents( $output . '/editorial-report.xlsx', $bytes ); return ''; } );
    $page->export();
}
echo 'Rendered ' . count( $cases ) . " validated report-controller fixtures.\n";
