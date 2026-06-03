/* global jQuery, GloqMiniCart */
( function ( $ ) {
	'use strict';

	var ns = ( window.GlotracolQuote = window.GlotracolQuote || {} );

	function $fab() { return $( '#gloq-fab' ); }

	function open() {
		var f = $fab();
		f.addClass( 'gloq-fab-open' );
		f.find( '.gloq-fab-btn' ).attr( 'aria-expanded', 'true' );
		f.find( '.gloq-fab-panel' ).attr( 'aria-hidden', 'false' );
		f.find( '.gloq-fab-overlay' ).prop( 'hidden', false );
	}

	function close() {
		var f = $fab();
		f.removeClass( 'gloq-fab-open' );
		f.find( '.gloq-fab-btn' ).attr( 'aria-expanded', 'false' );
		f.find( '.gloq-fab-panel' ).attr( 'aria-hidden', 'true' );
		f.find( '.gloq-fab-overlay' ).prop( 'hidden', true );
	}

	function syncVisibility() {
		var n = parseInt( $fab().find( '.gloq-fab-count' ).first().text(), 10 ) || 0;
		$fab().toggleClass( 'gloq-fab-hidden', n <= 0 );
		if ( n <= 0 ) { close(); }
	}

	function updateQty( key, qty ) {
		return $.post( GloqMiniCart.ajaxUrl, {
			action: 'gloq_update_qty',
			nonce: GloqMiniCart.qtyNonce,
			key: key,
			qty: qty
		} ).done( function () {
			// Refresca fragments WC (contador + cuerpo del panel).
			$( document.body ).trigger( 'wc_fragment_refresh' );
		} );
	}

	$( function () {
		var f = $fab();
		if ( ! f.length ) { return; }

		f.on( 'click', '.gloq-fab-btn', function () {
			f.hasClass( 'gloq-fab-open' ) ? close() : open();
		} );
		f.on( 'click', '.gloq-fab-close, .gloq-fab-overlay', close );

		// Cambiar cantidad (debounce 400ms).
		var t;
		f.on( 'change input', '.gloq-fab-qty', function () {
			var $i = $( this ), key = $i.data( 'key' ), qty = Math.max( 0, parseInt( $i.val(), 10 ) || 0 );
			clearTimeout( t );
			t = setTimeout( function () { updateQty( key, qty ); }, 400 );
		} );

		// Quitar item.
		f.on( 'click', '.gloq-fab-remove', function () {
			updateQty( $( this ).data( 'key' ), 0 );
		} );

		// Escape cierra.
		$( document ).on( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) { close(); }
		} );

		// Cuando WC actualiza fragments (add/remove/refresh), re-sincroniza visibilidad.
		$( document.body ).on( 'wc_fragments_refreshed wc_fragments_loaded added_to_cart removed_from_cart', syncVisibility );
	} );

	ns.miniCart = { open: open, close: close };
} )( jQuery );
