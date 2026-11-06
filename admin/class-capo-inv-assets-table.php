<?php
/**
 * All Assets list table.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Searchable, filterable, paginated asset table for managers.
 */
class CAPO_Inv_Assets_Table extends WP_List_Table {

	/**
	 * Current filters, also used for the export links.
	 *
	 * @var array
	 */
	public $filters = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'asset',
				'plural'   => 'assets',
				'ajax'     => false,
			)
		);

		$status        = CAPO_Inv_Admin::get_key( 'status', 'active' );
		$this->filters = array(
			'search'      => CAPO_Inv_Admin::get_text( 's' ),
			'status'      => in_array( $status, array( 'active', 'archived', 'all' ), true ) ? $status : 'active',
			'type_id'     => CAPO_Inv_Admin::get_int( 'type_id' ),
			'location_id' => CAPO_Inv_Admin::get_int( 'location_id' ),
			'employee_id' => CAPO_Inv_Admin::get_int( 'employee_id' ),
			'orderby'     => CAPO_Inv_Admin::get_key( 'orderby', 'asset_tag' ),
			'order'       => CAPO_Inv_Admin::get_key( 'order', 'asc' ),
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'asset_tag'     => CAPO_Inv_Fields::label( 'asset_tag' ),
			'type_id'       => CAPO_Inv_Fields::label( 'type_id' ),
			'description'   => CAPO_Inv_Fields::label( 'description' ),
			'serial_number' => CAPO_Inv_Fields::label( 'serial_number' ),
			'location_id'   => CAPO_Inv_Fields::label( 'location_id' ),
			'employee'      => CAPO_Inv_Fields::label( 'employee_id' ),
			'status'        => CAPO_Inv_Fields::label( 'status' ),
			'updated_at'    => CAPO_Inv_Fields::label( 'updated_at' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'asset_tag'     => array( 'asset_tag', false ),
			'description'   => array( 'description', false ),
			'serial_number' => array( 'serial_number', false ),
			'employee'      => array( 'employee', false ),
			'updated_at'    => array( 'updated_at', true ),
		);
	}

	/**
	 * Status views.
	 *
	 * @return array
	 */
	protected function get_views() {
		$views = array();
		$base  = remove_query_arg( array( 'status', 'paged' ) );
		foreach ( array(
			'active'   => __( 'Active', 'capo-inventory' ),
			'archived' => __( 'Archived', 'capo-inventory' ),
			'all'      => __( 'All', 'capo-inventory' ),
		) as $key => $label ) {
			$views[ $key ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( add_query_arg( 'status', $key, $base ) ),
				$this->filters['status'] === $key ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		}
		return $views;
	}

	/**
	 * Load rows.
	 */
	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'capo_inv_assets_per_page', 25 );
		$result   = CAPO_Inv_Assets::query(
			$this->filters + array(
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);

		$this->items           = $result['rows'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'asset_tag' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Filters above the table.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		echo '<div class="alignleft actions">';

		echo '<label class="screen-reader-text" for="capo-filter-type">' . esc_html( CAPO_Inv_Fields::label( 'type_id' ) ) . '</label>';
		echo '<select name="type_id" id="capo-filter-type"><option value="0">' . esc_html__( 'All asset types', 'capo-inventory' ) . '</option>';
		foreach ( CAPO_Inv_Lists::all( 'types' ) as $id => $row ) {
			printf( '<option value="%d"%s>%s</option>', (int) $id, selected( $this->filters['type_id'], $id, false ), esc_html( $row->name ) );
		}
		echo '</select>';

		echo '<label class="screen-reader-text" for="capo-filter-location">' . esc_html( CAPO_Inv_Fields::label( 'location_id' ) ) . '</label>';
		echo '<select name="location_id" id="capo-filter-location"><option value="0">' . esc_html__( 'All locations', 'capo-inventory' ) . '</option>';
		foreach ( CAPO_Inv_Lists::all( 'locations' ) as $id => $row ) {
			printf( '<option value="%d"%s>%s</option>', (int) $id, selected( $this->filters['location_id'], $id, false ), esc_html( $row->name ) );
		}
		echo '</select>';

		echo '<label class="screen-reader-text" for="capo-filter-employee">' . esc_html( CAPO_Inv_Fields::label( 'employee_id' ) ) . '</label>';
		echo '<select name="employee_id" id="capo-filter-employee"><option value="0">' . esc_html__( 'All employees', 'capo-inventory' ) . '</option>';
		foreach ( CAPO_Inv_Assets::employees() as $user ) {
			printf( '<option value="%d"%s>%s</option>', (int) $user->ID, selected( $this->filters['employee_id'], (int) $user->ID, false ), esc_html( $user->display_name ) );
		}
		echo '</select>';

		submit_button( __( 'Filter', 'capo-inventory' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Asset Tag column with row actions.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_asset_tag( $item ) {
		$actions = array();
		if ( 'active' === $item['status'] ) {
			$actions['edit']    = sprintf( '<a href="%s">%s</a>', esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_ASSETS, array( 'view' => 'edit', 'asset' => $item['id'] ) ) ), esc_html__( 'Edit', 'capo-inventory' ) );
			$actions['archive'] = sprintf( '<a href="%s">%s</a>', esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_ASSETS, array( 'view' => 'status', 'asset' => $item['id'] ) ) ), esc_html__( 'Archive', 'capo-inventory' ) );
		} else {
			$actions['restore'] = sprintf( '<a href="%s">%s</a>', esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_ASSETS, array( 'view' => 'status', 'asset' => $item['id'] ) ) ), esc_html__( 'Restore', 'capo-inventory' ) );
		}
		$actions['history'] = sprintf( '<a href="%s">%s</a>', esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_AUDIT, array( 'asset' => $item['id'] ) ) ), esc_html__( 'History', 'capo-inventory' ) );

		$pending = CAPO_Inv_Requests::pending_for_asset( (int) $item['id'] );
		$badge   = $pending
			? sprintf( ' <a class="capo-inv-badge capo-inv-badge--pending" href="%s">%s</a>', esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_REQUESTS, array( 'view' => 'review', 'request' => $pending['id'] ) ) ), esc_html__( 'Pending request', 'capo-inventory' ) )
			: '';

		return '<strong class="capo-inv-tag">' . esc_html( $item['asset_tag'] ) . '</strong>' . $badge . $this->row_actions( $actions );
	}

	/**
	 * Employee column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_employee( $item ) {
		if ( ! $item['employee_id'] ) {
			return '';
		}
		$name = null !== $item['employee_name'] ? $item['employee_name'] : CAPO_Inv_Fields::employee_name( (int) $item['employee_id'], false );
		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_ASSETS, array( 'employee_id' => $item['employee_id'] ) ) ),
			esc_html( $name )
		);
	}

	/**
	 * Default column output.
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'type_id':
				return esc_html( CAPO_Inv_Lists::name( 'types', (int) $item['type_id'] ) );
			case 'location_id':
				return esc_html( CAPO_Inv_Lists::name( 'locations', (int) $item['location_id'] ) );
			case 'status':
				return esc_html( CAPO_Inv_Fields::status_label( $item['status'] ) );
			case 'updated_at':
				return esc_html( CAPO_Inv_Fields::format_datetime( $item['updated_at'] ) );
			default:
				return esc_html( isset( $item[ $column_name ] ) ? (string) $item[ $column_name ] : '' );
		}
	}

	/**
	 * Empty table message.
	 */
	public function no_items() {
		esc_html_e( 'No assets match these filters.', 'capo-inventory' );
	}
}
