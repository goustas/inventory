<?php
/**
 * Audit log.
 *
 * Changes are stored as human-readable values at the time of the change, so
 * renaming a type or location later does not rewrite history.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Audit log writer and reader.
 */
final class CAPO_Inv_Audit {

	/**
	 * Translated labels for audit actions.
	 *
	 * @param string $action Action key.
	 * @return string
	 */
	public static function action_label( $action ) {
		$map = array(
			'created'           => __( 'Asset registered', 'capo-inventory' ),
			'updated'           => __( 'Asset updated', 'capo-inventory' ),
			'archived'          => __( 'Asset archived', 'capo-inventory' ),
			'restored'          => __( 'Asset restored', 'capo-inventory' ),
			'request_submitted' => __( 'Request submitted', 'capo-inventory' ),
			'request_approved'  => __( 'Request approved', 'capo-inventory' ),
			'request_rejected'  => __( 'Request rejected', 'capo-inventory' ),
			'request_cancelled' => __( 'Request cancelled', 'capo-inventory' ),
		);
		return isset( $map[ $action ] ) ? $map[ $action ] : $action;
	}

	/**
	 * Field-by-field differences as display values.
	 *
	 * @param array    $before Previous record (empty for a new asset).
	 * @param array    $after  New record.
	 * @param string[] $fields Fields to compare.
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function diff( array $before, array $after, array $fields ) {
		$changes = array();
		foreach ( $fields as $field ) {
			$old = array_key_exists( $field, $before ) ? (string) $before[ $field ] : '';
			$new = array_key_exists( $field, $after ) ? (string) $after[ $field ] : '';
			if ( in_array( $field, array( 'type_id', 'location_id', 'employee_id' ), true ) ) {
				$old = $old ? (string) (int) $old : '';
				$new = $new ? (string) (int) $new : '';
			}
			if ( $old === $new ) {
				continue;
			}
			$changes[ $field ] = array(
				'' === $old ? '' : CAPO_Inv_Fields::display_value( $field, $old ),
				'' === $new ? '' : CAPO_Inv_Fields::display_value( $field, $new ),
			);
		}
		return $changes;
	}

	/**
	 * Write an audit entry.
	 *
	 * @param int|null $asset_id   Asset ID, if any.
	 * @param string   $action     Action key.
	 * @param int      $actor_id   User who acted.
	 * @param array    $changes    Field => [old, new] display values.
	 * @param int      $request_id Related request, or 0.
	 * @param string   $note       Optional note.
	 */
	public static function log( $asset_id, $action, $actor_id, array $changes = array(), $request_id = 0, $note = '' ) {
		global $wpdb;
		$wpdb->insert(
			CAPO_Inv_Install::table( 'audit' ),
			array(
				'asset_id'   => $asset_id ? (int) $asset_id : null,
				'request_id' => $request_id ? (int) $request_id : null,
				'action'     => $action,
				'actor_id'   => (int) $actor_id,
				'changes'    => $changes ? wp_json_encode( $changes ) : null,
				'note'       => '' === $note ? null : $note,
				'created_at' => CAPO_Inv_Fields::now(),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Read audit entries, newest first.
	 *
	 * @param array $args asset_id, search (Asset Tag), per_page, page.
	 * @return array{rows: array[], total: int}
	 */
	public static function query( array $args ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'asset_id' => 0,
				'search'   => '',
				'per_page' => 50,
				'page'     => 1,
			)
		);

		$audit  = CAPO_Inv_Install::table( 'audit' );
		$assets = CAPO_Inv_Install::table( 'assets' );
		$where  = array( '1=1' );
		$params = array();

		if ( (int) $args['asset_id'] > 0 ) {
			$where[]  = 'l.asset_id = %d';
			$params[] = (int) $args['asset_id'];
		}
		$search = CAPO_Inv_Fields::trim( $args['search'] );
		if ( '' !== $search ) {
			$where[]  = 'a.asset_tag LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		$where_sql = implode( ' AND ', $where );
		$from      = "{$audit} l LEFT JOIN {$assets} a ON a.id = l.asset_id";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table names are internal; values are prepared.
		$count_sql = "SELECT COUNT(*) FROM {$from} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		$sql      = "SELECT l.*, a.asset_tag FROM {$from} WHERE {$where_sql} ORDER BY l.id DESC LIMIT %d OFFSET %d";
		$params[] = max( 1, (int) $args['per_page'] );
		$params[] = max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] );
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		// phpcs:enable

		return array(
			'rows'  => (array) $rows,
			'total' => $total,
		);
	}

	/**
	 * Decode the stored changes of an entry.
	 *
	 * @param array $row Audit row.
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function changes( array $row ) {
		if ( empty( $row['changes'] ) ) {
			return array();
		}
		$decoded = json_decode( $row['changes'], true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
