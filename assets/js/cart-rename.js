/**
 * Glotracol Cotizador — Cart Rename (Layer 2 of 3)
 *
 * Reescribe en el DOM las strings del carrito que el filter `gettext` (Layer 1)
 * no haya capturado por cambios en WooCommerce, contexto distinto, dominio
 * inesperado o porque la string venga renderizada por otro plugin/tema.
 *
 * Diseñado para sobrevivir actualizaciones: si WC cambia el HTML, solo se
 * actualiza el config (selectores) — la lógica permanece estable.
 *
 * Capa 3 (CSS) cubre los headings críticos vía pseudo-elementos como última
 * línea de defensa.
 */
(function () {
	'use strict';

	if (typeof window.GlotracolQuote === 'undefined') {
		window.GlotracolQuote = {};
	}

	/**
	 * Config: selectores estables + sus reemplazos.
	 * Edita esta tabla si un update de WC/Hello Elementor cambia la estructura.
	 */
	var CONFIG = {
		// Reemplazos de TEXTO COMPLETO de un elemento (texto exacto del nodo).
		exactText: [
			{ selectors: ['.woocommerce-cart .page-title', '.woocommerce-cart h1.entry-title', '.woocommerce-cart-form ~ h1', '.elementor-page-title h1', '.elementor-heading-title'], match: /^(carrito|cart|mi carrito|tu carrito)$/i, to: 'Mi cotización' },
			{ selectors: ['.cart_totals h2', '.cart-collaterals h2'], match: /^(cart totals|totales del carrito|totales)$/i, to: 'Resumen de cotización' },
			{ selectors: ['.shop_table th.product-subtotal'], match: /^(subtotal)$/i, to: '' },
			{ selectors: ['.shop_table th.product-price'], match: /^(price|precio)$/i, to: '' },
			{ selectors: ['.cart_item .product-remove a.remove'], match: /^.+$/, to: '×', attr: { 'aria-label': 'Quitar de la cotización', 'title': 'Quitar de la cotización' } }
		],
		// Reemplazos de SUBSTRING (regex) en cualquier texto del nodo.
		substring: [
			{ selectors: ['.woocommerce-cart .cart-empty', '.woocommerce-info'], regex: /(your cart is currently empty|tu carrito (está|esta) (actualmente )?vac[íi]o)[\.\!]?/gi, to: 'Tu cotización aún no tiene productos.' },
			{ selectors: ['.return-to-shop a.button', '.wc-backward'], regex: /^(return to shop|volver a la tienda)$/i, to: 'Ver catálogo' },
			{ selectors: ['button[name="update_cart"]', 'input[name="update_cart"]'], regex: /^(update cart|actualizar carrito)$/i, to: 'Actualizar cotización', isInput: true },
			{ selectors: ['.coupon label'], regex: /^(coupon code|código de cupón|cupón)$/i, to: 'Código de descuento' },
			{ selectors: ['.coupon button[name="apply_coupon"]', '.coupon input[name="apply_coupon"]'], regex: /^(apply coupon|aplicar cupón)$/i, to: 'Aplicar código', isInput: true }
		]
	};

	/**
	 * Reemplaza el texto de un nodo de elemento sólo si su contenido textual
	 * directo (sin hijos) coincide con `match`. Preserva hijos.
	 */
	function replaceExactText(el, match, to, attrs) {
		if (!el) return false;
		var children = el.childNodes;
		var didReplace = false;
		// Caso simple: solo un text node hijo
		if (children.length === 1 && children[0].nodeType === Node.TEXT_NODE) {
			var current = children[0].nodeValue.trim();
			if (match.test(current)) {
				children[0].nodeValue = to;
				didReplace = true;
			}
		} else if (children.length === 0) {
			// Sin hijos pero con textContent
			var t = (el.textContent || '').trim();
			if (match.test(t)) {
				el.textContent = to;
				didReplace = true;
			}
		}
		// Si es un input/button con `value`
		if (!didReplace && (el.tagName === 'INPUT' || el.tagName === 'BUTTON')) {
			var val = (el.value || '').trim();
			if (val && match.test(val)) {
				el.value = to;
				didReplace = true;
			}
		}
		if (didReplace && attrs) {
			Object.keys(attrs).forEach(function (k) { try { el.setAttribute(k, attrs[k]); } catch (e) {} });
		}
		return didReplace;
	}

	/**
	 * Reemplaza substring en todos los text nodes descendientes del elemento.
	 */
	function replaceSubstring(el, regex, to, isInput) {
		if (!el) return;
		if (isInput && (el.tagName === 'INPUT' || el.tagName === 'BUTTON')) {
			if (el.value && regex.test(el.value)) {
				el.value = el.value.replace(regex, to);
			}
			return;
		}
		// Walker para text nodes
		try {
			var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
			var node;
			while ((node = walker.nextNode())) {
				if (regex.test(node.nodeValue)) {
					node.nodeValue = node.nodeValue.replace(regex, to);
				}
			}
		} catch (e) { /* navegadores muy viejos */ }
	}

	/**
	 * Aplica todos los reemplazos del CONFIG a la página actual.
	 * Idempotente: seguro de llamar múltiples veces.
	 */
	function applyRenames() {
		// exactText
		CONFIG.exactText.forEach(function (rule) {
			rule.selectors.forEach(function (sel) {
				try {
					document.querySelectorAll(sel).forEach(function (el) {
						replaceExactText(el, rule.match, rule.to, rule.attr);
					});
				} catch (e) { /* selector inválido en algún navegador */ }
			});
		});
		// substring
		CONFIG.substring.forEach(function (rule) {
			rule.selectors.forEach(function (sel) {
				try {
					document.querySelectorAll(sel).forEach(function (el) {
						// Reset lastIndex en regex globales
						if (rule.regex.global) rule.regex.lastIndex = 0;
						replaceSubstring(el, rule.regex, rule.to, rule.isInput);
					});
				} catch (e) {}
			});
		});
		// Detección de fallback — si después de aplicar capas 1 y 2 algún
		// heading crítico sigue diciendo "Cart" o variantes, marcarlo con
		// `gloq-rename-failed` para que la Capa 3 (CSS) lo cubra.
		flagFailedHeadings();
	}

	/**
	 * Marca con .gloq-rename-failed los headings cuyo texto seguía siendo
	 * "Cart" / "Cart totals" / etc. después de aplicar Capa 1 y Capa 2.
	 */
	function flagFailedHeadings() {
		var heading_selectors = [
			'.woocommerce-cart .page-title',
			'.woocommerce-cart h1.entry-title',
			'.woocommerce-cart h1.product_title',
			'.woocommerce-cart .cart_totals h2'
		];
		var failure_patterns = /^(cart|carrito|cart totals|totales del carrito)\s*$/i;
		heading_selectors.forEach(function (sel) {
			try {
				document.querySelectorAll(sel).forEach(function (el) {
					var t = (el.textContent || '').trim();
					if (failure_patterns.test(t)) {
						el.classList.add('gloq-rename-failed');
					} else {
						el.classList.remove('gloq-rename-failed');
					}
				});
			} catch (e) {}
		});
	}

	// Exponer para debugging y para que el Compatibility Check del admin pueda invocar
	window.GlotracolQuote.cartRename = {
		apply: applyRenames,
		config: CONFIG
	};

	// Aplicar cuando el DOM esté listo
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', applyRenames);
	} else {
		applyRenames();
	}

	// Reaplicar cuando WC actualice carrito vía AJAX (mini-cart fragments, etc.)
	if (typeof jQuery !== 'undefined') {
		jQuery(function ($) {
			$(document.body).on(
				'wc_fragments_loaded wc_fragments_refreshed updated_wc_div updated_cart_totals removed_from_cart added_to_cart',
				function () { setTimeout(applyRenames, 50); }
			);
		});
	}
})();
