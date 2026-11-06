<?php
/**
 * Uninstall. Data is deleted only if an Administrator enabled the option
 * under CAPO Inventory → Settings → General.
 *
 * @package CAPO_Inventory
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove the plugin's data from the current site, if allowed.
 */
function capo_inv_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'capo_inv_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	foreach ( array( 'assets', 'requests', 'audit', 'types', 'locations' ) as $name ) {
		$table = $wpdb->prefix . 'capo_inv_' . $name;
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- internal table name.
	}

	delete_option( 'capo_inv_settings' );
	delete_option( 'capo_inv_db_version' );

	remove_role( 'capo_employee' );
	remove_role( 'capo_inventory_manager' );
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->remove_cap( 'capo_inv_use' );
		$admin->remove_cap( 'capo_inv_manage' );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $capo_site_id ) {
		switch_to_blog( $capo_site_id );
		capo_inv_uninstall_site();
		restore_current_blog();
	}
} else {
	capo_inv_uninstall_site();
}
