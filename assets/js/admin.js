/**
 * Bukas Hours — settings screen helpers.
 * Adds/removes holiday rows, copies Monday's hours and inserts PH holidays.
 */
( function () {
	'use strict';

	var __ = window.wp && window.wp.i18n ? window.wp.i18n.__ : function ( s ) { return s; };
	var body = document.getElementById( 'bukas-closures-body' );
	var tpl = document.getElementById( 'bukas-closure-template' );
	if ( ! body || ! tpl ) {
		return;
	}
	var counter = Date.now(); // Unique index for new rows.

	function addRow( date, label ) {
		var html = tpl.innerHTML.replace( /__i__/g, String( counter++ ) );
		var tmp = document.createElement( 'tbody' );
		tmp.innerHTML = html;
		var row = tmp.firstElementChild;
		if ( date ) {
			row.querySelector( 'input[type="date"]' ).value = date;
		}
		if ( label ) {
			row.querySelector( 'input[type="text"]' ).value = label;
		}
		body.appendChild( row );
		return row;
	}

	function existingDates() {
		return Array.prototype.map.call( body.querySelectorAll( 'input[type="date"]' ), function ( i ) {
			return i.value;
		} );
	}

	/** Last Monday of August — National Heroes Day. */
	function lastMondayOfAugust( year ) {
		var d = new Date( Date.UTC( year, 7, 31 ) );
		while ( d.getUTCDay() !== 1 ) {
			d.setUTCDate( d.getUTCDate() - 1 );
		}
		return d.toISOString().slice( 0, 10 );
	}

	/** Fixed-date regular holidays (RA 9492 and later laws). Verify yearly. */
	function phHolidays( y ) {
		return [
			[ y + '-01-01', __( "New Year's Day", 'bukas-hours' ) ],
			[ y + '-04-09', __( 'Araw ng Kagitingan', 'bukas-hours' ) ],
			[ y + '-05-01', __( 'Labor Day', 'bukas-hours' ) ],
			[ y + '-06-12', __( 'Independence Day', 'bukas-hours' ) ],
			[ lastMondayOfAugust( y ), __( 'National Heroes Day', 'bukas-hours' ) ],
			[ y + '-11-30', __( 'Bonifacio Day', 'bukas-hours' ) ],
			[ y + '-12-25', __( 'Christmas Day', 'bukas-hours' ) ],
			[ y + '-12-30', __( 'Rizal Day', 'bukas-hours' ) ]
		];
	}

	document.getElementById( 'bukas-add-closure' ).addEventListener( 'click', function () {
		addRow().querySelector( 'input' ).focus();
	} );

	document.getElementById( 'bukas-add-ph' ).addEventListener( 'click', function () {
		var year = parseInt( document.getElementById( 'bukas-ph-year' ).value, 10 );
		if ( ! year ) {
			return;
		}
		var have = existingDates();
		phHolidays( year ).forEach( function ( h ) {
			if ( have.indexOf( h[ 0 ] ) === -1 ) {
				addRow( h[ 0 ], h[ 1 ] );
			}
		} );
	} );

	body.addEventListener( 'click', function ( e ) {
		if ( e.target.classList.contains( 'bukas-remove-row' ) ) {
			e.target.closest( 'tr' ).remove();
		}
	} );

	document.getElementById( 'bukas-copy-monday' ).addEventListener( 'click', function () {
		var rows = document.querySelectorAll( '.bukas-week tbody tr' );
		var src = rows[ 0 ].querySelectorAll( 'input' );
		for ( var r = 1; r <= 4; r++ ) {
			var dst = rows[ r ].querySelectorAll( 'input' );
			for ( var i = 0; i < src.length; i++ ) {
				if ( src[ i ].type === 'checkbox' ) {
					dst[ i ].checked = src[ i ].checked;
				} else {
					dst[ i ].value = src[ i ].value;
				}
			}
			rows[ r ].classList.toggle( 'is-closed', src[ 0 ].checked );
		}
	} );

	document.querySelectorAll( '.bukas-closed-toggle' ).forEach( function ( box ) {
		box.addEventListener( 'change', function () {
			box.closest( 'tr' ).classList.toggle( 'is-closed', box.checked );
		} );
	} );
} )();
