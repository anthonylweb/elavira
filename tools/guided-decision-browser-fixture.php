<?php
/**
 * Browser-only manual fixture for current Guided Decision layouts.
 *
 * Run from the plugin root with:
 * php -S 127.0.0.1:8765 -t .
 *
 * @package Elavira
 */

if ( 'cli-server' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

require dirname( __DIR__ ) . '/tests/bootstrap.php';

$allowed = array(
	'alternative_text',
	'aria_behavior',
	'audio_video_alternatives',
	'contrast_review',
	'focus_visibility',
	'form_labels_instructions',
	'heading_structure',
	'keyboard_interaction',
	'link_purpose',
	'use_of_color',
);
$flow    = isset( $_GET['flow'] ) ? sanitize_key( wp_unslash( $_GET['flow'] ) ) : 'link_purpose';
if ( ! in_array( $flow, $allowed, true ) ) {
	$flow = 'link_purpose';
}

$element = array(
	'tag_name'          => 'a',
	'link_kind'         => 'image_only',
	'image_filename'    => 'a-very-long-contact-envelope-filename-used-to-check-wrapping-at-narrow-widths.svg',
	'image_alt_excerpt' => 'Envelope icon with an intentionally long recorded alternative excerpt that must remain readable without document-level horizontal scrolling.',
	'link_destination'  => 'https://example.org/a/long/contact/destination/that/must/wrap/without/losing/important/information/',
);
$fixture = new \Elavira\Tests\Support\DecisionFixture( $flow, $element );

header( 'Content-Type: text/html; charset=UTF-8' );
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Guided Decision Browser Fixture</title>
	<link rel="stylesheet" href="/assets/css/reports.css">
	<style>body{margin:0;padding:0 8px;background:#f0f0f1;font:16px/1.5 system-ui,sans-serif}.wrap{margin:0 auto}button,input,textarea{font:inherit}</style>
</head>
<body>
	<?php echo $fixture->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The production controller escapes each rendered value. ?>
</body>
</html>
