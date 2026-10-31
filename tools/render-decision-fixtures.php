<?php
/** CLI-only real-controller decision fixtures in isolated repositories. */
if ( 'cli' !== PHP_SAPI ) { exit; }
require dirname( __DIR__ ) . '/tests/bootstrap.php';
$output = realpath( $argv[1] ?? '' );
if ( ! $output || ! is_dir( $output ) ) { throw new RuntimeException( 'Supply an existing output directory.' ); }
$link = array( 'tag_name' => 'a', 'link_kind' => 'text', 'text_excerpt' => 'Learn more', 'link_name_excerpt' => 'Learn more', 'link_destination' => 'https://example.org/services/' );
$image = array( 'tag_name' => 'a', 'link_kind' => 'image_only', 'image_filename' => 'envelope.svg', 'image_alt_state' => 'present', 'image_alt_excerpt' => 'Envelope icon', 'link_name_excerpt' => 'Envelope icon', 'link_destination' => 'https://example.org/contact/' );
$cases = array(
 array( 'text-context', 'link_purpose', array( 'text', 'no', 'yes' ), array_merge( $link, array( 'context_excerpt' => 'Learn about our services. Learn more' ) ), array() ),
 array( 'text-no-context', 'link_purpose', array( 'text', 'no', 'no' ), $link, array() ),
 array( 'envelope', 'link_purpose', array( 'image_only', 'no', 'no', 'no' ), $image, array() ),
 array( 'contact-name', 'link_purpose', array( 'image_only', 'no', 'yes' ), array_merge( $image, array( 'image_alt_excerpt' => 'Contact us', 'link_name_excerpt' => 'Contact us' ) ), array() ),
 array( 'image-words', 'link_purpose', array( 'image_only', 'yes', 'no' ), array_merge( $image, array( 'image_filename' => 'fall-collection.png', 'image_alt_excerpt' => 'Clothing photo', 'link_name_excerpt' => 'Clothing photo', 'link_destination' => 'https://example.org/fall/' ) ), array() ),
 array( 'decorative-arrow', 'link_purpose', array( 'mixed', 'no', 'yes', 'yes' ), array( 'tag_name' => 'a', 'link_kind' => 'mixed_or_unknown', 'image_filename' => 'arrow.svg', 'image_alt_state' => 'empty', 'text_excerpt' => 'View pricing', 'link_destination' => 'https://example.org/pricing/' ), array() ),
 array( 'headings', 'heading_structure', array( 'yes', 'no' ), array( 'tag_name' => 'h4', 'text_excerpt' => 'Delivery times' ), array() ),
 array( 'alternative', 'alternative_text', array( 'decorative', 'no' ), array( 'tag_name' => 'img', 'image_filename' => 'decoration.svg', 'image_alt_state' => 'missing' ), array( 'alt_state' => 'missing' ) ),
 array( 'form', 'form_labels_instructions', array( 'label', 'no' ), array( 'tag_name' => 'input', 'input_type' => 'email', 'field_name' => 'contact_email' ), array( 'name_state' => 'missing', 'control_type' => 'email' ) ),
 array( 'keyboard', 'keyboard_interaction', array( 'yes', 'yes', 'no' ), array( 'tag_name' => 'button', 'text_excerpt' => 'Open menu' ), array() ),
 array( 'color', 'use_of_color', array( 'yes', 'no' ), array( 'tag_name' => 'input', 'field_name' => 'email' ), array() ),
 array( 'obscured-focus', 'focus_visibility', array( 'yes', 'yes', 'no' ), array( 'tag_name' => 'button', 'text_excerpt' => 'Save preferences' ), array() ),
 array( 'aria-state', 'aria_behavior', array( 'yes', 'yes', 'no' ), array( 'tag_name' => 'button', 'text_excerpt' => 'Delivery options' ), array( 'attribute_name' => 'aria-expanded', 'role' => 'button' ) ),
 array( 'contrast', 'contrast_review', array( 'text', 'yes', 'no', 'uncertain' ), array( 'tag_name' => 'p', 'text_excerpt' => 'Text over a photograph' ), array( 'candidate' => 'normal_text', 'reason' => 'background_image', 'required_milli' => 4500, 'rendered_state' => 'current' ) ),
 array( 'media', 'audio_video_alternatives', array( 'both', 'yes', 'yes', 'no' ), array(), array() ),
 array( 'historical', 'link_purpose', array( 'uncertain' ), array(), array(), true ),
);
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/admin.css' ) . file_get_contents( dirname( __DIR__ ) . '/assets/css/reports.css' );
$manifest = array();
foreach ( $cases as $case ) {
 list( $name, $id, $answers, $element, $evidence ) = $case;
 $fixture = new Elavira\Tests\Support\DecisionFixture( $id, $element, $evidence, $case[5] ?? false );
 for ( $length = 0; $length <= count( $answers ); ++$length ) {
  $html = $fixture->render( array_slice( $answers, 0, $length ) );
  $filename = 'decision-' . $name . '-' . $length . '.html';
  $navigation = '<nav aria-label="QA fixture navigation">';
  if ( $length ) { $navigation .= '<a href="decision-' . $name . '-' . ( $length - 1 ) . '.html">Previous fixture step</a> '; }
  if ( $length < count( $answers ) ) { $navigation .= '<a href="decision-' . $name . '-' . ( $length + 1 ) . '.html">Next fixture step</a>'; }
  $navigation .= '</nav>';
  file_put_contents( $output . '/' . $filename, '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Decision fixture: ' . $name . '</title><style>body{font:16px/1.5 sans-serif;margin:20px;color:#1d2327;background:#f0f0f1}a{color:#135e96}button,input,select,textarea{font:inherit}' . $css . '</style></head><body><main>' . $navigation . $html . '</main></body></html>' );
  $manifest[] = array( 'file' => $filename, 'case' => $name, 'answers' => array_slice( $answers, 0, $length ) );
 }
}
file_put_contents( $output . '/decision-fixtures.json', json_encode( $manifest, JSON_PRETTY_PRINT ) );
echo 'Rendered ' . count( $manifest ) . ' actual-controller pages across ' . count( $cases ) . " decision cases.\n";
