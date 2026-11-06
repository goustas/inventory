<?php
/**
 * Shared asset form fields.
 *
 * Variables: $values (array), $errors (array), $current (array|null).
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

$capo_types     = CAPO_Inv_Lists::options( 'types', $current ? (int) $current['type_id'] : 0 );
$capo_locations = CAPO_Inv_Lists::options( 'locations', $current ? (int) $current['location_id'] : 0 );

$capo_invalid = static function ( $field ) use ( $errors ) {
	if ( ! empty( $errors[ $field ] ) ) {
		echo ' aria-invalid="true" aria-describedby="' . esc_attr( 'capo-' . $field . '-error' ) . '"';
	}
};
?>
<tr>
	<th scope="row"><label for="capo-asset-tag"><?php echo esc_html( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?> <span class="capo-inv-required" aria-hidden="true">*</span></label></th>
	<td>
		<input type="text" id="capo-asset-tag" name="asset_tag" class="regular-text" required
			maxlength="<?php echo esc_attr( CAPO_Inv_Fields::TAG_MAX ); ?>"
			pattern="[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?"
			autocomplete="off" spellcheck="false" inputmode="text"
			value="<?php echo esc_attr( $values['asset_tag'] ); ?>"<?php $capo_invalid( 'asset_tag' ); ?> />
		<p class="description"><?php esc_html_e( 'The number on the physical inventory sticker. Letters, numbers and hyphens; leading zeros are kept (for example 000123).', 'capo-inventory' ); ?></p>
		<?php CAPO_Inv_Admin::field_error( $errors, 'asset_tag' ); ?>
	</td>
</tr>
<tr>
	<th scope="row"><label for="capo-type"><?php echo esc_html( CAPO_Inv_Fields::label( 'type_id' ) ); ?> <span class="capo-inv-required" aria-hidden="true">*</span></label></th>
	<td>
		<select id="capo-type" name="type_id" required<?php $capo_invalid( 'type_id' ); ?>>
			<option value=""><?php esc_html_e( '— Select —', 'capo-inventory' ); ?></option>
			<?php foreach ( $capo_types as $capo_id => $capo_name ) : ?>
				<option value="<?php echo esc_attr( $capo_id ); ?>" <?php selected( (int) $values['type_id'], $capo_id ); ?>><?php echo esc_html( $capo_name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php CAPO_Inv_Admin::field_error( $errors, 'type_id' ); ?>
	</td>
</tr>
<tr>
	<th scope="row"><label for="capo-description"><?php echo esc_html( CAPO_Inv_Fields::label( 'description' ) ); ?></label></th>
	<td>
		<input type="text" id="capo-description" name="description" class="regular-text"
			maxlength="<?php echo esc_attr( CAPO_Inv_Fields::DESCRIPTION_MAX ); ?>"
			placeholder="<?php esc_attr_e( 'e.g. Dell Latitude 5540', 'capo-inventory' ); ?>"
			value="<?php echo esc_attr( $values['description'] ); ?>"<?php $capo_invalid( 'description' ); ?> />
		<?php CAPO_Inv_Admin::field_error( $errors, 'description' ); ?>
	</td>
</tr>
<tr>
	<th scope="row"><label for="capo-location"><?php echo esc_html( CAPO_Inv_Fields::label( 'location_id' ) ); ?> <span class="capo-inv-required" aria-hidden="true">*</span></label></th>
	<td>
		<select id="capo-location" name="location_id" required<?php $capo_invalid( 'location_id' ); ?>>
			<option value=""><?php esc_html_e( '— Select —', 'capo-inventory' ); ?></option>
			<?php foreach ( $capo_locations as $capo_id => $capo_name ) : ?>
				<option value="<?php echo esc_attr( $capo_id ); ?>" <?php selected( (int) $values['location_id'], $capo_id ); ?>><?php echo esc_html( $capo_name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php CAPO_Inv_Admin::field_error( $errors, 'location_id' ); ?>
	</td>
</tr>
<tr>
	<th scope="row"><label for="capo-serial"><?php echo esc_html( CAPO_Inv_Fields::label( 'serial_number' ) ); ?></label></th>
	<td>
		<input type="text" id="capo-serial" name="serial_number" class="regular-text"
			maxlength="<?php echo esc_attr( CAPO_Inv_Fields::SERIAL_MAX ); ?>" autocomplete="off" spellcheck="false"
			value="<?php echo esc_attr( $values['serial_number'] ); ?>"<?php $capo_invalid( 'serial_number' ); ?> />
		<p class="description"><?php esc_html_e( 'Optional. Leave blank if the item has no serial number.', 'capo-inventory' ); ?></p>
		<?php CAPO_Inv_Admin::field_error( $errors, 'serial_number' ); ?>
	</td>
</tr>
<tr>
	<th scope="row"><label for="capo-notes"><?php echo esc_html( CAPO_Inv_Fields::label( 'notes' ) ); ?></label></th>
	<td>
		<textarea id="capo-notes" name="notes" rows="3" class="large-text"
			maxlength="<?php echo esc_attr( CAPO_Inv_Fields::NOTES_MAX ); ?>"<?php $capo_invalid( 'notes' ); ?>><?php echo esc_textarea( $values['notes'] ); ?></textarea>
		<?php CAPO_Inv_Admin::field_error( $errors, 'notes' ); ?>
	</td>
</tr>
