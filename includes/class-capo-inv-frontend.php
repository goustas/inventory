<?php
/**
 * Front-end shortcodes.
 *
 * [capo_my_assets]  Employee: own assets, registration, change and removal requests.
 * [capo_requests]   Manager:  request queue and review with approve/reject.
 * [capo_assets]     Manager:  searchable read-only asset list and per-employee view.
 *
 * Forms post to the same handlers as the admin screens (admin-post.php), which
 * check the capability and nonce, then return the user to the page they came from.
 *
 * Templates live in /templates and can be overridden by copying them to
 * your-theme/capo-inventory/.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode controller.
 */
final class CAPO_Inv_Frontend {

	/**
	 * Query arguments the shortcodes read. Prefixed so they never clash with
	 * WordPress query variables such as "page" or "s".
	 */
	const QUERY_KEYS = array(
		'capo_view',
		'capo_asset',
		'capo_request',
		'capo_status',
		'capo_paged',
		'capo_s',
		'capo_type_id',
		'capo_location_id',
		'capo_employee_id',
	);

	const SHORTCODES = array( 'capo_my_assets', 'capo_requests', 'capo_assets' );

	/**
	 * Singleton instance.
	 *
	 * @var CAPO_Inv_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return CAPO_Inv_Frontend
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
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'template_redirect', array( $this, 'prevent_caching' ) );
	}

	/**
	 * Register the shortcodes.
	 */
	public function register_shortcodes() {
		add_shortcode( 'capo_my_assets', array( $this, 'shortcode_my_assets' ) );
		add_shortcode( 'capo_requests', array( $this, 'shortcode_requests' ) );
		add_shortcode( 'capo_assets', array( $this, 'shortcode_assets' ) );
	}

	/**
	 * Whether the current singular page contains one of the shortcodes.
	 *
	 * @return bool
	 */
	private function page_has_shortcode() {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post( get_queried_object_id() );
		if ( ! $post ) {
			return false;
		}
		foreach ( self::SHORTCODES as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Register the stylesheet; enqueue it on pages that use a shortcode.
	 */
	public function register_assets() {
		wp_register_style( 'capo-inventory-frontend', CAPO_INV_URL . 'assets/css/frontend.css', array(), CAPO_INV_VERSION );
		if ( $this->page_has_shortcode() ) {
			wp_enqueue_style( 'capo-inventory-frontend' );
		}
	}

	/**
	 * Pages with personal inventory data must never be served from a page cache.
	 */
	public function prevent_caching() {
		if ( ! $this->page_has_shortcode() ) {
			return;
		}
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // Honoured by most WordPress caching plugins.
		}
	}

	/* ---------------------------------------------------------------------
	 * Helpers used by the templates
	 * ------------------------------------------------------------------ */

	/**
	 * URL of the current page without any capo_* arguments.
	 *
	 * @return string
	 */
	public static function base_url() {
		if ( is_singular() ) {
			$url = get_permalink( get_queried_object_id() );
			// Keep non-plugin query args (for example ?lang=el from a translation plugin).
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
			foreach ( wp_unslash( $_GET ) as $key => $value ) {
				if ( is_string( $value ) && ! in_array( $key, self::QUERY_KEYS, true ) ) {
					$url = add_query_arg( sanitize_key( $key ), rawurlencode( sanitize_text_field( $value ) ), $url );
				}
			}
			return $url;
		}
		// Not a singular page (for example a widget): rebuild the URL from the site's
		// own scheme and host, never from the client-supplied Host header.
		$uri   = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$home  = wp_parse_url( home_url() );
		$port  = isset( $home['port'] ) ? ':' . $home['port'] : '';
		$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		$url   = $home['scheme'] . '://' . $home['host'] . $port . (string) wp_parse_url( $uri, PHP_URL_PATH ) . ( '' !== $query ? '?' . $query : '' );
		return remove_query_arg( self::QUERY_KEYS, $url );
	}

	/**
	 * URL of the current page with shortcode arguments (keys given without the capo_ prefix).
	 *
	 * @param array $args Arguments, e.g. array( 'view' => 'register' ).
	 * @return string
	 */
	public static function url( array $args = array() ) {
		$mapped = array();
		foreach ( $args as $key => $value ) {
			if ( '' !== $value && null !== $value && 0 !== $value ) {
				$mapped[ 'capo_' . $key ] = $value;
			}
		}
		return add_query_arg( $mapped, self::base_url() );
	}

	/**
	 * Integer argument from the query string.
	 *
	 * @param string $key Key without prefix.
	 * @return int
	 */
	public static function arg_int( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing value.
		return isset( $_GET[ 'capo_' . $key ] ) ? absint( $_GET[ 'capo_' . $key ] ) : 0;
	}

	/**
	 * Key argument from the query string.
	 *
	 * @param string $key     Key without prefix.
	 * @param string $default Default.
	 * @return string
	 */
	public static function arg_key( $key, $default = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing value.
		return isset( $_GET[ 'capo_' . $key ] ) ? sanitize_key( wp_unslash( $_GET[ 'capo_' . $key ] ) ) : $default;
	}

	/**
	 * Text argument from the query string.
	 *
	 * @param string $key Key without prefix.
	 * @return string
	 */
	public static function arg_text( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter value.
		return isset( $_GET[ 'capo_' . $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'capo_' . $key ] ) ) : '';
	}

