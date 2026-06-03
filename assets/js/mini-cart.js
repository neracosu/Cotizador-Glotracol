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

	function updateQty( key, qty, $row ) {
		return $.post( GloqMiniCart.ajaxUrl, {
			action: 'gloq_update_qty',
			_wpnonce: GloqMiniCart.qtyNonce,
			key: key,
			qty: qty
		} ).done( function ( resp ) {
			if ( ! resp || ! resp.success ) { return; }
			var data = resp.data || {};
			var n = parseInt( data.count, 10 );
			if ( isNaN( n ) ) { n = 0; }

			// Actualizamos el panel directamente con la respuesta — no dependemos
			// del ciclo de fragments de WooCommerce (que en este sitio no refresca
			// nuestro panel).
			if ( qty <= 0 && $row && $row.length ) { $row.remove(); }
			$fab().find( '.gloq-fab-count' ).text( n );

			if ( data.empty || n <= 0 ) {
				$fab().find( '.gloq-fab-panel-body' ).html( '<p class="gloq-fab-empty">Aún no has añadido productos.</p>' );
				$fab().addClass( 'gloq-fab-hidden' );
				close();
			}

			// Sincroniza otros widgets de carrito (menu-cart de Elementor, contadores).
			$( document.body ).trigger( 'wc_fragment_refresh' );
		} );
	}

	$( function () {
		var f = $fab();
		if ( ! f.length ) { return; }
		if ( f.data( 'gloqInit' ) ) { return; } // evita doble binding si el script carga dos veces
		f.data( 'gloqInit', true );

		f.on( 'click', '.gloq-fab-btn', function () {
			f.hasClass( 'gloq-fab-open' ) ? close() : open();
		} );
		f.on( 'click', '.gloq-fab-close, .gloq-fab-overlay', close );

		// Cambiar cantidad (debounce 400ms).
		var t;
		f.on( 'change input', '.gloq-fab-qty', function () {
			var $i = $( this ), key = $i.data( 'key' ), qty = Math.max( 0, parseInt( $i.val(), 10 ) || 0 );
			var $row = $i.closest( '.gloq-fab-item' );
			clearTimeout( t );
			t = setTimeout( function () { updateQty( key, qty, $row ); }, 400 );
		} );

		// Quitar item.
		f.on( 'click', '.gloq-fab-remove', function () {
			var $b = $( this );
			updateQty( $b.data( 'key' ), 0, $b.closest( '.gloq-fab-item' ) );
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
