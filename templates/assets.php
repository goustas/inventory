<?php
/**
 * [capo_assets] — searchable, read-only asset list and per-employee inventory
 * for Inventory Managers. Editing stays in the dashboard.
 *
 * Override by copying to your-theme/capo-inventory/assets.php.
 *
 * @var array $capo { atts: array }
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

$capo_per     = (int) $capo['atts']['per_page'];
$capo_status  = CAPO_Inv_Frontend::arg_key( 'status', 'active' );
$capo_status  = in_array( $capo_status, array( 'active', 'archived', 'all' ), true ) ? $capo_status : 'active';
$capo_filters = array(
	'search'      => CAPO_Inv_Frontend::arg_text( 's' ),
	'status'      => $capo_status,
	'type_id'     => CAPO_Inv_Frontend::arg_int( 'type_id' ),
	'location_id' => CAPO_Inv_Frontend::arg_int( 'location_id' ),
	'employee_id' => CAPO_Inv_Frontend::arg_int( 'employee_id' ),
);
$capo_page    = max( 1, CAPO_Inv_Frontend::arg_int( 'paged' ) );
$capo_result  = CAPO_Inv_Assets::query(
	$capo_filters + array(
		'per_page' => $capo_per,
		'page'     => $capo_page,
	)
);
$capo_emp     = $capo_filters['employee_id'];

// Hidden inputs that keep non-plugin query args (e.g. page_id on plain permalinks) when filtering.
$capo_keep = array();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
foreach ( wp_unslash( $_GET ) as $capo_key => $capo_value ) {
	if ( is_string( $capo_value ) && ! in_array( $capo_key, CAPO_Inv_Frontend::QUERY_KEYS, true ) ) {
		$capo_keep[ sanitize_key( $capo_key ) ] = sanitize_text_field( $capo_value );
	}
}

$capo_export = wp_nonce_url(
	add_query_arg(
		array_filter(
			array(
				'action'      => 'capo_inv_export',
				's'           => $capo_filters['search'],
				'status'      => $capo_filters['status'],
				'type_id'     => $capo_filters['type_id'],
				'location_id' => $capo_filters['location_id'],
				'employee_id' => $capo_filters['employee_id'],
			)
		),
		admin_url( 'admin-post.php' )
	),
	'capo_inv_export'
);
?>
<div class="capo-inv-fe capo-inv-fe--assets">
	<div class="capo-inv-fe__header">
		<h2 class="capo-inv-fe__title">
			<?php
			if ( $capo_emp ) {
				/* translators: %s: employee name and email. */
				printf( esc_html__( 'Inventory of %s', 'capo-inventory' ), esc_html( CAPO_Inv_Fields::employee_name( $capo_emp ) ) );
			} else {
				esc_html_e( 'All assets', 'capo-inventory' );
			}
			?>
		</h2>
		<?php if ( $capo_emp ) : ?>
			<a href="<?php echo esc_url( CAPO_Inv_Frontend::url() ); ?>"><?php esc_html_e( 'Show all employees', 'capo-inventory' ); ?></a>
		<?php endif; ?>
	</div>

	<form method="get" action="<?php echo esc_url( remove_query_arg( array_keys( $capo_keep ), CAPO_Inv_Frontend::base_url() ) ); ?>" class="capo-inv-fe__filters" role="search">
		<?php foreach ( $capo_keep as $capo_key => $capo_value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $capo_key ); ?>" value="<?php echo esc_attr( $capo_value ); ?>" />
		<?php endforeach; ?>
		<div class="capo-inv-fe__field">
			<label for="capo-fe-search"><?php esc_html_e( 'Search', 'capo-inventory' ); ?></label>
			<input type="search" id="capo-fe-search" name="capo_s" value="<?php echo esc_attr( $capo_filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Asset Tag, model, serial, employee…', 'capo-inventory' ); ?>" />
		</div>
		<div class="capo-inv-fe__field">
			<label for="capo-fe-status"><?php echo esc_html( CAPO_Inv_Fields::label( 'status' ) ); ?></label>
			<select id="capo-fe-status" name="capo_status">
				<option value="active" <?php selected( $capo_status, 'active' ); ?>><?php esc_html_e( 'Active', 'capo-inventory' ); ?></option>
				<option value="archived" <?php selected( $capo_status, 'archived' ); ?>><?php esc_html_e( 'Archived', 'capo-inventory' ); ?></option>
				<option value="all" <?php selected( $capo_status, 'all' ); ?>><?php esc_html_e( 'All', 'capo-inventory' ); ?></option>
			</select>
		</div>
		<div class="capo-inv-fe__field">
			<label for="capo-fe-ftype"><?php echo esc_html( CAPO_Inv_Fields::label( 'type_id' ) ); ?></label>
			<select id="capo-fe-ftype" name="capo_type_id">
				<option value="0"><?php esc_html_e( 'All asset types', 'capo-inventory' ); ?></option>
				<?php foreach ( CAPO_Inv_Lists::all( 'types' ) as $capo_id => $capo_row ) : ?>
					<option value="<?php echo esc_attr( $capo_id ); ?>" <?php selected( $capo_filters['type_id'], $capo_id ); ?>><?php echo esc_html( $capo_row->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="capo-inv-fe__field">
			<label for="capo-fe-flocation"><?php echo esc_html( CAPO_Inv_Fields::label( 'location_id' ) ); ?></label>
			<select id="capo-fe-flocation" name="capo_location_id">
				<option value="0"><?php esc_html_e( 'All locations', 'capo-inventory' ); ?></option>
				<?php foreach ( CAPO_Inv_Lists::all( 'locations' ) as $capo_id => $capo_row ) : ?>
					<option value="<?php echo esc_attr( $capo_id ); ?>" <?php selected( $capo_filters['location_id'], $capo_id ); ?>><?php echo esc_html( $capo_row->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="capo-inv-fe__field">
			<label for="capo-fe-femployee"><?php echo esc_html( CAPO_Inv_Fields::label( 'employee_id' ) ); ?></label>
			<select id="capo-fe-femployee" name="capo_employee_id">
				<option value="0"><?php esc_html_e( 'All employees', 'capo-inventory' ); ?></option>
				<?php foreach ( CAPO_Inv_Assets::employees() as $capo_user ) : ?>
					<option value="<?php echo esc_attr( $capo_user->ID ); ?>" <?php selected( $capo_emp, (int) $capo_user->ID ); ?>><?php echo esc_html( $capo_user->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<p class="capo-inv-fe__actions">
			<button type="submit" class="capo-inv-fe__button capo-inv-fe__button--primary"><?php esc_html_e( 'Filter', 'capo-inventory' ); ?></button>
			<a href="<?php echo esc_url( CAPO_Inv_Frontend::url() ); ?>"><?php esc_html_e( 'Reset', 'capo-inventory' ); ?></a>
		</p>
	</form>

	<p class="capo-inv-fe__count" aria-live="polite">
		<?php
		/* translators: %s: number of assets. */
		printf( esc_html( _n( '%s asset found.', '%s assets found.', $capo_result['total'], 'capo-inventory' ) ), esc_html( number_format_i18n( $capo_result['total'] ) ) );
		?>
	</p>

	<?php if ( $capo_result['rows'] ) : ?>
		<table class="capo-inv-fe__table capo-inv-fe__table--stack">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'type_id' ) ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'description' ) ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'serial_number' ) ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'location_id' ) ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'employee_id' ) ); ?></th>
					<th scope="col"><?php echo esc_html( CAPO_Inv_Fields::label( 'status' ) ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $capo_result['rows'] as $capo_row ) : ?>
				<?php
				$capo_pending = 'active' === $capo_row['status'] ? CAPO_Inv_Requests::pending_for_asset( (int) $capo_row['id'] ) : null;
				$capo_name    = null !== $capo_row['employee_name'] ? $capo_row['employee_name'] : CAPO_Inv_Fields::employee_name( (int) $capo_row['employee_id'], false );
				?>
				<tr>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?>">
						<span class="capo-inv-fe__tag"><?php echo esc_html( $capo_row['asset_tag'] ); ?></span>
						<?php if ( $capo_pending ) : ?>
							<span class="capo-inv-fe__badge capo-inv-fe__badge--pending"><?php esc_html_e( 'Pending request', 'capo-inventory' ); ?></span>
						<?php endif; ?>
						<?php if ( 'active' === $capo_row['status'] ) : ?>
							<br /><a class="capo-inv-fe__small" href="<?php echo esc_url( CAPO_Inv_Admin::url( CAPO_Inv_Admin::SLUG_ASSETS, array( 'view' => 'edit', 'asset' => $capo_row['id'] ) ) ); ?>"><?php esc_html_e( 'Edit in dashboard', 'capo-inventory' ); ?></a>
						<?php endif; ?>
					</td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'type_id' ) ); ?>"><?php echo esc_html( CAPO_Inv_Lists::name( 'types', (int) $capo_row['type_id'] ) ); ?></td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'description' ) ); ?>"><?php echo esc_html( $capo_row['description'] ); ?></td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'serial_number' ) ); ?>"><?php echo esc_html( $capo_row['serial_number'] ); ?></td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'location_id' ) ); ?>"><?php echo esc_html( CAPO_Inv_Lists::name( 'locations', (int) $capo_row['location_id'] ) ); ?></td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'employee_id' ) ); ?>">
						<?php if ( $capo_row['employee_id'] ) : ?>
							<a href="<?php echo esc_url( CAPO_Inv_Frontend::url( array( 'employee_id' => $capo_row['employee_id'] ) ) ); ?>"><?php echo esc_html( $capo_name ); ?></a>
						<?php endif; ?>
					</td>
					<td data-label="<?php echo esc_attr( CAPO_Inv_Fields::label( 'status' ) ); ?>"><?php echo esc_html( CAPO_Inv_Fields::status_label( $capo_row['status'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php CAPO_Inv_Frontend::pagination( $capo_result['total'], $capo_per, $capo_page ); ?>
	<?php else : ?>
		<p class="capo-inv-fe__empty"><?php esc_html_e( 'No assets match these filters.', 'capo-inventory' ); ?></p>
	<?php endif; ?>

	<p class="capo-inv-fe__export">
		<?php esc_html_e( 'Export the assets matching these filters:', 'capo-inventory' ); ?>
		<a class="capo-inv-fe__button" href="<?php echo esc_url( add_query_arg( 'format', 'excel', $capo_export ) ); ?>"><?php esc_html_e( 'CSV for Excel', 'capo-inventory' ); ?></a>
		<a class="capo-inv-fe__button" href="<?php echo esc_url( add_query_arg( 'format', 'csv', $capo_export ) ); ?>"><?php esc_html_e( 'Plain CSV', 'capo-inventory' ); ?></a>
	</p>
</div>
