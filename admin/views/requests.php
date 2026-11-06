<?php
/**
 * Manager screen: request queue and review with approval comparison.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

$capo_view    = CAPO_Inv_Admin::get_key( 'view' );
$capo_base    = CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_REQUESTS );
$capo_request = 'review' === $capo_view ? CAPO_Inv_Requests::get( CAPO_Inv_Admin::get_int( 'request' ) ) : null;

/**
 * Asset Tag shown for a request: proposed tag, or the asset's current tag.
 *
 * @param array $request Request row.
 * @return string
 */
$capo_request_tag = static function ( array $request ) {
	if ( '' !== $request['payload_data']['asset_tag'] ) {
		return $request['payload_data']['asset_tag'];
	}
	$asset = $request['asset_id'] ? CAPO_Inv_Assets::get( (int) $request['asset_id'] ) : null;
	return $asset ? $asset['asset_tag'] : '';
};
?>
<div class="wrap capo-inv">
<?php if ( $capo_request ) : ?>
	<h1>
		<?php
		/* translators: 1: request ID, 2: request type. */
		printf( esc_html__( 'Request #%1$d: %2$s', 'capo-inventory' ), (int) $capo_request['id'], esc_html( CAPO_Inv_Requests::type_label( $capo_request['request_type'] ) ) );
		?>
	</h1>
	<?php $this->render_flash(); ?>

	<table class="widefat striped capo-inv-details">
		<tbody>
			<tr><th scope="row"><?php echo esc_html( CAPO_Inv_Fields::label( 'employee_id' ) ); ?></th><td><?php echo esc_html( CAPO_Inv_Fields::employee_name( (int) $capo_request['employee_id'] ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Submitted', 'capo-inventory' ); ?></th><td><?php echo esc_html( CAPO_Inv_Fields::format_datetime( $capo_request['created_at'] ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Status', 'capo-inventory' ); ?></th><td><span class="capo-inv-badge capo-inv-badge--<?php echo esc_attr( $capo_request['status'] ); ?>"><?php echo esc_html( CAPO_Inv_Requests::status_label( $capo_request['status'] ) ); ?></span></td></tr>
			<?php if ( '' !== (string) $capo_request['reason'] ) : ?>
				<tr><th scope="row"><?php esc_html_e( 'Employee comment', 'capo-inventory' ); ?></th><td><?php echo nl2br( esc_html( $capo_request['reason'] ) ); ?></td></tr>
			<?php endif; ?>
			<?php if ( 'pending' !== $capo_request['status'] ) : ?>
				<tr><th scope="row"><?php esc_html_e( 'Decided by', 'capo-inventory' ); ?></th><td><?php echo esc_html( CAPO_Inv_Fields::employee_name( (int) $capo_request['reviewer_id'] ) . ' · ' . CAPO_Inv_Fields::format_datetime( $capo_request['reviewed_at'] ) ); ?></td></tr>
				<?php if ( '' !== (string) $capo_request['review_note'] ) : ?>
					<tr><th scope="row"><?php esc_html_e( 'Reviewer comment', 'capo-inventory' ); ?></th><td><?php echo nl2br( esc_html( $capo_request['review_note'] ) ); ?></td></tr>
				<?php endif; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( 'pending' === $capo_request['status'] ) : ?>
		<h2><?php esc_html_e( 'Comparison', 'capo-inventory' ); ?></h2>
		<?php if ( 'delete' === $capo_request['request_type'] ) : ?>
			<p><?php esc_html_e( 'Approving archives this asset: it leaves the employee\'s active inventory and its Asset Tag stays reserved.', 'capo-inventory' ); ?></p>
		<?php elseif ( 'create' === $capo_request['request_type'] ) : ?>
			<p><?php esc_html_e( 'Approving adds this asset to the employee\'s active inventory.', 'capo-inventory' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Changed fields are highlighted. The approved record keeps its current values until you approve.', 'capo-inventory' ); ?></p>
		<?php endif; ?>
		<table class="widefat capo-inv-compare">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Field', 'capo-inventory' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Current (approved)', 'capo-inventory' ); ?></th>
					<th scope="col"><?php echo 'delete' === $capo_request['request_type'] ? esc_html__( 'After approval', 'capo-inventory' ) : esc_html__( 'Proposed', 'capo-inventory' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( CAPO_Inv_Requests::comparison( $capo_request ) as $capo_row ) : ?>
				<tr class="<?php echo $capo_row['changed'] ? 'capo-inv-changed' : ''; ?>">
					<th scope="row">
						<?php echo esc_html( $capo_row['label'] ); ?>
						<?php if ( $capo_row['changed'] ) : ?>
							<span class="screen-reader-text"><?php esc_html_e( '(changed)', 'capo-inventory' ); ?></span>
						<?php endif; ?>
					</th>
					<td><?php echo '' === $capo_row['current'] ? '<span class="capo-inv-empty">—</span>' : nl2br( esc_html( $capo_row['current'] ) ); ?></td>
					<td>
						<?php
						if ( 'delete' === $capo_request['request_type'] ) {
							echo '' === $capo_row['current'] ? '<span class="capo-inv-empty">—</span>' : nl2br( esc_html( $capo_row['current'] ) );
						} else {
							echo '' === $capo_row['proposed'] ? '<span class="capo-inv-empty">—</span>' : nl2br( esc_html( $capo_row['proposed'] ) );
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( 'delete' === $capo_request['request_type'] ) : ?>
				<tr class="capo-inv-changed">
					<th scope="row"><?php echo esc_html( CAPO_Inv_Fields::label( 'status' ) ); ?></th>
					<td><?php echo esc_html( CAPO_Inv_Fields::status_label( 'active' ) ); ?></td>
					<td><?php echo esc_html( CAPO_Inv_Fields::status_label( 'archived' ) ); ?></td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>

		<?php if ( CAPO_Inv_Requests::can_review( $capo_request, get_current_user_id() ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="capo-inv-review">
				<input type="hidden" name="action" value="capo_inv_review" />
				<input type="hidden" name="request_id" value="<?php echo esc_attr( $capo_request['id'] ); ?>" />
				<?php wp_nonce_field( 'capo_inv_review' ); ?>
				<p>
					<label for="capo-review-note"><?php esc_html_e( 'Comment for the employee (optional)', 'capo-inventory' ); ?></label><br />
					<textarea id="capo-review-note" name="review_note" rows="2" class="large-text" maxlength="1000"></textarea>
				</p>
				<p>
					<button type="submit" name="decision" value="approve" class="button button-primary"><?php esc_html_e( 'Approve', 'capo-inventory' ); ?></button>
					<button type="submit" name="decision" value="reject" class="button"><?php esc_html_e( 'Reject', 'capo-inventory' ); ?></button>
					<a href="<?php echo esc_url( $capo_base ); ?>"><?php esc_html_e( 'Back to requests', 'capo-inventory' ); ?></a>
				</p>
			</form>
		<?php else : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'This is your own request. Another Inventory Manager or an Administrator must decide on it.', 'capo-inventory' ); ?></p></div>
		<?php endif; ?>

	<?php else : ?>
		<?php if ( 'delete' !== $capo_request['request_type'] ) : ?>
			<h2><?php esc_html_e( 'Values requested', 'capo-inventory' ); ?></h2>
			<table class="widefat striped capo-inv-details">
				<tbody>
				<?php
				$capo_shown = null === $capo_request['payload_data']['_changed'] ? CAPO_Inv_Fields::editable_fields() : $capo_request['payload_data']['_changed'];
				foreach ( $capo_shown as $capo_field ) :
					$capo_value = $capo_request['payload_data'][ $capo_field ];
					?>
					<tr>
						<th scope="row"><?php echo esc_html( CAPO_Inv_Fields::label( $capo_field ) ); ?></th>
						<td><?php echo ( '' === $capo_value || 0 === $capo_value ) ? '—' : nl2br( esc_html( CAPO_Inv_Fields::display_value( $capo_field, $capo_value ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php if ( $capo_request['asset_id'] ) : ?>
			<p><a href="<?php echo esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_AUDIT, array( 'asset' => $capo_request['asset_id'] ) ) ); ?>"><?php esc_html_e( 'View asset history', 'capo-inventory' ); ?></a></p>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( $capo_base ); ?>"><?php esc_html_e( 'Back to requests', 'capo-inventory' ); ?></a></p>
	<?php endif; ?>

<?php else : ?>
	<?php
	$capo_status = CAPO_Inv_Admin::get_key( 'status', 'pending' );
	if ( ! in_array( $capo_status, array( 'pending', 'approved', 'rejected', 'cancelled', 'all' ), true ) ) {
		$capo_status = 'pending';
	}
	$capo_page   = max( 1, CAPO_Inv_Admin::get_int( 'paged' ) );
	$capo_result = CAPO_Inv_Requests::query(
		array(
			'status'   => $capo_status,
			'per_page' => 25,
			'page'     => $capo_page,
		)
	);
	?>
	<h1><?php esc_html_e( 'Requests', 'capo-inventory' ); ?></h1>
	<?php $this->render_flash(); ?>
	<ul class="subsubsub">
		<?php
		$capo_tabs = array(
			'pending'   => __( 'Pending', 'capo-inventory' ),
			'approved'  => __( 'Approved', 'capo-inventory' ),
			'rejected'  => __( 'Rejected', 'capo-inventory' ),
			'cancelled' => __( 'Cancelled', 'capo-inventory' ),
			'all'       => __( 'All', 'capo-inventory' ),
		);
		$capo_links = array();
		foreach ( $capo_tabs as $capo_key => $capo_label ) {
			$capo_links[] = sprintf(
				'<li><a href="%s"%s>%s</a></li>',
				esc_url( add_query_arg( 'status', $capo_key, $capo_base ) ),
				$capo_key === $capo_status ? ' class="current" aria-current="page"' : '',
				esc_html( $capo_label )
			);
		}
		echo implode( ' | ', $capo_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		?>
	</ul>
	<table class="widefat striped capo-inv-table">
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
		<?php if ( ! $capo_result['rows'] ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No requests here.', 'capo-inventory' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $capo_result['rows'] as $capo_req ) : ?>
			<tr>
				<td><a href="<?php echo esc_url( add_query_arg( array( 'view' => 'review', 'request' => $capo_req['id'] ), $capo_base ) ); ?>">
					<?php
					/* translators: %d: request ID. */
					printf( esc_html__( 'Review #%d', 'capo-inventory' ), (int) $capo_req['id'] );
					?>
				</a></td>
				<td><?php echo esc_html( CAPO_Inv_Requests::type_label( $capo_req['request_type'] ) ); ?></td>
				<td class="capo-inv-tag"><?php echo esc_html( $capo_request_tag( $capo_req ) ); ?></td>
				<td><?php echo esc_html( CAPO_Inv_Fields::employee_name( (int) $capo_req['employee_id'] ) ); ?></td>
				<td><?php echo esc_html( CAPO_Inv_Fields::format_datetime( $capo_req['created_at'] ) ); ?></td>
				<td><span class="capo-inv-badge capo-inv-badge--<?php echo esc_attr( $capo_req['status'] ); ?>"><?php echo esc_html( CAPO_Inv_Requests::status_label( $capo_req['status'] ) ); ?></span></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php CAPO_Inv_Admin::pagination( $capo_result['total'], 25, $capo_page ); ?>
<?php endif; ?>
</div>
