<?php
/**
 * Dropdown lists: Asset Types and Locations.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the two dropdown lists.
 */
final class CAPO_Inv_Lists {

	/**
	 * Per-request cache of list rows.
	 *
	 * @var array<string,array>
	 */
	private static $cache = array();

	/**
	 * Validated table name for a list.
	 *
	 * @param string $list "types" or "locations".
	 * @return string
	 */
	private static function table( $list ) {
		if ( ! in_array( $list, array( 'types', 'locations' ), true ) ) {
			$list = 'types';
		}
		return CAPO_Inv_Install::table( $list );
	}

	/**
	 * All rows of a list, keyed by ID, ordered for display.
	 *
	 * @param string $list "types" or "locations".
	 * @return array<int,object>
	 */
	public static function all( $list ) {
		if ( ! isset( self::$cache[ $list ] ) ) {
			global $wpdb;
			$table = self::table( $list );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
			$rows = $wpdb->get_results( "SELECT id, name, is_active, sort_order FROM {$table} ORDER BY sort_order ASC, name ASC" );
			$out  = array();
			foreach ( (array) $rows as $row ) {
				$out[ (int) $row->id ] = $row;
			}
			self::$cache[ $list ] = $out;
		}
		return self::$cache[ $list ];
	}

	/**
	 * Clear the cache after a change.
	 */
	public static function flush() {
		self::$cache = array();
	}

	/**
	 * Options for a dropdown: active entries plus the current value if inactive.
	 *
	 * @param string $list    "types" or "locations".
	 * @param int    $current Current value to keep available.
	 * @return array<int,string>
	 */
	public static function options( $list, $current = 0 ) {
		$out = array();
		foreach ( self::all( $list ) as $id => $row ) {
			if ( (int) $row->is_active || $id === (int) $current ) {
				$out[ $id ] = (int) $row->is_active
					? $row->name
					/* translators: %s: list entry name. */
					: sprintf( __( '%s (inactive)', 'capo-inventory' ), $row->name );
			}
		}
		return $out;
	}

	/**
	 * Whether an ID may be chosen: active, or already the current value.
	 *
	 * @param string $list    "types" or "locations".
	 * @param int    $id      Chosen ID.
	 * @param int    $current Current value.
	 * @return bool
	 */
	public static function is_selectable( $list, $id, $current = 0 ) {
		$all = self::all( $list );
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		return (int) $all[ $id ]->is_active || (int) $id === (int) $current;
	}

	/**
	 * Display name of an entry.
	 *
	 * @param string $list "types" or "locations".
	 * @param int    $id   Entry ID.
	 * @return string
	 */
	public static function name( $list, $id ) {
		$all = self::all( $list );
		if ( isset( $all[ $id ] ) ) {
			return $all[ $id ]->name;
		}
		/* translators: %d: entry ID. */
		return $id ? sprintf( __( 'Unknown (#%d)', 'capo-inventory' ), $id ) : '';
	}

	/**
	 * Whether a name is already used (case-insensitive).
	 *
	 * @param string $list       "types" or "locations".
	 * @param string $name       Name.
	 * @param int    $exclude_id ID to ignore.
	 * @return bool
	 */
	public static function name_exists( $list, $name, $exclude_id = 0 ) {
		$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
		foreach ( self::all( $list ) as $id => $row ) {
			$row_key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $row->name, 'UTF-8' ) : strtolower( $row->name );
			if ( $id !== (int) $exclude_id && $row_key === $key ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add an entry.
	 *
	 * @param string $list "types" or "locations".
	 * @param string $name Name.
	 * @return true|WP_Error
	 */
	public static function add( $list, $name ) {
		global $wpdb;
		$name = CAPO_Inv_Fields::trim( $name );
		$check = self::check_name( $list, $name, 0 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$max = 0;
		foreach ( self::all( $list ) as $row ) {
			$max = max( $max, (int) $row->sort_order );
		}
		$ok = $wpdb->insert(
			self::table( $list ),
			array(
				'name'       => $name,
				'is_active'  => 1,
				'sort_order' => $max + 1,
			),
			array( '%s', '%d', '%d' )
		);
		self::flush();
		return $ok ? true : new WP_Error( 'db', __( 'The entry could not be saved.', 'capo-inventory' ) );
	}

	/**
	 * Rename an entry.
	 *
	 * @param string $list "types" or "locations".
	 * @param int    $id   Entry ID.
	 * @param string $name New name.
	 * @return true|WP_Error
	 */
	public static function rename( $list, $id, $name ) {
		global $wpdb;
		$name  = CAPO_Inv_Fields::trim( $name );
		$check = self::check_name( $list, $name, $id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$ok = $wpdb->update( self::table( $list ), array( 'name' => $name ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
		self::flush();
		return false === $ok ? new WP_Error( 'db', __( 'The entry could not be saved.', 'capo-inventory' ) ) : true;
	}

	/**
	 * Activate or deactivate an entry. Inactive entries stay on existing assets.
	 *
	 * @param string $list   "types" or "locations".
	 * @param int    $id     Entry ID.
	 * @param bool   $active New state.
	 */
	public static function set_active( $list, $id, $active ) {
		global $wpdb;
		$wpdb->update( self::table( $list ), array( 'is_active' => $active ? 1 : 0 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
		self::flush();
	}

	/**
	 * Number of assets and pending requests that reference an entry.
	 *
	 * @param string $list "types" or "locations".
	 * @param int    $id   Entry ID.
	 * @return int
	 */
	public static function usage_count( $list, $id ) {
		global $wpdb;
		$column = 'types' === $list ? 'type_id' : 'location_id';
		$assets = CAPO_Inv_Install::table( 'assets' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and column names are internal.
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$assets} WHERE {$column} = %d", $id ) );

		foreach ( CAPO_Inv_Requests::pending_payloads() as $payload ) {
			if ( isset( $payload[ $column ] ) && (int) $payload[ $column ] === (int) $id ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Delete an unused entry.
	 *
	 * @param string $list "types" or "locations".
	 * @param int    $id   Entry ID.
	 * @return true|WP_Error
	 */
	public static function delete( $list, $id ) {
		global $wpdb;
		if ( self::usage_count( $list, $id ) > 0 ) {
			return new WP_Error( 'in_use', __( 'This entry is used by assets or pending requests. Deactivate it instead.', 'capo-inventory' ) );
		}
		$wpdb->delete( self::table( $list ), array( 'id' => $id ), array( '%d' ) );
		self::flush();
		return true;
	}

	/**
	 * Validate a list entry name.
	 *
	 * @param string $list       List.
	 * @param string $name       Name.
	 * @param int    $exclude_id Entry being renamed.
	 * @return true|WP_Error
	 */
	private static function check_name( $list, $name, $exclude_id ) {
		if ( '' === $name ) {
			return new WP_Error( 'empty', __( 'Please enter a name.', 'capo-inventory' ) );
		}
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $name, 'UTF-8' ) : strlen( $name );
		if ( $len > 100 ) {
			return new WP_Error( 'long', __( 'Names must be 100 characters or fewer.', 'capo-inventory' ) );
		}
		if ( self::name_exists( $list, $name, $exclude_id ) ) {
			return new WP_Error( 'exists', __( 'An entry with this name already exists.', 'capo-inventory' ) );
		}
		return true;
	}
}
