<?php
/**
 * Field names, labels, normalisation and validation for asset records.
 *
 * Every screen, export and audit display takes its labels from here so the
 * field names stay consistent across the plugin.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Asset field definitions and rules.
 */
final class CAPO_Inv_Fields {

	const TAG_MAX         = 64;
	const SERIAL_MAX      = 100;
	const DESCRIPTION_MAX = 190;
	const NOTES_MAX       = 2000;

	/**
	 * Letters, digits and hyphens; must start and end with a letter or digit.
	 */
	const TAG_PATTERN = '/^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/';

	/**
	 * Fields an asset request can propose, in display order.
	 *
	 * @return string[]
	 */
	public static function editable_fields() {
		return array( 'asset_tag', 'type_id', 'description', 'location_id', 'serial_number', 'notes' );
	}

	/**
	 * Translated labels for every asset field.
	 *
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			'id'            => __( 'Internal ID', 'capo-inventory' ),
			'asset_tag'     => __( 'Asset Tag / Inventory Number', 'capo-inventory' ),
			'type_id'       => __( 'Asset Type', 'capo-inventory' ),
			'description'   => __( 'Description / Make and Model', 'capo-inventory' ),
			'location_id'   => __( 'Location', 'capo-inventory' ),
			'serial_number' => __( 'Serial Number', 'capo-inventory' ),
			'notes'         => __( 'Notes', 'capo-inventory' ),
			'employee_id'   => __( 'Assigned Employee', 'capo-inventory' ),
			'status'        => __( 'Record Status', 'capo-inventory' ),
			'created_at'    => __( 'Created', 'capo-inventory' ),
			'updated_at'    => __( 'Last Updated', 'capo-inventory' ),
			'archived_at'   => __( 'Archived', 'capo-inventory' ),
		);
	}

	/**
	 * Label for one field.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	public static function label( $key ) {
		$labels = self::labels();
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
	}

	/**
	 * Translated record status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$map = array(
			'active'   => __( 'Active', 'capo-inventory' ),
			'archived' => __( 'Archived', 'capo-inventory' ),
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : $status;
	}

	/**
	 * Trim ordinary and Unicode whitespace (including non-breaking spaces) from both ends.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function trim( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = preg_replace( '/^[\s\x{00A0}\x{FEFF}]+|[\s\x{00A0}\x{FEFF}]+$/u', '', $value );
		return null === $value ? '' : $value;
	}

	/**
	 * Clean an Asset Tag as entered. Kept as text so leading zeros survive.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function clean_tag( $value ) {
		return self::trim( $value );
	}

	/**
	 * Comparison key used for Asset Tag uniqueness: trimmed and upper-cased.
	 *
	 * @param mixed $value Raw or cleaned value.
	 * @return string
	 */
	public static function normalize_tag( $value ) {
		return strtoupper( self::clean_tag( $value ) );
	}

