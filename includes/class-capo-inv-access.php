<?php
/**
 * Who may submit assets and hold a personal inventory.
 *
 * Every user with a role on the site may submit assets, except users with a
 * role listed under Settings → General → "Roles that cannot submit assets"
 * (Inventory Manager by default). This is decided at runtime through the
 * user_has_cap filter, so roles created later by other plugins are covered
 * without editing them.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runtime grant of the "use" capability.
 */
final class CAPO_Inv_Access {

	/**
	 * Register the capability filter.
	 */
	public static function init() {
		add_filter( 'user_has_cap', array( __CLASS__, 'filter_caps' ), 10, 4 );
	}

	/**
	 * Role slugs that cannot submit assets.
	 *
	 * @return string[]
	 */
	public static function excluded_roles() {
		$settings = get_option( 'capo_inv_settings', array() );
		if ( ! is_array( $settings ) || ! isset( $settings['no_submit_roles'] ) || ! is_array( $settings['no_submit_roles'] ) ) {
			return array( CAPO_Inv_Install::ROLE_MANAGER );
		}
		return array_values( array_map( 'strval', $settings['no_submit_roles'] ) );
	}

	/**
	 * Whether a user's roles allow submitting assets.
	 *
	 * @param string[] $roles User's roles on this site.
	 * @return bool
	 */
	public static function roles_may_submit( array $roles ) {
		if ( ! $roles ) {
			return false; // No role on this site (e.g. "No role for this site" on multisite).
		}
		return ! array_intersect( $roles, self::excluded_roles() );
	}

	/**
	 * Grant or deny capo_inv_use according to the user's roles.
	 *
	 * @param bool[]   $allcaps All capabilities of the user.
	 * @param string[] $caps    Primitive capabilities being checked.
	 * @param array    $args    Original has_cap() arguments.
	 * @param WP_User  $user    The user.
	 * @return bool[]
	 */
	public static function filter_caps( $allcaps, $caps, $args, $user ) {
		if ( ! in_array( CAPO_Inv_Install::CAP_USE, (array) $caps, true ) ) {
			return $allcaps;
		}
		$roles = ( is_object( $user ) && isset( $user->roles ) ) ? (array) $user->roles : array();
		$allcaps[ CAPO_Inv_Install::CAP_USE ] = ( is_object( $user ) && ! empty( $user->ID ) ) && self::roles_may_submit( $roles );
		return $allcaps;
	}
}
