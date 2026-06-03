<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_Cart_Overrides {

	/**
	 * Cache de strings ya resueltas en `rename_cart_strings`. Evita reprocesar
	 * cada llamada de `gettext` (que se invoca cientos de veces por request).
	 *
	 * @var array<string,string>
	 */
	private static $gettext_cache = [];

	/**
	 * Mapa estable (sobrevive a updates de WC):
	 *  - regex case-insensitive con tolerancia a tildes/puntuación
	 *  - cada entrada se aplica solo si el dominio coincide con su lista
	 *
	 * Si un update de WC introduce nuevas variantes, agregar aquí.
	 *
	 * @var array<int, array{ regex: string, to: string, domains: array<int,string> }>
	 */
	private static $rename_rules = [
		[ 'regex' => '/^cart$/i',                                                      'to' => 'Mi cotización',                  'domains' => [ 'woocommerce', 'default' ] ],
		[ 'regex' => '/^view cart$/i',                                                 'to' => 'Ver mi cotización',              'domains' => [ 'woocommerce', 'default' ] ],
		[ 'regex' => '/^ver carrito$/i',                                               'to' => 'Ver mi cotización',              'domains' => [ 'woocommerce', 'default' ] ],
		[ 'regex' => '/^cart totals$/i',                                               'to' => 'Resumen de cotización',          'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^totales del carrito$/i',                                       'to' => 'Resumen de cotización',          'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^update cart$/i',                                               'to' => 'Actualizar cotización',          'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^actualizar carrito$/i',                                        'to' => 'Actualizar cotización',          'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^cart updated\.?$/i',                                           'to' => 'Cotización actualizada.',        'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^carrito actualizado\.?$/i',                                    'to' => 'Cotización actualizada.',        'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^your cart is currently empty[\.\!]?$/i',                       'to' => 'Tu cotización aún no tiene productos.', 'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^tu carrito (est[áa]) (actualmente )?vac[íi]o[\.\!]?$/iu',     'to' => 'Tu cotización aún no tiene productos.', 'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^return to shop$/i',                                            'to' => 'Ver catálogo',                   'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^volver a la tienda$/i',                                        'to' => 'Ver catálogo',                   'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^remove this item$/i',                                          'to' => 'Quitar de la cotización',        'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^eliminar este art[íi]culo$/iu',                               'to' => 'Quitar de la cotización',        'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^coupon code$/i',                                               'to' => 'Código de descuento',            'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^apply coupon$/i',                                              'to' => 'Aplicar código',                 'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^aplicar cup[óo]n$/iu',                                        'to' => 'Aplicar código',                 'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^have a coupon\??$/i',                                          'to' => '',                                'domains' => [ 'woocommerce' ] ],
		[ 'regex' => '/^click here to enter your code$/i',                             'to' => '',                                'domains' => [ 'woocommerce' ] ],
	];

	public function __construct() {
		add_filter( 'woocommerce_cart_item_price', '__return_empty_string' );
		add_filter( 'woocommerce_cart_item_subtotal', '__return_empty_string' );
		add_filter( 'woocommerce_cart_subtotal', '__return_empty_string', 10, 0 );
		add_filter( 'woocommerce_cart_totals_order_total_html', '__return_empty_string' );

		// PRESENTACIONES (Fase C):
		// 1) Captura presentacion_idx del POST al añadir al carrito.
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'capture_presentation' ], 10, 3 );
		// 2) Hace el item único por presentación (no se mergean dos presentaciones del mismo producto).
		add_filter( 'woocommerce_cart_id', [ $this, 'cart_id_with_presentation' ], 10, 5 );
		// 3) Muestra la presentación elegida en la línea del carrito.
		add_filter( 'woocommerce_get_item_data', [ $this, 'display_presentation_in_cart' ], 10, 2 );
		// 4) Persiste presentación al recargar carrito desde sesión.
		add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'restore_presentation' ], 10, 2 );
		// 5) Endpoint AJAX para cambiar presentación sin remove/add.
		add_action( 'wp_ajax_gloq_swap_presentation', [ $this, 'ajax_swap_presentation' ] );
		add_action( 'wp_ajax_nopriv_gloq_swap_presentation', [ $this, 'ajax_swap_presentation' ] );
		// 6) Permite que el JS muestre dropdown en cada línea del carrito.
		add_filter( 'woocommerce_cart_item_name', [ $this, 'append_presentation_dropdown' ], 10, 3 );

		// Reemplazo del botón "Proceder al pago" — usamos prioridad alta + CSS.
		// CSS oculta el botón nativo (más resiliente que remove_action si WC
		// cambia la prioridad/callback del builtin). El nuestro se añade en 25.
		remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'proceed_to_quote_button' ], 25 );

		add_action( 'template_redirect', [ $this, 'block_checkout' ] );

		add_filter( 'wc_add_to_cart_message_html', [ $this, 'add_to_cart_message' ], 10, 2 );

		add_action( 'wp_print_styles', [ $this, 'inline_hide_price_columns' ], 50 );

		// Capa 1 — gettext con regex tolerantes (ver self::$rename_rules)
		add_filter( 'gettext', [ $this, 'rename_cart_strings' ], 20, 3 );
		add_filter( 'gettext_with_context', [ $this, 'rename_cart_strings_ctx' ], 20, 4 );

		// Capa 2 — JS DOM rewrite (registrado y enqueued en class-plugin.php)

		// Datos del carrito disponibles en JS para toast
		add_action( 'wp_enqueue_scripts', [ $this, 'localize_data' ], 20 );

		// Page title de la página de carrito
		add_filter( 'the_title', [ $this, 'cart_page_title' ], 10, 2 );
	}

	public function proceed_to_quote_button() {
		$url = glotracol_quote_get_form_page_url();
		echo '<a href="' . esc_url( $url ) . '" class="checkout-button button alt wc-forward glotracol-quote-button">Solicitar cotización ahora →</a>';
	}

	public function block_checkout() {
		if ( ! function_exists( 'is_checkout' ) ) return;
		if ( is_checkout() && ! is_wc_endpoint_url() ) {
			wp_safe_redirect( glotracol_quote_get_form_page_url(), 302 );
			exit;
		}
		if ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
			wp_safe_redirect( glotracol_quote_get_form_page_url(), 302 );
			exit;
		}
	}

	public function add_to_cart_message( $message, $products ) {
		$count = is_array( $products ) ? count( $products ) : 1;
		$cart_url = wc_get_cart_url();
		$quote_url = glotracol_quote_get_form_page_url();
		$verb = $count > 1 ? 'productos añadidos' : 'producto añadido';
		$cart_icon = '<svg class="gloq-cart-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>';
		return sprintf(
			'<div class="gloq-msg-wrap">' .
				'<span class="gloq-msg-text"><strong>%3$d</strong> %4$s a tu cotización.</span>' .
				'<span class="gloq-msg-actions">' .
					'<a href="%1$s" tabindex="1" class="button wc-forward gloq-msg-btn gloq-msg-btn-secondary">%5$s Ver mi cotización</a>' .
					'<a href="%2$s" tabindex="1" class="button wc-forward gloq-msg-btn gloq-msg-btn-primary glotracol-quote-button">Solicitar cotización ahora →</a>' .
				'</span>' .
			'</div>',
			esc_url( $cart_url ),
			esc_url( $quote_url ),
			$count,
			$verb,
			$cart_icon
		);
	}

	public function inline_hide_price_columns() {
		if ( ! is_cart() ) return;
		// Capa 3 — pseudo-element override SIEMPRE activo en /carrito.
		// Coste cero: cuando las capas 1 y 2 funcionan, el texto visible y el
		// del pseudo coinciden. Cuando fallan, el pseudo es la única defensa.
		// Para no hacer override agresivo cuando capa 1 funciona, los pseudos
		// solo activan en elementos con clase `gloq-rename-failed` que la
		// Capa 2 (JS) añade explícitamente cuando detecta que el texto sigue
		// siendo "Cart" después del enqueue de scripts.
		echo '<style>
		.woocommerce-cart .product-price,
		.woocommerce-cart .product-subtotal,
		.cart_totals .order-total,
		.cart_totals .cart-subtotal,
		.woocommerce-shipping-totals,
		.cart-collaterals .shipping-calculator-button,
		.cart-collaterals .checkout-button:not(.glotracol-quote-button){display:none!important}
		.cart_totals h2{display:block!important}

		/* Capa 3: pseudo-element override (solo si JS marca .gloq-rename-failed) */
		.woocommerce-cart .gloq-rename-failed{font-size:0!important;line-height:0!important;visibility:hidden;position:relative;height:1em;display:block}
		.woocommerce-cart .gloq-rename-failed::after{font-size:2rem;line-height:1.2;visibility:visible;position:absolute;left:0;top:0;color:inherit;font-weight:inherit;font-family:inherit}
		.woocommerce-cart h1.gloq-rename-failed::after,.woocommerce-cart .page-title.gloq-rename-failed::after{content:"Mi cotización"}
		.woocommerce-cart .cart_totals h2.gloq-rename-failed::after{content:"Resumen de cotización";font-size:1.25rem}
		</style>';
	}

	/**
	 * Capa 1 del rename: filtro `gettext` con cache estática + regex tolerantes
	 * a tildes y mayúsculas. Si WC cambia el dominio o el contexto, las capas
	 * 2 (JS) y 3 (CSS) actúan como red de seguridad.
	 *
	 * Hot path: este filtro se invoca cientos de veces por request. La cache
	 * estática evita reprocesar la misma string múltiples veces.
	 */
	public function rename_cart_strings( $translated, $original, $domain ) {
		// Solo intervenimos en dominios específicos. La lista se evalúa
		// dentro de cada regla, pero corto-circuitamos los dominios que
		// nunca queremos tocar para no impactar perf.
		if ( ! in_array( $domain, [ 'woocommerce', 'default' ], true ) ) {
			return $translated;
		}

		// Cache key incluye dominio para evitar colisiones
		$cache_key = $domain . '|' . $original;
		if ( isset( self::$gettext_cache[ $cache_key ] ) ) {
			return self::$gettext_cache[ $cache_key ];
		}

		$result = $translated;
		foreach ( self::$rename_rules as $rule ) {
			if ( ! in_array( $domain, $rule['domains'], true ) ) {
				continue;
			}
			// preg_match con suppresor por si la regex falla en alguna versión PHP
			if ( @preg_match( $rule['regex'], $original ) === 1 ) {
				$result = $rule['to'];
				break;
			}
		}

		self::$gettext_cache[ $cache_key ] = $result;
		return $result;
	}

	public function rename_cart_strings_ctx( $translated, $original, $context, $domain ) {
		// Mismas reglas — el contexto no nos importa para los renombres del carrito
		return $this->rename_cart_strings( $translated, $original, $domain );
	}

	/**
	 * Helper público para que el Compatibility Check del dashboard pueda
	 * verificar en runtime si el filtro está interceptando correctamente.
	 *
	 * @return bool true si "Cart" se traduce a "Mi cotización".
	 */
	public static function self_test_gettext() {
		$result = apply_filters( 'gettext', 'Cart', 'Cart', 'woocommerce' );
		return $result === 'Mi cotización';
	}

	public function cart_page_title( $title, $post_id = 0 ) {
		if ( $post_id && function_exists( 'wc_get_page_id' ) ) {
			$cart_id = wc_get_page_id( 'cart' );
			if ( $cart_id > 0 && $post_id === $cart_id ) {
				return 'Mi cotización';
			}
		}
		return $title;
	}

	public function localize_data() {
		if ( ! wp_script_is( 'glotracol-quote', 'registered' ) ) return;
		wp_localize_script( 'glotracol-quote', 'GloqData', [
			'cartUrl'   => wc_get_cart_url(),
			'quoteUrl'  => glotracol_quote_get_form_page_url(),
			'cartCount' => function_exists( 'WC' ) && WC() && WC()->cart ? WC()->cart->get_cart_contents_count() : 0,
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'swapNonce' => wp_create_nonce( 'gloq_swap_presentation' ),
			'i18n'      => [
				'added'             => 'añadido a tu cotización',
				'addedPlural'       => 'productos añadidos a tu cotización',
				'viewQuote'         => 'Ver mi cotización',
				'requestQuoteNow'   => 'Solicitar ahora',
				'inQuote'           => 'en tu cotización',
				'addedConfirmation' => 'Producto añadido',
			],
		] );
	}

	/* -------------------------------------------------------------------------
	 * PRESENTACIONES (Fase C)
	 * ---------------------------------------------------------------------- */

	/**
	 * Captura `gloq_presentacion_idx` del form de single-product al añadir al carrito.
	 *
	 * @param array $cart_item_data
	 * @param int   $product_id
	 * @param int   $variation_id
	 * @return array
	 */
	public function capture_presentation( $cart_item_data, $product_id, $variation_id ) {
		if ( ! isset( $_REQUEST['gloq_presentacion_idx'] ) || $_REQUEST['gloq_presentacion_idx'] === '' ) {
			return $cart_item_data;
		}
		$idx = (int) $_REQUEST['gloq_presentacion_idx'];
		$presentacion = glotracol_quote_get_presentacion( $product_id, $idx );
		if ( $presentacion ) {
			$cart_item_data['gloq_presentacion'] = [
				'idx'             => $idx,
				'label'           => $presentacion['label'] ?? '',
				'sku'             => $presentacion['sku'] ?? '',
				'peso_g'          => (int) ( $presentacion['peso_g'] ?? 0 ),
				'precio_publico'  => (int) ( $presentacion['precio_publico'] ?? 0 ),
			];
			// Forzar unique key (también lo hace el filter cart_id pero con esto
			// se respeta también en sesiones AJAX viejas)
			$cart_item_data['unique_key'] = md5( microtime() . $product_id . '_' . $idx );
		}
		return $cart_item_data;
	}

	/**
	 * Hace que dos presentaciones distintas del mismo producto sean items
	 * separados en el carrito (no se mergean al sumar quantities).
	 */
	public function cart_id_with_presentation( $cart_id, $product_id, $variation_id, $variation, $cart_item_data ) {
		if ( isset( $cart_item_data['gloq_presentacion']['idx'] ) ) {
			return md5( $cart_id . '|gloq:' . (int) $cart_item_data['gloq_presentacion']['idx'] );
		}
		return $cart_id;
	}

	/**
	 * Muestra la presentación elegida en la línea del carrito (cart, mini-cart).
	 */
	public function display_presentation_in_cart( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['gloq_presentacion']['label'] ) ) {
			$label = $cart_item['gloq_presentacion']['label'];
			$sku = $cart_item['gloq_presentacion']['sku'] ?? '';
			$display = $sku ? $label . ' <small style="color:#666">(' . esc_html( $sku ) . ')</small>' : $label;
			$item_data[] = [
				'key'     => 'Presentación',
				'value'   => $display,
				'display' => '',
			];
		}
		return $item_data;
	}

	/**
	 * Restaura los datos de presentación cuando WC carga el carrito desde sesión.
	 */
	public function restore_presentation( $cart_item, $values ) {
		if ( isset( $values['gloq_presentacion'] ) ) {
			$cart_item['gloq_presentacion'] = $values['gloq_presentacion'];
		}
		return $cart_item;
	}

	/**
	 * AJAX endpoint para cambiar la presentación de un item del carrito sin
	 * remove/add (preserva la posición y la cantidad).
	 */
	public function ajax_swap_presentation() {
		check_ajax_referer( 'gloq_swap_presentation', '_wpnonce' );
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( [ 'message' => 'Carrito no disponible' ] );
		}
		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$new_idx = isset( $_POST['idx'] ) ? (int) $_POST['idx'] : -1;
		if ( ! $key || $new_idx < 0 ) {
			wp_send_json_error( [ 'message' => 'Parámetros inválidos' ] );
		}
		$cart = WC()->cart->get_cart();
		if ( ! isset( $cart[ $key ] ) ) {
			wp_send_json_error( [ 'message' => 'Item no encontrado en el carrito' ] );
		}
		$item = $cart[ $key ];
		$product_id = (int) ( $item['product_id'] ?? 0 );
		$qty = (int) ( $item['quantity'] ?? 1 );
		$nueva = glotracol_quote_get_presentacion( $product_id, $new_idx );
		if ( ! $nueva ) {
			wp_send_json_error( [ 'message' => 'Presentación inválida' ] );
		}
		// La forma robusta de cambiar es: remove → add con new cart_item_data.
		WC()->cart->remove_cart_item( $key );
		$new_cart_item_data = [
			'gloq_presentacion' => [
				'idx'             => (int) $nueva['idx'],
				'label'           => $nueva['label'] ?? '',
				'sku'             => $nueva['sku'] ?? '',
				'peso_g'          => (int) ( $nueva['peso_g'] ?? 0 ),
				'precio_publico'  => (int) ( $nueva['precio_publico'] ?? 0 ),
			],
			'unique_key' => md5( microtime() . $product_id . '_' . $new_idx ),
		];
		$new_key = WC()->cart->add_to_cart( $product_id, $qty, 0, [], $new_cart_item_data );
		WC()->cart->calculate_totals();
		if ( ! $new_key ) {
			wp_send_json_error( [ 'message' => 'No se pudo cambiar la presentación' ] );
		}
		wp_send_json_success( [
			'new_key' => $new_key,
			'count'   => WC()->cart->get_cart_contents_count(),
			'reload'  => true,
		] );
	}

	/**
	 * Si el item del carrito tiene presentaciones disponibles, agrega un
	 * dropdown debajo del nombre para cambiar sin recargar.
	 */
	public function append_presentation_dropdown( $name_html, $cart_item, $cart_item_key ) {
		if ( ! is_cart() ) return $name_html;
		$product_id = (int) ( $cart_item['product_id'] ?? 0 );
		if ( ! $product_id ) return $name_html;
		$presentaciones = glotracol_quote_get_presentaciones( $product_id );
		if ( count( $presentaciones ) < 2 ) return $name_html; // si solo hay una o ninguna, no hace falta cambiar
		$current_idx = isset( $cart_item['gloq_presentacion']['idx'] ) ? (int) $cart_item['gloq_presentacion']['idx'] : (int) ( $presentaciones[0]['idx'] ?? 0 );
		$options = '';
		foreach ( $presentaciones as $p ) {
			$idx = (int) ( $p['idx'] ?? 0 );
			$label = $p['label'] ?? '';
			$sku = $p['sku'] ?? '';
			$options .= sprintf(
				'<option value="%d"%s>%s%s</option>',
				$idx,
				selected( $idx, $current_idx, false ),
				esc_html( $label ),
				$sku ? ' (' . esc_html( $sku ) . ')' : ''
			);
		}
		$dropdown = sprintf(
			'<div class="gloq-cart-pres-dropdown"><label>Cambiar presentación: <select class="gloq-swap-presentation" data-cart-key="%s">%s</select></label></div>',
			esc_attr( $cart_item_key ),
			$options
		);
		return $name_html . $dropdown;
	}
}
