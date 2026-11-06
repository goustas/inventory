/**
 * CAPO Inventory — Quick Edit on the All Assets screen.
 * George Oustas, Pixelcrafters.com.cy — https://www.pixelcrafters.com.cy
 *
 * Opens an inline editor in place of a table row, saves through admin-ajax.php
 * (capability, nonce and all validation are checked on the server) and swaps
 * in the updated row returned by the server.
 */
( function () {
	'use strict';

	var cfg = window.capoInvQuickEdit;
	var template = document.getElementById( 'capo-inv-qe-template' );
	if ( ! cfg || ! template ) {
		return;
	}

	var FIELDS = [ 'asset_tag', 'type_id', 'description', 'serial_number', 'location_id', 'employee_id', 'status', 'notes' ];
	var DETAIL_FIELDS = [ 'asset_tag', 'type_id', 'description', 'serial_number', 'location_id', 'employee_id', 'notes' ];
	var open = null; // { row, editor, data, opener }

	function rowData( row ) {
		var el = row.querySelector( '.capo-inv-qe-data' );
		if ( ! el ) {
			return null;
		}
		try {
			return JSON.parse( el.getAttribute( 'data-capo' ) );
		} catch ( e ) {
			return null;
		}
	}

	function field( editor, name ) {
		return editor.querySelector( '[data-field="' + name + '"]' );
	}

	function setSelect( select, value, label ) {
		value = String( value || '' );
		var found = false;
		Array.prototype.forEach.call( select.options, function ( opt ) {
			// Inactive list entries stay available only when already on the asset.
			if ( opt.getAttribute( 'data-inactive' ) === '1' ) {
				opt.disabled = opt.value !== value;
				opt.hidden = opt.value !== value;
			}
			if ( opt.value === value ) {
				found = true;
			}
		} );
		if ( ! found && value && label ) {
			// Current employee no longer eligible (for example, role changed): keep them visible.
			var extra = document.createElement( 'option' );
			extra.value = value;
			extra.textContent = label;
			extra.setAttribute( 'data-added', '1' );
			select.appendChild( extra );
		}
		select.value = value;
	}

	function syncArchivedState( editor, data ) {
		var archivedStays = data.status === 'archived' && field( editor, 'status' ).value === 'archived';
		DETAIL_FIELDS.forEach( function ( name ) {
			field( editor, name ).disabled = archivedStays;
		} );
		editor.querySelector( '.capo-inv-qe__archived-note' ).hidden = ! archivedStays;
	}

	function clearErrors( editor ) {
		Array.prototype.forEach.call( editor.querySelectorAll( '[data-error]' ), function ( el ) {
			el.textContent = '';
		} );
		Array.prototype.forEach.call( editor.querySelectorAll( '[aria-invalid]' ), function ( el ) {
			el.removeAttribute( 'aria-invalid' );
		} );
		var box = editor.querySelector( '.capo-inv-qe__message' );
		box.hidden = true;
		box.querySelector( 'p' ).textContent = '';
	}

	function showErrors( editor, message, fields ) {
		var first = null;
		Object.keys( fields || {} ).forEach( function ( name ) {
			var slot = editor.querySelector( '[data-error="' + name + '"]' );
			var input = field( editor, name );
			if ( slot ) {
				slot.textContent = fields[ name ];
			}
			if ( input ) {
				input.setAttribute( 'aria-invalid', 'true' );
				first = first || input;
			}
		} );
		if ( message ) {
			var box = editor.querySelector( '.capo-inv-qe__message' );
			box.querySelector( 'p' ).textContent = message;
			box.hidden = false;
		}
		( first || editor.querySelector( '.capo-inv-qe-save' ) ).focus();
	}

	function close( restoreFocus ) {
		if ( ! open ) {
			return;
		}
		var opener = open.opener;
		open.editor.parentNode.removeChild( open.editor );
		open.row.hidden = false;
		if ( opener ) {
			opener.setAttribute( 'aria-expanded', 'false' );
		}
		open = null;
		if ( restoreFocus && opener ) {
			opener.focus();
		}
	}

	function openEditor( row, opener ) {
		close( false );
		var data = rowData( row );
		if ( ! data ) {
			return;
		}

		var editor = template.cloneNode( true );
		editor.id = 'capo-inv-qe-' + data.id;
		editor.querySelector( 'td' ).colSpan = row.cells.length;
		editor.querySelector( '.capo-inv-qe__tag' ).textContent = data.asset_tag;

		[ 'asset_tag', 'description', 'serial_number', 'notes' ].forEach( function ( name ) {
			field( editor, name ).value = data[ name ] || '';
		} );
		setSelect( field( editor, 'type_id' ), data.type_id );
		setSelect( field( editor, 'location_id' ), data.location_id );
		setSelect( field( editor, 'employee_id' ), data.employee_id, data.employee_name );
		field( editor, 'status' ).value = data.status;

		row.hidden = true;
		row.parentNode.insertBefore( editor, row.nextSibling );
		opener.setAttribute( 'aria-expanded', 'true' );
		open = { row: row, editor: editor, data: data, opener: opener };

		syncArchivedState( editor, data );
		field( editor, 'status' ).addEventListener( 'change', function () {
			syncArchivedState( editor, data );
		} );

		var first = field( editor, 'asset_tag' );
		( first.disabled ? field( editor, 'status' ) : first ).focus();
	}

	function save() {
		if ( ! open || open.busy ) {
			return;
		}
		var editor = open.editor;
		var body = new FormData();
		body.append( 'action', 'capo_inv_quick_edit' );
		body.append( '_ajax_nonce', cfg.nonce );
		body.append( 'asset_id', open.data.id );
		body.append( 'updated_at', open.data.updated_at );
		FIELDS.forEach( function ( name ) {
			body.append( name, field( editor, name ).value );
		} );

		clearErrors( editor );
		open.busy = true;
		var spinner = editor.querySelector( '.spinner' );
		var button = editor.querySelector( '.capo-inv-qe-save' );
		spinner.classList.add( 'is-active' );
		button.disabled = true;

		var current = open;
		fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json().catch( function () {
					return { success: false, data: { message: cfg.i18n.failed } };
				} );
			} )
			.then( function ( json ) {
				if ( current !== open ) {
					return;
				}
				open.busy = false;
				spinner.classList.remove( 'is-active' );
				button.disabled = false;

				if ( json && json.success && json.data && json.data.row ) {
					var holder = document.createElement( 'tbody' );
					holder.innerHTML = json.data.row;
					var fresh = holder.querySelector( 'tr' );
					var oldRow = open.row;
					editor.parentNode.removeChild( editor );
					oldRow.parentNode.replaceChild( fresh, oldRow );
					open = null;
					announce( json.data.message || cfg.i18n.saved );
					var btn = fresh.querySelector( '.capo-inv-qe-open' );
					if ( btn ) {
						btn.focus();
					}
					return;
				}
				var data = ( json && json.data ) || {};
				showErrors( editor, data.message || cfg.i18n.failed, data.fields );
			} )
			.catch( function () {
				if ( current !== open ) {
					return;
				}
				open.busy = false;
				spinner.classList.remove( 'is-active' );
				button.disabled = false;
				showErrors( editor, cfg.i18n.failed, null );
			} );
	}

	function announce( text ) {
		if ( window.wp && window.wp.a11y && window.wp.a11y.speak ) {
			window.wp.a11y.speak( text );
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var openBtn = event.target.closest( '.capo-inv-qe-open' );
		if ( openBtn ) {
			event.preventDefault();
			openEditor( openBtn.closest( 'tr' ), openBtn );
			return;
		}
		if ( event.target.closest( '.capo-inv-qe-cancel' ) ) {
			event.preventDefault();
			close( true );
			return;
		}
		if ( event.target.closest( '.capo-inv-qe-save' ) ) {
			event.preventDefault();
			save();
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( ! open || ! open.editor.contains( event.target ) ) {
			return;
		}
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			close( true );
		} else if ( event.key === 'Enter' && event.target.tagName !== 'TEXTAREA' && event.target.tagName !== 'BUTTON' ) {
			// Save instead of submitting the surrounding filter form.
			event.preventDefault();
			save();
		}
	} );
}() );
