(function ($) {
	'use strict';

	var GLOQ = window.GloqData || {};

	function ensureToastContainer() {
		var $c = $('#gloq-toast-container');
		if (!$c.length) {
			$c = $('<div id="gloq-toast-container"></div>').appendTo('body');
		}
		return $c;
	}

	function showToast(productName) {
		var $c = ensureToastContainer();
		var msg = productName
			? '<strong>' + escapeHtml(productName) + '</strong> ' + (GLOQ.i18n ? GLOQ.i18n.added : 'añadido a tu cotización')
			: (GLOQ.i18n ? GLOQ.i18n.addedConfirmation : 'Producto añadido');
		var cartIcon = '<svg class="gloq-cart-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>';
		var $t = $(
			'<div class="gloq-toast" role="status" aria-live="polite">' +
				'<div class="gloq-toast-icon">✓</div>' +
				'<div class="gloq-toast-body">' +
					'<div class="gloq-toast-msg">' + msg + '</div>' +
					'<div class="gloq-toast-actions">' +
						'<a href="' + (GLOQ.cartUrl || '/carrito/') + '" class="gloq-toast-link gloq-with-icon">' + cartIcon + ' ' + (GLOQ.i18n ? GLOQ.i18n.viewQuote : 'Ver mi cotización') + '</a>' +
						'<a href="' + (GLOQ.quoteUrl || '/solicitar-cotizacion/') + '" class="gloq-toast-link gloq-toast-link-primary">' + (GLOQ.i18n ? GLOQ.i18n.requestQuoteNow : 'Solicitar ahora') + ' →</a>' +
					'</div>' +
				'</div>' +
				'<button type="button" class="gloq-toast-close" aria-label="Cerrar">×</button>' +
			'</div>'
		);
		$c.append($t);
		// trigger CSS transition
		setTimeout(function () { $t.addClass('gloq-toast-show'); }, 20);
		// auto-dismiss
		var timer = setTimeout(function () { dismissToast($t); }, 5000);
		$t.find('.gloq-toast-close').on('click', function () {
			clearTimeout(timer);
			dismissToast($t);
		});
	}

	function dismissToast($t) {
		$t.removeClass('gloq-toast-show').addClass('gloq-toast-hide');
		setTimeout(function () { $t.remove(); }, 350);
	}

	function escapeHtml(s) {
		return String(s || '').replace(/[&<>"']/g, function (m) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
		});
	}

	function updateMiniCounter(count) {
		var $els = $('.gloq-cart-counter, .gloq-quote-counter');
		if (!$els.length) return;
		$els.text(count).toggleClass('gloq-counter-empty', !count);
	}

	$(function () {
		// Hook al evento nativo de WooCommerce cuando un producto se añade vía AJAX
		$(document.body).on('added_to_cart', function (event, fragments, cart_hash, $button) {
			var name = '';
			if ($button && $button.length) {
				name = $button.data('gloq-product-name') || $button.attr('aria-label') || '';
				name = String(name).replace(/^Lee más sobre [\u201c"]?|[\u201d"]?$/g, '').replace(/^Read more about/, '').trim();
				// Visual confirmation on the button
				$button.addClass('gloq-just-added');
				setTimeout(function () { $button.removeClass('gloq-just-added'); }, 1800);
			}
			showToast(name);

			// Try to refresh count from fragments if available
			if (fragments && fragments['div.widget_shopping_cart_content']) {
				var html = fragments['div.widget_shopping_cart_content'];
				var m = (html || '').match(/(\d+)\s*items?/i);
				if (m) updateMiniCounter(parseInt(m[1], 10));
			}
		});

		// Form submit con modal "Cotización vs Pedido"
		var $form = $('#gloq-form');
		var $modal = $('#gloq-type-modal');
		var $typeInput = $('#gloq-type-input');
		var $submitBtn = $('#gloq-submit-btn');

		function openTypeModal() {
			if (!$modal.length) return;
			$modal.removeAttr('hidden').addClass('gloq-modal-open');
			document.body.style.overflow = 'hidden';
			// focus en la primera opción para a11y
			var firstOpt = $modal.find('.gloq-modal-option').get(0);
			if (firstOpt) firstOpt.focus();
		}
		function closeTypeModal() {
			$modal.attr('hidden', 'hidden').removeClass('gloq-modal-open');
			document.body.style.overflow = '';
		}

		if ($form.length) {
			$form.on('submit', function (e) {
				// Si el tipo aún no fue elegido, abrir modal
				if (!$typeInput.val()) {
					e.preventDefault();
					openTypeModal();
					return false;
				}
				// Si ya fue elegido, dejar que el form se envíe; deshabilitar botón
				var original = $submitBtn.text();
				$submitBtn.prop('disabled', true).text('Enviando…');
				setTimeout(function () { $submitBtn.prop('disabled', false).text(original); }, 30000);
			});
		}

		// Click en una opción del modal → setea valor y reenvía form
		$modal.on('click', '.gloq-modal-option', function () {
			var type = $(this).data('gloq-type');
			if (type !== 'quote' && type !== 'order') return;
			$typeInput.val(type);
			closeTypeModal();
			// pequeño delay para que el modal se cierre antes de enviar
			setTimeout(function () { $form.trigger('submit'); }, 50);
		});

		// Cerrar modal: botón X o click en overlay o tecla Esc
		$('#gloq-modal-close').on('click', closeTypeModal);
		$modal.on('click', function (e) {
			if (e.target === this) closeTypeModal();
		});
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && !$modal.attr('hidden')) closeTypeModal();
		});

		// Auto-clear error param from URL
		if (window.history && window.history.replaceState && /[\?&]gloq_(error|old)=/.test(location.search)) {
			var clean = location.pathname + location.search.replace(/[\?&]gloq_(error|old)=[^&]*/g, '').replace(/^&/, '?');
			if (clean.endsWith('?')) clean = clean.slice(0, -1);
			window.history.replaceState({}, document.title, clean + location.hash);
		}

		// Cart page: dropdown para cambiar presentación sin recargar
		$(document.body).on('change', '.gloq-swap-presentation', function () {
			var $sel = $(this);
			var key = $sel.data('cart-key');
			var idx = parseInt($sel.val(), 10);
			if (!key || isNaN(idx) || !GLOQ.swapNonce) return;
			var $row = $sel.closest('tr');
			$row.css('opacity', 0.5);
			$.post(GLOQ.ajaxUrl || '/wp-admin/admin-ajax.php', {
				action: 'gloq_swap_presentation',
				_wpnonce: GLOQ.swapNonce,
				key: key,
				idx: idx
			}).done(function (resp) {
				if (resp && resp.success) {
					// Reload page to refresh cart fragments + line totals
					location.reload();
				} else {
					alert((resp && resp.data && resp.data.message) || 'No se pudo cambiar la presentación');
					$row.css('opacity', 1);
				}
			}).fail(function () {
				alert('Error de conexión');
				$row.css('opacity', 1);
			});
		});

		// Quote page: inline qty editor
		var $qtyInputs = $('.gloq-qty-input');
		if ($qtyInputs.length && window.GloqAjax) {
			var debounceTimers = {};
			$qtyInputs.on('change input', function () {
				var $input = $(this);
				var key = $input.data('cart-key');
				var qty = parseInt($input.val(), 10);
				if (isNaN(qty) || qty < 1) qty = 1;
				$input.val(qty);
				clearTimeout(debounceTimers[key]);
				debounceTimers[key] = setTimeout(function () { updateQty(key, qty, $input); }, 400);
			});
			$('.gloq-remove-item').on('click', function (e) {
				e.preventDefault();
				var key = $(this).data('cart-key');
				if (!confirm('¿Quitar este producto de tu cotización?')) return;
				updateQty(key, 0, $(this).closest('tr'));
			});
		}

		function updateQty(key, qty, $context) {
			$context.addClass('gloq-loading');
			$.post(GloqAjax.url, {
				action: 'gloq_update_qty',
				_wpnonce: GloqAjax.nonce,
				key: key,
				qty: qty
			}).done(function (resp) {
				if (resp && resp.success) {
					if (qty === 0) {
						// Remove row visually
						var $row = $('tr[data-cart-key="' + key + '"]');
						$row.fadeOut(200, function () {
							$row.remove();
							if (!$('tbody tr', '.glotracol-quote-items').length) {
								location.reload();
							}
						});
					} else {
						$context.removeClass('gloq-loading').addClass('gloq-saved');
						setTimeout(function () { $context.removeClass('gloq-saved'); }, 1200);
					}
				} else {
					alert((resp && resp.data && resp.data.message) || 'No pudimos actualizar la cantidad. Recarga la página.');
					$context.removeClass('gloq-loading');
				}
			}).fail(function () {
				alert('Error de conexión. Intenta nuevamente.');
				$context.removeClass('gloq-loading');
			});
		}
	});
})(jQuery);