	/**
	 * Hidden fields every shortcode form needs: handler action, nonce and return page.
	 *
	 * @param string $action admin-post action, also used as the nonce action.
	 */
	public static function form_fields( $action ) {
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="capo_return" value="%s" />', esc_url( self::base_url() ) );
		wp_nonce_field( $action, '_wpnonce', false );
	}

	/**
	 * Print the message left by the previous form submission.
	 */
	public static function render_flash() {
		static $shown = false; // Only once per page, even with several shortcodes.
		$flash = CAPO_Inv_Admin::instance()->flash();
		if ( $shown || empty( $flash['message'] ) ) {
			return;
		}
		$shown = true;
		$type = in_array( $flash['type'], array( 'success', 'warning', 'error' ), true ) ? $flash['type'] : 'error';
		printf(
			'<div class="capo-inv-fe__notice capo-inv-fe__notice--%1$s" role="%2$s"><p>%3$s</p></div>',
			esc_attr( $type ),
			'success' === $type ? 'status' : 'alert',
			esc_html( $flash['message'] )
		);
	}

	/**
	 * Print a field error.
	 *
	 * @param array  $errors Field errors.
	 * @param string $field  Field key.
	 */
	public static function field_error( array $errors, $field ) {
		if ( ! empty( $errors[ $field ] ) ) {
			printf( '<p class="capo-inv-fe__error" id="%s">%s</p>', esc_attr( 'capo-fe-' . $field . '-error' ), esc_html( $errors[ $field ] ) );
		}
	}

	/**
	 * aria attributes for a field with an error.
	 *
	 * @param array  $errors Field errors.
	 * @param string $field  Field key.
	 */
	public static function invalid_attrs( array $errors, $field ) {
		if ( ! empty( $errors[ $field ] ) ) {
			echo ' aria-invalid="true" aria-describedby="' . esc_attr( 'capo-fe-' . $field . '-error' ) . '"';
		}
	}

