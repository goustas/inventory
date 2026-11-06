<?php
/**
 * Manager screen: All Assets, add/edit form, archive/restore confirmation.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

$capo_view   = CAPO_Inv_Admin::get_key( 'view' );
$capo_errors = $this->field_errors();
$capo_old    = $this->old_input();
$capo_base   = CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_ASSETS );

$capo_asset = null;
if ( in_array( $capo_view, array( 'edit', 'status' ), true ) ) {
	$capo_asset = CAPO_Inv_Assets::get( CAPO_Inv_Admin::get_int( 'asset' ) );
	if ( ! $capo_asset || ( 'edit' === $capo_view && 'active' !== $capo_asset['status'] ) ) {
		$capo_view = '';
	}
}
?>
<div class="wrap capo-inv">
<?php if ( 'new' === $capo_view || 'edit' === $capo_view ) : ?>
	<?php
	$capo_values   = $capo_old ? $capo_old : ( $capo_asset ? $capo_asset : array() );
	$capo_employee = isset( $capo_values['employee_id'] ) ? (int) $capo_values['employee_id'] : CAPO_Inv_Admin::get_int( 'employee_id' );
	$capo_return   = CAPO_Inv_Admin::get_int( 'employee_id' );
	?>
	<h1>
		<?php
		if ( $capo_asset ) {
			/* translators: %s: Asset Tag. */
			printf( esc_html__( 'Edit Asset %s', 'capo-inventory' ), esc_html( $capo_asset['asset_tag'] ) );
		} else {
			esc_html_e( 'Register an Asset', 'capo-inventory' );
		}
		?>
	</h1>
	<?php $this->render_flash(); ?>
	<?php if ( $capo_asset && ( $capo_pending = CAPO_Inv_Requests::pending_for_asset( (int) $capo_asset['id'] ) ) ) : // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found ?>
		<div class="notice notice-warning inline"><p>
			<?php esc_html_e( 'The employee has a pending request for this asset. Saving here changes the approved record now; review the request afterwards.', 'capo-inventory' ); ?>
			<a href="<?php echo esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_REQUESTS, array( 'view' => 'review', 'request' => $capo_pending['id'] ) ) ); ?>"><?php esc_html_e( 'View request', 'capo-inventory' ); ?></a>
		</p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
		<input type="hidden" name="action" value="capo_inv_save_asset" />
		<input type="hidden" name="asset_id" value="<?php echo esc_attr( $capo_asset ? $capo_asset['id'] : 0 ); ?>" />
		<input type="hidden" name="return_employee" value="<?php echo esc_attr( $capo_return ); ?>" />
		<?php wp_nonce_field( 'capo_inv_save_asset' ); ?>
		<table class="form-table" role="presentation">
			<?php if ( $capo_asset ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( CAPO_Inv_Fields::label( 'id' ) ); ?></th>
					<td><?php echo esc_html( $capo_asset['id'] ); ?></td>
				</tr>
			<?php endif; ?>
			<?php $this->render_asset_fields( $capo_values, $capo_errors, $capo_asset ); ?>
			<tr>
				<th scope="row"><label for="capo-employee"><?php echo esc_html( CAPO_Inv_Fields::label( 'employee_id' ) ); ?> <span class="capo-inv-required" aria-hidden="true">*</span></label></th>
				<td>
					<select id="capo-employee" name="employee_id" required>
						<option value=""><?php esc_html_e( '— Select —', 'capo-inventory' ); ?></option>
						<?php
						$capo_found = false;
						foreach ( CAPO_Inv_Assets::employees() as $capo_user ) :
							$capo_found = $capo_found || (int) $capo_user->ID === $capo_employee;
							?>
							<option value="<?php echo esc_attr( $capo_user->ID ); ?>" <?php selected( $capo_employee, (int) $capo_user->ID ); ?>><?php echo esc_html( $capo_user->display_name . ' (' . $capo_user->user_email . ')' ); ?></option>
						<?php endforeach; ?>
						<?php if ( $capo_employee && ! $capo_found ) : ?>
							<option value="<?php echo esc_attr( $capo_employee ); ?>" selected><?php echo esc_html( CAPO_Inv_Fields::employee_name( $capo_employee ) ); ?></option>
						<?php endif; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Users whose role cannot submit assets (Settings → General) are not listed.', 'capo-inventory' ); ?></p>
					<?php CAPO_Inv_Admin::field_error( $capo_errors, 'employee_id' ); ?>
				</td>
			</tr>
			<?php if ( $capo_asset ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Record', 'capo-inventory' ); ?></th>
					<td>
						<?php
						/* translators: 1: status, 2: created date, 3: updated date. */
						printf( esc_html__( '%1$s · Created %2$s · Last updated %3$s', 'capo-inventory' ), esc_html( CAPO_Inv_Fields::status_label( $capo_asset['status'] ) ), esc_html( CAPO_Inv_Fields::format_datetime( $capo_asset['created_at'] ) ), esc_html( CAPO_Inv_Fields::format_datetime( $capo_asset['updated_at'] ) ) );
						?>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<?php submit_button( $capo_asset ? __( 'Save changes', 'capo-inventory' ) : __( 'Register asset', 'capo-inventory' ) ); ?>
		<a href="<?php echo esc_url( $capo_return ? add_query_arg( 'employee_id', $capo_return, $capo_base ) : $capo_base ); ?>"><?php esc_html_e( 'Cancel', 'capo-inventory' ); ?></a>
	</form>

<?php elseif ( 'status' === $capo_view ) : ?>
	<?php $capo_archiving = 'active' === $capo_asset['status']; ?>
	<h1>
		<?php
		/* translators: %s: Asset Tag. */
		printf( $capo_archiving ? esc_html__( 'Archive Asset %s', 'capo-inventory' ) : esc_html__( 'Restore Asset %s', 'capo-inventory' ), esc_html( $capo_asset['asset_tag'] ) );
		?>
	</h1>
	<?php $this->render_flash(); ?>
	<p>
		<?php
		echo $capo_archiving
			? esc_html__( 'Archived assets leave the employee\'s active inventory but stay in the register. Their Asset Tag remains reserved and cannot be reused.', 'capo-inventory' )
			: esc_html__( 'The asset returns to the active inventory of its assigned employee.', 'capo-inventory' );
		?>
	</p>
	<table class="widefat striped capo-inv-details">
		<tbody>
		<?php foreach ( CAPO_Inv_Assets::tracked_fields() as $capo_field ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( CAPO_Inv_Fields::label( $capo_field ) ); ?></th>
				<td><?php echo esc_html( CAPO_Inv_Fields::display_value( $capo_field, $capo_asset[ $capo_field ] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="capo_inv_set_status" />
		<input type="hidden" name="asset_id" value="<?php echo esc_attr( $capo_asset['id'] ); ?>" />
		<input type="hidden" name="status" value="<?php echo esc_attr( $capo_archiving ? 'archived' : 'active' ); ?>" />
		<?php wp_nonce_field( 'capo_inv_set_status' ); ?>
		<p>
			<label for="capo-note"><?php esc_html_e( 'Note for the audit log (optional)', 'capo-inventory' ); ?></label><br />
			<textarea id="capo-note" name="note" rows="2" class="large-text" maxlength="1000"></textarea>
		</p>
		<?php submit_button( $capo_archiving ? __( 'Archive asset', 'capo-inventory' ) : __( 'Restore asset', 'capo-inventory' ), $capo_archiving ? 'delete' : 'primary' ); ?>
		<a href="<?php echo esc_url( $capo_base ); ?>"><?php esc_html_e( 'Cancel', 'capo-inventory' ); ?></a>
	</form>

<?php else : ?>
	<?php
	$capo_table = new CAPO_Inv_Assets_Table();
	$capo_table->prepare_items();
	$capo_emp   = $capo_table->filters['employee_id'];
	?>
	<h1 class="wp-heading-inline">
		<?php
		if ( $capo_emp ) {
			/* translators: %s: employee name and email. */
			printf( esc_html__( 'Inventory of %s', 'capo-inventory' ), esc_html( CAPO_Inv_Fields::employee_name( $capo_emp ) ) );
		} else {
			esc_html_e( 'All Assets', 'capo-inventory' );
		}
		?>
	</h1>
	<a href="<?php echo esc_url( add_query_arg( array_filter( array( 'view' => 'new', 'employee_id' => $capo_emp ) ), $capo_base ) ); ?>" class="page-title-action"><?php esc_html_e( 'Register an Asset', 'capo-inventory' ); ?></a>
	<?php if ( $capo_emp ) : ?>
		<a href="<?php echo esc_url( $capo_base ); ?>" class="page-title-action"><?php esc_html_e( 'Show all employees', 'capo-inventory' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end" />
	<?php $this->render_flash(); ?>

	<?php $capo_table->views(); ?>
	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( CAPO_Inv_Admin::SLUG_ASSETS ); ?>" />
		<input type="hidden" name="status" value="<?php echo esc_attr( $capo_table->filters['status'] ); ?>" />
		<?php
		$capo_table->search_box( __( 'Search assets', 'capo-inventory' ), 'capo-inv-search' );
		$capo_table->display();
		?>
	</form>
	<?php include CAPO_INV_DIR . 'admin/views/quick-edit.php'; ?>

	<?php
	$capo_export_args = array_filter(
		array(
			'action'      => 'capo_inv_export',
			's'           => $capo_table->filters['search'],
			'status'      => $capo_table->filters['status'],
			'type_id'     => $capo_table->filters['type_id'],
			'location_id' => $capo_table->filters['location_id'],
			'employee_id' => $capo_table->filters['employee_id'],
			'orderby'     => $capo_table->filters['orderby'],
			'order'       => $capo_table->filters['order'],
		)
	);
	$capo_export_url = wp_nonce_url( add_query_arg( $capo_export_args, admin_url( 'admin-post.php' ) ), 'capo_inv_export' );
	?>
	<p class="capo-inv-export">
		<strong><?php esc_html_e( 'Export the assets shown by the current filters:', 'capo-inventory' ); ?></strong>
		<a class="button" href="<?php echo esc_url( add_query_arg( 'format', 'excel', $capo_export_url ) ); ?>"><?php esc_html_e( 'CSV for Excel', 'capo-inventory' ); ?></a>
		<a class="button" href="<?php echo esc_url( add_query_arg( 'format', 'csv', $capo_export_url ) ); ?>"><?php esc_html_e( 'Plain CSV', 'capo-inventory' ); ?></a>
		<br /><span class="description"><?php esc_html_e( '"CSV for Excel" keeps leading zeros in Asset Tags and serial numbers when opened in Excel. "Plain CSV" is for importing into other systems. In both, a text value that starts with =, +, -, or @ gets a leading apostrophe so spreadsheets cannot run it as a formula.', 'capo-inventory' ); ?></span>
	</p>
<?php endif; ?>
</div>
