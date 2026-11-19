<?php
/**
 * Human-readable occurrence identification, independent of rule detection.
 *
 * @package Elavira
 */

namespace Elavira\Reports\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment

/** Converts recorded facts into translatable labels without inventing missing evidence. */
final class OccurrencePresenter {
	/** Return plain text only; the report escapes each value at output. */
	public function present( array $occurrence, $rule_id ) {
		$element     = $this->decode( $occurrence['element_json'] ?? '' );
		$evidence    = $this->decode( $occurrence['evidence_json'] ?? '' );
		$target      = $this->decode( $occurrence['target_json'] ?? '' );
		$tag         = $this->first( array( $this->value( $element, 'tag_name' ), $this->value( $target, 'tag_name' ) ) );
		$types       = array(
			'a'        => 0 === strpos( $rule_id, 'elavira.link' ) || isset( $element['link_kind'] ) ? __( 'Link', 'elavira' ) : '',
			'form'     => __( 'Form', 'elavira' ),
			'label'    => __( 'Form label', 'elavira' ),
			'img'      => __( 'Image', 'elavira' ),
			'button'   => __( 'Button', 'elavira' ),
			'input'    => __( 'Form field', 'elavira' ),
			'select'   => __( 'Selection field', 'elavira' ),
			'textarea' => __( 'Text area', 'elavira' ),
			'html'     => __( 'Page', 'elavira' ),
			'main'     => __( 'Main content', 'elavira' ),
		);
		$description = $types[ $tag ] ?? '';
		$level       = preg_match( '/^h([1-6])$/', $tag, $match ) ? $match[1] : '';
		if ( $level ) {
			$description = __( 'Heading', 'elavira' );
		}
		if ( 'html' === $tag && in_array( $rule_id, array( 'elavira.document.language_missing', 'elavira.document.invalid_language' ), true ) ) {
			$description = __( 'Page language', 'elavira' );
		}
		if ( in_array( $tag, array( 'a', 'img' ), true ) && 'image_only' === ( $element['link_kind'] ?? '' ) ) {
			$description = __( 'Image link', 'elavira' );
		} elseif ( 'a' === $tag && 'text' === ( $element['link_kind'] ?? '' ) ) {
			$description = __( 'Text link', 'elavira' );
		}
		$field_types = array(
			'email'    => __( 'Email field', 'elavira' ),
			'password' => __( 'Password field', 'elavira' ),
			'text'     => __( 'Text field', 'elavira' ),
			'tel'      => __( 'Telephone field', 'elavira' ),
			'url'      => __( 'URL field', 'elavira' ),
			'search'   => __( 'Search field', 'elavira' ),
			'checkbox' => __( 'Checkbox', 'elavira' ),
			'radio'    => __( 'Radio button', 'elavira' ),
			'file'     => __( 'File upload field', 'elavira' ),
			'submit'   => __( 'Submit button', 'elavira' ),
		);
		$input_type  = $this->first( array( $this->value( $element, 'input_type' ), $this->value( $evidence, 'control_type' ) ) );
		if ( 'input' === $tag && isset( $field_types[ $input_type ] ) ) {
			$description = $field_types[ $input_type ];
		}
		$rows = array();
		$this->add( $rows, __( 'Element', 'elavira' ), $description );
		$this->add( $rows, __( 'Heading level', 'elavira' ), $level ? 'H' . $level : '' );
		$labels = array(
			'text_excerpt'      => 'img' === $tag ? __( 'Containing link text (excerpt)', 'elavira' ) : __( 'Element text (excerpt)', 'elavira' ),
			'link_destination'  => __( 'Link destination (query and fragment omitted)', 'elavira' ),
			'image_filename'    => __( 'Image filename', 'elavira' ),
			'image_alt_excerpt' => __( 'Current alt text (excerpt)', 'elavira' ),
			'link_name_excerpt' => __( 'Available link name (excerpt)', 'elavira' ),
			'field_name'        => __( 'Field name', 'elavira' ),
			'context_excerpt'   => __( 'Surrounding paragraph (excerpt)', 'elavira' ),
		);
		foreach ( $labels as $key => $label ) {
			$this->add( $rows, $label, $this->value( $element, $key ) );
		}
		if ( ! $this->value( $element, 'image_alt_excerpt' ) ) {
			$this->add( $rows, __( 'Current alt text (excerpt)', 'elavira' ), $this->value( $evidence, 'alt_excerpt' ) );
		}
		if ( ! $this->value( $element, 'link_name_excerpt' ) ) {
			$this->add( $rows, __( 'Recorded accessible name (excerpt)', 'elavira' ), $this->value( $evidence, 'accessible_name_excerpt' ) );
		}
		$alt_states = array(
			'missing' => __( 'No alt attribute was recorded.', 'elavira' ),
			'empty'   => __( 'Empty alt text was recorded.', 'elavira' ),
		);
		$alt_state  = $element['image_alt_state'] ?? $evidence['alt_state'] ?? '';
		$this->add( $rows, __( 'Current alt text', 'elavira' ), is_string( $alt_state ) ? ( $alt_states[ $alt_state ] ?? '' ) : '' );
		if ( 'missing' === ( $evidence['name_state'] ?? $evidence['accessible_name_state'] ?? '' ) ) {
			$this->add( $rows, __( 'Accessible name', 'elavira' ), __( 'No accessible name was detected by this check.', 'elavira' ) );
		}
		$language_states = array(
			'missing' => __( 'No page language attribute was recorded.', 'elavira' ),
			'empty'   => __( 'The page language attribute was empty.', 'elavira' ),
		);
		$language_state  = $this->value( $evidence, 'language_state' );
		$this->add( $rows, __( 'Page language', 'elavira' ), $language_states[ $language_state ] ?? $this->value( $evidence, 'declared_language' ) );
		$issues = array(
			'no_visible_headings'  => __( 'No visible headings were detected on this page.', 'elavira' ),
			'main_without_heading' => __( 'No visible headings were detected in the main content.', 'elavira' ),
		);
		$this->add( $rows, __( 'Location context', 'elavira' ), $issues[ $this->value( $evidence, 'issue' ) ] ?? '' );
		$identifier = $this->first( array( $this->value( $element, 'text_excerpt' ), $this->value( $element, 'image_filename' ), $this->value( $element, 'field_name' ), $this->value( $element, 'link_name_excerpt' ), $this->value( $evidence, 'alt_excerpt' ), $this->value( $evidence, 'accessible_name_excerpt' ) ) );
		$summary    = $level ? 'H' . $level : $description;
		if ( $identifier ) {
			preg_match( '/^.{0,70}/us', $identifier, $short );
			$excerpt = $short[0] ?? '';
			/* translators: 1: element description, 2: short recorded identifier (full captured excerpt is below). */
			$summary = $summary ? sprintf( __( '%1$s — “%2$s”', 'elavira' ), $summary, $excerpt . ( $excerpt !== $identifier ? '…' : '' ) ) : $excerpt;
		}
		$destination = $this->value( $element, 'link_destination' );
		if ( $destination && in_array( $tag, array( 'a', 'img' ), true ) ) {
			preg_match( '/^.{0,45}/us', $destination, $short_destination );
			$short_url = $short_destination[0] ?? '';
			/* translators: 1: element identification, 2: shortened destination, shown in full below. */
			$summary = sprintf( __( '%1$s to %2$s', 'elavira' ), $summary, $short_url . ( $short_url !== $destination ? '…' : '' ) );
		}
		/* translators: %d: persisted occurrence record ID. */
		$heading = sprintf( __( 'Occurrence %d', 'elavira' ), absint( $occurrence['id'] ?? 0 ) );
		if ( $summary ) {
			/* translators: 1: occurrence reference, 2: recorded element identification. */
			$heading = sprintf( __( '%1$s — %2$s', 'elavira' ), $heading, $summary );
		}
		return array(
			'heading'           => $heading,
			'rows'              => $rows,
			'technical_element' => array_diff_key( $element, $labels ),
		);
	}

	private function decode( $json ) {
		$value = is_string( $json ) ? json_decode( $json, true ) : null;
		return is_array( $value ) ? $value : array();
	}

	private function first( array $values ) {
		foreach ( $values as $value ) {
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}

	private function value( array $values, $key ) {
		return isset( $values[ $key ] ) && is_string( $values[ $key ] ) ? trim( $values[ $key ] ) : '';
	}

	private function add( array &$rows, $label, $value ) {
		if ( '' !== $value ) {
			$rows[] = array(
				'label' => $label,
				'value' => $value,
			);
		}
	}
}
