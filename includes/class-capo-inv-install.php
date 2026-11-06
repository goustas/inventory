<?php
/**
 * Database tables, roles and capabilities.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installation and upgrade routines.
 */
final class CAPO_Inv_Install {

	/** Capability: see own assets and submit requests. */
	const CAP_USE = 'capo_inv_use';

	/** Capability: manage all assets, approve requests, export, settings. */
	const CAP_MANAGE = 'capo_inv_manage';

	const ROLE_EMPLOYEE = 'capo_employee';
	const ROLE_MANAGER  = 'capo_inventory_manager';

	/**
	 * Fully qualified table name.
	 *
	 * @param string $name Short name: assets, requests, audit, types, locations.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'capo_inv_' . $name;
	}

	/**
	 * Activation hook. Handles network activation on multisite.
	 *
	 * @param bool $network_wide Whether activated network-wide.
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
				switch_to_blog( $site_id );
				self::install();
				restore_current_blog();
			}
			return;
		}
		self::install();
	}

	/**
	 * Run the installer when the stored schema version differs.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'capo_inv_db_version' ) !== CAPO_INV_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create or update tables, roles and default list entries.
	 */
	public static function install() {
		self::create_tables();
		self::add_roles();
		self::seed_types();
		update_option( 'capo_inv_db_version', CAPO_INV_DB_VERSION );
		add_option( 'capo_inv_settings', array( 'delete_data_on_uninstall' => 0 ) );
	}

	/**
	 * Create tables with dbDelta.
	 */
	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$assets    = self::table( 'assets' );
		$requests  = self::table( 'requests' );
		$audit     = self::table( 'audit' );
		$types     = self::table( 'types' );
		$locations = self::table( 'locations' );

		$sql = array();

		$sql[] = "CREATE TABLE {$assets} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  asset_tag varchar(64) NOT NULL,
  asset_tag_norm varchar(64) NOT NULL,
  type_id bigint(20) unsigned NOT NULL,
  description varchar(190) NOT NULL DEFAULT '',
  location_id bigint(20) unsigned NOT NULL,
  serial_number varchar(100) NOT NULL DEFAULT '',
  serial_norm varchar(100) NULL DEFAULT NULL,
  notes text NOT NULL,
  employee_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'active',
  created_at datetime NOT NULL,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL,
  updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
  archived_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY asset_tag_norm (asset_tag_norm),
  UNIQUE KEY serial_norm (serial_norm),
  KEY employee_status (employee_id,status),
  KEY status (status),
  KEY type_id (type_id),
  KEY location_id (location_id)
) {$charset};";

		$sql[] = "CREATE TABLE {$requests} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  request_type varchar(10) NOT NULL,
  asset_id bigint(20) unsigned NULL DEFAULT NULL,
  employee_id bigint(20) unsigned NOT NULL,
  submitted_by bigint(20) unsigned NOT NULL,
  payload longtext NULL,
  proposed_tag_norm varchar(64) NULL DEFAULT NULL,
  proposed_serial_norm varchar(100) NULL DEFAULT NULL,
  reason text NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  created_at datetime NOT NULL,
  reviewed_at datetime NULL DEFAULT NULL,
  reviewer_id bigint(20) unsigned NULL DEFAULT NULL,
  review_note text NULL,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY asset_status (asset_id,status),
  KEY employee_id (employee_id),
  KEY proposed_tag_norm (proposed_tag_norm),
  KEY proposed_serial_norm (proposed_serial_norm)
) {$charset};";

		$sql[] = "CREATE TABLE {$audit} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  asset_id bigint(20) unsigned NULL DEFAULT NULL,
  request_id bigint(20) unsigned NULL DEFAULT NULL,
  action varchar(30) NOT NULL,
  actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
  changes longtext NULL,
  note text NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY asset_id (asset_id),
  KEY created_at (created_at)
) {$charset};";

		foreach ( array( $types, $locations ) as $list_table ) {
			$sql[] = "CREATE TABLE {$list_table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  sort_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY is_active (is_active)
) {$charset};";
		}

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Add the plugin roles and grant capabilities to administrators.
	 */
	public static function add_roles() {
		add_role(
			self::ROLE_EMPLOYEE,
			__( 'CAPO Employee', 'capo-inventory' ),
			array(
				'read'         => true,
				self::CAP_USE  => true,
			)
		);
		add_role(
			self::ROLE_MANAGER,
			__( 'Inventory Manager', 'capo-inventory' ),
			array(
				'read'           => true,
				self::CAP_USE    => true,
				self::CAP_MANAGE => true,
			)
		);

		// add_role() does nothing if the role exists, so make sure the caps are present.
		$employee = get_role( self::ROLE_EMPLOYEE );
		if ( $employee ) {
			$employee->add_cap( self::CAP_USE );
		}
		$manager = get_role( self::ROLE_MANAGER );
		if ( $manager ) {
			$manager->add_cap( self::CAP_USE );
			$manager->add_cap( self::CAP_MANAGE );
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP_USE );
			$admin->add_cap( self::CAP_MANAGE );
		}
	}

	/**
	 * Remove roles and capabilities (used on uninstall).
	 */
	public static function remove_roles() {
		remove_role( self::ROLE_EMPLOYEE );
		remove_role( self::ROLE_MANAGER );
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->remove_cap( self::CAP_USE );
			$admin->remove_cap( self::CAP_MANAGE );
		}
	}

	/**
	 * Add starter asset types on first install only. Editable under Settings.
	 */
	private static function seed_types() {
		global $wpdb;
		$table = self::table( 'types' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > 0 ) {
			return;
		}
		$defaults = array( 'Laptop', 'Desktop', 'Monitor', 'Docking Station', 'Printer', 'Mobile Phone', 'Tablet', 'Other' );
		foreach ( $defaults as $i => $name ) {
			$wpdb->insert(
				$table,
				array(
					'name'       => $name,
					'is_active'  => 1,
					'sort_order' => $i,
				),
				array( '%s', '%d', '%d' )
			);
		}
	}
}
