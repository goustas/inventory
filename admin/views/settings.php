<?php
/**
 * Manager screen: Asset Types, Locations and general settings.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

$capo_tab = CAPO_Inv_Admin::get_key( 'tab', 'types' );
if ( ! in_array( $capo_tab, array( 'types', 'locations', 'general' ), true ) ) {
	$capo_tab = 'types';
}
$capo_tabs = array(
	'types'     => __( 'Asset Types', 'capo-inventory' ),
	'locations' => __( 'Locations', 'capo-inventory' ),
);
if ( current_user_can( 'manage_options' ) ) {
	$capo_tabs['general'] = __( 'General', 'capo-inventory' );
} elseif ( 'general' === $capo_tab ) {
	$capo_tab = 'types';
}
$capo_post = admin_url( 'admin-post.php' );
?>
<div class="wrap capo-inv">
	<h1><?php esc_html_e( 'Inventory Settings', 'capo-inventory' ); ?></h1>
	<nav class="nav-tab-wrapper">
		<?php foreach ( $capo_tabs as $capo_key => $capo_label ) : ?>
			<a href="<?php echo esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_SETTINGS, array( 'tab' => $capo_key ) ) ); ?>" class="nav-tab<?php echo $capo_key === $capo_tab ? ' nav-tab-active' : ''; ?>"<?php echo $capo_key === $capo_tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $capo_label ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php $this->render_flash(); ?>

<?php if ( 'general' === $capo_tab ) : ?>
	<?php $capo_settings = wp_parse_args( (array) get_option( 'capo_inv_settings', array() ), array( 'delete_data_on_uninstall' => 0 ) ); ?>
	<form method="post" action="<?php echo esc_url( $capo_post ); ?>">
		<input type="hidden" name="action" value="capo_inv_settings" />
		<?php wp_nonce_field( 'capo_inv_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Who can submit assets', 'capo-inventory' ); ?></th>
				<td>
					<fieldset>
						<legend><?php esc_html_e( 'Every user with a role on this site can register assets and has a personal inventory, except users with a role ticked below.', 'capo-inventory' ); ?></legend>
						<?php
						$capo_excluded = CAPO_Inv_Access::excluded_roles();
						foreach ( wp_roles()->get_names() as $capo_role => $capo_role_name ) :
							?>
							<label class="capo-inv-role">
								<input type="checkbox" name="no_submit_roles[]" value="<?php echo esc_attr( $capo_role ); ?>" <?php checked( in_array( $capo_role, $capo_excluded, true ) ); ?> />
								<?php echo esc_html( translate_user_role( $capo_role_name ) ); ?>
								<code><?php echo esc_html( $capo_role ); ?></code>
							</label><br />
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Users with a ticked role do not see My Assets and cannot be assigned assets. Assets they already hold stay in the register. Inventory Managers manage inventory through All Assets and Requests whether or not their role is ticked.', 'capo-inventory' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Uninstall', 'capo-inventory' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( (int) $capo_settings['delete_data_on_uninstall'], 1 ); ?> />
						<?php esc_html_e( 'Permanently delete all inventory data, requests and audit history when the plugin is deleted.', 'capo-inventory' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Leave unchecked to keep the data if the plugin is removed. Deactivating the plugin never deletes data.', 'capo-inventory' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>

<?php else : ?>
	<?php
	$capo_list  = $capo_tab;
	$capo_rows  = CAPO_Inv_Lists::all( $capo_list );
	$capo_label = 'types' === $capo_list ? CAPO_Inv_Fields::label( 'type_id' ) : CAPO_Inv_Fields::label( 'location_id' );
	?>
	<p>
		<?php esc_html_e( 'Entries appear in the dropdowns of the asset forms. Deactivate an entry to stop new use; assets that already have it keep it. Only unused entries can be deleted.', 'capo-inventory' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( $capo_post ); ?>" class="capo-inv-add">
		<input type="hidden" name="action" value="capo_inv_list" />
		<input type="hidden" name="list" value="<?php echo esc_attr( $capo_list ); ?>" />
		<input type="hidden" name="op" value="add" />
		<?php wp_nonce_field( 'capo_inv_list' ); ?>
		<label for="capo-new-name">
			<?php
			/* translators: %s: list name (Asset Type or Location). */
			printf( esc_html__( 'Add %s', 'capo-inventory' ), esc_html( $capo_label ) );
			?>
		</label>
		<input type="text" id="capo-new-name" name="name" maxlength="100" required />
		<?php submit_button( __( 'Add', 'capo-inventory' ), 'secondary', '', false ); ?>
	</form>

	<table class="widefat striped capo-inv-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Name', 'capo-inventory' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'capo-inventory' ); ?></th>
				<th scope="col" class="num"><?php esc_html_e( 'In use', 'capo-inventory' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'capo-inventory' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $capo_rows ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'No entries yet.', 'capo-inventory' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $capo_rows as $capo_id => $capo_row ) : ?>
			<?php $capo_used = CAPO_Inv_Lists::usage_count( $capo_list, $capo_id ); ?>
			<tr>
				<td>
					<form method="post" action="<?php echo esc_url( $capo_post ); ?>" class="capo-inv-inline-form">
						<input type="hidden" name="action" value="capo_inv_list" />
						<input type="hidden" name="list" value="<?php echo esc_attr( $capo_list ); ?>" />
						<input type="hidden" name="op" value="rename" />
						<input type="hidden" name="id" value="<?php echo esc_attr( $capo_id ); ?>" />
						<?php wp_nonce_field( 'capo_inv_list' ); ?>
						<label class="screen-reader-text" for="capo-name-<?php echo esc_attr( $capo_id ); ?>"><?php esc_html_e( 'Name', 'capo-inventory' ); ?></label>
						<input type="text" id="capo-name-<?php echo esc_attr( $capo_id ); ?>" name="name" value="<?php echo esc_attr( $capo_row->name ); ?>" maxlength="100" required />
						<button type="submit" class="button button-small"><?php esc_html_e( 'Rename', 'capo-inventory' ); ?></button>
					</form>
				</td>
				<td><?php echo (int) $capo_row->is_active ? esc_html__( 'Active', 'capo-inventory' ) : esc_html__( 'Inactive', 'capo-inventory' ); ?></td>
				<td class="num"><?php echo esc_html( number_format_i18n( $capo_used ) ); ?></td>
				<td>
					<form method="post" action="<?php echo esc_url( $capo_post ); ?>" class="capo-inv-inline-form">
						<input type="hidden" name="action" value="capo_inv_list" />
						<input type="hidden" name="list" value="<?php echo esc_attr( $capo_list ); ?>" />
						<input type="hidden" name="id" value="<?php echo esc_attr( $capo_id ); ?>" />
						<?php wp_nonce_field( 'capo_inv_list' ); ?>
						<?php if ( (int) $capo_row->is_active ) : ?>
							<button type="submit" name="op" value="deactivate" class="button button-small"><?php esc_html_e( 'Deactivate', 'capo-inventory' ); ?></button>
						<?php else : ?>
							<button type="submit" name="op" value="activate" class="button button-small"><?php esc_html_e( 'Activate', 'capo-inventory' ); ?></button>
						<?php endif; ?>
						<?php if ( 0 === $capo_used ) : ?>
							<button type="submit" name="op" value="delete" class="button button-small capo-inv-link-danger"><?php esc_html_e( 'Delete', 'capo-inventory' ); ?></button>
						<?php endif; ?>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
</div>
