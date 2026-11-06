<?php
/**
 * Plugin bootstrap.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress.
 */
final class CAPO_Inv_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var CAPO_Inv_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return CAPO_Inv_Plugin
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
		add_action( 'plugins_loaded', array( 'CAPO_Inv_Install', 'maybe_upgrade' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_initialize_site', array( $this, 'new_site' ), 20 );

		if ( is_admin() ) {
			require_once CAPO_INV_DIR . 'admin/class-capo-inv-admin.php';
			CAPO_Inv_Admin::instance();
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'capo-inventory', false, dirname( plugin_basename( CAPO_INV_FILE ) ) . '/languages' );
	}

	/**
	 * Install on new multisite sites when network-active.
	 *
	 * @param WP_Site $site New site.
	 */
	public function new_site( $site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active_for_network( plugin_basename( CAPO_INV_FILE ) ) ) {
			switch_to_blog( $site->blog_id );
			CAPO_Inv_Install::install();
			restore_current_blog();
		}
	}
}
