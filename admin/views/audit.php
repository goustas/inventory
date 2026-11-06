<?php
/**
 * Manager screen: audit log.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

$capo_asset_id = CAPO_Inv_Admin::get_int( 'asset' );
$capo_search   = CAPO_Inv_Admin::get_text( 's' );
$capo_page     = max( 1, CAPO_Inv_Admin::get_int( 'paged' ) );
$capo_result   = CAPO_Inv_Audit::query(
	array(
		'asset_id' => $capo_asset_id,
		'search'   => $capo_search,
		'per_page' => 50,
		'page'     => $capo_page,
	)
);
$capo_asset    = $capo_asset_id ? CAPO_Inv_Assets::get( $capo_asset_id ) : null;
?>
<div class="wrap capo-inv">
	<h1 class="wp-heading-inline">
		<?php
		if ( $capo_asset ) {
			/* translators: %s: Asset Tag. */
			printf( esc_html__( 'History of Asset %s', 'capo-inventory' ), esc_html( $capo_asset['asset_tag'] ) );
		} else {
			esc_html_e( 'Audit Log', 'capo-inventory' );
		}
		?>
	</h1>
	<?php if ( $capo_asset ) : ?>
		<a class="page-title-action" href="<?php echo esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_AUDIT ) ); ?>"><?php esc_html_e( 'Full audit log', 'capo-inventory' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end" />

	<?php if ( ! $capo_asset ) : ?>
		<form method="get" class="capo-inv-search">
			<input type="hidden" name="page" value="<?php echo esc_attr( CAPO_Inv_Admin::SLUG_AUDIT ); ?>" />
			<label for="capo-audit-search"><?php echo esc_html( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?></label>
			<input type="search" id="capo-audit-search" name="s" value="<?php echo esc_attr( $capo_search ); ?>" />
			<?php submit_button( __( 'Search', 'capo-inventory' ), '', '', false ); ?>
		</form>
	<?php endif; ?>

	<table class="widefat striped capo-inv-table capo-inv-audit">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Date', 'capo-inventory' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Action', 'capo-inventory' ); ?></th>
				<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?></th>
				<th scope="col"><?php esc_html_e( 'By', 'capo-inventory' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Changes', 'capo-inventory' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Note', 'capo-inventory' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $capo_result['rows'] ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No entries.', 'capo-inventory' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $capo_result['rows'] as $capo_row ) : ?>
			<?php $capo_changes = CAPO_Inv_Audit::changes( $capo_row ); ?>
			<tr>
				<td><?php echo esc_html( CAPO_Inv_Fields::format_datetime( $capo_row['created_at'] ) ); ?></td>
				<td>
					<?php echo esc_html( CAPO_Inv_Audit::action_label( $capo_row['action'] ) ); ?>
					<?php if ( $capo_row['request_id'] ) : ?>
						<br /><a href="<?php echo esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_REQUESTS, array( 'view' => 'review', 'request' => $capo_row['request_id'] ) ) ); ?>">
							<?php
							/* translators: %d: request ID. */
							printf( esc_html__( 'Request #%d', 'capo-inventory' ), (int) $capo_row['request_id'] );
							?>
						</a>
					<?php endif; ?>
				</td>
				<td class="capo-inv-tag">
					<?php if ( $capo_row['asset_id'] ) : ?>
						<a href="<?php echo esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_AUDIT, array( 'asset' => $capo_row['asset_id'] ) ) ); ?>"><?php echo esc_html( (string) $capo_row['asset_tag'] ); ?></a>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( CAPO_Inv_Fields::employee_name( (int) $capo_row['actor_id'], false ) ); ?></td>
				<td>
					<?php if ( $capo_changes ) : ?>
						<dl class="capo-inv-changes">
						<?php foreach ( $capo_changes as $capo_field => $capo_pair ) : ?>
							<dt><?php echo esc_html( CAPO_Inv_Fields::label( $capo_field ) ); ?></dt>
							<dd>
								<?php if ( '' !== (string) $capo_pair[0] ) : ?>
									<del><?php echo esc_html( $capo_pair[0] ); ?></del> →
								<?php endif; ?>
								<?php echo '' === (string) $capo_pair[1] ? '<em>' . esc_html__( '(blank)', 'capo-inventory' ) . '</em>' : esc_html( $capo_pair[1] ); ?>
							</dd>
						<?php endforeach; ?>
						</dl>
					<?php endif; ?>
				</td>
				<td><?php echo nl2br( esc_html( (string) $capo_row['note'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php CAPO_Inv_Admin::pagination( $capo_result['total'], 50, $capo_page ); ?>
</div>
