<?php
/**
 * [capo_my_assets] — the logged-in employee's equipment and requests.
 *
 * Override by copying to your-theme/capo-inventory/my-assets.php.
 *
 * @var array $capo { atts: array }
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

$capo_user_id = get_current_user_id();
$capo_view    = CAPO_Inv_Frontend::arg_key( 'view' );
$capo_admin   = CAPO_Inv_Admin::instance();
$capo_errors  = $capo_admin->field_errors();
$capo_old     = $capo_admin->old_input();
$capo_post    = admin_url( 'admin-post.php' );
$capo_per     = (int) $capo['atts']['per_page'];

// An asset is loaded only if it belongs to the logged-in user and is active.
$capo_asset = null;
if ( in_array( $capo_view, array( 'change', 'remove' ), true ) ) {
	$capo_candidate = CAPO_Inv_Assets::get( CAPO_Inv_Frontend::arg_int( 'asset' ) );
	if ( $capo_candidate && (int) $capo_candidate['employee_id'] === $capo_user_id && 'active' === $capo_candidate['status'] ) {
		$capo_asset = $capo_candidate;
	} else {
		$capo_view = '';
	}
}
?>
<div class="capo-inv-fe capo-inv-fe--my-assets">
<?php CAPO_Inv_Frontend::render_flash(); ?>

<?php if ( 'register' === $capo_view ) : ?>

	<h2 class="capo-inv-fe__title"><?php esc_html_e( 'Register an asset', 'capo-inventory' ); ?></h2>
	<p><?php esc_html_e( 'The asset will be assigned to you and added to your inventory once an Inventory Manager approves it.', 'capo-inventory' ); ?></p>
	<form method="post" action="<?php echo esc_url( $capo_post ); ?>" class="capo-inv-fe__form" novalidate>
		<?php CAPO_Inv_Frontend::form_fields( 'capo_inv_submit_create' ); ?>
		<div class="capo-inv-fe__field">
			<span class="capo-inv-fe__label"><?php echo esc_html( CAPO_Inv_Fields::label( 'employee_id' ) ); ?></span>
			<p class="capo-inv-fe__static"><?php echo esc_html( CAPO_Inv_Fields::employee_name( $capo_user_id ) ); ?></p>
		</div>
		<?php
		echo CAPO_Inv_Frontend::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output is escaped.
			'asset-fields.php',
			array(
				'values'  => $capo_old,
				'errors'  => $capo_errors,
				'current' => null,
			)
		);
		?>
		<div class="capo-inv-fe__field">
			<label for="capo-fe-reason"><?php esc_html_e( 'Comment for the reviewer', 'capo-inventory' ); ?></label>
			<textarea id="capo-fe-reason" name="reason" rows="2" maxlength="1000"><?php echo esc_textarea( isset( $capo_old['reason'] ) ? $capo_old['reason'] : '' ); ?></textarea>
		</div>
		<p class="capo-inv-fe__actions">
			<button type="submit" class="capo-inv-fe__button capo-inv-fe__button--primary"><?php esc_html_e( 'Submit for approval', 'capo-inventory' ); ?></button>
			<a href="<?php echo esc_url( CAPO_Inv_Frontend::url() ); ?>"><?php esc_html_e( 'Cancel', 'capo-inventory' ); ?></a>
		</p>
	</form>

<?php elseif ( 'change' === $capo_view ) : ?>

	<h2 class="capo-inv-fe__title">
		<?php
		/* translators: %s: Asset Tag. */
		printf( esc_html__( 'Request a change to %s', 'capo-inventory' ), '<span class="capo-inv-fe__tag">' . esc_html( $capo_asset['asset_tag'] ) . '</span>' );
		?>
	</h2>
	<?php if ( CAPO_Inv_Requests::pending_for_asset( (int) $capo_asset['id'] ) ) : ?>
		<p class="capo-inv-fe__notice capo-inv-fe__notice--warning"><?php esc_html_e( 'This asset already has a request awaiting approval.', 'capo-inventory' ); ?></p>
		<p><a href="<?php echo esc_url( CAPO_Inv_Frontend::url() ); ?>"><?php esc_html_e( 'Back to my assets', 'capo-inventory' ); ?></a></p>
	<?php else : ?>
		<p><?php esc_html_e( 'Edit the details below. The current details stay in place until your request is approved.', 'capo-inventory' ); ?></p>
		<form method="post" action="<?php echo esc_url( $capo_post ); ?>" class="capo-inv-fe__form" novalidate>
			<?php CAPO_Inv_Frontend::form_fields( 'capo_inv_submit_update' ); ?>
			<input type="hidden" name="asset_id" value="<?php echo esc_attr( $capo_asset['id'] ); ?>" />
			<?php
			echo CAPO_Inv_Frontend::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output is escaped.
				'asset-fields.php',
				array(
					'values'  => $capo_old ? $capo_old : $capo_asset,
					'errors'  => $capo_errors,
					'current' => $capo_asset,
				)
			);
			?>
			<div class="capo-inv-fe__field">
				<label for="capo-fe-reason"><?php esc_html_e( 'Reason for the change', 'capo-inventory' ); ?></label>
				<textarea id="capo-fe-reason" name="reason" rows="2" maxlength="1000"><?php echo esc_textarea( isset( $capo_old['reason'] ) ? $capo_old['reason'] : '' ); ?></textarea>
			</div>
			<p class="capo-inv-fe__actions">
				<button type="submit" class="capo-inv-fe__button capo-inv-fe__button--primary"><?php esc_html_e( 'Submit change for approval', 'capo-inventory' ); ?></button>
				<a href="<?php echo esc_url( CAPO_Inv_Frontend::url() ); ?>"><?php esc_html_e( 'Cancel', 'capo-inventory' ); ?></a>
			</p>
		</form>
	<?php endif; ?>