	/**
	 * Comparison key for serial numbers; null when blank so blanks never collide.
	 *
	 * @param mixed $value Raw or cleaned value.
	 * @return string|null
	 */
	public static function normalize_serial( $value ) {
		$value = self::trim( $value );
		if ( '' === $value ) {
			return null;
		}
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $value, 'UTF-8' ) : strtoupper( $value );
	}

	/**
	 * String length that counts characters, not bytes.
	 *
	 * @param string $value Value.
	 * @return int
	 */
	private static function length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * Clean raw input into a record array. Does not validate.
	 *
	 * @param array $raw Unslashed input.
	 * @return array
	 */
	public static function clean_input( array $raw ) {
		$get = static function ( $key ) use ( $raw ) {
			return isset( $raw[ $key ] ) ? $raw[ $key ] : '';
		};

		return array(
			// Not run through sanitize_text_field(): that would silently drop characters
			// such as "<" or "%20" and could turn a mistyped tag into a different valid one.
			// The strict format check in validate() rejects anything unexpected instead.
			'asset_tag'     => self::clean_tag( self::clean_text( $get( 'asset_tag' ) ) ),
			'type_id'       => absint( $get( 'type_id' ) ),
			'description'   => self::clean_text( $get( 'description' ) ),
			'location_id'   => absint( $get( 'location_id' ) ),
			'serial_number' => self::clean_text( $get( 'serial_number' ) ),
			'notes'         => self::clean_text( $get( 'notes' ), true ),
		);
	}

	/**
	 * Clean free text for storage without altering what the user typed.
	 *
	 * Removes invalid UTF-8 and control characters, and line breaks from
	 * single-line fields. HTML is kept as typed (for example "Monitor <24in>")
	 * and is escaped wherever it is displayed.
	 *
	 * @param mixed $value     Raw value.
	 * @param bool  $multiline Keep line breaks.
	 * @return string
	 */
	public static function clean_text( $value, $multiline = false ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = wp_check_invalid_utf8( $value );
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		if ( ! $multiline ) {
			$value = str_replace( array( "\n", "\t" ), ' ', $value );
		}
		$value = preg_replace( $multiline ? '/[^\P{Cc}\n\t]/u' : '/\p{Cc}/u', '', $value );
		return self::trim( null === $value ? '' : $value );
	}

	/**
	 * Validate the format of a cleaned record. Uniqueness is checked separately.
	 *
	 * @param array      $data    Cleaned record from clean_input().
	 * @param array|null $current Current approved record when editing, so a now-inactive
	 *                            type or location already on the asset may be kept.
	 * @return array<string,string> Field key => error message. Empty when valid.
	 */
	public static function validate( array $data, $current = null ) {
		$errors = array();

		if ( '' === $data['asset_tag'] ) {
			$errors['asset_tag'] = __( 'Asset Tag / Inventory Number is required.', 'capo-inventory' );
		} elseif ( self::length( $data['asset_tag'] ) > self::TAG_MAX ) {
			/* translators: %d: maximum number of characters. */
			$errors['asset_tag'] = sprintf( __( 'Asset Tag / Inventory Number must be %d characters or fewer.', 'capo-inventory' ), self::TAG_MAX );
		} elseif ( ! preg_match( self::TAG_PATTERN, $data['asset_tag'] ) ) {
			$errors['asset_tag'] = __( 'Asset Tag / Inventory Number may contain only letters, numbers and hyphens, and must start and end with a letter or number.', 'capo-inventory' );
		}

		$current_type = $current ? (int) $current['type_id'] : 0;
		if ( ! $data['type_id'] ) {
			$errors['type_id'] = __( 'Please select an Asset Type.', 'capo-inventory' );
		} elseif ( ! CAPO_Inv_Lists::is_selectable( 'types', $data['type_id'], $current_type ) ) {
			$errors['type_id'] = __( 'The selected Asset Type is not available.', 'capo-inventory' );
		}

		$current_location = $current ? (int) $current['location_id'] : 0;
		if ( ! $data['location_id'] ) {
			$errors['location_id'] = __( 'Please select a Location.', 'capo-inventory' );
		} elseif ( ! CAPO_Inv_Lists::is_selectable( 'locations', $data['location_id'], $current_location ) ) {
			$errors['location_id'] = __( 'The selected Location is not available.', 'capo-inventory' );
		}

		if ( self::length( $data['description'] ) > self::DESCRIPTION_MAX ) {
			/* translators: %d: maximum number of characters. */
			$errors['description'] = sprintf( __( 'Description / Make and Model must be %d characters or fewer.', 'capo-inventory' ), self::DESCRIPTION_MAX );
		}

		if ( self::length( $data['serial_number'] ) > self::SERIAL_MAX ) {
			/* translators: %d: maximum number of characters. */
			$errors['serial_number'] = sprintf( __( 'Serial Number must be %d characters or fewer.', 'capo-inventory' ), self::SERIAL_MAX );
		}

		if ( self::length( $data['notes'] ) > self::NOTES_MAX ) {
			/* translators: %d: maximum number of characters. */
			$errors['notes'] = sprintf( __( 'Notes must be %d characters or fewer.', 'capo-inventory' ), self::NOTES_MAX );
		}

		return $errors;
	}

	/**
	 * Duplicate Asset Tag message. Never names the other asset or its holder.
	 *
	 * @return string
	 */
	public static function duplicate_tag_message() {
		return __( 'This Asset Tag / Inventory Number is already registered or awaiting approval. Please check the number on the sticker. If it is correct, contact the IT department.', 'capo-inventory' );
	}

	/**
	 * Duplicate serial message. Never names the other asset or its holder.
	 *
	 * @return string
	 */
	public static function duplicate_serial_message() {
		return __( 'This Serial Number is already registered or awaiting approval. Please check it. If it is correct, contact the IT department. You may leave the Serial Number blank.', 'capo-inventory' );
	}

	/**
	 * Format a stored UTC datetime in the WordPress timezone.
	 *
	 * @param string|null $gmt MySQL datetime in UTC.
	 * @return string
	 */
	public static function format_datetime( $gmt ) {
		if ( empty( $gmt ) || '0000-00-00 00:00:00' === $gmt ) {
			return '';
		}
		$ts = strtotime( $gmt . ' UTC' );
		if ( false === $ts ) {
			return '';
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
	}

	/**
	 * Current UTC time in MySQL format.
	 *
	 * @return string
	 */
	public static function now() {
		return current_time( 'mysql', true );
	}

	/**
	 * Human-readable value of a record field, for tables, comparisons and audit logs.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Stored value.
	 * @return string
	 */
	public static function display_value( $key, $value ) {
		switch ( $key ) {
			case 'type_id':
				return CAPO_Inv_Lists::name( 'types', (int) $value );
			case 'location_id':
				return CAPO_Inv_Lists::name( 'locations', (int) $value );
			case 'employee_id':
				return self::employee_name( (int) $value );
			case 'status':
				return self::status_label( (string) $value );
			default:
				return null === $value ? '' : (string) $value;
		}
	}

	/**
	 * "Display Name (email)" for a user, or a placeholder for a deleted account.
	 *
	 * @param int  $user_id    User ID.
	 * @param bool $with_email Include the email address.
	 * @return string
	 */
	public static function employee_name( $user_id, $with_email = true ) {
		if ( ! $user_id ) {
			return '';
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			/* translators: %d: user ID. */
			return sprintf( __( 'Deleted user #%d', 'capo-inventory' ), $user_id );
		}
		return $with_email ? sprintf( '%s (%s)', $user->display_name, $user->user_email ) : $user->display_name;
	}
}
