<?php
/**
 * Safe semantic Statement rendering.
 *
 * @package Elavira
 */

namespace Elavira\Statement;

// phpcs:disable Squiz.Commenting.FunctionComment -- Concise renderer comments are intentional.

/** Renders structured plain-text fields without trusting stored markup. */
final class StatementRenderer {
	/** Render a Statement snapshot. @param array $fields Fields. @param array $meta Dates. @return string */
	public function render( array $fields, array $meta = array() ) {
		$html  = '<div class="elavira-accessibility-statement">';
		$html .= '<p><strong>' . esc_html__( 'Organization:', 'elavira' ) . '</strong> ' . esc_html( $fields['organization'] ?? '' ) . '</p>';
		$html .= $this->section( __( 'Our Accessibility Commitment', 'elavira' ), $fields['commitment'] ?? '' );
		$html .= '<section><h2>' . esc_html__( 'Conformance Status — Based on Plugin Findings', 'elavira' ) . '</h2>';
		$html .= '<p>' . esc_html__( 'Elavira findings do not constitute a complete determination of WCAG conformance. Automated testing cannot test every requirement, and human review may still be required.', 'elavira' ) . '</p>';
		$html .= '<dl><div><dt>' . esc_html__( 'WCAG Target', 'elavira' ) . '</dt><dd>' . esc_html( $this->target( $fields['wcag_target'] ?? '' ) ) . '</dd></div><div><dt>' . esc_html__( 'Plugin Findings', 'elavira' ) . '</dt><dd>' . esc_html( $this->label( $fields['plugin_findings_status'] ?? '' ) ) . '</dd></div><div><dt>' . esc_html__( "Organization's Stated Conformance", 'elavira' ) . '</dt><dd>' . esc_html( $this->label( $fields['stated_conformance'] ?? '' ) ) . '</dd></div></dl></section>';
		$html .= $this->section( __( 'Known Limitations', 'elavira' ), $fields['known_limitations'] ?? '' );
		$html .= $this->contact( $fields );
		$html .= $this->section( __( 'Compatibility', 'elavira' ), $fields['compatibility'] ?? '' );
		$html .= $this->section( __( 'Assessment Approach', 'elavira' ), $fields['assessment_approach'] ?? '' );
		$html .= $this->section( __( 'Additional Information', 'elavira' ), $fields['additional_information'] ?? '' );
		$html .= '<section><h2>' . esc_html__( 'Statement Information', 'elavira' ) . '</h2>';
		if ( ! empty( $meta['last_reviewed'] ) ) {
			$html .= '<p><strong>' . esc_html__( 'Last reviewed:', 'elavira' ) . '</strong> ' . esc_html( mysql2date( get_option( 'date_format' ), $meta['last_reviewed'] ) ) . '</p>';
		}
		if ( ! empty( $meta['updated_at'] ) ) {
			$html .= '<p><strong>' . esc_html__( 'Last updated:', 'elavira' ) . '</strong> ' . esc_html( mysql2date( get_option( 'date_format' ), $meta['updated_at'] ) ) . '</p>';
		}
		return $html . '</section></div>';
	}

	/** Render a nonempty plain-text section. @param string $title Title. @param string $text Text. @return string */
	private function section( $title, $text ) {
		return '' === trim( $text ) ? '' : '<section><h2>' . esc_html( $title ) . '</h2>' . wpautop( esc_html( $text ) ) . '</section>';
	}

	/** Render contact methods. @param array $fields Fields. @return string */
	private function contact( array $fields ) {
		$links = array();
		if ( ! empty( $fields['contact_email'] ) ) {
			$links[] = '<a href="' . esc_url( 'mailto:' . $fields['contact_email'] ) . '">' . esc_html( $fields['contact_email'] ) . '</a>';
		}
		if ( ! empty( $fields['contact_url'] ) ) {
			$links[] = '<a href="' . esc_url( $fields['contact_url'] ) . '">' . esc_html__( 'Contact form', 'elavira' ) . '</a>';
		}
		if ( ! empty( $fields['contact_phone'] ) ) {
			$links[] = esc_html( $fields['contact_phone'] );
		}
		$feedback = trim( $fields['feedback'] ?? '' );
		if ( '' === $feedback && ! $links ) {
			return '';
		}
		$html = '<section><h2>' . esc_html__( 'Feedback and Contact', 'elavira' ) . '</h2>';
		if ( '' !== $feedback ) {
			$html .= wpautop( esc_html( $feedback ) );
		}
		if ( $links ) {
			$html .= '<p>' . implode( '<br>', $links ) . '</p>';
		}
		return $html . '</section>';
	}

	/** Human label. @param string $value Value. @return string */
	private function label( $value ) {
		return ucwords( str_replace( '_', ' ', $value ) );
	}

	/** WCAG target label. @param string $value Value. @return string */
	private function target( $value ) {
		return '2.2_A' === $value ? __( 'WCAG 2.2 Level A', 'elavira' ) : __( 'WCAG 2.2 Level AA', 'elavira' );
	}
}
