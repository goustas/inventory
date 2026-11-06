<?php
/**
 * Front-end asset form fields.
 *
 * Override by copying to your-theme/capo-inventory/asset-fields.php.
 *
 * @var array $capo { values: array, errors: array, current: array|null }
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

$capo_values  = wp_parse_args(
	$capo['values'],
	array(
		'asset_tag'     => '',
		'type_id'       => 0,
		'description'   => '',
		'location_id'   => 0,
		'serial_number' => '',
		'notes'         => '',
	)
);
$capo_errors  = $capo['errors'];
$capo_current = $capo['current'];
$capo_types   = CAPO_Inv_Lists::options( 'types', $capo_current ? (int) $capo_current['type_id'] : 0 );
$capo_locs    = CAPO_Inv_Lists::options( 'locations', $capo_current ? (int) $capo_current['location_id'] : 0 );
?>
<div class="capo-inv-fe__field">
	<label for="capo-fe-asset-tag"><?php echo esc_html( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?> <span class="capo-inv-fe__required" aria-hidden="true">*</span></label>
	<input type="text" id="capo-fe-asset-tag" name="asset_tag" required
		maxlength="<?php echo esc_attr( CAPO_Inv_Fields::TAG_MAX ); ?>"
		autocomplete="off" spellcheck="false"
		value="<?php echo esc_attr( $capo_values['asset_tag'] ); ?>"<?php CAPO_Inv_Frontend::invalid_attrs( $capo_errors, 'asset_tag' ); ?> />
	<p class="capo-inv-fe__help"><?php esc_html_e( 'The number on the inventory sticker. Letters, numbers and hyphens; leading zeros are kept (for example 000123).', 'capo-inventory' ); ?></p>
	<?php CAPO_Inv_Frontend::field_error( $capo_errors, 'asset_tag' ); ?>
</div>

<div class="capo-inv-fe__field">
	<label for="capo-fe-type"><?php echo esc_html( CAPO_Inv_Fields::label( 'type_id' ) ); ?> <span class="capo-inv-fe__required" aria-hidden="true">*</span></label>
	<select id="capo-fe-type" name="type_id" required<?php CAPO_Inv_Frontend::invalid_attrs( $capo_errors, 'type_id' ); ?>>
		<option value=""><?php esc_html_e( '— Select —', 'capo-inventory' ); ?></option>
		<?php foreach ( $capo_types as $capo_id => $capo_name ) : ?>
			<option value="<?php echo esc_attr( $capo_id ); ?>" <?php selected( (int) $capo_values['type_id'], $capo_id ); ?>><?php echo esc_html( $capo_name ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php CAPO_Inv_Frontend::field_error( $capo_errors, 'type_id' ); ?>
</div>

<div class="capo-inv-fe__field">
	<label for="capo-fe-description"><?php echo esc_html( CAPO_Inv_Fields::label( 'description' ) ); ?></label>
	<input type="text" id="capo-fe-description" name="description"
		maxlength="<?php echo esc_attr( CAPO_Inv_Fields::DESCRIPTION_MAX ); ?>"
		placeholder="<?php esc_attr_e( 'e.g. Dell Latitude 5540', 'capo-inventory' ); ?>"
		value="<?php echo esc_attr( $capo_values['description'] ); ?>"<?php CAPO_Inv_Frontend::invalid_attrs( $capo_errors, 'description' ); ?> />
	<?php CAPO_Inv_Frontend::field_error( $capo_errors, 'description' ); ?>
</div>

<div class="capo-inv-fe__field">
	<label for="capo-fe-location"><?php echo esc_html( CAPO_Inv_Fields::label( 'location_id' ) ); ?> <span class="capo-inv-fe__required" aria-hidden="true">*</span></label>
	<select id="capo-fe-location" name="location_id" required<?php CAPO_Inv_Frontend::invalid_attrs( $capo_errors, 'location_id' ); ?>>
		<option value=""><?php esc_html_e( '— Select —', 'capo-inventory' ); ?></option>
		<?php foreach ( $capo_locs as $capo_id => $capo_name ) : ?>
			<option value="<?php echo esc_attr( $capo_id ); ?>" <?php selected( (int) $capo_values['location_id'], $capo_id ); ?>><?php echo esc_html( $capo_name ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php CAPO_Inv_Frontend::field_error( $capo_errors, 'location_id' ); ?>
</div>

<div class="capo-inv-fe__field">
	<label for="capo-fe-serial"><?php echo esc_html( CAPO_Inv_Fields::label( 'serial_number' ) ); ?></label>
	<input type="text" id="capo-fe-serial" name="serial_number"
		maxlength="<?php echo esc_attr( CAPO_Inv_Fields::SERIAL_MAX ); ?>" autocomplete="off" spellcheck="false"
		value="<?php echo esc_attr( $capo_values['serial_number'] ); ?>"<?php CAPO_Inv_Frontend::invalid_attrs( $capo_errors, 'serial_number' ); ?> />
	<p class="capo-inv-fe__help"><?php esc_html_e( 'Optional. Leave blank if the item has no serial number.', 'capo-inventory' ); ?></p>
	<?php CAPO_Inv_Frontend::field_error( $capo_errors, 'serial_number' ); ?>
</div>

<div class="capo-inv-fe__field">
	<label for="capo-fe-notes"><?php echo esc_html( CAPO_Inv_Fields::label( 'notes' ) ); ?></label>
	<textarea id="capo-fe-notes" name="notes" rows="3"
		maxlength="<?php echo esc_attr( CAPO_Inv_Fields::NOTES_MAX ); ?>"<?php CAPO_Inv_Frontend::invalid_attrs( $capo_errors, 'notes' ); ?>><?php echo esc_textarea( $capo_values['notes'] ); ?></textarea>
	<?php CAPO_Inv_Frontend::field_error( $capo_errors, 'notes' ); ?>
</div>