<?php elseif ( 'remove' === $capo_view ) : ?>

	<h2 class="capo-inv-fe__title">
		<?php
		/* translators: %s: Asset Tag. */
		printf( esc_html__( 'Request removal of %s', 'capo-inventory' ), '<span class="capo-inv-fe__tag">' . esc_html( $capo_asset['asset_tag'] ) . '</span>' );
		?>
	</h2>
	<dl class="capo-inv-fe__details">
		<?php foreach ( CAPO_Inv_Fields::editable_fields() as $capo_field ) : ?>
			<dt><?php echo esc_html( CAPO_Inv_Fields::label( $capo_field ) ); ?></dt>
			<dd><?php echo esc_html( CAPO_Inv_Fields::display_value( $capo_field, $capo_asset[ $capo_field ] ) ); ?>&nbsp;</dd>
		<?php endforeach; ?>
	</dl>
	<form method="post" action="<?php echo esc_url( $capo_post ); ?>" class="capo-inv-fe__form">
		<?php CAPO_Inv_Frontend::form_fields( 'capo_inv_submit_delete' ); ?>
		<input type="hidden" name="asset_id" value="<?php echo esc_attr( $capo_asset['id'] ); ?>" />
		<div class="capo-inv-fe__field">
			<label for="capo-fe-reason"><?php esc_html_e( 'Why should this asset be removed from your inventory?', 'capo-inventory' ); ?> <span class="capo-inv-fe__required" aria-hidden="true">*</span></label>
			<textarea id="capo-fe-reason" name="reason" rows="3" maxlength="1000" required<?php CAPO_Inv_Frontend::invalid_attrs( $capo_errors, 'reason' ); ?>><?php echo esc_textarea( isset( $capo_old['reason'] ) ? $capo_old['reason'] : '' ); ?></textarea>
			<?php CAPO_Inv_Frontend::field_error( $capo_errors, 'reason' ); ?>
		</div>
		<p class="capo-inv-fe__actions">
			<button type="submit" class="capo-inv-fe__button capo-inv-fe__button--primary"><?php esc_html_e( 'Submit removal request', 'capo-inventory' ); ?></button>
			<a href="<?php echo esc_url( CAPO_Inv_Frontend::url() ); ?>"><?php esc_html_e( 'Cancel', 'capo-inventory' ); ?></a>
		</p>
	</form>

