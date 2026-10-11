/**
 * Bukas Hours — keeps the status badge live in the visitor's browser.
 *
 * The server embeds the next 8 days of opening ranges (Unix timestamps) in
 * data-bukas. Because the maths happens here, the badge stays correct even
 * when the page HTML is served from a full-page cache hours later.
 */
( function () {
	'use strict';

	var locale = document.documentElement.lang || undefined;

	/** Replace %s / %1$s / %2$s placeholders (WordPress sprintf style). */
	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return str.replace( /%(\d+\$)?s/g, function ( m, pos ) {
			return pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ];
		} );
	}

	/** Format a timestamp in the business timezone. */
	function inTz( ts, tz, opts ) {
		try {
			return new Intl.DateTimeFormat( locale, Object.assign( { timeZone: tz }, opts ) ).format( new Date( ts * 1000 ) );
		} catch ( e ) {
			return new Date( ts * 1000 ).toLocaleString();
		}
	}

	/** YYYY-MM-DD in the business timezone, for "today/tomorrow" checks. */
	function ymd( ts, tz ) {
		return inTz( ts, tz, { year: 'numeric', month: '2-digit', day: '2-digit' } );
	}

	/** Pure status calculation; exported on window for testing. */
	function compute( cfg, nowTs ) {
		var soon = ( cfg.soon || 0 ) * 60;
		for ( var i = 0; i < cfg.ranges.length; i++ ) {
			var r = cfg.ranges[ i ];
			if ( nowTs >= r[ 0 ] && nowTs < r[ 1 ] ) {
				return { state: r[ 1 ] - nowTs <= soon ? 'closing_soon' : 'open', next: r[ 1 ] };
			}
		}
		for ( var j = 0; j < cfg.ranges.length; j++ ) {
			if ( cfg.ranges[ j ][ 0 ] > nowTs ) {
				return { state: 'closed', next: cfg.ranges[ j ][ 0 ] };
			}
		}
		return { state: 'closed', next: null };
	}

	function detail( cfg, st, nowTs ) {
		var t = cfg.i18n;
		if ( ! st.next ) {
			return t.noUpcoming;
		}
		var time = inTz( st.next, cfg.tz, { hour: 'numeric', minute: '2-digit' } );
		if ( st.state !== 'closed' ) {
			return fmt( t.closesAt, time );
		}
		if ( ymd( st.next, cfg.tz ) === ymd( nowTs, cfg.tz ) ) {
			return fmt( t.opensAt, time );
		}
		if ( ymd( st.next, cfg.tz ) === ymd( nowTs + 86400, cfg.tz ) ) {
			return fmt( t.opensTmrw, time );
		}
		return fmt( t.opensOn, inTz( st.next, cfg.tz, { weekday: 'long' } ), time );
	}

	function update( node ) {
		var cfg;
		try {
			cfg = JSON.parse( node.getAttribute( 'data-bukas' ) );
		} catch ( e ) {
			return; // Leave server-rendered markup untouched.
		}
		var nowTs = Math.floor( Date.now() / 1000 );
		var st = compute( cfg, nowTs );
		node.className = node.className.replace( /\bis-[a-z-]+\b/, 'is-' + st.state.replace( '_', '-' ) );
		node.querySelector( '.bukas-status__label' ).textContent = cfg.labels[ st.state ];
		node.querySelector( '.bukas-status__detail' ).textContent = detail( cfg, st, nowTs );
	}

	function run() {
		var nodes = document.querySelectorAll( '[data-bukas]' );
		Array.prototype.forEach.call( nodes, update );
	}

	window.bukasHours = { compute: compute };

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
	setInterval( run, 60000 ); // Re-check every minute.
} )();
