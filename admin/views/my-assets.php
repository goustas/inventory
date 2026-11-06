<?php
/**
 * Employee screen: My Assets and My Requests.
 *
 * Everything here is scoped to the logged-in account. Asset IDs in the URL
 * are always checked against the current user.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

$capo_user_id = get_current_user_id();
$capo_view    = CAPO_Inv_Admin::get_key( 'view' );
$capo_errors  = $this->field_errors();
$capo_old     = $this->old_input();
$capo_base    = CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_MY );

// Load an asset only if it belongs to this user and is active.
$capo_asset = null;
if ( in_array( $capo_view, array( 'change', 'remove' ), true ) ) {
	$capo_candidate = CAPO_Inv_Assets::get( CAPO_Inv_Admin::get_int( 'asset' ) );
	if ( $capo_candidate && (int) $capo_candidate['employee_id'] === $capo_user_id && 'active' === $capo_candidate['status'] ) {
		$capo_asset = $capo_candidate;
	} else {
		$capo_view = '';
	}
}
?>
<div class="wrap capo-inv">
<?php if ( 'register' === $capo_view ) : ?>

	<h1><?php esc_html_e( 'Register an Asset', 'capo-inventory' ); ?></h1>
	<?php $this->render_flash(); ?>
	<p><?php esc_html_e( 'The asset will be assigned to you and added to your inventory once an Inventory Manager approves it.', 'capo-inventory' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
		<input type="hidden" name="action" value="capo_inv_submit_create" />
		<?php wp_nonce_field( 'capo_inv_submit_create' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html( CAPO_Inv_Fields::label( 'employee_id' ) ); ?></th>
				<td><?php echo esc_html( CAPO_Inv_Fields::employee_name( $capo_user_id ) ); ?></td>
			</tr>
			<?php $this->render_asset_fields( $capo_old, $capo_errors ); ?>
			<tr>
				<th scope="row"><label for="capo-reason"><?php esc_html_e( 'Comment for the reviewer', 'capo-inventory' ); ?></label></th>
				<td><textarea id="capo-reason" name="reason" rows="2" class="large-text" maxlength="1000"><?php echo esc_textarea( isset( $capo_old['reason'] ) ? $capo_old['reason'] : '' ); ?></textarea></td>
			</tr>
		</table>
		<?php submit_button( __( 'Submit for approval', 'capo-inventory' ) ); ?>
		<a href="<?php echo esc_url( $capo_base ); ?>"><?php esc_html_e( 'Cancel', 'capo-inventory' ); ?></a>
	</form>

<?php elseif ( 'change' === $capo_view ) : ?>

	<h1>
		<?php
		/* translators: %s: Asset Tag. */
		printf( esc_html__( 'Request a Change to %s', 'capo-inventory' ), esc_html( $capo_asset['asset_tag'] ) );
		?>
	</h1>
	<?php $this->render_flash(); ?>
	<?php if ( CAPO_Inv_Requests::pending_for_asset( (int) $capo_asset['id'] ) ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'This asset already has a request awaiting approval.', 'capo-inventory' ); ?></p></div>
	<?php else : ?>
		<p><?php esc_html_e( 'Edit the details below. The current details stay in place until your request is approved.', 'capo-inventory' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
			<input type="hidden" name="action" value="capo_inv_submit_update" />
			<input type="hidden" name="asset_id" value="<?php echo esc_attr( $capo_asset['id'] ); ?>" />
			<?php wp_nonce_field( 'capo_inv_submit_update' ); ?>
			<table class="form-table" role="presentation">
				<?php $this->render_asset_fields( $capo_old ? $capo_old : $capo_asset, $capo_errors, $capo_asset ); ?>
				<tr>
					<th scope="row"><label for="capo-reason"><?php esc_html_e( 'Reason for the change', 'capo-inventory' ); ?></label></th>
					<td><textarea id="capo-reason" name="reason" rows="2" class="large-text" maxlength="1000"><?php echo esc_textarea( isset( $capo_old['reason'] ) ? $capo_old['reason'] : '' ); ?></textarea></td>
				</tr>
			</table>
			<?php submit_button( __( 'Submit change for approval', 'capo-inventory' ) ); ?>
			<a href="<?php echo esc_url( $capo_base ); ?>"><?php esc_html_e( 'Cancel', 'capo-inventory' ); ?></a>
		</form>
	<?php endif; ?>

<?php elseif ( 'remove' === $capo_view ) : ?>

	<h1>
		<?php
		/* translators: %s: Asset Tag. */
		printf( esc_html__( 'Request Removal of %s', 'capo-inventory' ), esc_html( $capo_asset['asset_tag'] ) );
		?>
	</h1>
	<?php $this->render_flash(); ?>
	<table class="widefat striped capo-inv-details">
		<tbody>
		<?php foreach ( CAPO_Inv_Fields::editable_fields() as $capo_field ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( CAPO_Inv_Fields::label( $capo_field ) ); ?></th>
				<td><?php echo esc_html( CAPO_Inv_Fields::display_value( $capo_field, $capo_asset[ $capo_field ] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="capo_inv_submit_delete" />
		<input type="hidden" name="asset_id" value="<?php echo esc_attr( $capo_asset['id'] ); ?>" />
		<?php wp_nonce_field( 'capo_inv_submit_delete' ); ?>
		<p>
			<label for="capo-reason"><strong><?php esc_html_e( 'Why should this asset be removed from your inventory?', 'capo-inventory' ); ?></strong> <span class="capo-inv-required" aria-hidden="true">*</span></label><br />
			<textarea id="capo-reason" name="reason" rows="3" class="large-text" maxlength="1000" required><?php echo esc_textarea( isset( $capo_old['reason'] ) ? $capo_old['reason'] : '' ); ?></textarea>
			<?php CAPO_Inv_Admin::field_error( $capo_errors, 'reason' ); ?>
		</p>
		<?php submit_button( __( 'Submit removal request', 'capo-inventory' ) ); ?>
		<a href="<?php echo esc_url( $capo_base ); ?>"><?php esc_html_e( 'Cancel', 'capo-inventory' ); ?></a>
	</form>

<?php else : ?>

	<h1 class="wp-heading-inline"><?php esc_html_e( 'My Assets', 'capo-inventory' ); ?></h1>
	<a href="<?php echo esc_url( add_query_arg( 'view', 'register', $capo_base ) ); ?>" class="page-title-action"><?php esc_html_e( 'Register an Asset', 'capo-inventory' ); ?></a>
	<hr class="wp-header-end" />
	<?php $this->render_flash(); ?>

	<?php $capo_assets = CAPO_Inv_Assets::active_for_employee( $capo_user_id ); ?>
	<p>
		<?php
		/* translators: %d: number of assets. */
		printf( esc_html( _n( '%d active asset is assigned to you.', '%d active assets are assigned to you.', count( $capo_assets ), 'capo-inventory' ) ), count( $capo_assets ) );
		?>
	</p>

	<table class="widefat striped capo-inv-table">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?></th>
				<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'type_id' ) ); ?></th>
				<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'description' ) ); ?></th>
				<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'serial_number' ) ); ?></th>
				<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'location_id' ) ); ?></th>
				<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'notes' ) ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'capo-inventory' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $capo_assets ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'No assets are assigned to you yet.', 'capo-inventory' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $capo_assets as $capo_row ) : ?>
			<?php $capo_pending = CAPO_Inv_Requests::pending_for_asset( (int) $capo_row['id'] ); ?>
			<tr>
				<td class="capo-inv-tag"><?php echo esc_html( $capo_row['asset_tag'] ); ?></td>
				<td><?php echo esc_html( CAPO_Inv_Lists::name( 'types', (int) $capo_row['type_id'] ) ); ?></td>
				<td><?php echo esc_html( $capo_row['description'] ); ?></td>
				<td><?php echo esc_html( $capo_row['serial_number'] ); ?></td>
				<td><?php echo esc_html( CAPO_Inv_Lists::name( 'locations', (int) $capo_row['location_id'] ) ); ?></td>
				<td><?php echo nl2br( esc_html( $capo_row['notes'] ) ); ?></td>
				<td>
					<?php if ( $capo_pending ) : ?>
						<span class="capo-inv-badge capo-inv-badge--pending">
							<?php
							/* translators: %s: request type. */
							printf( esc_html__( 'Pending: %s', 'capo-inventory' ), esc_html( CAPO_Inv_Requests::type_label( $capo_pending['request_type'] ) ) );
							?>
						</span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'change', 'asset' => $capo_row['id'] ), $capo_base ) ); ?>"><?php esc_html_e( 'Request change', 'capo-inventory' ); ?></a>
						|
						<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'remove', 'asset' => $capo_row['id'] ), $capo_base ) ); ?>"><?php esc_html_e( 'Request removal', 'capo-inventory' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'My Requests', 'capo-inventory' ); ?></h2>
	<?php
	$capo_page     = max( 1, CAPO_Inv_Admin::get_int( 'paged' ) );
	$capo_requests = CAPO_Inv_Requests::query(
		array(
			'status'      => 'all',
			'employee_id' => $capo_user_id,
			'per_page'    => 20,
			'page'        => $capo_page,
		)
	);
	?>
	<table class="widefat striped capo-inv-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Submitted', 'capo-inventory' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Request', 'capo-inventory' ); ?></th>
				<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'capo-inventory' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Reviewer comment', 'capo-inventory' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'capo-inventory' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $capo_requests['rows'] ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'You have not submitted any requests.', 'capo-inventory' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $capo_requests['rows'] as $capo_req ) : ?>
			<?php
			$capo_tag = $capo_req['payload_data']['asset_tag'];
			if ( '' === $capo_tag && $capo_req['asset_id'] ) {
				$capo_req_asset = CAPO_Inv_Assets::get( (int) $capo_req['asset_id'] );
				$capo_tag       = $capo_req_asset ? $capo_req_asset['asset_tag'] : '';
			}
			?>
			<tr>
				<td><?php echo esc_html( CAPO_Inv_Fields::format_datetime( $capo_req['created_at'] ) ); ?></td>
				<td><?php echo esc_html( CAPO_Inv_Requests::type_label( $capo_req['request_type'] ) ); ?></td>
				<td class="capo-inv-tag"><?php echo esc_html( $capo_tag ); ?></td>
				<td><span class="capo-inv-badge capo-inv-badge--<?php echo esc_attr( $capo_req['status'] ); ?>"><?php echo esc_html( CAPO_Inv_Requests::status_label( $capo_req['status'] ) ); ?></span></td>
				<td><?php echo nl2br( esc_html( (string) $capo_req['review_note'] ) ); ?></td>
				<td>
					<?php if ( 'pending' === $capo_req['status'] ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="capo-inv-inline-form">
							<input type="hidden" name="action" value="capo_inv_cancel" />
							<input type="hidden" name="request_id" value="<?php echo esc_attr( $capo_req['id'] ); ?>" />
							<?php wp_nonce_field( 'capo_inv_cancel' ); ?>
							<button type="submit" class="button-link capo-inv-link-danger"><?php esc_html_e( 'Cancel request', 'capo-inventory' ); ?></button>
						</form>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php CAPO_Inv_Admin::pagination( $capo_requests['total'], 20, $capo_page ); ?>

<?php endif; ?>
</div>
