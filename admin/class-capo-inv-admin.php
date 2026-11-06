<?php
/**
 * Admin menus, screens and form handlers.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
final class CAPO_Inv_Admin {

	const SLUG_MY        = 'capo-inventory';
	const SLUG_ASSETS    = 'capo-inventory-assets';
	const SLUG_EMPLOYEES = 'capo-inventory-employees';
	const SLUG_REQUESTS  = 'capo-inventory-requests';
	const SLUG_AUDIT     = 'capo-inventory-audit';
	const SLUG_SETTINGS  = 'capo-inventory-settings';

	/**
	 * Singleton instance.
	 *
	 * @var CAPO_Inv_Admin|null
	 */
	private static $instance = null;

	/**
	 * Flash data for the current page load.
	 *
	 * @var array|null
	 */
	private $flash = null;

	/**
	 * Get the instance.
	 *
	 * @return CAPO_Inv_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'setup_notice' ) );

		$handlers = array(
			'capo_inv_submit_create' => 'handle_submit_create',
			'capo_inv_submit_update' => 'handle_submit_update',
			'capo_inv_submit_delete' => 'handle_submit_delete',
			'capo_inv_cancel'        => 'handle_cancel',
			'capo_inv_save_asset'    => 'handle_save_asset',
			'capo_inv_set_status'    => 'handle_set_status',
			'capo_inv_review'        => 'handle_review',
			'capo_inv_export'        => 'handle_export',
			'capo_inv_list'          => 'handle_list',
			'capo_inv_settings'      => 'handle_settings',
		);
		foreach ( $handlers as $action => $method ) {
			add_action( 'admin_post_' . $action, array( $this, $method ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Menus and assets
	 * ------------------------------------------------------------------ */

	/**
	 * Register admin menus.
	 */
	public function menu() {
		add_menu_page(
			__( 'My Assets', 'capo-inventory' ),
			__( 'CAPO Inventory', 'capo-inventory' ),
			CAPO_Inv_Install::CAP_USE,
			self::SLUG_MY,
			array( $this, 'page_my_assets' ),
			'dashicons-laptop',
			26
		);
		add_submenu_page( self::SLUG_MY, __( 'My Assets', 'capo-inventory' ), __( 'My Assets', 'capo-inventory' ), CAPO_Inv_Install::CAP_USE, self::SLUG_MY, array( $this, 'page_my_assets' ) );

		if ( ! current_user_can( CAPO_Inv_Install::CAP_MANAGE ) ) {
			return;
		}

		$pending = CAPO_Inv_Requests::pending_count();
		$badge   = $pending ? sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $pending ) : '';

		$assets_hook = add_submenu_page( self::SLUG_MY, __( 'All Assets', 'capo-inventory' ), __( 'All Assets', 'capo-inventory' ), CAPO_Inv_Install::CAP_MANAGE, self::SLUG_ASSETS, array( $this, 'page_assets' ) );
		if ( $assets_hook ) {
			add_action( 'load-' . $assets_hook, array( $this, 'clean_list_url' ) );
		}
		add_submenu_page( self::SLUG_MY, __( 'Employees', 'capo-inventory' ), __( 'Employees', 'capo-inventory' ), CAPO_Inv_Install::CAP_MANAGE, self::SLUG_EMPLOYEES, array( $this, 'page_employees' ) );
		add_submenu_page( self::SLUG_MY, __( 'Requests', 'capo-inventory' ), __( 'Requests', 'capo-inventory' ) . $badge, CAPO_Inv_Install::CAP_MANAGE, self::SLUG_REQUESTS, array( $this, 'page_requests' ) );
		add_submenu_page( self::SLUG_MY, __( 'Audit Log', 'capo-inventory' ), __( 'Audit Log', 'capo-inventory' ), CAPO_Inv_Install::CAP_MANAGE, self::SLUG_AUDIT, array( $this, 'page_audit' ) );
		add_submenu_page( self::SLUG_MY, __( 'Inventory Settings', 'capo-inventory' ), __( 'Settings', 'capo-inventory' ), CAPO_Inv_Install::CAP_MANAGE, self::SLUG_SETTINGS, array( $this, 'page_settings' ) );
	}

	/**
	 * Enqueue the admin stylesheet on plugin screens only.
	 *
	 * @param string $hook Screen hook.
	 */
	public function assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 !== strpos( $page, 'capo-inventory' ) ) {
			return;
		}
		wp_enqueue_style( 'capo-inventory-admin', CAPO_INV_URL . 'assets/css/admin.css', array(), CAPO_INV_VERSION );
	}

	/**
	 * The list table's filter form submits by GET and carries the table's nonce
	 * and referer fields. Strip them, as WordPress core does on its own list
	 * screens, so filter URLs stay short and shareable.
	 */
	public function clean_list_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only removes query args.
		if ( empty( $_GET['_wp_http_referer'] ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		wp_safe_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'filter_action' ), $uri ) );
		exit;
	}

	/**
	 * Tell managers when no Location exists yet, since Location is required.
	 */
	public function setup_notice() {
		if ( ! current_user_can( CAPO_Inv_Install::CAP_MANAGE ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 !== strpos( $page, 'capo-inventory' ) || CAPO_Inv_Lists::options( 'locations' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'No Locations are set up yet, so assets cannot be registered.', 'capo-inventory' ),
			esc_url( self::url( self::SLUG_SETTINGS, array( 'tab' => 'locations' ) ) ),
			esc_html__( 'Add Locations', 'capo-inventory' )
		);
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Admin URL for a plugin page.
	 *
	 * @param string $slug Page slug.
	 * @param array  $args Query args.
	 * @return string
	 */
	public static function url( $slug, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Store a message (and form state) for the next page load, then redirect.
	 *
	 * @param string $url     Destination.
	 * @param string $type    success|error|warning.
	 * @param string $message Message.
	 * @param array  $errors  Field errors.
	 * @param array  $old     Submitted values to refill the form.
	 */
	private function redirect( $url, $type, $message, array $errors = array(), array $old = array() ) {
		set_transient(
			'capo_inv_flash_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
				'errors'  => $errors,
				'old'     => $old,
			),
			120
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Redirect back after a WP_Error, keeping field errors and input.
	 *
	 * @param string   $url   Destination.
	 * @param WP_Error $error Error.
	 * @param array    $old   Submitted values.
	 */
	private function redirect_error( $url, WP_Error $error, array $old = array() ) {
		$data   = $error->get_error_data();
		$fields = is_array( $data ) && isset( $data['fields'] ) ? $data['fields'] : array();
		$this->redirect( $url, 'error', $error->get_error_message(), $fields, $old );
	}

	/**
	 * Flash data saved by the previous request (read once).
	 *
	 * @return array
	 */
	public function flash() {
		if ( null === $this->flash ) {
			$key         = 'capo_inv_flash_' . get_current_user_id();
			$data        = get_transient( $key );
			$this->flash = is_array( $data ) ? $data : array();
			if ( $data ) {
				delete_transient( $key );
			}
		}
		return $this->flash;
	}

	/**
	 * Print the flash message, if any.
	 */
	public function render_flash() {
		$flash = $this->flash();
		if ( empty( $flash['message'] ) ) {
			return;
		}
		$class = 'success' === $flash['type'] ? 'notice-success' : ( 'warning' === $flash['type'] ? 'notice-warning' : 'notice-error' );
		printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $flash['message'] ) );
	}

	/**
	 * Field errors from the flash.
	 *
	 * @return array
	 */
	public function field_errors() {
		$flash = $this->flash();
		return isset( $flash['errors'] ) && is_array( $flash['errors'] ) ? $flash['errors'] : array();
	}

	/**
	 * Previously submitted values from the flash.
	 *
	 * @return array
	 */
	public function old_input() {
		$flash = $this->flash();
		return isset( $flash['old'] ) && is_array( $flash['old'] ) ? $flash['old'] : array();
	}

	/**
	 * Verify capability and nonce for a handler, or stop.
	 *
	 * @param string $cap    Capability.
	 * @param string $action Nonce action.
	 */
	private function guard( $cap, $action ) {
		if ( ! is_user_logged_in() || ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'capo-inventory' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Unslashed POST array.
	 *
	 * @return array
	 */
	private function post() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run guard() first.
		return wp_unslash( $_POST );
	}

	/**
	 * Integer from the request.
	 *
	 * @param string $key Key.
	 * @return int
	 */
	public static function get_int( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- read-only routing value.
		return isset( $_REQUEST[ $key ] ) ? absint( $_REQUEST[ $key ] ) : 0;
	}

	/**
	 * Sanitized key from the query string.
	 *
	 * @param string $key     Key.
	 * @param string $default Default.
	 * @return string
	 */
	public static function get_key( $key, $default = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing value.
		return isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : $default;
	}

	/**
	 * Text from the query string.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function get_text( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter value.
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	/**
	 * Render the shared asset fields.
	 *
	 * @param array      $values  Field values.
	 * @param array      $errors  Field errors.
	 * @param array|null $current Current asset when editing (keeps inactive list values selectable).
	 */
	public function render_asset_fields( array $values, array $errors, $current = null ) {
		$values = wp_parse_args(
			$values,
			array(
				'asset_tag'     => '',
				'type_id'       => 0,
				'description'   => '',
				'location_id'   => 0,
				'serial_number' => '',
				'notes'         => '',
			)
		);
		include CAPO_INV_DIR . 'admin/views/asset-fields.php';
	}

	/**
	 * Print a field error, if any.
	 *
	 * @param array  $errors Field errors.
	 * @param string $field  Field key.
	 */
	public static function field_error( array $errors, $field ) {
		if ( ! empty( $errors[ $field ] ) ) {
			printf( '<p class="capo-inv-field-error" id="%s-error">%s</p>', esc_attr( 'capo-' . $field ), esc_html( $errors[ $field ] ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Pages
	 * ------------------------------------------------------------------ */

	/**
	 * Employee page: own assets and requests.
	 */
	public function page_my_assets() {
		if ( ! current_user_can( CAPO_Inv_Install::CAP_USE ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'capo-inventory' ) );
		}
		include CAPO_INV_DIR . 'admin/views/my-assets.php';
	}

	/**
	 * Manager page: all assets.
	 */
	public function page_assets() {
		$this->require_manage();
		require_once CAPO_INV_DIR . 'admin/class-capo-inv-assets-table.php';
		include CAPO_INV_DIR . 'admin/views/assets.php';
	}

	/**
	 * Manager page: employees and their inventory.
	 */
	public function page_employees() {
		$this->require_manage();
		include CAPO_INV_DIR . 'admin/views/employees.php';
	}

	/**
	 * Manager page: requests.
	 */
	public function page_requests() {
		$this->require_manage();
		include CAPO_INV_DIR . 'admin/views/requests.php';
	}

	/**
	 * Manager page: audit log.
	 */
	public function page_audit() {
		$this->require_manage();
		include CAPO_INV_DIR . 'admin/views/audit.php';
	}

	/**
	 * Manager page: settings.
	 */
	public function page_settings() {
		$this->require_manage();
		include CAPO_INV_DIR . 'admin/views/settings.php';
	}

	/**
	 * Stop unless the user can manage the inventory.
	 */
	private function require_manage() {
		if ( ! current_user_can( CAPO_Inv_Install::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'capo-inventory' ) );
		}
	}

	/**
	 * Print simple pagination links.
	 *
	 * @param int    $total    Total rows.
	 * @param int    $per_page Rows per page.
	 * @param int    $current  Current page.
	 */
	public static function pagination( $total, $per_page, $current ) {
		$pages = (int) ceil( $total / max( 1, $per_page ) );
		if ( $pages < 2 ) {
			return;
		}
		$links = paginate_links(
			array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $current,
				'total'   => $pages,
			)
		);
		echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
	}

	/* ---------------------------------------------------------------------
	 * Employee handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Employee submits a new asset.
	 */
	public function handle_submit_create() {
		$this->guard( CAPO_Inv_Install::CAP_USE, 'capo_inv_submit_create' );
		$post   = $this->post();
		$user   = get_current_user_id(); // Always the logged-in account, never a submitted value.
		$result = CAPO_Inv_Requests::submit_create( $post, $user, isset( $post['reason'] ) ? $post['reason'] : '' );
		if ( is_wp_error( $result ) ) {
			$this->redirect_error( self::url( self::SLUG_MY, array( 'view' => 'register' ) ), $result, $post );
		}
		$this->redirect( self::url( self::SLUG_MY ), 'success', __( 'Your asset was submitted for approval.', 'capo-inventory' ) );
	}

	/**
	 * Employee proposes a change.
	 */
	public function handle_submit_update() {
		$this->guard( CAPO_Inv_Install::CAP_USE, 'capo_inv_submit_update' );
		$post     = $this->post();
		$asset_id = isset( $post['asset_id'] ) ? absint( $post['asset_id'] ) : 0;
		$result   = CAPO_Inv_Requests::submit_update( $asset_id, $post, get_current_user_id(), isset( $post['reason'] ) ? $post['reason'] : '' );
		if ( is_wp_error( $result ) ) {
			$back = in_array( $result->get_error_code(), array( 'invalid', 'no_change' ), true )
				? self::url( self::SLUG_MY, array( 'view' => 'change', 'asset' => $asset_id ) )
				: self::url( self::SLUG_MY );
			$this->redirect_error( $back, $result, $post );
		}
		$this->redirect( self::url( self::SLUG_MY ), 'success', __( 'Your change request was submitted for approval.', 'capo-inventory' ) );
	}

	/**
	 * Employee asks for removal.
	 */
	public function handle_submit_delete() {
		$this->guard( CAPO_Inv_Install::CAP_USE, 'capo_inv_submit_delete' );
		$post     = $this->post();
		$asset_id = isset( $post['asset_id'] ) ? absint( $post['asset_id'] ) : 0;
		$result   = CAPO_Inv_Requests::submit_delete( $asset_id, get_current_user_id(), isset( $post['reason'] ) ? $post['reason'] : '' );
		if ( is_wp_error( $result ) ) {
			$back = 'invalid' === $result->get_error_code()
				? self::url( self::SLUG_MY, array( 'view' => 'remove', 'asset' => $asset_id ) )
				: self::url( self::SLUG_MY );
			$this->redirect_error( $back, $result, $post );
		}
		$this->redirect( self::url( self::SLUG_MY ), 'success', __( 'Your removal request was submitted for approval.', 'capo-inventory' ) );
	}

	/**
	 * Employee cancels a pending request.
	 */
	public function handle_cancel() {
		$this->guard( CAPO_Inv_Install::CAP_USE, 'capo_inv_cancel' );
		$post   = $this->post();
		$result = CAPO_Inv_Requests::cancel( isset( $post['request_id'] ) ? absint( $post['request_id'] ) : 0, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$this->redirect_error( self::url( self::SLUG_MY ), $result );
		}
		$this->redirect( self::url( self::SLUG_MY ), 'success', __( 'Your request was cancelled.', 'capo-inventory' ) );
	}

	/* ---------------------------------------------------------------------
	 * Manager handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Manager creates or edits an asset directly.
	 */
	public function handle_save_asset() {
		$this->guard( CAPO_Inv_Install::CAP_MANAGE, 'capo_inv_save_asset' );
		$post     = $this->post();
		$asset_id = isset( $post['asset_id'] ) ? absint( $post['asset_id'] ) : 0;
		$employee = isset( $post['employee_id'] ) ? absint( $post['employee_id'] ) : 0;
		$data     = CAPO_Inv_Fields::clean_input( $post );
		$actor    = get_current_user_id();

		if ( $asset_id ) {
			$result = CAPO_Inv_Assets::update( $asset_id, $data, $employee, $actor );
			$back   = self::url( self::SLUG_ASSETS, array( 'view' => 'edit', 'asset' => $asset_id ) );
			$done   = __( 'Asset updated.', 'capo-inventory' );
		} else {
			$result = CAPO_Inv_Assets::create( $data, $employee, $actor );
			$back   = self::url( self::SLUG_ASSETS, array( 'view' => 'new' ) );
			$done   = __( 'Asset registered.', 'capo-inventory' );
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect_error( $back, $result, $post );
		}
		$return = isset( $post['return_employee'] ) ? absint( $post['return_employee'] ) : 0;
		$this->redirect( self::url( self::SLUG_ASSETS, $return ? array( 'employee_id' => $return ) : array() ), 'success', $done );
	}

	/**
	 * Manager archives or restores an asset.
	 */
	public function handle_set_status() {
		$this->guard( CAPO_Inv_Install::CAP_MANAGE, 'capo_inv_set_status' );
		$post     = $this->post();
		$asset_id = isset( $post['asset_id'] ) ? absint( $post['asset_id'] ) : 0;
		$note     = isset( $post['note'] ) ? CAPO_Inv_Fields::clean_text( $post['note'], true ) : '';
		$target   = isset( $post['status'] ) && 'active' === $post['status'] ? 'active' : 'archived';

		$result = 'active' === $target
			? CAPO_Inv_Assets::restore( $asset_id, get_current_user_id(), $note )
			: CAPO_Inv_Assets::archive( $asset_id, get_current_user_id(), 0, $note );

		if ( is_wp_error( $result ) ) {
			$this->redirect_error( self::url( self::SLUG_ASSETS ), $result );
		}
		$this->redirect(
			self::url( self::SLUG_ASSETS, array( 'status' => $target ) ),
			'success',
			'active' === $target ? __( 'Asset restored.', 'capo-inventory' ) : __( 'Asset archived.', 'capo-inventory' )
		);
	}

	/**
	 * Manager approves or rejects a request.
	 */
	public function handle_review() {
		$this->guard( CAPO_Inv_Install::CAP_MANAGE, 'capo_inv_review' );
		$post       = $this->post();
		$request_id = isset( $post['request_id'] ) ? absint( $post['request_id'] ) : 0;
		$note       = isset( $post['review_note'] ) ? $post['review_note'] : '';
		$decision   = isset( $post['decision'] ) ? sanitize_key( $post['decision'] ) : '';
		$back       = self::url( self::SLUG_REQUESTS, array( 'view' => 'review', 'request' => $request_id ) );

		if ( 'approve' === $decision ) {
			$result = CAPO_Inv_Requests::approve( $request_id, get_current_user_id(), $note );
			$done   = __( 'Request approved and applied.', 'capo-inventory' );
		} elseif ( 'reject' === $decision ) {
			$result = CAPO_Inv_Requests::reject( $request_id, get_current_user_id(), $note );
			$done   = __( 'Request rejected.', 'capo-inventory' );
		} else {
			$result = new WP_Error( 'decision', __( 'Please choose Approve or Reject.', 'capo-inventory' ) );
		}

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['fields'] ) ) {
				// Validation failed at approval time (for example a duplicate created meanwhile).
				$messages = implode( ' ', array_values( $data['fields'] ) );
				$this->redirect( $back, 'error', __( 'The request cannot be approved:', 'capo-inventory' ) . ' ' . $messages );
			}
			$this->redirect_error( $back, $result );
		}
		$this->redirect( self::url( self::SLUG_REQUESTS ), 'success', $done );
	}

	/**
	 * Manager downloads a CSV export.
	 */
	public function handle_export() {
		if ( ! is_user_logged_in() || ! current_user_can( CAPO_Inv_Install::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'capo-inventory' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'capo_inv_export' );

		$status = self::get_key( 'status', 'active' );
		CAPO_Inv_Export::stream(
			array(
				'search'      => self::get_text( 's' ),
				'status'      => in_array( $status, array( 'active', 'archived', 'all' ), true ) ? $status : 'active',
				'type_id'     => self::get_int( 'type_id' ),
				'location_id' => self::get_int( 'location_id' ),
				'employee_id' => self::get_int( 'employee_id' ),
				'orderby'     => self::get_key( 'orderby', 'asset_tag' ),
				'order'       => self::get_key( 'order', 'asc' ),
			),
			self::get_key( 'format', 'csv' )
		);
	}

	/**
	 * Manager edits the Asset Type or Location lists.
	 */
	public function handle_list() {
		$this->guard( CAPO_Inv_Install::CAP_MANAGE, 'capo_inv_list' );
		$post = $this->post();
		$list = isset( $post['list'] ) && 'locations' === $post['list'] ? 'locations' : 'types';
		$op   = isset( $post['op'] ) ? sanitize_key( $post['op'] ) : '';
		$id   = isset( $post['id'] ) ? absint( $post['id'] ) : 0;
		$name = isset( $post['name'] ) ? sanitize_text_field( $post['name'] ) : '';
		$back = self::url( self::SLUG_SETTINGS, array( 'tab' => $list ) );

		switch ( $op ) {
			case 'add':
				$result = CAPO_Inv_Lists::add( $list, $name );
				break;
			case 'rename':
				$result = CAPO_Inv_Lists::rename( $list, $id, $name );
				break;
			case 'activate':
			case 'deactivate':
				CAPO_Inv_Lists::set_active( $list, $id, 'activate' === $op );
				$result = true;
				break;
			case 'delete':
				$result = CAPO_Inv_Lists::delete( $list, $id );
				break;
			default:
				$result = new WP_Error( 'op', __( 'Unknown action.', 'capo-inventory' ) );
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect_error( $back, $result );
		}
		$this->redirect( $back, 'success', __( 'Saved.', 'capo-inventory' ) );
	}

	/**
	 * Save general settings. Administrators only, since it controls data deletion.
	 */
	public function handle_settings() {
		$this->guard( 'manage_options', 'capo_inv_settings' );
		$post = $this->post();
		update_option(
			'capo_inv_settings',
			array(
				'delete_data_on_uninstall' => ! empty( $post['delete_data_on_uninstall'] ) ? 1 : 0,
			)
		);
		$this->redirect( self::url( self::SLUG_SETTINGS, array( 'tab' => 'general' ) ), 'success', __( 'Settings saved.', 'capo-inventory' ) );
	}
}
