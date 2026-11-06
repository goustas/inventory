<?php
/**
 * Quick Edit template for the All Assets table. Cloned by
 * assets/js/admin-quick-edit.js into the row being edited.
 *
 * The inputs deliberately have no name attributes: the table sits inside the
 * GET filter form, and pressing Enter must save the asset, not submit filters.
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;
?>
<table hidden aria-hidden="true">
	<tbody>
		<tr id="capo-inv-qe-template" class="inline-edit-row capo-inv-qe-row">
			<td colspan="8" class="colspanchange">
				<div class="capo-inv-qe" role="group" aria-label="<?php esc_attr_e( 'Quick Edit', 'capo-inventory' ); ?>">
					<h2 class="capo-inv-qe__title"><?php esc_html_e( 'Quick Edit', 'capo-inventory' ); ?> <span class="capo-inv-qe__tag capo-inv-tag"></span></h2>
					<div class="capo-inv-qe__grid">
						<label class="capo-inv-qe__field">
							<span class="capo-inv-qe__label"><?php echo esc_html( CAPO_Inv_Fields::label( 'asset_tag' ) ); ?> <span class="capo-inv-required" aria-hidden="true">*</span></span>
							<input type="text" data-field="asset_tag" maxlength="<?php echo esc_attr( CAPO_Inv_Fields::TAG_MAX ); ?>" autocomplete="off" spellcheck="false" required />
							<span class="capo-inv-field-error" data-error="asset_tag" role="alert"></span>
						</label>
						<label class="capo-inv-qe__field">
							<span class="capo-inv-qe__label"><?php echo esc_html( CAPO_Inv_Fields::label( 'type_id' ) ); ?> <span class="capo-inv-required" aria-hidden="true">*</span></span>
							<select data-field="type_id" required>
								<option value=""><?php esc_html_e( '— Select —', 'capo-inventory' ); ?></option>
								<?php foreach ( CAPO_Inv_Lists::all( 'types' ) as $capo_id => $capo_row ) : ?>
									<option value="<?php echo esc_attr( $capo_id ); ?>" data-inactive="<?php echo (int) $capo_row->is_active ? '0' : '1'; ?>">
										<?php
										echo esc_html(
											(int) $capo_row->is_active
												? $capo_row->name
												/* translators: %s: list entry name. */
												: sprintf( __( '%s (inactive)', 'capo-inventory' ), $capo_row->name )
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<span class="capo-inv-field-error" data-error="type_id" role="alert"></span>
						</label>
						<label class="capo-inv-qe__field capo-inv-qe__field--wide">
							<span class="capo-inv-qe__label"><?php echo esc_html( CAPO_Inv_Fields::label( 'description' ) ); ?></span>
							<input type="text" data-field="description" maxlength="<?php echo esc_attr( CAPO_Inv_Fields::DESCRIPTION_MAX ); ?>" />
							<span class="capo-inv-field-error" data-error="description" role="alert"></span>
						</label>
						<label class="capo-inv-qe__field">
							<span class="capo-inv-qe__label"><?php echo esc_html( CAPO_Inv_Fields::label( 'serial_number' ) ); ?></span>
							<input type="text" data-field="serial_number" maxlength="<?php echo esc_attr( CAPO_Inv_Fields::SERIAL_MAX ); ?>" autocomplete="off" spellcheck="false" />
							<span class="capo-inv-field-error" data-error="serial_number" role="alert"></span>
						</label>
						<label class="capo-inv-qe__field">
							<span class="capo-inv-qe__label"><?php echo esc_html( CAPO_Inv_Fields::label( 'location_id' ) ); ?> <span class="capo-inv-required" aria-hidden="true">*</span></span>
							<select data-field="location_id" required>
								<option value=""><?php esc_html_e( '— Select —', 'capo-inventory' ); ?></option>
								<?php foreach ( CAPO_Inv_Lists::all( 'locations' ) as $capo_id => $capo_row ) : ?>
									<option value="<?php echo esc_attr( $capo_id ); ?>" data-inactive="<?php echo (int) $capo_row->is_active ? '0' : '1'; ?>">
										<?php
										echo esc_html(
											(int) $capo_row->is_active
												? $capo_row->name
												/* translators: %s: list entry name. */
												: sprintf( __( '%s (inactive)', 'capo-inventory' ), $capo_row->name )
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<span class="capo-inv-field-error" data-error="location_id" role="alert"></span>
						</label>
						<label class="capo-inv-qe__field">
							<span class="capo-inv-qe__label"><?php echo esc_html( CAPO_Inv_Fields::label( 'employee_id' ) ); ?> <span class="capo-inv-required" aria-hidden="true">*</span></span>
							<select data-field="employee_id" required>
								<option value=""><?php esc_html_e( '— Select —', 'capo-inventory' ); ?></option>
								<?php foreach ( CAPO_Inv_Assets::employees() as $capo_user ) : ?>
									<option value="<?php echo esc_attr( $capo_user->ID ); ?>"><?php echo esc_html( $capo_user->display_name . ' (' . $capo_user->user_email . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="capo-inv-field-error" data-error="employee_id" role="alert"></span>
						</label>
						<label class="capo-inv-qe__field">
							<span class="capo-inv-qe__label"><?php echo esc_html( CAPO_Inv_Fields::label( 'status' ) ); ?></span>
							<select data-field="status">
								<option value="active"><?php echo esc_html( CAPO_Inv_Fields::status_label( 'active' ) ); ?></option>
								<option value="archived"><?php echo esc_html( CAPO_Inv_Fields::status_label( 'archived' ) ); ?></option>
							</select>
							<span class="capo-inv-field-error" data-error="status" role="alert"></span>
						</label>
						<label class="capo-inv-qe__field capo-inv-qe__field--wide">
							<span class="capo-inv-qe__label"><?php echo esc_html( CAPO_Inv_Fields::label( 'notes' ) ); ?></span>
							<textarea data-field="notes" rows="2" maxlength="<?php echo esc_attr( CAPO_Inv_Fields::NOTES_MAX ); ?>"></textarea>
							<span class="capo-inv-field-error" data-error="notes" role="alert"></span>
						</label>
					</div>
					<p class="capo-inv-qe__archived-note description" hidden><?php esc_html_e( 'This asset is archived. Set Record Status to Active to restore it and edit its details.', 'capo-inventory' ); ?></p>
					<div class="capo-inv-qe__message notice notice-error inline" hidden><p></p></div>
					<p class="capo-inv-qe__actions">
						<button type="button" class="button button-primary capo-inv-qe-save"><?php esc_html_e( 'Update', 'capo-inventory' ); ?></button>
						<button type="button" class="button capo-inv-qe-cancel"><?php esc_html_e( 'Cancel', 'capo-inventory' ); ?></button>
						<span class="spinner"></span>
					</p>
				</div>
			</td>
		</tr>
	</tbody>
</table>
