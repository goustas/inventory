<?php
/**
 * Employee requests and the approval workflow.
 *
 * Employees never change approved records directly. They submit a registration,
 * change or removal request; an Inventory Manager or Administrator approves or
 * rejects it. Approved records stay untouched until approval.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Request workflow.
 */
final class CAPO_Inv_Requests {

	const TYPES = array( 'create', 'update', 'delete' );

	/**
	 * Translated request type label.
	 *
	 * @param string $type Type key.
	 * @return string
	 */
	public static function type_label( $type ) {
		$map = array(
			'create' => __( 'New asset registration', 'capo-inventory' ),
			'update' => __( 'Change to asset', 'capo-inventory' ),
			'delete' => __( 'Removal of asset', 'capo-inventory' ),
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : $type;
	}

	/**
	 * Translated request status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$map = array(
			'pending'   => __( 'Pending', 'capo-inventory' ),
			'approved'  => __( 'Approved', 'capo-inventory' ),
			'rejected'  => __( 'Rejected', 'capo-inventory' ),
			'cancelled' => __( 'Cancelled', 'capo-inventory' ),
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : $status;
	}

	/**
	 * One request, with payload decoded, or null.
	 *
	 * @param int  $id         Request ID.
	 * @param bool $for_update Lock the row (inside a transaction).
	 * @return array|null
	 */
	public static function get( $id, $for_update = false ) {
		global $wpdb;
		$table = CAPO_Inv_Install::table( 'requests' );
		$lock  = $for_update ? ' FOR UPDATE' : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d{$lock}", $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$row['payload_data'] = self::decode_payload( $row['payload'] );
		return $row;
	}

	/**
	 * Decode a stored payload into a complete record array.
	 *
	 * @param string|null $json Stored JSON.
	 * @return array
	 */
	private static function decode_payload( $json ) {
		$data = $json ? json_decode( $json, true ) : array();
		$data = is_array( $data ) ? $data : array();
		return array(
			'asset_tag'     => isset( $data['asset_tag'] ) ? (string) $data['asset_tag'] : '',
			'type_id'       => isset( $data['type_id'] ) ? (int) $data['type_id'] : 0,
			'description'   => isset( $data['description'] ) ? (string) $data['description'] : '',
			'location_id'   => isset( $data['location_id'] ) ? (int) $data['location_id'] : 0,
			'serial_number' => isset( $data['serial_number'] ) ? (string) $data['serial_number'] : '',
			'notes'         => isset( $data['notes'] ) ? (string) $data['notes'] : '',
			// Fields the employee changed (change requests only). Null means "all fields".
			'_changed'      => isset( $data['_changed'] ) && is_array( $data['_changed'] )
				? array_values( array_intersect( CAPO_Inv_Fields::editable_fields(), $data['_changed'] ) )
				: null,
		);
	}

	/**
	 * The record a change request would produce: the asset's current approved
	 * values with only the employee's changed fields replaced. Edits a manager
	 * made after the request was submitted are therefore kept on approval.
	 *
	 * @param array $request Request row.
	 * @param array $asset   Current asset.
	 * @return array
	 */
	public static function proposed_record( array $request, array $asset ) {
		$payload = $request['payload_data'];
		$fields  = null === $payload['_changed'] ? CAPO_Inv_Fields::editable_fields() : $payload['_changed'];
		$record  = array();
		foreach ( CAPO_Inv_Fields::editable_fields() as $field ) {
			$record[ $field ] = in_array( $field, $fields, true ) ? $payload[ $field ] : $asset[ $field ];
		}
		return $record;
	}

	/**
	 * Payloads of all pending requests (used to check list-entry usage).
	 *
	 * @return array[]
	 */
	public static function pending_payloads() {
		global $wpdb;
		$table = CAPO_Inv_Install::table( 'requests' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$rows = $wpdb->get_col( "SELECT payload FROM {$table} WHERE status = 'pending' AND payload IS NOT NULL" );
		return array_map( array( __CLASS__, 'decode_payload' ), (array) $rows );
	}

	/**
	 * The pending request of an asset, if any.
	 *
	 * @param int $asset_id Asset ID.
	 * @return array|null
	 */
	public static function pending_for_asset( $asset_id ) {
		global $wpdb;
		$table = CAPO_Inv_Install::table( 'requests' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE asset_id = %d AND status = 'pending' LIMIT 1", $asset_id ) );
		return $id ? self::get( (int) $id ) : null;
	}

	/**
	 * Count pending requests (for the menu badge).
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;
		$table = CAPO_Inv_Install::table( 'requests' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
	}

	/**
	 * List requests.
	 *
	 * @param array $args status (pending|approved|rejected|cancelled|all), employee_id, per_page, page.
	 * @return array{rows: array[], total: int}
	 */
	public static function query( array $args ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'status'      => 'pending',
				'employee_id' => 0,
				'per_page'    => 20,
				'page'        => 1,
			)
		);

		$table  = CAPO_Inv_Install::table( 'requests' );
		$where  = array( '1=1' );
		$params = array();
		if ( in_array( $args['status'], array( 'pending', 'approved', 'rejected', 'cancelled' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( (int) $args['employee_id'] > 0 ) {
			$where[]  = 'employee_id = %d';
			$params[] = (int) $args['employee_id'];
		}
		$where_sql = implode( ' AND ', $where );
		$order     = 'pending' === $args['status'] ? 'ASC' : 'DESC';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name is internal; values are prepared.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		$sql      = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id {$order} LIMIT %d OFFSET %d";
		$params[] = max( 1, (int) $args['per_page'] );
		$params[] = max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] );
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		// phpcs:enable

		$rows = (array) $rows;
		foreach ( $rows as &$row ) {
			$row['payload_data'] = self::decode_payload( $row['payload'] );
		}
		unset( $row );

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Load an asset the employee may request changes to.
	 *
	 * @param int $asset_id    Asset ID.
	 * @param int $employee_id Requesting employee.
	 * @return array|WP_Error
	 */
	private static function own_active_asset( $asset_id, $employee_id ) {
		$asset = CAPO_Inv_Assets::get( $asset_id );
		// The same message for "missing" and "not yours", so other employees' assets are not revealed.
		if ( ! $asset || (int) $asset['employee_id'] !== (int) $employee_id || 'active' !== $asset['status'] ) {
			return new WP_Error( 'not_found', __( 'That asset was not found in your inventory.', 'capo-inventory' ) );
		}
		if ( self::pending_for_asset( $asset_id ) ) {
			return new WP_Error( 'pending', __( 'This asset already has a request awaiting approval. Cancel it or wait for a decision before submitting another.', 'capo-inventory' ) );
		}
		return $asset;
	}

	/**
	 * Insert a request row.
	 *
	 * @param string     $type        Request type.
	 * @param int|null   $asset_id    Asset ID.
	 * @param int        $employee_id Employee.
	 * @param array|null $data        Proposed record.
	 * @param string     $reason      Employee comment.
	 * @param array|null $changed     Fields changed by a change request; null for all.
	 * @return int|WP_Error Request ID.
	 */
	private static function insert( $type, $asset_id, $employee_id, $data, $reason, $changed = null ) {
		global $wpdb;

		// Reserve the Asset Tag / serial only when the request sets them.
		$reserve_tag    = null !== $data && ( null === $changed || in_array( 'asset_tag', $changed, true ) );
		$reserve_serial = null !== $data && ( null === $changed || in_array( 'serial_number', $changed, true ) );
		if ( null !== $data && null !== $changed ) {
			$data['_changed'] = array_values( $changed );
		}

		$ok = $wpdb->insert(
			CAPO_Inv_Install::table( 'requests' ),
			array(
				'request_type'         => $type,
				'asset_id'             => $asset_id ? (int) $asset_id : null,
				'employee_id'          => (int) $employee_id,
				'submitted_by'         => get_current_user_id(),
				'payload'              => null === $data ? null : wp_json_encode( $data ),
				'proposed_tag_norm'    => $reserve_tag ? CAPO_Inv_Fields::normalize_tag( $data['asset_tag'] ) : null,
				'proposed_serial_norm' => $reserve_serial ? CAPO_Inv_Fields::normalize_serial( $data['serial_number'] ) : null,
				'reason'               => '' === $reason ? null : $reason,
				'status'               => 'pending',
				'created_at'           => CAPO_Inv_Fields::now(),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( ! $ok ) {
			return new WP_Error( 'db', __( 'Your request could not be saved because of a database error.', 'capo-inventory' ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Clean an employee comment.
	 *
	 * @param mixed $reason Raw text.
	 * @return string
	 */
	private static function clean_reason( $reason ) {
		$reason = CAPO_Inv_Fields::clean_text( $reason, true );
		return function_exists( 'mb_substr' ) ? mb_substr( $reason, 0, 1000, 'UTF-8' ) : substr( $reason, 0, 1000 );
	}

	/**
	 * Employee submits a new asset for registration.
	 *
	 * @param array  $raw         Unslashed form input.
	 * @param int    $employee_id Logged-in employee.
	 * @param string $reason      Comment.
	 * @return int|WP_Error
	 */
	public static function submit_create( array $raw, $employee_id, $reason = '' ) {
		$data   = CAPO_Inv_Fields::clean_input( $raw );
		$errors = CAPO_Inv_Assets::validate( $data );
		if ( $errors ) {
			return CAPO_Inv_Assets::errors_to_wp_error( $errors );
		}
		$reason = self::clean_reason( $reason );
		$id     = self::insert( 'create', null, $employee_id, $data, $reason );
		if ( ! is_wp_error( $id ) ) {
			CAPO_Inv_Audit::log( null, 'request_submitted', get_current_user_id(), CAPO_Inv_Audit::diff( array(), $data, CAPO_Inv_Fields::editable_fields() ), $id, $reason );
		}
		return $id;
	}

	/**
	 * Employee proposes a change to one of their assets.
	 *
	 * @param int    $asset_id    Asset ID.
	 * @param array  $raw         Unslashed form input.
	 * @param int    $employee_id Logged-in employee.
	 * @param string $reason      Comment.
	 * @return int|WP_Error
	 */
	public static function submit_update( $asset_id, array $raw, $employee_id, $reason = '' ) {
		$asset = self::own_active_asset( $asset_id, $employee_id );
		if ( is_wp_error( $asset ) ) {
			return $asset;
		}
		$data   = CAPO_Inv_Fields::clean_input( $raw );
		$errors = CAPO_Inv_Assets::validate( $data, $asset );
		if ( $errors ) {
			return CAPO_Inv_Assets::errors_to_wp_error( $errors );
		}
		$changes = CAPO_Inv_Audit::diff( $asset, $data, CAPO_Inv_Fields::editable_fields() );
		if ( ! $changes ) {
			return new WP_Error( 'no_change', __( 'You did not change anything, so no request was submitted.', 'capo-inventory' ) );
		}
		$reason = self::clean_reason( $reason );
		$id     = self::insert( 'update', $asset_id, $employee_id, $data, $reason, array_keys( $changes ) );
		if ( ! is_wp_error( $id ) ) {
			CAPO_Inv_Audit::log( $asset_id, 'request_submitted', get_current_user_id(), $changes, $id, $reason );
		}
		return $id;
	}

	/**
	 * Employee asks for one of their assets to be removed.
	 *
	 * @param int    $asset_id    Asset ID.
	 * @param int    $employee_id Logged-in employee.
	 * @param string $reason      Comment.
	 * @return int|WP_Error
	 */
	public static function submit_delete( $asset_id, $employee_id, $reason = '' ) {
		$asset = self::own_active_asset( $asset_id, $employee_id );
		if ( is_wp_error( $asset ) ) {
			return $asset;
		}
		$reason = self::clean_reason( $reason );
		if ( '' === $reason ) {
			return CAPO_Inv_Assets::errors_to_wp_error( array( 'reason' => __( 'Please explain why this asset should be removed.', 'capo-inventory' ) ) );
		}
		$id = self::insert( 'delete', $asset_id, $employee_id, null, $reason );
		if ( ! is_wp_error( $id ) ) {
			CAPO_Inv_Audit::log( $asset_id, 'request_submitted', get_current_user_id(), array(), $id, $reason );
		}
		return $id;
	}

	/**
	 * Employee withdraws their own pending request.
	 *
	 * @param int $request_id Request ID.
	 * @param int $user_id    Logged-in user.
	 * @return true|WP_Error
	 */
	public static function cancel( $request_id, $user_id ) {
		global $wpdb;
		$request = self::get( $request_id );
		if ( ! $request || (int) $request['employee_id'] !== (int) $user_id ) {
			return new WP_Error( 'not_found', __( 'That request was not found.', 'capo-inventory' ) );
		}
		if ( 'pending' !== $request['status'] ) {
			return new WP_Error( 'not_pending', __( 'Only pending requests can be cancelled.', 'capo-inventory' ) );
		}
		$table = CAPO_Inv_Install::table( 'requests' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'cancelled', reviewed_at = %s, reviewer_id = %d WHERE id = %d AND status = 'pending'", CAPO_Inv_Fields::now(), $user_id, $request_id ) );
		if ( ! $updated ) {
			return new WP_Error( 'not_pending', __( 'Only pending requests can be cancelled.', 'capo-inventory' ) );
		}
		CAPO_Inv_Audit::log( $request['asset_id'], 'request_cancelled', $user_id, array(), $request_id );
		return true;
	}

	/**
	 * Whether a reviewer may decide on a request. Inventory Managers may not
	 * approve their own requests; Administrators may.
	 *
	 * @param array $request     Request row.
	 * @param int   $reviewer_id Reviewer.
	 * @return bool
	 */
	public static function can_review( array $request, $reviewer_id ) {
		if ( ! user_can( $reviewer_id, CAPO_Inv_Install::CAP_MANAGE ) ) {
			return false;
		}
		if ( (int) $request['employee_id'] === (int) $reviewer_id && ! user_can( $reviewer_id, 'manage_options' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Approve a request and apply it to the approved records.
	 *
	 * @param int    $request_id  Request ID.
	 * @param int    $reviewer_id Reviewer.
	 * @param string $note        Reviewer note.
	 * @return true|WP_Error
	 */
	public static function approve( $request_id, $reviewer_id, $note = '' ) {
		global $wpdb;
		$note = self::clean_reason( $note );

		$wpdb->query( 'START TRANSACTION' );

		$request = self::get( $request_id, true );
		if ( ! $request || 'pending' !== $request['status'] ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'not_pending', __( 'This request is no longer pending.', 'capo-inventory' ) );
		}
		if ( ! self::can_review( $request, $reviewer_id ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'forbidden', __( 'You cannot decide on your own request. Ask another Inventory Manager or an Administrator.', 'capo-inventory' ) );
		}

		$asset_id = (int) $request['asset_id'];
		switch ( $request['request_type'] ) {
			case 'create':
				$result = CAPO_Inv_Assets::create( $request['payload_data'], (int) $request['employee_id'], $reviewer_id, $request_id );
				if ( ! is_wp_error( $result ) ) {
					$asset_id = (int) $result;
				}
				break;
			case 'update':
				$asset = CAPO_Inv_Assets::get( $asset_id );
				if ( ! $asset || (int) $asset['employee_id'] !== (int) $request['employee_id'] ) {
					$result = new WP_Error( 'moved', __( 'The asset has been reassigned or removed since this request was submitted. Reject the request instead.', 'capo-inventory' ) );
				} else {
					$result = CAPO_Inv_Assets::update( $asset_id, self::proposed_record( $request, $asset ), null, $reviewer_id, $request_id );
				}
				break;
			case 'delete':
				$asset = CAPO_Inv_Assets::get( $asset_id );
				if ( ! $asset || 'active' !== $asset['status'] || (int) $asset['employee_id'] !== (int) $request['employee_id'] ) {
					$result = new WP_Error( 'moved', __( 'The asset has been reassigned or removed since this request was submitted. Reject the request instead.', 'capo-inventory' ) );
				} else {
					$result = CAPO_Inv_Assets::archive( $asset_id, $reviewer_id, $request_id, (string) $request['reason'] );
				}
				break;
			default:
				$result = new WP_Error( 'type', __( 'Unknown request type.', 'capo-inventory' ) );
		}

		if ( is_wp_error( $result ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $result;
		}

		$table   = CAPO_Inv_Install::table( 'requests' );
		$updated = $wpdb->update(
			$table,
			array(
				'status'      => 'approved',
				'asset_id'    => $asset_id ? $asset_id : null,
				'reviewed_at' => CAPO_Inv_Fields::now(),
				'reviewer_id' => (int) $reviewer_id,
				'review_note' => '' === $note ? null : $note,
			),
			array( 'id' => $request_id ),
			array( '%s', '%d', '%s', '%d', '%s' ),
			array( '%d' )
		);
		if ( ! $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'db', __( 'The request could not be marked as approved because of a database error. Nothing was changed.', 'capo-inventory' ) );
		}
		CAPO_Inv_Audit::log( $asset_id, 'request_approved', $reviewer_id, array(), $request_id, $note );

		$wpdb->query( 'COMMIT' );
		return true;
	}

	/**
	 * Reject a request. Approved records are not changed.
	 *
	 * @param int    $request_id  Request ID.
	 * @param int    $reviewer_id Reviewer.
	 * @param string $note        Reason shown to the employee.
	 * @return true|WP_Error
	 */
	public static function reject( $request_id, $reviewer_id, $note = '' ) {
		global $wpdb;
		$note    = self::clean_reason( $note );
		$request = self::get( $request_id );
		if ( ! $request || 'pending' !== $request['status'] ) {
			return new WP_Error( 'not_pending', __( 'This request is no longer pending.', 'capo-inventory' ) );
		}
		if ( ! self::can_review( $request, $reviewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot decide on your own request. Ask another Inventory Manager or an Administrator.', 'capo-inventory' ) );
		}
		$table = CAPO_Inv_Install::table( 'requests' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'rejected', reviewed_at = %s, reviewer_id = %d, review_note = %s WHERE id = %d AND status = 'pending'", CAPO_Inv_Fields::now(), $reviewer_id, $note, $request_id ) );
		if ( ! $updated ) {
			return new WP_Error( 'not_pending', __( 'This request is no longer pending.', 'capo-inventory' ) );
		}
		CAPO_Inv_Audit::log( $request['asset_id'], 'request_rejected', $reviewer_id, array(), $request_id, $note );
		return true;
	}

	/**
	 * Field-by-field comparison of approved values and proposed values.
	 *
	 * @param array $request Request row.
	 * @return array[] Each: field, label, current, proposed, changed.
	 */
	public static function comparison( array $request ) {
		$asset  = $request['asset_id'] ? CAPO_Inv_Assets::get( (int) $request['asset_id'] ) : null;
		$target = ( 'update' === $request['request_type'] && $asset ) ? self::proposed_record( $request, $asset ) : $request['payload_data'];
		$rows   = array();
		foreach ( CAPO_Inv_Fields::editable_fields() as $field ) {
			$current = $asset ? CAPO_Inv_Fields::display_value( $field, $asset[ $field ] ) : '';
			if ( 'delete' === $request['request_type'] ) {
				$proposed = $current;
			} else {
				$value    = $target[ $field ];
				$proposed = ( '' === $value || 0 === $value ) ? '' : CAPO_Inv_Fields::display_value( $field, $value );
			}
			$rows[] = array(
				'field'    => $field,
				'label'    => CAPO_Inv_Fields::label( $field ),
				'current'  => $current,
				'proposed' => $proposed,
				'changed'  => 'delete' !== $request['request_type'] && $current !== $proposed,
			);
		}
		return $rows;
	}
}
