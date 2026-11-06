<?php
/**
 * Asset records: queries, uniqueness checks and approved changes.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Data access and business rules for approved asset records.
 */
final class CAPO_Inv_Assets {

	/**
	 * Fields tracked in the audit log, in display order.
	 *
	 * @return string[]
	 */
	public static function tracked_fields() {
		return array_merge( CAPO_Inv_Fields::editable_fields(), array( 'employee_id' ) );
	}

	/**
	 * One asset as an associative array, or null.
	 *
	 * @param int $id Asset ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = CAPO_Inv_Install::table( 'assets' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Check Asset Tag and Serial Number uniqueness against every asset (active and
	 * archived) and every pending request, ignoring the asset being changed.
	 *
	 * @param array $data             Cleaned record.
	 * @param int   $exclude_asset_id Asset being edited, or 0.
	 * @param int   $exclude_request  Request being approved or edited, or 0.
	 * @return array<string,string> Field => message. Messages never reveal the other record.
	 */
	public static function check_unique( array $data, $exclude_asset_id = 0, $exclude_request = 0 ) {
		global $wpdb;
		$assets   = CAPO_Inv_Install::table( 'assets' );
		$requests = CAPO_Inv_Install::table( 'requests' );
		$errors   = array();

		$tag_norm = CAPO_Inv_Fields::normalize_tag( $data['asset_tag'] );
		if ( '' !== $tag_norm ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are internal.
			$in_assets   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$assets} WHERE asset_tag_norm = %s AND id <> %d LIMIT 1", $tag_norm, $exclude_asset_id ) );
			$in_requests = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$requests} WHERE status = 'pending' AND proposed_tag_norm = %s AND id <> %d AND ( asset_id IS NULL OR asset_id <> %d ) LIMIT 1",
					$tag_norm,
					$exclude_request,
					$exclude_asset_id
				)
			);
			// phpcs:enable
			if ( $in_assets || $in_requests ) {
				$errors['asset_tag'] = CAPO_Inv_Fields::duplicate_tag_message();
			}
		}

		$serial_norm = CAPO_Inv_Fields::normalize_serial( $data['serial_number'] );
		if ( null !== $serial_norm ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are internal.
			$in_assets   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$assets} WHERE serial_norm = %s AND id <> %d LIMIT 1", $serial_norm, $exclude_asset_id ) );
			$in_requests = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$requests} WHERE status = 'pending' AND proposed_serial_norm = %s AND id <> %d AND ( asset_id IS NULL OR asset_id <> %d ) LIMIT 1",
					$serial_norm,
					$exclude_request,
					$exclude_asset_id
				)
			);
			// phpcs:enable
			if ( $in_assets || $in_requests ) {
				$errors['serial_number'] = CAPO_Inv_Fields::duplicate_serial_message();
			}
		}

		return $errors;
	}

	/**
	 * Full validation: format plus uniqueness.
	 *
	 * @param array      $data            Cleaned record.
	 * @param array|null $current         Current asset when editing.
	 * @param int        $exclude_request Request being approved, or 0.
	 * @return array<string,string>
	 */
	public static function validate( array $data, $current = null, $exclude_request = 0 ) {
		$errors = CAPO_Inv_Fields::validate( $data, $current );
		$unique = self::check_unique( $data, $current ? (int) $current['id'] : 0, $exclude_request );
		foreach ( $unique as $field => $message ) {
			if ( ! isset( $errors[ $field ] ) ) {
				$errors[ $field ] = $message;
			}
		}
		return $errors;
	}

	/**
	 * Turn a validation error array into a WP_Error carrying the field messages.
	 *
	 * @param array $errors Field => message.
	 * @return WP_Error
	 */
	public static function errors_to_wp_error( array $errors ) {
		$error = new WP_Error( 'invalid', __( 'Please correct the highlighted fields.', 'capo-inventory' ) );
		$error->add_data( array( 'fields' => $errors ) );
		return $error;
	}

	/**
	 * Map a database duplicate-key failure to the matching user message.
	 *
	 * @return WP_Error
	 */
	private static function db_error() {
		global $wpdb;
		$last = (string) $wpdb->last_error;
		if ( false !== stripos( $last, 'Duplicate' ) ) {
			$field = false !== stripos( $last, 'serial_norm' ) ? 'serial_number' : 'asset_tag';
			$msg   = 'serial_number' === $field ? CAPO_Inv_Fields::duplicate_serial_message() : CAPO_Inv_Fields::duplicate_tag_message();
			return self::errors_to_wp_error( array( $field => $msg ) );
		}
		return new WP_Error( 'db', __( 'The asset could not be saved because of a database error.', 'capo-inventory' ) );
	}

	/**
	 * Column values for an insert or update.
	 *
	 * @param array $data Cleaned record.
	 * @return array
	 */
	private static function columns( array $data ) {
		return array(
			'asset_tag'      => $data['asset_tag'],
			'asset_tag_norm' => CAPO_Inv_Fields::normalize_tag( $data['asset_tag'] ),
			'type_id'        => (int) $data['type_id'],
			'description'    => $data['description'],
			'location_id'    => (int) $data['location_id'],
			'serial_number'  => $data['serial_number'],
			'serial_norm'    => CAPO_Inv_Fields::normalize_serial( $data['serial_number'] ),
			'notes'          => $data['notes'],
		);
	}

	/**
	 * Column formats for wpdb, matching columns() plus extras.
	 *
	 * @param array $columns Column => value.
	 * @return array
	 */
	private static function formats( array $columns ) {
		$int     = array( 'type_id', 'location_id', 'employee_id', 'created_by', 'updated_by' );
		$formats = array();
		foreach ( array_keys( $columns ) as $key ) {
			// wpdb writes SQL NULL for null values whatever the format.
			$formats[] = in_array( $key, $int, true ) ? '%d' : '%s';
		}
		return $formats;
	}

	/**
	 * Create an approved asset.
	 *
	 * @param array $data        Cleaned record.
	 * @param int   $employee_id Assigned employee.
	 * @param int   $actor_id    User performing the change.
	 * @param int   $request_id  Request being approved, or 0.
	 * @return int|WP_Error New asset ID.
	 */
	public static function create( array $data, $employee_id, $actor_id, $request_id = 0 ) {
		global $wpdb;

		$errors = self::validate( $data, null, $request_id );
		if ( ! self::is_employee( $employee_id ) ) {
			$errors['employee_id'] = __( 'Please select a valid employee.', 'capo-inventory' );
		}
		if ( $errors ) {
			return self::errors_to_wp_error( $errors );
		}

		$now     = CAPO_Inv_Fields::now();
		$columns = self::columns( $data ) + array(
			'employee_id' => (int) $employee_id,
			'status'      => 'active',
			'created_at'  => $now,
			'created_by'  => (int) $actor_id,
			'updated_at'  => $now,
			'updated_by'  => (int) $actor_id,
		);

		if ( ! $wpdb->insert( CAPO_Inv_Install::table( 'assets' ), $columns, self::formats( $columns ) ) ) {
			return self::db_error();
		}
		$id = (int) $wpdb->insert_id;

		$after   = $data + array( 'employee_id' => (int) $employee_id );
		$changes = CAPO_Inv_Audit::diff( array(), $after, self::tracked_fields() );
		CAPO_Inv_Audit::log( $id, 'created', $actor_id, $changes, $request_id );

		return $id;
	}

	/**
	 * Update an approved asset.
	 *
	 * @param int      $id          Asset ID.
	 * @param array    $data        Cleaned record.
	 * @param int|null $employee_id New assigned employee, or null to keep.
	 * @param int      $actor_id    User performing the change.
	 * @param int      $request_id  Request being approved, or 0.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data, $employee_id, $actor_id, $request_id = 0 ) {
		global $wpdb;

		$current = self::get( $id );
		if ( ! $current ) {
			return new WP_Error( 'not_found', __( 'The asset no longer exists.', 'capo-inventory' ) );
		}
		if ( 'active' !== $current['status'] ) {
			return new WP_Error( 'archived', __( 'Archived assets cannot be edited. Restore the asset first.', 'capo-inventory' ) );
		}

		$errors = self::validate( $data, $current, $request_id );
		if ( null !== $employee_id && (int) $employee_id !== (int) $current['employee_id'] && ! self::is_employee( $employee_id ) ) {
			$errors['employee_id'] = __( 'Please select a valid employee.', 'capo-inventory' );
		}
		if ( $errors ) {
			return self::errors_to_wp_error( $errors );
		}

		$new_employee = null === $employee_id ? (int) $current['employee_id'] : (int) $employee_id;
		$after        = $data + array( 'employee_id' => $new_employee );
		$changes      = CAPO_Inv_Audit::diff( $current, $after, self::tracked_fields() );
		if ( ! $changes ) {
			return true;
		}

		$columns = self::columns( $data ) + array(
			'employee_id' => $new_employee,
			'updated_at'  => CAPO_Inv_Fields::now(),
			'updated_by'  => (int) $actor_id,
		);

		if ( false === $wpdb->update( CAPO_Inv_Install::table( 'assets' ), $columns, array( 'id' => $id ), self::formats( $columns ), array( '%d' ) ) ) {
			return self::db_error();
		}

		CAPO_Inv_Audit::log( $id, 'updated', $actor_id, $changes, $request_id );
		return true;
	}

	/**
	 * Archive an asset. Archived assets keep their Asset Tag reserved.
	 *
	 * @param int    $id         Asset ID.
	 * @param int    $actor_id   User.
	 * @param int    $request_id Request being approved, or 0.
	 * @param string $note       Optional reason.
	 * @return true|WP_Error
	 */
	public static function archive( $id, $actor_id, $request_id = 0, $note = '' ) {
		return self::set_status( $id, 'archived', $actor_id, $request_id, $note );
	}

	/**
	 * Restore an archived asset to active.
	 *
	 * @param int    $id       Asset ID.
	 * @param int    $actor_id User.
	 * @param string $note     Optional reason.
	 * @return true|WP_Error
	 */
	public static function restore( $id, $actor_id, $note = '' ) {
		return self::set_status( $id, 'active', $actor_id, 0, $note );
	}

	/**
	 * Change record status with audit.
	 *
	 * @param int    $id         Asset ID.
	 * @param string $status     "active" or "archived".
	 * @param int    $actor_id   User.
	 * @param int    $request_id Request ID or 0.
	 * @param string $note       Note.
	 * @return true|WP_Error
	 */
	private static function set_status( $id, $status, $actor_id, $request_id, $note ) {
		global $wpdb;
		$current = self::get( $id );
		if ( ! $current ) {
			return new WP_Error( 'not_found', __( 'The asset no longer exists.', 'capo-inventory' ) );
		}
		if ( $current['status'] === $status ) {
			return true;
		}
		if ( 'archived' === $status && $request_id <= 0 && CAPO_Inv_Requests::pending_for_asset( $id ) ) {
			return new WP_Error( 'pending', __( 'This asset has a pending request. Approve or reject it before archiving the asset.', 'capo-inventory' ) );
		}

		$now     = CAPO_Inv_Fields::now();
		$columns = array(
			'status'      => $status,
			'archived_at' => 'archived' === $status ? $now : null,
			'updated_at'  => $now,
			'updated_by'  => (int) $actor_id,
		);
		$formats = array( '%s', '%s', '%s', '%d' );

		if ( false === $wpdb->update( CAPO_Inv_Install::table( 'assets' ), $columns, array( 'id' => $id ), $formats, array( '%d' ) ) ) {
			return self::db_error();
		}

		$changes = array(
			'status' => array(
				CAPO_Inv_Fields::status_label( $current['status'] ),
				CAPO_Inv_Fields::status_label( $status ),
			),
		);
		CAPO_Inv_Audit::log( $id, 'archived' === $status ? 'archived' : 'restored', $actor_id, $changes, $request_id, $note );
		return true;
	}

	/**
	 * Whether a user can hold assets.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_employee( $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : false;
		return $user && $user->has_cap( CAPO_Inv_Install::CAP_USE );
	}

	/**
	 * Users who can hold assets, for dropdowns.
	 *
	 * @return WP_User[]
	 */
	public static function employees() {
		return get_users(
			array(
				'capability' => CAPO_Inv_Install::CAP_USE,
				'orderby'    => 'display_name',
				'order'      => 'ASC',
				'fields'     => array( 'ID', 'display_name', 'user_email' ),
			)
		);
	}

	/**
	 * All active assets of one employee. No pagination.
	 *
	 * @param int $employee_id User ID.
	 * @return array[]
	 */
	public static function active_for_employee( $employee_id ) {
		global $wpdb;
		$table = CAPO_Inv_Install::table( 'assets' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE employee_id = %d AND status = 'active' ORDER BY asset_tag ASC", $employee_id ), ARRAY_A );
		return (array) $rows;
	}

	/**
	 * Count active assets per employee.
	 *
	 * @return array<int,int> Employee ID => count.
	 */
	public static function active_counts() {
		global $wpdb;
		$table = CAPO_Inv_Install::table( 'assets' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$rows = $wpdb->get_results( "SELECT employee_id, COUNT(*) AS n FROM {$table} WHERE status = 'active' GROUP BY employee_id", ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['employee_id'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * Search and filter assets for the admin table and exports.
	 *
	 * @param array $args {
	 *     @type string $search      Text searched in tag, description, serial, notes, employee name and email.
	 *     @type string $status      active|archived|all. Default active.
	 *     @type int    $type_id     Asset Type filter.
	 *     @type int    $location_id Location filter.
	 *     @type int    $employee_id Employee filter.
	 *     @type string $orderby     Sort column key.
	 *     @type string $order       ASC|DESC.
	 *     @type int    $per_page    Rows per page; 0 for all rows.
	 *     @type int    $page        1-based page.
	 * }
	 * @return array{rows: array[], total: int}
	 */
	public static function query( array $args ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'search'      => '',
				'status'      => 'active',
				'type_id'     => 0,
				'location_id' => 0,
				'employee_id' => 0,
				'orderby'     => 'asset_tag',
				'order'       => 'ASC',
				'per_page'    => 20,
				'page'        => 1,
			)
		);

		$table  = CAPO_Inv_Install::table( 'assets' );
		$where  = array( '1=1' );
		$params = array();

		if ( in_array( $args['status'], array( 'active', 'archived' ), true ) ) {
			$where[]  = 'a.status = %s';
			$params[] = $args['status'];
		}
		foreach ( array( 'type_id', 'location_id', 'employee_id' ) as $col ) {
			if ( (int) $args[ $col ] > 0 ) {
				$where[]  = "a.{$col} = %d";
				$params[] = (int) $args[ $col ];
			}
		}
		$search = CAPO_Inv_Fields::trim( $args['search'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( a.asset_tag LIKE %s OR a.description LIKE %s OR a.serial_number LIKE %s OR a.notes LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s )';
			$params   = array_merge( $params, array_fill( 0, 6, $like ) );
		}

		$sortable = array(
			'asset_tag'     => 'a.asset_tag',
			'description'   => 'a.description',
			'serial_number' => 'a.serial_number',
			'employee'      => 'u.display_name',
			'updated_at'    => 'a.updated_at',
			'id'            => 'a.id',
		);
		$orderby = isset( $sortable[ $args['orderby'] ] ) ? $sortable[ $args['orderby'] ] : 'a.asset_tag';
		$order   = 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC';

		$where_sql = implode( ' AND ', $where );
		$from      = "{$table} a LEFT JOIN {$wpdb->users} u ON u.ID = a.employee_id";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- identifiers are whitelisted; values are prepared.
		$count_sql = "SELECT COUNT(*) FROM {$from} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		$sql = "SELECT a.*, u.display_name AS employee_name, u.user_email AS employee_email FROM {$from} WHERE {$where_sql} ORDER BY {$orderby} {$order}, a.id ASC";
		if ( (int) $args['per_page'] > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = (int) $args['per_page'];
			$params[] = max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] );
		}
		$rows = $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, ARRAY_A );
		// phpcs:enable

		return array(
			'rows'  => (array) $rows,
			'total' => $total,
		);
	}
}