<?php else : ?>

	<?php $capo_assets = CAPO_Inv_Assets::active_for_employee( $capo_user_id ); ?>
	<div class="capo-inv-fe__header">
		<h2 class="capo-inv-fe__title"><?php esc_html_e( 'My assets', 'capo-inventory' ); ?></h2>
		<a class="capo-inv-fe__button capo-inv-fe__button--primary" href="<?php echo esc_url( CAPO_Inv_Frontend::url( array( 'view' => 'register' ) ) ); ?>"><?php esc_html_e( 'Register an asset', 'capo-inventory' ); ?></a>
	</div>
	<p>
		<?php
		/* translators: %d: number of assets. */
		printf( esc_html( _n( '%d active asset is assigned to you.', '%d active assets are assigned to you.', count( $capo_assets ), 'capo-inventory' ) ), count( $capo_assets ) );
		?>
	</p>

	<?php if ( $capo_assets ) : ?>
		<table class="capo-inv-fe__table capo-inv-fe__table--stack">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'type_id' ) ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'description' ) ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'serial_number' ) ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'location_id' ) ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'capo-inventory' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $capo_assets as $capo_row ) : ?>
				<?php $capo_pending = CAPO_Inv_Requests::pending_for_asset( (int) $capo_row['id'] ); ?>
				<tr>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?>"><span class="capo-inv-fe__tag"><?php echo esc_html( $capo_row['asset_tag'] ); ?></span></td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'type_id' ) ); ?>"><?php echo esc_html( CAPO_Inv_Lists::name( 'types', (int) $capo_row['type_id'] ) ); ?></td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'description' ) ); ?>"><?php echo esc_html( $capo_row['description'] ); ?></td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'serial_number' ) ); ?>"><?php echo esc_html( $capo_row['serial_number'] ); ?></td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'location_id' ) ); ?>"><?php echo esc_html( CAPO_Inv_Lists::name( 'locations', (int) $capo_row['location_id'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Actions', 'capo-inventory' ); ?>">
						<?php if ( $capo_pending ) : ?>
							<span class="capo-inv-fe__badge capo-inv-fe__badge--pending">
								<?php
								/* translators: %s: request type. */
								printf( esc_html__( 'Pending: %s', 'capo-inventory' ), esc_html( CAPO_Inv_Requests::type_label( $capo_pending['request_type'] ) ) );
								?>
							</span>
						<?php else : ?>
							<a href="<?php echo esc_url( CAPO_Inv_Frontend::url( array( 'view' => 'change', 'asset' => $capo_row['id'] ) ) ); ?>"><?php esc_html_e( 'Request change', 'capo-inventory' ); ?></a>
							<span aria-hidden="true">·</span>
							<a href="<?php echo esc_url( CAPO_Inv_Frontend::url( array( 'view' => 'remove', 'asset' => $capo_row['id'] ) ) ); ?>"><?php esc_html_e( 'Request removal', 'capo-inventory' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="capo-inv-fe__empty"><?php esc_html_e( 'No assets are assigned to you yet.', 'capo-inventory' ); ?></p>
	<?php endif; ?>

	<h3 class="capo-inv-fe__subtitle"><?php esc_html_e( 'My requests', 'capo-inventory' ); ?></h3>
	<?php
	$capo_page     = max( 1, CAPO_Inv_Frontend::arg_int( 'paged' ) );
	$capo_requests = CAPO_Inv_Requests::query(
		array(
			'status'      => 'all',
			'employee_id' => $capo_user_id,
			'per_page'    => $capo_per,
			'page'        => $capo_page,
		)
	);
	?>
	<?php if ( $capo_requests['rows'] ) : ?>
		<table class="capo-inv-fe__table capo-inv-fe__table--stack">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Submitted', 'capo-inventory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Request', 'capo-inventory' ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'capo-inventory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Reviewer comment', 'capo-inventory' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'capo-inventory' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $capo_requests['rows'] as $capo_req ) : ?>
				<?php
				$capo_tag = $capo_req['payload_data']['asset_tag'];
				if ( $capo_req['asset_id'] && ( '' === $capo_tag || 'delete' === $capo_req['request_type'] ) ) {
					$capo_req_asset = CAPO_Inv_Assets::get( (int) $capo_req['asset_id'] );
					$capo_tag       = $capo_req_asset ? $capo_req_asset['asset_tag'] : $capo_tag;
				}
				?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Submitted', 'capo-inventory' ); ?>"><?php echo esc_html( CAPO_Inv_Fields::format_datetime( $capo_req['created_at'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Request', 'capo-inventory' ); ?>"><?php echo esc_html( CAPO_Inv_Requests::type_label( $capo_req['request_type'] ) ); ?></td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?>"><span class="capo-inv-fe__tag"><?php echo esc_html( $capo_tag ); ?></span></td>
					<td data-label="<?php esc_attr_e( 'Status', 'capo-inventory' ); ?>"><span class="capo-inv-fe__badge capo-inv-fe__badge--<?php echo esc_attr( $capo_req['status'] ); ?>"><?php echo esc_html( CAPO_Inv_Requests::status_label( $capo_req['status'] ) ); ?></span></td>
					<td data-label="<?php esc_attr_e( 'Reviewer comment', 'capo-inventory' ); ?>"><?php echo nl2br( esc_html( (string) $capo_req['review_note'] ) ); ?></td>
					<td>
						<?php if ( 'pending' === $capo_req['status'] ) : ?>
							<form method="post" action="<?php echo esc_url( $capo_post ); ?>" class="capo-inv-fe__inline">
								<?php CAPO_Inv_Frontend::form_fields( 'capo_inv_cancel' ); ?>
								<input type="hidden" name="request_id" value="<?php echo esc_attr( $capo_req['id'] ); ?>" />
								<button type="submit" class="capo-inv-fe__link-button"><?php esc_html_e( 'Cancel request', 'capo-inventory' ); ?></button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php CAPO_Inv_Frontend::pagination( $capo_requests['total'], $capo_per, $capo_page ); ?>
	<?php else : ?>
		<p class="capo-inv-fe__empty"><?php esc_html_e( 'You have not submitted any requests.', 'capo-inventory' ); ?></p>
	<?php endif; ?>

<?php endif; ?>
</div>
