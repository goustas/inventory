<?php
/**
 * [capo_requests] — request queue and review for Inventory Managers.
 *
 * Override by copying to your-theme/capo-inventory/requests.php.
 *
 * @var array $capo { atts: array }
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

$capo_post    = admin_url( 'admin-post.php' );
$capo_per     = (int) $capo['atts']['per_page'];
$capo_request = 'review' === CAPO_Inv_Frontend::arg_key( 'view' ) ? CAPO_Inv_Requests::get( CAPO_Inv_Frontend::arg_int( 'request' ) ) : null;

$capo_request_tag = static function ( array $request ) {
	if ( '' !== $request['payload_data']['asset_tag'] && 'delete' !== $request['request_type'] ) {
		return $request['payload_data']['asset_tag'];
	}
	$asset = $request['asset_id'] ? CAPO_Inv_Assets::get( (int) $request['asset_id'] ) : null;
	return $asset ? $asset['asset_tag'] : '';
};

$capo_empty = '<span class="capo-inv-fe__muted">—</span>';
?>
<div class="capo-inv-fe capo-inv-fe--requests">
<?php CAPO_Inv_Frontend::render_flash(); ?>

<?php if ( $capo_request ) : ?>

	<p><a href="<?php echo esc_url( CAPO_Inv_Frontend::url() ); ?>">&larr; <?php esc_html_e( 'Back to requests', 'capo-inventory' ); ?></a></p>
	<h2 class="capo-inv-fe__title">
		<?php
		/* translators: 1: request ID, 2: request type. */
		printf( esc_html__( 'Request #%1$d: %2$s', 'capo-inventory' ), (int) $capo_request['id'], esc_html( CAPO_Inv_Requests::type_label( $capo_request['request_type'] ) ) );
		?>
	</h2>

	<dl class="capo-inv-fe__details">
		<dt><?php echo esc_html( CAPO_Inv_Fields::label( 'employee_id' ) ); ?></dt>
		<dd><?php echo esc_html( CAPO_Inv_Fields::employee_name( (int) $capo_request['employee_id'] ) ); ?></dd>
		<dt><?php esc_html_e( 'Submitted', 'capo-inventory' ); ?></dt>
		<dd><?php echo esc_html( CAPO_Inv_Fields::format_datetime( $capo_request['created_at'] ) ); ?></dd>
		<dt><?php esc_html_e( 'Status', 'capo-inventory' ); ?></dt>
		<dd><span class="capo-inv-fe__badge capo-inv-fe__badge--<?php echo esc_attr( $capo_request['status'] ); ?>"><?php echo esc_html( CAPO_Inv_Requests::status_label( $capo_request['status'] ) ); ?></span></dd>
		<?php if ( '' !== (string) $capo_request['reason'] ) : ?>
			<dt><?php esc_html_e( 'Employee comment', 'capo-inventory' ); ?></dt>
			<dd><?php echo nl2br( esc_html( $capo_request['reason'] ) ); ?></dd>
		<?php endif; ?>
		<?php if ( 'pending' !== $capo_request['status'] ) : ?>
			<dt><?php esc_html_e( 'Decided by', 'capo-inventory' ); ?></dt>
			<dd><?php echo esc_html( CAPO_Inv_Fields::employee_name( (int) $capo_request['reviewer_id'] ) . ' · ' . CAPO_Inv_Fields::format_datetime( $capo_request['reviewed_at'] ) ); ?></dd>
			<?php if ( '' !== (string) $capo_request['review_note'] ) : ?>
				<dt><?php esc_html_e( 'Reviewer comment', 'capo-inventory' ); ?></dt>
				<dd><?php echo nl2br( esc_html( $capo_request['review_note'] ) ); ?></dd>
			<?php endif; ?>
		<?php endif; ?>
	</dl>

	<?php if ( 'pending' === $capo_request['status'] ) : ?>
		<h3 class="capo-inv-fe__subtitle"><?php esc_html_e( 'Comparison', 'capo-inventory' ); ?></h3>
		<p>
			<?php
			if ( 'delete' === $capo_request['request_type'] ) {
				esc_html_e( 'Approving archives this asset: it leaves the employee\'s active inventory and its Asset Tag stays reserved.', 'capo-inventory' );
			} elseif ( 'create' === $capo_request['request_type'] ) {
				esc_html_e( 'Approving adds this asset to the employee\'s active inventory.', 'capo-inventory' );
			} else {
				esc_html_e( 'Changed fields are highlighted. The approved record keeps its current values until you approve.', 'capo-inventory' );
			}
			?>
		</p>
		<table class="capo-inv-fe__table capo-inv-fe__compare">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Field', 'capo-inventory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Current (approved)', 'capo-inventory' ); ?></th>
					<th scope="col"><?php echo 'delete' === $capo_request['request_type'] ? esc_html__( 'After approval', 'capo-inventory' ) : esc_html__( 'Proposed', 'capo-inventory' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( CAPO_Inv_Requests::comparison( $capo_request ) as $capo_row ) : ?>
				<?php $capo_after = 'delete' === $capo_request['request_type'] ? $capo_row['current'] : $capo_row['proposed']; ?>
				<tr<?php echo $capo_row['changed'] ? ' class="capo-inv-fe__changed"' : ''; ?>>
					<th scope="row">
						<?php echo esc_html( $capo_row['label'] ); ?>
						<?php if ( $capo_row['changed'] ) : ?>
							<span class="capo-inv-fe__changed-label"><?php esc_html_e( 'changed', 'capo-inventory' ); ?></span>
						<?php endif; ?>
					</th>
					<td><?php echo '' === $capo_row['current'] ? $capo_empty : nl2br( esc_html( $capo_row['current'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup or escaped. ?></td>
					<td><?php echo '' === $capo_after ? $capo_empty : nl2br( esc_html( $capo_after ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup or escaped. ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( 'delete' === $capo_request['request_type'] ) : ?>
				<tr class="capo-inv-fe__changed">
					<th scope="row"><?php echo esc_html( CAPO_Inv_Fields::label( 'status' ) ); ?> <span class="capo-inv-fe__changed-label"><?php esc_html_e( 'changed', 'capo-inventory' ); ?></span></th>
					<td><?php echo esc_html( CAPO_Inv_Fields::status_label( 'active' ) ); ?></td>
					<td><?php echo esc_html( CAPO_Inv_Fields::status_label( 'archived' ) ); ?></td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>

		<?php if ( CAPO_Inv_Requests::can_review( $capo_request, get_current_user_id() ) ) : ?>
			<form method="post" action="<?php echo esc_url( $capo_post ); ?>" class="capo-inv-fe__form">
				<?php CAPO_Inv_Frontend::form_fields( 'capo_inv_review' ); ?>
				<input type="hidden" name="request_id" value="<?php echo esc_attr( $capo_request['id'] ); ?>" />
				<div class="capo-inv-fe__field">
					<label for="capo-fe-review-note"><?php esc_html_e( 'Comment for the employee (optional)', 'capo-inventory' ); ?></label>
					<textarea id="capo-fe-review-note" name="review_note" rows="2" maxlength="1000"></textarea>
				</div>
				<p class="capo-inv-fe__actions">
					<button type="submit" name="decision" value="approve" class="capo-inv-fe__button capo-inv-fe__button--primary"><?php esc_html_e( 'Approve', 'capo-inventory' ); ?></button>
					<button type="submit" name="decision" value="reject" class="capo-inv-fe__button"><?php esc_html_e( 'Reject', 'capo-inventory' ); ?></button>
				</p>
			</form>
		<?php else : ?>
			<p class="capo-inv-fe__notice capo-inv-fe__notice--warning"><?php esc_html_e( 'This is your own request. Another Inventory Manager or an Administrator must decide on it.', 'capo-inventory' ); ?></p>
		<?php endif; ?>

	<?php elseif ( 'delete' !== $capo_request['request_type'] ) : ?>
		<h3 class="capo-inv-fe__subtitle"><?php esc_html_e( 'Values requested', 'capo-inventory' ); ?></h3>
		<dl class="capo-inv-fe__details">
			<?php
			$capo_shown = null === $capo_request['payload_data']['_changed'] ? CAPO_Inv_Fields::editable_fields() : $capo_request['payload_data']['_changed'];
			foreach ( $capo_shown as $capo_field ) :
				$capo_value = $capo_request['payload_data'][ $capo_field ];
				?>
				<dt><?php echo esc_html( CAPO_Inv_Fields::label( $capo_field ) ); ?></dt>
				<dd><?php echo ( '' === $capo_value || 0 === $capo_value ) ? $capo_empty : nl2br( esc_html( CAPO_Inv_Fields::display_value( $capo_field, $capo_value ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup or escaped. ?></dd>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>

<?php else : ?>
	<?php
	$capo_status = CAPO_Inv_Frontend::arg_key( 'status', 'pending' );
	if ( ! in_array( $capo_status, array( 'pending', 'approved', 'rejected', 'cancelled', 'all' ), true ) ) {
		$capo_status = 'pending';
	}
	$capo_page   = max( 1, CAPO_Inv_Frontend::arg_int( 'paged' ) );
	$capo_result = CAPO_Inv_Requests::query(
		array(
			'status'   => $capo_status,
			'per_page' => $capo_per,
			'page'     => $capo_page,
		)
	);
	$capo_tabs   = array(
		'pending'   => __( 'Pending', 'capo-inventory' ),
		'approved'  => __( 'Approved', 'capo-inventory' ),
		'rejected'  => __( 'Rejected', 'capo-inventory' ),
		'cancelled' => __( 'Cancelled', 'capo-inventory' ),
		'all'       => __( 'All', 'capo-inventory' ),
	);
	?>
	<h2 class="capo-inv-fe__title"><?php esc_html_e( 'Requests', 'capo-inventory' ); ?></h2>
	<nav class="capo-inv-fe__tabs" aria-label="<?php esc_attr_e( 'Request status', 'capo-inventory' ); ?>">
		<?php foreach ( $capo_tabs as $capo_key => $capo_label ) : ?>
			<a href="<?php echo esc_url( CAPO_Inv_Frontend::url( array( 'status' => $capo_key ) ) ); ?>"<?php echo $capo_key === $capo_status ? ' class="is-current" aria-current="page"' : ''; ?>><?php echo esc_html( $capo_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( $capo_result['rows'] ) : ?>
		<table class="capo-inv-fe__table capo-inv-fe__table--stack">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Request', 'capo-inventory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'capo-inventory' ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'employee_id' ) ); ?></th>
					<th scope="col"><?php esc_html_e( 'Submitted', 'capo-inventory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'capo-inventory' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $capo_result['rows'] as $capo_req ) : ?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Request', 'capo-inventory' ); ?>">
						<a href="<?php echo esc_url( CAPO_Inv_Frontend::url( array( 'view' => 'review', 'request' => $capo_req['id'] ) ) ); ?>">
							<?php
							/* translators: %d: request ID. */
							printf( esc_html__( 'Review #%d', 'capo-inventory' ), (int) $capo_req['id'] );
							?>
						</a>
					</td>
					<td data-label="<?php esc_attr_e( 'Type', 'capo-inventory' ); ?>"><?php echo esc_html( CAPO_Inv_Requests::type_label( $capo_req['request_type'] ) ); ?></td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?>"><span class="capo-inv-fe__tag"><?php echo esc_html( $capo_request_tag( $capo_req ) ); ?></span></td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'employee_id' ) ); ?>"><?php echo esc_html( CAPO_Inv_Fields::employee_name( (int) $capo_req['employee_id'], false ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Submitted', 'capo-inventory' ); ?>"><?php echo esc_html( CAPO_Inv_Fields::format_datetime( $capo_req['created_at'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Status', 'capo-inventory' ); ?>"><span class="capo-inv-fe__badge capo-inv-fe__badge--<?php echo esc_attr( $capo_req['status'] ); ?>"><?php echo esc_html( CAPO_Inv_Requests::status_label( $capo_req['status'] ) ); ?></span></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php CAPO_Inv_Frontend::pagination( $capo_result['total'], $capo_per, $capo_page ); ?>
	<?php else : ?>
		<p class="capo-inv-fe__empty"><?php esc_html_e( 'No requests here.', 'capo-inventory' ); ?></p>
	<?php endif; ?>
<?php endif; ?>
</div>