	/**
	 * Print pagination links.
	 *
	 * @param int $total    Total rows.
	 * @param int $per_page Rows per page.
	 * @param int $current  Current page.
	 */
	public static function pagination( $total, $per_page, $current ) {
		$pages = (int) ceil( $total / max( 1, $per_page ) );
		if ( $pages < 2 ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- keep current filters.
		$base  = add_query_arg( 'capo_paged', '%#%', remove_query_arg( 'capo_paged', add_query_arg( array() ) ) );
		$links = paginate_links(
			array(
				'base'      => esc_url_raw( $base ),
				'format'    => '',
				'current'   => $current,
				'total'     => $pages,
				'prev_text' => __( '« Previous', 'capo-inventory' ),
				'next_text' => __( 'Next »', 'capo-inventory' ),
			)
		);
		echo '<nav class="capo-inv-fe__pagination" aria-label="' . esc_attr__( 'Pages', 'capo-inventory' ) . '">' . wp_kses_post( $links ) . '</nav>';
	}

	/* ---------------------------------------------------------------------
	 * Shortcodes
	 * ------------------------------------------------------------------ */

	/**
	 * Login form or access message when the visitor may not see a shortcode.
	 *
	 * @param string $cap Required capability.
	 * @return string Empty when allowed.
	 */
	private function gate( $cap ) {
		if ( ! is_user_logged_in() ) {
			$form = wp_login_form(
				array(
					'echo'     => false,
					'redirect' => self::base_url(),
					'form_id'  => 'capo-inv-login',
				)
			);
			return '<div class="capo-inv-fe capo-inv-fe--login"><p>' . esc_html__( 'Please log in to see your equipment.', 'capo-inventory' ) . '</p>' . $form . '</div>';
		}
		if ( ! current_user_can( $cap ) ) {
			return '<div class="capo-inv-fe"><p class="capo-inv-fe__notice capo-inv-fe__notice--warning">' . esc_html__( 'Your account does not have access to this section. Contact the IT department if you think this is wrong.', 'capo-inventory' ) . '</p></div>';
		}
		return '';
	}

	/**
	 * Render a template to a string. A theme can override it in /capo-inventory/.
	 *
	 * @param string $template File name in /templates.
	 * @param array  $vars     Variables for the template.
	 * @return string
	 */
	public static function render( $template, array $vars = array() ) {
		$template = basename( $template );
		$file     = locate_template( 'capo-inventory/' . $template );
		if ( ! $file ) {
			$file = CAPO_INV_DIR . 'templates/' . $template;
		}
		wp_enqueue_style( 'capo-inventory-frontend' );
		ob_start();
		( static function ( $capo_file, $capo ) {
			include $capo_file;
		} )( $file, $vars );
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode attributes shared by the list shortcodes.
	 *
	 * @param array|string $atts Raw attributes.
	 * @param string       $tag  Shortcode tag.
	 * @return array
	 */
	private function atts( $atts, $tag ) {
		$atts             = shortcode_atts( array( 'per_page' => 20 ), $atts, $tag );
		$atts['per_page'] = min( 100, max( 5, absint( $atts['per_page'] ) ) );
		return $atts;
	}

	/**
	 * [capo_my_assets]
	 *
	 * @param array|string $atts Attributes: per_page (requests list).
	 * @return string
	 */
	public function shortcode_my_assets( $atts ) {
		$gate = $this->gate( CAPO_Inv_Install::CAP_USE );
		if ( '' !== $gate ) {
			return $gate;
		}
		return self::render( 'my-assets.php', array( 'atts' => $this->atts( $atts, 'capo_my_assets' ) ) );
	}

	/**
	 * [capo_requests]
	 *
	 * @param array|string $atts Attributes: per_page.
	 * @return string
	 */
	public function shortcode_requests( $atts ) {
		$gate = $this->gate( CAPO_Inv_Install::CAP_MANAGE );
		if ( '' !== $gate ) {
			return $gate;
		}
		return self::render( 'requests.php', array( 'atts' => $this->atts( $atts, 'capo_requests' ) ) );
	}

	/**
	 * [capo_assets]
	 *
	 * @param array|string $atts Attributes: per_page.
	 * @return string
	 */
	public function shortcode_assets( $atts ) {
		$gate = $this->gate( CAPO_Inv_Install::CAP_MANAGE );
		if ( '' !== $gate ) {
			return $gate;
		}
		return self::render( 'assets.php', array( 'atts' => $this->atts( $atts, 'capo_assets' ) ) );
	}
}
