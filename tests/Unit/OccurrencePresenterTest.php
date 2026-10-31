<?php
/**
 * Occurrence presentation tests.
 *
 * @package Elavira
 */

namespace Elavira\Tests\Unit;

use Elavira\Reports\Admin\OccurrencePresenter;
use PHPUnit\Framework\TestCase;

final class OccurrencePresenterTest extends TestCase {
	private function present( array $element = array(), array $evidence = array(), $rule = 'elavira.link.purpose_review' ) {
		return ( new OccurrencePresenter() )->present(
			array(
				'id'            => 12,
				'element_json'  => wp_json_encode( $element ),
				'evidence_json' => wp_json_encode( $evidence ),
			),
			$rule
		);
	}

	public function test_missing_and_malformed_historical_evidence_falls_back_without_rows() {
		foreach ( array( '', 'null', '{broken', '[]', '123' ) as $json ) {
			$result = ( new OccurrencePresenter() )->present(
				array(
					'id'           => 4,
					'element_json' => $json,
				),
				'unknown'
			);
			$this->assertSame( 'Occurrence 4', $result['heading'] );
			$this->assertSame( array(), $result['rows'] );
		}
		$this->assertSame(
			array(),
			$this->present(
				array(
					'text_excerpt'    => array(),
					'context_excerpt' => '',
				)
			)['rows']
		);
	}

	public function test_image_link_identifies_relationship_and_preserves_full_captured_values() {
		$result = $this->present(
			array(
				'tag_name'          => 'a',
				'link_kind'         => 'image_only',
				'link_destination'  => 'https://example.org/contact/',
				'image_filename'    => 'envelope.svg',
				'image_alt_excerpt' => 'Envelope icon',
				'link_name_excerpt' => 'Envelope icon',
			)
		);
		$this->assertSame( 'Occurrence 12 — Image link — “envelope.svg” to https://example.org/contact/', $result['heading'] );
		$rows = array_column( $result['rows'], 'value', 'label' );
		$this->assertSame( 'Envelope icon', $rows['Current alt text (excerpt)'] );
		$this->assertSame( 'https://example.org/contact/', $rows['Link destination (query and fragment omitted)'] );
		$this->assertArrayNotHasKey( 'link_destination', $result['technical_element'] );
	}

	public function test_headings_fields_and_document_descriptions_are_supported_by_recorded_facts() {
		$this->assertSame(
			'Occurrence 12 — H3 — “Our Services”',
			$this->present(
				array(
					'tag_name'     => 'h3',
					'text_excerpt' => 'Our Services',
				)
			)['heading']
		);
		$this->assertSame(
			'Occurrence 12 — Email field',
			$this->present(
				array(
					'tag_name'   => 'input',
					'input_type' => 'email',
				)
			)['heading']
		);
		$result = $this->present( array( 'tag_name' => 'html' ), array( 'language_state' => 'missing' ), 'elavira.document.language_missing' );
		$this->assertSame( 'Occurrence 12 — Page language', $result['heading'] );
		$this->assertContains( 'No page language attribute was recorded.', array_column( $result['rows'], 'value' ) );
		$this->assertSame( 'Occurrence 12 — Link', $this->present( array( 'tag_name' => 'a' ) )['heading'] );
	}

	public function test_empty_alt_is_distinct_from_missing_or_unrecorded_alt() {
		$this->assertContains(
			'Empty alt text was recorded.',
			array_column(
				$this->present(
					array(
						'tag_name'        => 'img',
						'image_alt_state' => 'empty',
					)
				)['rows'],
				'value'
			)
		);
		$this->assertContains( 'No alt attribute was recorded.', array_column( $this->present( array( 'tag_name' => 'img' ), array( 'alt_state' => 'missing' ) )['rows'], 'value' ) );
		$this->assertCount( 1, $this->present( array( 'tag_name' => 'img' ) )['rows'] );
	}

	public function test_headings_differ_and_only_heading_excerpts_are_shortened() {
		$first  = $this->present(
			array(
				'tag_name'     => 'a',
				'text_excerpt' => 'Learn more',
			)
		);
		$second = $this->present(
			array(
				'tag_name'     => 'a',
				'text_excerpt' => 'Read more',
			)
		);
		$this->assertNotSame( $first['heading'], $second['heading'] );
		$long   = str_repeat( 'Long-name-', 100 );
		$result = $this->present(
			array(
				'tag_name'     => 'a',
				'text_excerpt' => $long,
			)
		);
		$this->assertLessThan( 120, strlen( $result['heading'] ) );
		$this->assertContains( $long, array_column( $result['rows'], 'value' ) );
	}
}
