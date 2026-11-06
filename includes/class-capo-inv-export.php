<?php
/**
 * CSV export of assets.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Streams the asset register as CSV.
 *
 * Two formats:
 * - "csv":   plain values exactly as stored (best for other systems).
 * - "excel": Asset Tags and serial numbers are written as ="000123" so Excel
 *            keeps leading zeros when the file is opened by double-clicking.
 */
final class CAPO_Inv_Export {

	/**
	 * Protect a free-text cell from spreadsheet formula injection.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	public static function safe_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Cell for an identifier (Asset Tag, serial) that must keep leading zeros.
	 *
	 * @param string $value  Value.
	 * @param string $format "csv" or "excel".
	 * @return string
	 */
	public static function id_cell( $value, $format ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		// Only wrap values made of safe characters; anything else is treated as free text.
		if ( 'excel' === $format && preg_match( '/^[A-Za-z0-9][A-Za-z0-9 \/._-]*$/', $value ) ) {
			return '="' . $value . '"';
		}
		return self::safe_cell( $value );
	}

	/**
	 * Header row.
	 *
	 * @return string[]
	 */
	public static function headers() {
		return array(
			CAPO_Inv_Fields::label( 'id' ),
			CAPO_Inv_Fields::label( 'asset_tag' ),
			CAPO_Inv_Fields::label( 'type_id' ),
			CAPO_Inv_Fields::label( 'description' ),
			CAPO_Inv_Fields::label( 'serial_number' ),
			CAPO_Inv_Fields::label( 'location_id' ),
			CAPO_Inv_Fields::label( 'notes' ),
			CAPO_Inv_Fields::label( 'employee_id' ),
			__( 'Employee Email', 'capo-inventory' ),
			CAPO_Inv_Fields::label( 'status' ),
			CAPO_Inv_Fields::label( 'created_at' ),
			CAPO_Inv_Fields::label( 'updated_at' ),
			CAPO_Inv_Fields::label( 'archived_at' ),
		);
	}

	/**
	 * One data row.
	 *
	 * @param array  $row    Asset row from CAPO_Inv_Assets::query().
	 * @param string $format "csv" or "excel".
	 * @return string[]
	 */
	public static function row( array $row, $format ) {
		$employee = $row['employee_id'] ? ( isset( $row['employee_name'] ) && null !== $row['employee_name'] ? $row['employee_name'] : CAPO_Inv_Fields::employee_name( (int) $row['employee_id'], false ) ) : '';
		return array(
			(string) (int) $row['id'],
			self::id_cell( $row['asset_tag'], $format ),
			self::safe_cell( CAPO_Inv_Lists::name( 'types', (int) $row['type_id'] ) ),
			self::safe_cell( $row['description'] ),
			self::id_cell( $row['serial_number'], $format ),
			self::safe_cell( CAPO_Inv_Lists::name( 'locations', (int) $row['location_id'] ) ),
			self::safe_cell( $row['notes'] ),
			self::safe_cell( $employee ),
			self::safe_cell( isset( $row['employee_email'] ) ? (string) $row['employee_email'] : '' ),
			CAPO_Inv_Fields::status_label( $row['status'] ),
			CAPO_Inv_Fields::format_datetime( $row['created_at'] ),
			CAPO_Inv_Fields::format_datetime( $row['updated_at'] ),
			CAPO_Inv_Fields::format_datetime( $row['archived_at'] ),
		);
	}

	/**
	 * Send the CSV for the given filters and exit. Caller checks permissions and nonce.
	 *
	 * @param array  $filters Filters for CAPO_Inv_Assets::query().
	 * @param string $format  "csv" or "excel".
	 */
	public static function stream( array $filters, $format ) {
		$format             = 'excel' === $format ? 'excel' : 'csv';
		$filters['per_page'] = 0;
		$result             = CAPO_Inv_Assets::query( $filters );

		$filename = sprintf( 'capo-inventory-%s%s.csv', wp_date( 'Y-m-d-His' ), 'excel' === $format ? '-excel' : '' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel shows Greek correctly.
		fputcsv( $out, self::headers(), ",", "\"", "" );
		foreach ( $result['rows'] as $row ) {
			fputcsv( $out, self::row( $row, $format ), ",", "\"", "" );
		}
		fclose( $out );
		exit;
	}
}
