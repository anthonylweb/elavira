<?php
/**
 * Dependency-free, bounded Core XLSX export.
 *
 * @package Elavira
 */

namespace Elavira\Reports\Export;

use RuntimeException;
use ZipArchive;

/** Writes the small OOXML subset needed by report workbooks. */
final class XlsxExporter {
	/** Build an XLSX file and return its temporary path.
	 *
	 * @param array $sheets Named row sets.
	 * @return string
	 * @throws RuntimeException When a workbook cannot be created.
	 */
	public function create( array $sheets ) {
		if ( ! function_exists( 'wp_tempnam' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			throw new RuntimeException( 'Elavira could not access temporary-file support.' );
		}
		$file = wp_tempnam( 'elavira-report.xlsx' );
		if ( ! $file ) {
			throw new RuntimeException( 'Elavira could not create the export file.' ); }
		$sheet_names = array_keys( $sheets );
		$files       = array(
			'[Content_Types].xml'        => $this->content_types( count( $sheets ) ),
			'_rels/.rels'                => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
			'xl/workbook.xml'            => $this->workbook( $sheet_names ),
			'xl/_rels/workbook.xml.rels' => $this->workbook_relationships( count( $sheets ) ),
			'xl/styles.xml'              => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf xfId="0" fontId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs></styleSheet>',
		);
		$index       = 1;
		foreach ( $sheets as $rows ) {
			$files[ 'xl/worksheets/sheet' . $index . '.xml' ] = $this->worksheet( $rows );
			++$index; }
		$this->write_archive( $file, $files );
		return $file;
	}

	/** Write OOXML parts using native ZIP or WordPress's bundled fallback.
	 *
	 * @param string $file Archive path.
	 * @param array  $files Named contents.
	 * @return void
	 * @throws RuntimeException When neither archive writer succeeds.
	 */
	private function write_archive( $file, array $files ) {
		if ( class_exists( ZipArchive::class ) ) {
			$zip = new ZipArchive();
			if ( true !== $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				throw new RuntimeException( 'Elavira could not create the workbook.' );
			}
			foreach ( $files as $name => $contents ) {
				$zip->addFromString( $name, $contents );
			}
			$zip->close();
			return;
		}
		if ( ! class_exists( 'PclZip' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		}
		if ( ! class_exists( 'PclZip' ) ) {
			throw new RuntimeException( 'XLSX export is unavailable because no ZIP writer is installed.' );
		}
		$entries = array();
		foreach ( $files as $name => $contents ) {
			$entries[] = array(
				PCLZIP_ATT_FILE_NAME    => $name,
				PCLZIP_ATT_FILE_CONTENT => $contents,
			);
		}
		$archive = new \PclZip( $file );
		if ( 0 === $archive->create( $entries ) ) {
			throw new RuntimeException( 'Elavira could not create the workbook.' );
		}
	}

	/** Prevent untrusted cell values from becoming spreadsheet formulas.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public function safe_cell( $value ) {
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value );
		return preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
	}

	/** Create worksheet XML.
	 *
	 * @param array $rows Worksheet rows.
	 * @return string
	 */
	private function worksheet( array $rows ) {
		$xml        = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
		$row_number = 1;
		foreach ( $rows as $row ) {
			$xml   .= '<row r="' . $row_number . '">';
			$column = 1;
			foreach ( $row as $value ) {
				$reference = $this->column( $column ) . $row_number;
				$xml      .= '<c r="' . $reference . '" t="inlineStr"' . ( 1 === $row_number ? ' s="1"' : '' ) . '><is><t xml:space="preserve">' . $this->xml( $this->safe_cell( $value ) ) . '</t></is></c>';
				++$column;
			} $xml .= '</row>';
			++$row_number;
		} return $xml . '</sheetData></worksheet>'; }
	/** Create workbook XML.
	 *
	 * @param array $names Sheet names.
	 * @return string
	 */
	private function workbook( array $names ) {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
		foreach ( $names as $index => $name ) {
			$xml .= '<sheet name="' . $this->xml( substr( $name, 0, 31 ) ) . '" sheetId="' . ( $index + 1 ) . '" r:id="rId' . ( $index + 1 ) . '"/>';
		} return $xml . '</sheets></workbook>'; }
	/** Create workbook relationships XML.
	 *
	 * @param int $count Sheet count.
	 * @return string
	 */
	private function workbook_relationships( $count ) {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'; for ( $index = 1; $index <= $count; ++$index ) {
			$xml .= '<Relationship Id="rId' . $index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml"/>';
		} $xml .= '<Relationship Id="rId' . ( $count + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
		return $xml; }
	/** Create package content-types XML.
	 *
	 * @param int $count Sheet count.
	 * @return string
	 */
	private function content_types( $count ) {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'; for ( $index = 1; $index <= $count; ++$index ) {
			$xml .= '<Override PartName="/xl/worksheets/sheet' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		} return $xml . '</Types>'; }
	/** Convert a column number to an Excel column label.
	 *
	 * @param int $number Column number.
	 * @return string
	 */
	private function column( $number ) {
		$result = '';
		while ( $number > 0 ) {
			--$number;
			$result = chr( 65 + ( $number % 26 ) ) . $result;
			$number = intdiv( $number, 26 );
		} return $result; }
	/** Escape an OOXML text value.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function xml( $value ) {
		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' ); }
}
