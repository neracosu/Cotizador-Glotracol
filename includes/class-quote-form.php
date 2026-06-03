<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_Form {

	const SUBMIT_ACTION = 'glotracol_quote_submit';
	const NONCE_ACTION  = 'glotracol_quote_submit_nonce';
	const NONCE_FIELD   = 'glotracol_quote_nonce';

	public function __construct() {
		add_shortcode( 'glotracol_quote_form', [ $this, 'render_form_shortcode' ] );
		add_shortcode( 'glotracol_quote_thanks', [ $this, 'render_thanks_shortcode' ] );
		add_action( 'admin_post_nopriv_' . self::SUBMIT_ACTION, [ $this, 'handle_submit' ] );
		add_action( 'admin_post_' . self::SUBMIT_ACTION, [ $this, 'handle_submit' ] );

		// AJAX para editar cantidades inline en /solicitar-cotizacion
		add_action( 'wp_ajax_gloq_update_qty', [ $this, 'ajax_update_qty' ] );
		add_action( 'wp_ajax_nopriv_gloq_update_qty', [ $this, 'ajax_update_qty' ] );
	}

	public function ajax_update_qty() {
		check_ajax_referer( 'gloq_update_qty', '_wpnonce' );

		$this->ensure_wc_cart_loaded();

		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$qty = isset( $_POST['qty'] ) ? max( 0, (int) $_POST['qty'] ) : 0;

		if ( ! $key || ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( [ 'message' => 'Sesión inválida' ] );
		}

		if ( $qty === 0 ) {
			$ok = WC()->cart->remove_cart_item( $key );
		} else {
			$ok = WC()->cart->set_quantity( $key, $qty, true );
		}

		WC()->cart->calculate_totals();
		wp_send_json_success( [
			'count' => WC()->cart->get_cart_contents_count(),
			'empty' => WC()->cart->is_empty(),
		] );
	}

	/**
	 * Inicializa WC frontend (sesión + cart) cuando el endpoint actual no lo hace
	 * automáticamente (ej. admin-post.php, admin-ajax.php).
	 *
	 * Defensiva: si WooCommerce reorganiza archivos en una versión futura, este
	 * helper no aborta — registra la incidencia y continúa sin cart. El caller
	 * verá `WC()->cart` null y devolverá error UX al usuario en lugar de un
	 * fatal en producción.
	 *
	 * @return bool true si quedó cart cargado y operativo; false en caso contrario.
	 */
	private function ensure_wc_cart_loaded() {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			$this->log_wc_load_failure( 'WC() no disponible' );
			return false;
		}

		// Cargar funciones de frontend (incluye wc-cart-functions.php) — sin esto,
		// get_cart_from_session() falla con "wc_get_cart_item_data_hash() undefined".
		if ( ! function_exists( 'wc_get_cart_item_data_hash' ) ) {
			$wc_path = defined( 'WC_ABSPATH' ) ? WC_ABSPATH : ( WP_PLUGIN_DIR . '/woocommerce/' );
			$includes = [
				$wc_path . 'includes/wc-cart-functions.php',
				$wc_path . 'includes/wc-notice-functions.php',
				$wc_path . 'includes/wc-template-functions.php',
			];
			foreach ( $includes as $inc ) {
				if ( ! file_exists( $inc ) ) continue;
				try {
					require_once $inc;
				} catch ( \Throwable $e ) {
					$this->log_wc_load_failure( 'require_once falló: ' . $inc . ' — ' . $e->getMessage() );
				}
			}
			if ( ! function_exists( 'wc_get_cart_item_data_hash' ) ) {
				$this->log_wc_load_failure( 'wc_get_cart_item_data_hash sigue sin existir tras requires (paths posiblemente renombrados en esta versión de WC)' );
				return false;
			}
		}

		try {
			if ( ! WC()->session ) {
				$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
				if ( class_exists( $session_class ) ) {
					WC()->session = new $session_class();
					WC()->session->init();
				}
			}
			if ( ! WC()->customer && class_exists( 'WC_Customer' ) ) {
				WC()->customer = new WC_Customer( get_current_user_id(), true );
			}
			if ( ! WC()->cart && class_exists( 'WC_Cart' ) ) {
				WC()->cart = new WC_Cart();
			}
			// Cargar items desde la sesión (clave para que admin-post.php vea el cart real del usuario)
			if ( WC()->cart && method_exists( WC()->cart, 'get_cart_from_session' ) && function_exists( 'wc_get_cart_item_data_hash' ) ) {
				WC()->cart->get_cart_from_session();
			}
		} catch ( \Throwable $e ) {
			$this->log_wc_load_failure( 'Excepción durante init de session/customer/cart: ' . $e->getMessage() );
			return false;
		}

		// Validación final
		if ( ! WC()->cart ) {
			$this->log_wc_load_failure( 'WC()->cart sigue null tras intento de init' );
			return false;
		}
		return true;
	}

	/**
	 * Registra un fallo en la carga manual de WC frontend. Se persiste en una
	 * option transient que el dashboard puede consultar para mostrar admin notice.
	 *
	 * @param string $reason Descripción de la falla.
	 */
	private function log_wc_load_failure( $reason ) {
		if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
			Glotracol_Quote_Logger::error( 'wc_compat', 'ensure_wc_cart_loaded falló', [
				'reason'     => $reason,
				'wc_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'desconocida',
			] );
		} elseif ( function_exists( 'error_log' ) ) {
			error_log( '[Glotracol Cotizador] ensure_wc_cart_loaded falló: ' . $reason );
		}
		// Persistir el último fallo por 24h para que el admin lo vea en el dashboard
		set_transient( 'glotracol_quote_wc_load_failure', [
			'reason'    => $reason,
			'wc_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'desconocida',
			'timestamp' => current_time( 'mysql' ),
		], DAY_IN_SECONDS );
	}

	public function render_form_shortcode() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '<p>El carrito no está disponible.</p>';
		}
		WC()->cart->calculate_totals();
		if ( WC()->cart->is_empty() ) {
			$shop = function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );
			return '<div class="glotracol-quote-empty"><p>Aún no has agregado productos a tu cotización.</p><p><a class="button" href="' . esc_url( $shop ) . '">Ver catálogo</a></p></div>';
		}

		$error = isset( $_GET['gloq_error'] ) ? sanitize_text_field( wp_unslash( $_GET['gloq_error'] ) ) : '';
		$old   = isset( $_GET['gloq_old'] ) ? (array) json_decode( base64_decode( wp_unslash( $_GET['gloq_old'] ) ), true ) : [];
		if ( ! is_array( $old ) ) $old = [];

		$cart_items = [];
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product ) continue;
			$pres = $item['gloq_presentacion'] ?? null;
			$cart_items[] = [
				'key'        => $key,
				'product_id' => $product->get_id(),
				'name'       => $product->get_name(),
				'sku'        => $pres && ! empty( $pres['sku'] ) ? $pres['sku'] : $product->get_sku(),
				'quantity'   => (int) $item['quantity'],
				'permalink'  => get_permalink( $product->get_id() ),
				'image'      => $product->get_image( [ 60, 60 ] ),
				'presentacion_label' => $pres['label'] ?? '',
				'presentacion_idx'   => isset( $pres['idx'] ) ? (int) $pres['idx'] : null,
			];
		}

		return glotracol_quote_load_template( 'form.php', [
			'cart_items'  => $cart_items,
			'error'       => $error,
			'old'         => $old,
			'action_url'  => esc_url( admin_url( 'admin-post.php' ) ),
			'submit_action' => self::SUBMIT_ACTION,
			'nonce_field' => self::NONCE_FIELD,
			'nonce_action' => self::NONCE_ACTION,
			'form_intro'  => glotracol_quote_get_setting( 'form_intro' ),
			'terms_text'  => glotracol_quote_get_setting( 'terms_text' ),
			'shop_url'    => function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' ),
		] );
	}

	public function render_thanks_shortcode() {
		$qid = isset( $_GET['qid'] ) ? sanitize_text_field( wp_unslash( $_GET['qid'] ) ) : '';
		$post_id = 0;
		$customer_email = '';
		$customer_name = '';
		if ( $qid ) {
			$lookup = get_posts( [
				'post_type'   => 'glo_quote',
				'post_status' => 'any',
				'numberposts' => 1,
				'meta_key'    => '_glo_qid',
				'meta_value'  => $qid,
				'fields'      => 'ids',
			] );
			if ( ! empty( $lookup ) ) {
				$post_id = (int) $lookup[0];
				$customer_email = get_post_meta( $post_id, '_glo_customer_email', true );
				$customer_name  = get_post_meta( $post_id, '_glo_customer_name', true );
			}
		}
		$msg = glotracol_quote_get_setting( 'thanks_message' );
		$msg = glotracol_quote_replace_placeholders( $msg, [
			'quote_id'       => $post_id,
			'customer_email' => $customer_email,
			'customer_name'  => $customer_name,
		] );
		return glotracol_quote_load_template( 'thanks.php', [
			'message'        => $msg,
			'quote_id'       => $post_id,
			'customer_email' => $customer_email,
			'customer_name'  => $customer_name,
		] );
	}

	public function handle_submit() {
		$form_url = glotracol_quote_get_form_page_url();
		$thanks_url = glotracol_quote_get_thanks_page_url();

		// admin-post.php no inicializa WC frontend → forzamos load del cart desde la sesión
		$this->ensure_wc_cart_loaded();

		// Honeypot
		if ( ! empty( $_POST['gloq_website'] ) ) {
			wp_safe_redirect( $thanks_url );
			exit;
		}

		// Nonce
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_FIELD ] ), self::NONCE_ACTION ) ) {
			$this->redirect_with_error( $form_url, 'Tu sesión expiró. Recarga la página e intenta de nuevo.' );
		}

		$ip = glotracol_quote_get_client_ip();
		if ( ! Glotracol_Quote_Rate_Limit::check( $ip ) ) {
			$this->redirect_with_error( $form_url, 'Has enviado demasiadas solicitudes recientemente. Intenta más tarde.' );
		}

		$fields = [
			'name'    => sanitize_text_field( wp_unslash( $_POST['gloq_name'] ?? '' ) ),
			'email'   => sanitize_email( wp_unslash( $_POST['gloq_email'] ?? '' ) ),
			'phone'   => sanitize_text_field( wp_unslash( $_POST['gloq_phone'] ?? '' ) ),
			'company' => sanitize_text_field( wp_unslash( $_POST['gloq_company'] ?? '' ) ),
			'nit'     => sanitize_text_field( wp_unslash( $_POST['gloq_nit'] ?? '' ) ),
			'city'    => sanitize_text_field( wp_unslash( $_POST['gloq_city'] ?? '' ) ),
			'message' => sanitize_textarea_field( wp_unslash( $_POST['gloq_message'] ?? '' ) ),
		];
		$type = isset( $_POST['gloq_type'] ) ? sanitize_key( wp_unslash( $_POST['gloq_type'] ) ) : 'quote';
		if ( ! in_array( $type, [ 'quote', 'order' ], true ) ) $type = 'quote';
		$terms = isset( $_POST['gloq_terms'] );

		$missing = [];
		if ( $fields['name'] === '' ) $missing[] = 'nombre';
		if ( ! is_email( $fields['email'] ) ) $missing[] = 'email válido';
		if ( $fields['phone'] === '' ) $missing[] = 'teléfono';
		if ( $fields['company'] === '' ) $missing[] = 'empresa';
		if ( ! $terms ) $missing[] = 'aceptación de términos';

		if ( ! empty( $missing ) ) {
			$this->redirect_with_error( $form_url, 'Faltan los siguientes campos: ' . implode( ', ', $missing ) . '.', $fields );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			$this->redirect_with_error( $form_url, 'Tu cotización no contiene productos.' );
		}

		$items = [];
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			if ( ! $product ) continue;
			$pres = $cart_item['gloq_presentacion'] ?? null;
			$items[] = [
				'product_id'         => $product->get_id(),
				'name'               => $product->get_name(),
				// SKU "efectivo": el de la presentación si existe; si no, el del producto.
				'sku'                => $pres && ! empty( $pres['sku'] ) ? $pres['sku'] : $product->get_sku(),
				'sku_producto'       => $product->get_sku(),
				'quantity'           => (int) $cart_item['quantity'],
				'presentacion_idx'   => isset( $pres['idx'] ) ? (int) $pres['idx'] : null,
				'presentacion_label' => $pres['label'] ?? '',
			];
		}

		// Resolución de cliente B2B por NIT y precios
		$client_id = function_exists( 'glotracol_quote_find_client_by_nit' ) ? (int) glotracol_quote_find_client_by_nit( $fields['nit'] ) : 0;
		$pricing_result = class_exists( 'Glotracol_Quote_Pricing' )
			? Glotracol_Quote_Pricing::resolve_items( $items, $client_id )
			: [ 'items' => $items, 'all_priced' => false, 'total' => 0, 'sources' => [] ];
		$items = $pricing_result['items']; // ahora cada item incluye precio_unitario, precio_origen, precio_subtotal
		$all_priced = (bool) $pricing_result['all_priced'];
		$total = (int) $pricing_result['total'];
		$has_public_pricing = class_exists( 'Glotracol_Quote_Pricing' ) && ! empty( Glotracol_Quote_Pricing::get_public_pricing() );
		$pricing_status = $all_priced ? 'priced' : ( ( $client_id || $has_public_pricing ) ? 'partial' : 'none' );

		$payload = [
			'customer'   => $fields,
			'type'       => $type,
			'client_id'  => $client_id,
			'items'      => $items,
			'pricing'    => [
				'status' => $pricing_status,
				'total'  => $total,
				'sources'=> $pricing_result['sources'],
			],
			'meta'       => [
				'ip'         => $ip,
				'user_agent' => substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
				'referer'    => esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) ),
				'lang'       => get_locale(),
				'timestamp'  => current_time( 'mysql' ),
			],
		];

		do_action( 'glotracol_quote_before_save', $payload );

		// Status inicial según pricing y settings
		$auto_respond_enabled = glotracol_quote_get_setting( 'auto_respond_enabled', 'yes' ) === 'yes';
		$initial_status = 'glo-new';
		if ( $pricing_status === 'priced' && $auto_respond_enabled ) {
			$initial_status = 'glo-auto-priced';
		} elseif ( $pricing_status === 'partial' ) {
			$initial_status = 'glo-pending-prices';
		}

		$type_label = glotracol_quote_type_label( $type );
		$post_title = sprintf( '%s — %s', $type_label, $fields['name'] );
		$post_id = wp_insert_post( [
			'post_type'   => 'glo_quote',
			'post_status' => $initial_status,
			'post_title'  => $post_title,
		], true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$this->redirect_with_error( $form_url, 'No pudimos guardar tu cotización. Intenta nuevamente.', $fields );
		}

		wp_update_post( [ 'ID' => $post_id, 'post_title' => sprintf( '%s #%d — %s', $type_label, $post_id, $fields['name'] ) ] );

		$qid = wp_generate_password( 16, false, false );
		update_post_meta( $post_id, '_glo_qid', $qid );
		update_post_meta( $post_id, '_glo_customer_name', $fields['name'] );
		update_post_meta( $post_id, '_glo_customer_email', $fields['email'] );
		update_post_meta( $post_id, '_glo_customer_phone', $fields['phone'] );
		update_post_meta( $post_id, '_glo_customer_company', $fields['company'] );
		update_post_meta( $post_id, '_glo_customer_nit', $fields['nit'] );
		update_post_meta( $post_id, '_glo_customer_city', $fields['city'] );
		update_post_meta( $post_id, '_glo_customer_message', $fields['message'] );
		update_post_meta( $post_id, '_glo_items', $items );
		update_post_meta( $post_id, '_glo_meta', $payload['meta'] );
		update_post_meta( $post_id, '_glo_email_log', [] );

		// F3 — clasificación automática por tamaño
		$units_total = 0;
		foreach ( $items as $it ) {
			$units_total += isset( $it['quantity'] ) ? (int) $it['quantity'] : 0;
		}
		$size_tag = glotracol_quote_size_tag( $units_total, count( $items ) );
		update_post_meta( $post_id, '_glo_size_tag', $size_tag );
		update_post_meta( $post_id, '_glo_units_total', $units_total );
		// F4 — flag para pedidos grandes
		update_post_meta( $post_id, '_glo_is_large_alert', $size_tag === 'large' ? 1 : 0 );

		// F6 (Fase D) — tipo, cliente B2B y pricing
		update_post_meta( $post_id, '_glo_type', $type );
		update_post_meta( $post_id, '_glo_client_id', (int) $client_id );
		update_post_meta( $post_id, '_glo_pricing_status', $pricing_status );
		update_post_meta( $post_id, '_glo_total', (int) $total );
		update_post_meta( $post_id, '_glo_pricing_sources', $pricing_result['sources'] );

		Glotracol_Quote_Rate_Limit::record( $ip );

		if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
			Glotracol_Quote_Logger::info( 'quote_created', sprintf( '%s #%d creada (status=%s, pricing=%s, total=%d)', glotracol_quote_type_label( $type ), $post_id, $initial_status, $pricing_status, $total ), [
				'quote_id'       => $post_id,
				'type'           => $type,
				'client_id'      => $client_id,
				'pricing_status' => $pricing_status,
				'total'          => $total,
				'items_count'    => count( $items ),
				'units_total'    => $units_total,
				'size_tag'       => $size_tag,
				'nit'            => $fields['nit'],
			] );
		}

		do_action( 'glotracol_quote_created', $post_id, $payload );

		WC()->cart->empty_cart();

		$redirect = add_query_arg( [ 'qid' => $qid ], $thanks_url );
		wp_safe_redirect( $redirect );
		exit;
	}

	private function redirect_with_error( $url, $message, $old = [] ) {
		$args = [ 'gloq_error' => rawurlencode( $message ) ];
		if ( ! empty( $old ) ) {
			$args['gloq_old'] = rawurlencode( base64_encode( wp_json_encode( $old ) ) );
		}
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}
}
