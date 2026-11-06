<?php
/**
 * Manager screen: employees and links to each employee's inventory.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

$capo_counts    = CAPO_Inv_Assets::active_counts();
$capo_search    = CAPO_Inv_Admin::get_text( 's' );
$capo_employees = CAPO_Inv_Assets::employees();

if ( '' !== $capo_search ) {
	$capo_needle    = function_exists( 'mb_strtolower' ) ? mb_strtolower( $capo_search, 'UTF-8' ) : strtolower( $capo_search );
	$capo_employees = array_filter(
		$capo_employees,
		static function ( $user ) use ( $capo_needle ) {
			$hay = $user->display_name . ' ' . $user->user_email;
			$hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay, 'UTF-8' ) : strtolower( $hay );
			return false !== strpos( $hay, $capo_needle );
		}
	);
}
?>
<div class="wrap capo-inv">
	<h1><?php esc_html_e( 'Employees', 'capo-inventory' ); ?></h1>
	<?php $this->render_flash(); ?>
	<p><?php esc_html_e( 'Users with the CAPO Employee, Inventory Manager or Administrator role. To add someone, give their WordPress account one of these roles under Users.', 'capo-inventory' ); ?></p>

	<form method="get" class="capo-inv-search">
		<input type="hidden" name="page" value="<?php echo esc_attr( CAPO_Inv_Admin::SLUG_EMPLOYEES ); ?>" />
		<label class="screen-reader-text" for="capo-emp-search"><?php esc_html_e( 'Search employees', 'capo-inventory' ); ?></label>
		<input type="search" id="capo-emp-search" name="s" value="<?php echo esc_attr( $capo_search ); ?>" placeholder="<?php esc_attr_e( 'Name or email', 'capo-inventory' ); ?>" />
		<?php submit_button( __( 'Search', 'capo-inventory' ), '', '', false ); ?>
	</form>

	<table class="widefat striped capo-inv-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Name', 'capo-inventory' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Email', 'capo-inventory' ); ?></th>
				<th scope="col" class="num"><?php esc_html_e( 'Active assets', 'capo-inventory' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'capo-inventory' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $capo_employees ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'No employees found.', 'capo-inventory' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $capo_employees as $capo_user ) : ?>
			<?php $capo_n = isset( $capo_counts[ (int) $capo_user->ID ] ) ? $capo_counts[ (int) $capo_user->ID ] : 0; ?>
			<tr>
				<td><?php echo esc_html( $capo_user->display_name ); ?></td>
				<td><?php echo esc_html( $capo_user->user_email ); ?></td>
				<td class="num"><?php echo esc_html( number_format_i18n( $capo_n ) ); ?></td>
				<td>
					<a href="<?php echo esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_ASSETS, array( 'employee_id' => $capo_user->ID ) ) ); ?>"><?php esc_html_e( 'View inventory', 'capo-inventory' ); ?></a>
					|
					<a href="<?php echo esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_ASSETS, array( 'view' => 'new', 'employee_id' => $capo_user->ID ) ) ); ?>"><?php esc_html_e( 'Register an asset', 'capo-inventory' ); ?></a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
