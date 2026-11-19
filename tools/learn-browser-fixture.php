<?php
/**
 * Browser-only manual fixture for the production Learn controller.
 *
 * Run from the plugin root with:
 * php -S 127.0.0.1:8766 -t .
 *
 * @package Elavira
 */

if ( 'cli-server' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

require dirname( __DIR__ ) . '/tests/bootstrap.php';

$GLOBALS['elavira_test_capabilities']['read'] = true;
$topics                                       = \Elavira\Learn\CoreTopicRegistry::create();
$rules                                        = \Elavira\Rules\CoreRuleRegistry::create();
$learn_page                                   = new \Elavira\Learn\Admin\LearnPage( $topics, \Elavira\GuidedDecisions\CoreDecisionRegistry::create(), $rules, new \Elavira\Learn\WcagReference( $topics, $rules ) );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Local read-only manual fixture controls.
$topic  = isset( $_GET['topic'] ) ? sanitize_key( wp_unslash( $_GET['topic'] ) ) : '';
$narrow = isset( $_GET['narrow'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['narrow'] ) );
// phpcs:enable WordPress.Security.NonceVerification.Recommended

header( 'Content-Type: text/html; charset=UTF-8' );
// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone local browser fixture.
?><!doctype html>
<html lang="en"<?php echo $narrow ? ' class="elavira-narrow"' : ''; ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Learn Center Browser Fixture</title>
	<link rel="stylesheet" href="/assets/css/learn.css"><!-- phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone local browser fixture. -->
	<style>html.elavira-narrow{width:320px;max-width:320px}body{box-sizing:border-box;margin:0;padding:0 8px;background:#f0f0f1;font:16px/1.5 system-ui,sans-serif}html.elavira-narrow body{width:320px;max-width:320px}.wrap{margin:0 auto}button,input,select{font:inherit}a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible{outline:3px solid #135e96;outline-offset:2px}</style>
</head>
<body>
	<?php $topic ? $learn_page->detail() : $learn_page->landing(); ?>
</body>
</html>
