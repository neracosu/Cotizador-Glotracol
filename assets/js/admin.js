/* Glotracol Cotizador — JS del panel de administración.
 * Consolida lo que antes vivía en bloques <script> inline:
 *  - Test de envío SMTP (con estado de carga).
 *  - Alta/baja de filas dinámicas (presentaciones, precios B2B) por delegación.
 *  - Modal "Convertir cotización en pedido".
 * Datos y nonces llegan vía wp_localize_script (objeto GloqAdmin).
 */
( function ( $ ) {
	'use strict';

	var G = window.GloqAdmin || { ajaxUrl: window.ajaxurl, i18n: {} };
	var I18N = G.i18n || {};

	/* ---------------------------------------------------------------
	 * Filas dinámicas
	 * Botón:    <button class="button" data-gloq-add-row
	 *                   data-target="#tbody-id" data-template="#tpl-id" data-next="N">
	 * Plantilla: <template id="tpl-id"> con el marcador __IDX__ en los name=""
	 * ------------------------------------------------------------- */
	$( document ).on( 'click', '[data-gloq-add-row]', function () {
		var $btn  = $( this );
		var $body = $( $btn.data( 'target' ) );
		var tpl   = document.querySelector( $btn.data( 'template' ) );
		if ( ! $body.length || ! tpl ) {
			return;
		}
		var next = parseInt( $btn.attr( 'data-next' ), 10 );
		if ( isNaN( next ) ) {
			next = $body.children( 'tr' ).length;
		}
		$body.append( tpl.innerHTML.replace( /__IDX__/g, next ) );
		$btn.attr( 'data-next', next + 1 );
	} );

	/* Quitar fila: vacía el campo clave (sku/label) y atenúa la fila.
	 * El guardado en servidor ignora filas con clave vacía. */
	$( document ).on( 'click', '.gloq-remove-row', function ( e ) {
		e.preventDefault();
		var $tr  = $( this ).closest( 'tr' );
		// Si la fila ya tiene datos, confirmar: al guardar se eliminará ese registro.
		var hasData = false;
		$tr.find( 'input' ).each( function () {
			if ( $.trim( $( this ).val() ) !== '' ) { hasData = true; }
		} );
		if ( hasData && ! window.confirm( 'Quitar esta fila eliminará ese registro al guardar el cambio. ¿Continuar?' ) ) {
			return;
		}
		var $key = $tr.find( 'input[name$="[sku]"], input[name$="[label]"], input[name$="[product_id]"]' ).first();
		if ( $key.length ) {
			$key.val( '' );
		}
		$tr.addClass( 'gloq-row-removed' );
		$tr.find( 'input' ).prop( 'disabled', true );
	} );

	/* ---------------------------------------------------------------
	 * Test de envío SMTP
	 * ------------------------------------------------------------- */
	$( document ).on( 'click', '#gloq-smtp-test-btn', function () {
		var $btn = $( this );
		var $r   = $( '#gloq-smtp-test-result' );
		var to   = $.trim( $( '#gloq-smtp-test-to' ).val() );
		if ( ! to ) {
			$r.html( '<span class="gloq-msg gloq-msg-err">Email requerido</span>' );
			return;
		}
		if ( ! window.confirm( 'Se enviará un email de prueba real a ' + to + '. ¿Continuar?' ) ) {
			return;
		}
		$btn.data( 'label', $btn.text() ).prop( 'disabled', true ).text( I18N.sending || 'Enviando…' );
		$r.html( '<span class="gloq-spinner" aria-hidden="true"></span>' );
		$.post( G.ajaxUrl, { action: 'gloq_smtp_test', _wpnonce: G.smtpNonce, to: to } )
			.done( function ( resp ) {
				if ( resp && resp.success ) {
					$r.html( '<span class="gloq-msg gloq-msg-ok">' + resp.data.message + '</span>' );
				} else {
					$r.html( '<span class="gloq-msg gloq-msg-err">' + ( ( resp && resp.data && resp.data.message ) || 'Error desconocido' ) + '</span>' );
				}
			} )
			.fail( function () {
				$r.html( '<span class="gloq-msg gloq-msg-err">Error de conexión</span>' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( $btn.data( 'label' ) );
			} );
	} );

	/* ---------------------------------------------------------------
	 * Convertir cotización en pedido (pantalla de edición de cotización)
	 * ------------------------------------------------------------- */
	$( function () {
		var $modal = $( '#gloq-convert-modal' );
		if ( ! $modal.length ) {
			return;
		}
		var $status = $( '#gloq-convert-status' );
		var postId  = parseInt( $modal.data( 'post-id' ), 10 ) || 0;

		function fmt( amount ) {
			return '$ ' + ( parseInt( amount, 10 ) || 0 ).toLocaleString( 'es-CO' ) + ' COP';
		}
		function recompute() {
			var total = 0;
			$( '.gloq-convert-price' ).each( function () {
				var price = parseInt( $( this ).val(), 10 ) || 0;
				var qty   = parseInt( $( this ).data( 'qty' ), 10 ) || 0;
				var sub   = price * qty;
				$( '.gloq-convert-subtotal[data-idx="' + $( this ).data( 'idx' ) + '"]' ).text( fmt( sub ) );
				total += sub;
			} );
			$( '#gloq-convert-total' ).text( fmt( total ) );
		}

		$( '#gloq-convert-btn' ).on( 'click', function () { $modal.show(); recompute(); } );
		$( '#gloq-convert-cancel, .gloq-convert-close' ).on( 'click', function () { $modal.hide(); } );
		$modal.on( 'click', function ( e ) { if ( e.target === this ) { $modal.hide(); } } );
		$( document ).on( 'input change', '.gloq-convert-price', recompute );

		$( '#gloq-convert-confirm' ).on( 'click', function () {
			if ( ! window.confirm( 'El cliente recibirá ahora el email de confirmación de pedido con estos precios. ¿Enviar?' ) ) {
				return;
			}
			var $c = $( this );
			var prices = {};
			$( '.gloq-convert-price' ).each( function () {
				prices[ $( this ).data( 'idx' ) ] = parseInt( $( this ).val(), 10 ) || 0;
			} );
			$c.data( 'label', $c.text() ).prop( 'disabled', true ).text( I18N.converting || 'Convirtiendo…' );
			$.post( G.ajaxUrl, {
				action: 'gloq_convert_to_order',
				_wpnonce: G.convertNonce,
				post_id: postId,
				prices: prices
			} )
				.done( function ( resp ) {
					if ( resp && resp.success ) {
						$status.html( '<span class="gloq-msg gloq-msg-ok">' + resp.data.message + '</span>' );
						setTimeout( function () { location.reload(); }, 1200 );
					} else {
						window.alert( ( resp && resp.data && resp.data.message ) || 'Error desconocido' );
						$c.prop( 'disabled', false ).text( $c.data( 'label' ) );
					}
				} )
				.fail( function () {
					window.alert( 'Error de conexión' );
					$c.prop( 'disabled', false ).text( $c.data( 'label' ) );
				} );
		} );
	} );

	/* Cotización → caja "Log de envíos": reenviar el correo al cliente. */
	$( function () {
		var $btn = $( '#gloq-resend-btn' );
		if ( ! $btn.length ) return;
		var $status = $( '#gloq-resend-status' );
		$btn.on( 'click', function () {
			if ( ! window.confirm( 'Se volverá a enviar al cliente el correo de la cotización con el PDF adjunto. ¿Enviar?' ) ) return;
			$btn.data( 'label', $btn.text() ).prop( 'disabled', true ).text( I18N.sending || 'Enviando…' );
			$status.empty();
			$.post( G.ajaxUrl, {
				action: 'gloq_resend_customer',
				_wpnonce: G.resendNonce,
				post_id: $btn.data( 'post-id' )
			} )
				.done( function ( resp ) {
					var ok = resp && resp.success;
					var msg = ( resp && resp.data && resp.data.message ) || 'Respuesta inesperada';
					$status.html( '<span class="gloq-msg ' + ( ok ? 'gloq-msg-ok' : 'gloq-msg-err' ) + '">' + msg + '</span>' );
					if ( ok ) setTimeout( function () { location.reload(); }, 1500 );
				} )
				.fail( function () {
					$status.html( '<span class="gloq-msg gloq-msg-err">Error de conexión</span>' );
				} )
				.always( function () {
					$btn.prop( 'disabled', false ).text( $btn.data( 'label' ) );
				} );
		} );
	} );

	/* ---------------------------------------------------------------
	 * Cotejo de importación: filtros por estado y plegado de "iguales".
	 * ------------------------------------------------------------- */
	$( function () {
		var $cotejo = $( '.gloq-cotejo-table' );
		if ( $cotejo.length ) {
			$( '.gloq-cotejo-chip' ).on( 'click', function () {
				var f = $( this ).data( 'filter' );
				$( '.gloq-cotejo-chip' ).removeClass( 'is-active' );
				$( this ).addClass( 'is-active' );
				$cotejo.find( 'tbody tr' ).each( function () {
					var $tr = $( this );
					var show = ( f === 'all' ) ||
						( f === 'alert' ? $tr.data( 'alert' ) == 1 : $tr.data( 'status' ) === f );
					$tr.toggle( !! show );
				} );
			} );
			// Iguales plegados por defecto.
			$cotejo.find( 'tbody tr[data-status="same"]' ).hide();
		}
	} );

} )( jQuery );

// Ajustes → Integraciones → GoHighLevel: rellena las etapas según el pipeline elegido.
(function () {
	var raw = document.getElementById('gloq-ghl-pipelines');
	var pipeSel = document.getElementById('gloq-ghl-pipeline');
	if (!raw || !pipeSel) return;

	var data = {};
	try { data = JSON.parse(raw.textContent || '{}'); } catch (e) { return; }

	var stageSels = ['gloq-ghl-stage', 'gloq-ghl-stage-pending']
		.map(function (id) { return document.getElementById(id); })
		.filter(Boolean);

	function fill() {
		var stages = (data[pipeSel.value] && data[pipeSel.value].stages) || {};
		stageSels.forEach(function (sel) {
			var want = sel.getAttribute('data-selected') || '';
			sel.innerHTML = '';
			var empty = document.createElement('option');
			empty.value = '';
			empty.textContent = '— Elegir —';
			sel.appendChild(empty);
			Object.keys(stages).forEach(function (sid) {
				var o = document.createElement('option');
				o.value = sid;
				o.textContent = stages[sid];
				if (sid === want) o.selected = true;
				sel.appendChild(o);
			});
		});
	}

	pipeSel.addEventListener('change', fill);
	fill();
})();

// Ajustes → Integraciones → GoHighLevel: botón "Probar conexión".
(function () {
	var btn = document.getElementById('gloq-ghl-test');
	if (!btn || typeof GloqAdmin === 'undefined') return;
	var out = document.getElementById('gloq-ghl-test-result');

	btn.addEventListener('click', function () {
		btn.disabled = true;
		out.textContent = 'Probando…';
		out.style.color = '#666';

		var body = new URLSearchParams();
		body.append('action', 'gloq_ghl_test');
		body.append('_wpnonce', GloqAdmin.ghlNonce);

		fetch(GloqAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				var okey = j && j.success;
				out.textContent = (j && j.data && j.data.message) || 'Respuesta inesperada';
				out.style.color = okey ? '#0a4d3a' : '#dc3545';
			})
			.catch(function () {
				out.textContent = 'No se pudo contactar al servidor';
				out.style.color = '#dc3545';
			})
			.finally(function () { btn.disabled = false; });
	});
})();

/* Apariencia: selector de color y de logo */
(function () {
	if (typeof jQuery === 'undefined') return;
	jQuery(function ($) {
		var $color = $('.gloq-color-field');
		if ($color.length && $.fn.wpColorPicker) $color.wpColorPicker();

		var $pick = $('#gloq-brand-logo-pick');
		if (!$pick.length || typeof wp === 'undefined' || !wp.media) return;
		var $id = $('#gloq-brand-logo-id'), $prev = $('.gloq-logo-preview'), $clear = $('#gloq-brand-logo-clear');
		var frame;
		$pick.on('click', function (e) {
			e.preventDefault();
			if (!frame) {
				frame = wp.media({ title: 'Elegir logo', library: { type: 'image' }, multiple: false, button: { text: 'Usar este logo' } });
				frame.on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					var src = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
					$id.val(a.id);
					$prev.find('img').attr('src', src);
					$prev.prop('hidden', false);
					$clear.prop('hidden', false);
				});
			}
			frame.open();
		});
		$clear.on('click', function (e) {
			e.preventDefault();
			$id.val(0);
			$prev.prop('hidden', true);
			$clear.prop('hidden', true);
		});
	});
})();
