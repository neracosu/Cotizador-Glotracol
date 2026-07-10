<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function glotracol_quote_get_settings() {
	$defaults = [
		'destination_emails'        => get_option( 'admin_email' ),
		'bcc_emails'                => '',
		'sender_name'               => get_bloginfo( 'name' ),
		'sender_email'              => get_option( 'admin_email' ),
		'admin_subject'             => 'Nueva cotización #{quote_id} — {customer_name}',
		'customer_subject'          => 'Hemos recibido tu cotización — Glotracol',
		'customer_intro'            => 'Hola {customer_name}, hemos recibido tu solicitud de cotización. Nuestro equipo comercial revisará los productos solicitados y te contactará pronto con disponibilidad y precio.',
		'admin_intro'               => 'Se recibió una nueva solicitud de cotización a través del sitio web.',
		'thanks_message'            => 'Tu cotización fue recibida correctamente. El equipo de Glotracol te responderá al correo {customer_email} con disponibilidad y precio.',
		'form_intro'                => 'Completa tus datos para enviar la solicitud. El equipo de Glotracol te responderá con la cotización formal.',
		'terms_text'                => 'Acepto que mis datos sean utilizados para responder a esta cotización.',
		'webhook_url'               => '',
		'webhook_secret'            => '',
		'rate_limit_per_hour'       => 3,
		'delete_data_on_uninstall'  => 'no',
		// Reglas de clasificación por tamaño (F3 + F4)
		'size_threshold_medium_units' => 25,   // ≥ 25 unidades → medium
		'size_threshold_large_units'  => 80,   // ≥ 80 unidades → large
		'size_threshold_medium_skus'  => 5,    // ≥ 5 SKUs distintos → medium
		'size_threshold_large_skus'   => 12,   // ≥ 12 SKUs distintos → large
		'large_alert_email'           => '',   // Email destacado para pedidos large (opcional, vacío = usa destination_emails)
		'large_alert_enabled'         => 'yes',
		'weight_threshold_large_kg' => 200,    // ≥ 200 kg → grande (amarillo)
		'weight_threshold_tons_kg'  => 1000,   // ≥ 1000 kg → toneladas (rojo)
		// Auto-respuesta con precios (F6)
		'auto_respond_enabled'        => 'yes',
		// Apariencia (Feature 3 — herencia de colores de Elementor)
		'appearance_inherit_elementor' => 'no',          // 'yes'|'no'
		'appearance_elementor_slot'    => 'primary',     // 'primary'|'secondary'|'accent'
		'mini_cart_enabled'  => 'yes',           // 'yes'|'no'
		'mini_cart_position' => 'bottom-left',   // bottom-left|bottom-right|top-left|top-right
		'smtp_enabled'              => 'no',
		'smtp_host'                 => '',
		'smtp_port'                 => '587',
		'smtp_encryption'           => 'tls',
		'smtp_username'             => '',
		'smtp_password'             => '',
		'smtp_from_name'            => '',
		'smtp_from_email'           => '',
	];
	$saved = get_option( 'glotracol_quote_settings', [] );
	if ( ! is_array( $saved ) ) {
		$saved = [];
	}
	return wp_parse_args( $saved, $defaults );
}

function glotracol_quote_get_setting( $key, $fallback = '' ) {
	$settings = glotracol_quote_get_settings();
	return isset( $settings[ $key ] ) && $settings[ $key ] !== '' ? $settings[ $key ] : $fallback;
}

function glotracol_quote_replace_placeholders( $text, $vars ) {
	foreach ( $vars as $key => $value ) {
		$text = str_replace( '{' . $key . '}', (string) $value, $text );
	}
	return $text;
}

function glotracol_quote_get_form_page_url() {
	$page_id = (int) get_option( 'glotracol_quote_form_page_id' );
	if ( $page_id ) {
		$url = get_permalink( $page_id );
		if ( $url ) return $url;
	}
	return home_url( '/solicitar-cotizacion/' );
}

function glotracol_quote_get_thanks_page_url() {
	$page_id = (int) get_option( 'glotracol_quote_thanks_page_id' );
	if ( $page_id ) {
		$url = get_permalink( $page_id );
		if ( $url ) return $url;
	}
	return home_url( '/cotizacion-enviada/' );
}

function glotracol_quote_emails_to_array( $csv ) {
	$out = [];
	$parts = preg_split( '/[\s,;]+/', (string) $csv );
	foreach ( (array) $parts as $p ) {
		$p = trim( $p );
		if ( $p && is_email( $p ) ) {
			$out[] = sanitize_email( $p );
		}
	}
	return array_unique( $out );
}

function glotracol_quote_get_client_ip() {
	foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ] as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$ip = explode( ',', $_SERVER[ $key ] )[0];
			$ip = trim( $ip );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}
	return '0.0.0.0';
}

function glotracol_quote_load_template( $name, $vars = [] ) {
	$theme_path = get_stylesheet_directory() . '/glotracol-quote/' . $name;
	$plugin_path = GLOTRACOL_QUOTE_PATH . 'templates/' . $name;
	$template = file_exists( $theme_path ) ? $theme_path : $plugin_path;
	if ( ! file_exists( $template ) ) return '';
	if ( is_array( $vars ) ) {
		extract( $vars, EXTR_SKIP );
	}
	ob_start();
	include $template;
	return ob_get_clean();
}

function glotracol_quote_status_label( $slug ) {
	$map = [
		'glo-new'             => 'Nueva',
		'glo-pending-prices'  => 'Pendiente de precios',
		'glo-auto-priced'     => 'Auto-cotizada',
		'glo-processing'      => 'En proceso',
		'glo-responded'       => 'Respondida',
		'glo-closed'          => 'Cerrada',
	];
	return isset( $map[ $slug ] ) ? $map[ $slug ] : $slug;
}

/**
 * Etiqueta para el tipo de cotización ('quote' | 'order').
 */
function glotracol_quote_type_label( $type ) {
	$map = [
		'quote' => 'Cotización',
		'order' => 'Pedido',
	];
	return $map[ $type ] ?? 'Cotización';
}

/**
 * Clasifica una cotización según cantidad de items y unidades totales.
 *
 * Estrategia: la clasificación es la MÁS ALTA entre el match por unidades y
 * el match por SKUs distintos. Esto asegura que un pedido de pocas SKUs pero
 * muchas unidades (o viceversa) se etiquete correctamente.
 *
 * @param int $units_total  Suma de quantities en todos los items.
 * @param int $skus_distinct Cantidad de items (productos distintos) en la cotización.
 * @return string 'small'|'medium'|'large'
 */
function glotracol_quote_size_tag( $units_total, $skus_distinct ) {
	$units_total = max( 0, (int) $units_total );
	$skus_distinct = max( 0, (int) $skus_distinct );

	$thresh_med_units  = (int) glotracol_quote_get_setting( 'size_threshold_medium_units', 25 );
	$thresh_lrg_units  = (int) glotracol_quote_get_setting( 'size_threshold_large_units', 80 );
	$thresh_med_skus   = (int) glotracol_quote_get_setting( 'size_threshold_medium_skus', 5 );
	$thresh_lrg_skus   = (int) glotracol_quote_get_setting( 'size_threshold_large_skus', 12 );

	$by_units = 'small';
	if ( $units_total >= $thresh_lrg_units )       $by_units = 'large';
	elseif ( $units_total >= $thresh_med_units )   $by_units = 'medium';

	$by_skus = 'small';
	if ( $skus_distinct >= $thresh_lrg_skus )      $by_skus = 'large';
	elseif ( $skus_distinct >= $thresh_med_skus )  $by_skus = 'medium';

	$rank = [ 'small' => 0, 'medium' => 1, 'large' => 2 ];
	return $rank[ $by_units ] >= $rank[ $by_skus ] ? $by_units : $by_skus;
}

/**
 * Etiqueta human-readable del size tag.
 */
function glotracol_quote_size_tag_label( $tag ) {
	$map = [
		'small'  => 'Pequeña',
		'medium' => 'Grande',     // normalización de datos viejos
		'large'  => 'Grande',
		'tons'   => 'Toneladas',
	];
	return $map[ $tag ] ?? ucfirst( (string) $tag );
}

/**
 * Busca un cliente B2B por NIT/Cédula. Devuelve post_id o 0.
 *
 * Lookup O(1) vía option `glotracol_quote_nit_index` mantenido por hooks
 * del CPT glo_client.
 *
 * @param string $nit NIT/Cédula tal como vino del formulario (con guiones, espacios, etc.)
 * @return int post_id del cliente o 0 si no hay match.
 */
function glotracol_quote_find_client_by_nit( $nit ) {
	if ( ! class_exists( 'Glotracol_Quote_Client_CPT' ) ) return 0;
	$clean = Glotracol_Quote_Client_CPT::normalize_nit( $nit );
	if ( ! $clean ) return 0;
	$index = Glotracol_Quote_Client_CPT::get_nit_index();
	if ( ! is_array( $index ) || empty( $index ) ) return 0;
	$post_id = isset( $index[ $clean ] ) ? (int) $index[ $clean ] : 0;
	if ( ! $post_id ) return 0;
	// Verifica que el cliente esté activo y publicado
	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== Glotracol_Quote_Client_CPT::POST_TYPE ) return 0;
	if ( $post->post_status === 'trash' ) return 0;
	$active = get_post_meta( $post_id, '_glo_client_active', true );
	if ( $active === 'no' ) return 0; // cliente inactivo → tratar como si no existiera
	return $post_id;
}

/**
 * Devuelve los datos básicos de un cliente B2B.
 */
function glotracol_quote_get_client_data( $client_id ) {
	$client_id = (int) $client_id;
	if ( ! $client_id ) return null;
	return [
		'id'      => $client_id,
		'nit'     => get_post_meta( $client_id, '_glo_client_nit', true ),
		'name'    => get_post_meta( $client_id, '_glo_client_name', true ),
		'email'   => get_post_meta( $client_id, '_glo_client_email', true ),
		'phone'   => get_post_meta( $client_id, '_glo_client_phone', true ),
		'contact' => get_post_meta( $client_id, '_glo_client_contact', true ),
		'city'    => get_post_meta( $client_id, '_glo_client_city', true ),
		'pricing' => get_post_meta( $client_id, '_glo_client_pricing', true ) ?: [],
	];
}

/**
 * Formatea un precio en COP. Por defecto sin decimales.
 */
function glotracol_quote_format_price( $amount, $currency = 'COP' ) {
	$amount = (int) $amount;
	if ( $currency === 'COP' ) {
		return '$ ' . number_format( $amount, 0, ',', '.' ) . ' COP';
	}
	return '$ ' . number_format( $amount, 2, ',', '.' ) . ' ' . esc_html( $currency );
}

/**
 * Devuelve las presentaciones de un producto. Array vacío si no tiene.
 *
 * Fachada — si en el futuro migramos a productos variables WC, basta cambiar
 * esta función sin tocar a los callers.
 *
 * @param int|WC_Product $product_or_id
 * @return array<int, array{idx:int,label:string,sku:string,peso_g:int,precio_publico:int}>
 */
function glotracol_quote_get_presentaciones( $product_or_id ) {
	$product_id = $product_or_id instanceof WC_Product ? $product_or_id->get_id() : (int) $product_or_id;
	if ( ! $product_id ) return [];
	$presentaciones = get_post_meta( $product_id, '_glo_presentaciones', true );
	return is_array( $presentaciones ) ? array_values( $presentaciones ) : [];
}

/**
 * Resuelve una presentación específica por idx. Devuelve null si no existe.
 */
function glotracol_quote_get_presentacion( $product_id, $idx ) {
	$list = glotracol_quote_get_presentaciones( $product_id );
	$idx = (int) $idx;
	foreach ( $list as $p ) {
		if ( (int) ( $p['idx'] ?? -1 ) === $idx ) return $p;
	}
	return null;
}

/**
 * Peso total (kg) de una lista de items.
 *
 * Peso por item: peso_g/1000 de la presentación si existe; si no, peso WC del
 * producto (get_weight(), asumido en kg). Sin peso → 0.
 *
 * @param array $items Items con product_id, quantity y (opcional) presentacion_idx.
 * @return float kg totales.
 */
function glotracol_quote_weight_total( $items ) {
	$total = 0.0;
	foreach ( (array) $items as $item ) {
		$qty = isset( $item['quantity'] ) ? max( 0, (int) $item['quantity'] ) : 0;
		if ( $qty <= 0 ) continue;
		$kg  = 0.0;
		$pid = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
		$idx = isset( $item['presentacion_idx'] ) && $item['presentacion_idx'] !== null ? (int) $item['presentacion_idx'] : null;
		if ( $pid && $idx !== null ) {
			$pres = glotracol_quote_get_presentacion( $pid, $idx );
			if ( $pres && ! empty( $pres['peso_g'] ) ) {
				$kg = (float) $pres['peso_g'] / 1000.0;
			}
		}
		if ( $kg <= 0 && $pid && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $pid );
			if ( $product ) {
				$w = (float) $product->get_weight();
				if ( $w > 0 ) $kg = $w;
			}
		}
		$total += $kg * $qty;
	}
	return $total;
}

/**
 * Clasificación semáforo por peso real, con fallback por unidades/SKUs.
 *
 * @param float $weight_kg     Peso total en kg (de glotracol_quote_weight_total).
 * @param int   $units_total   Unidades totales (fallback si weight_kg == 0).
 * @param int   $skus_distinct SKUs distintos (fallback).
 * @return string 'small'|'large'|'tons'
 */
function glotracol_quote_semaforo( $weight_kg, $units_total = 0, $skus_distinct = 0 ) {
	$weight_kg = (float) $weight_kg;
	if ( $weight_kg > 0 ) {
		$large = (int) glotracol_quote_get_setting( 'weight_threshold_large_kg', 200 );
		$tons  = (int) glotracol_quote_get_setting( 'weight_threshold_tons_kg', 1000 );
		if ( $weight_kg >= $tons )  return 'tons';
		if ( $weight_kg >= $large ) return 'large';
		return 'small';
	}
	// Fallback por unidades: reusa la clasificación previa y la traduce.
	$legacy = glotracol_quote_size_tag( $units_total, $skus_distinct ); // small|medium|large
	if ( $legacy === 'large' )  return 'tons';   // muchas unidades sin peso → rojo
	if ( $legacy === 'medium' ) return 'large';  // medio → amarillo
	return 'small';
}

/**
 * Precio público (COP) de un producto, leído del meta privado _glo_price.
 * @return int|null  null si no tiene precio.
 */
function glotracol_quote_get_product_price( $product_id ) {
	$product_id = (int) $product_id;
	if ( $product_id <= 0 ) return null;
	$p = get_post_meta( $product_id, '_glo_price', true );
	return ( $p === '' || $p === null ) ? null : (int) $p;
}

/**
 * Setea el precio público del producto en _glo_price. price<=0 borra el meta.
 */
function glotracol_quote_set_product_price( $product_id, $price ) {
	$product_id = (int) $product_id;
	if ( $product_id <= 0 ) return false;
	$price = (int) $price;
	if ( $price <= 0 ) { delete_post_meta( $product_id, '_glo_price' ); return true; }
	update_post_meta( $product_id, '_glo_price', $price );
	return true;
}

/**
 * Cantidad de productos con precio público cargado (meta _glo_price > 0).
 * Fuente de verdad del modelo por ID (reemplaza el conteo de la opción legada
 * `glotracol_quote_public_pricing`, que solo aplica a datos antiguos por SKU).
 */
function glotracol_quote_count_products_with_price() {
	global $wpdb;
	return (int) $wpdb->get_var(
		"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
		 WHERE meta_key = '_glo_price' AND meta_value != '' AND meta_value > 0"
	);
}

/**
 * Precio de Lista B (COP) de un producto, leído del meta privado _glo_price_b.
 * @return int|null  null si no tiene precio B.
 */
function glotracol_quote_get_product_price_b( $product_id ) {
	$product_id = (int) $product_id;
	if ( $product_id <= 0 ) return null;
	$p = get_post_meta( $product_id, '_glo_price_b', true );
	return ( $p === '' || $p === null ) ? null : (int) $p;
}

/**
 * Setea el precio de Lista B del producto en _glo_price_b. price<=0 borra el meta.
 */
function glotracol_quote_set_product_price_b( $product_id, $price ) {
	$product_id = (int) $product_id;
	if ( $product_id <= 0 ) return false;
	$price = (int) $price;
	if ( $price <= 0 ) { delete_post_meta( $product_id, '_glo_price_b' ); return true; }
	update_post_meta( $product_id, '_glo_price_b', $price );
	return true;
}

/**
 * Nivel de lista de precios de un cliente: 'A' (default) o 'B'.
 * Cualquier valor distinto de 'B' se trata como 'A'.
 */
function glotracol_quote_get_client_price_list( $client_id ) {
	$client_id = (int) $client_id;
	if ( $client_id <= 0 ) return 'A';
	$v = get_post_meta( $client_id, '_glo_price_list', true );
	return ( $v === 'B' ) ? 'B' : 'A';
}

/**
 * Texto visible de "Presentación" para el cuadro de cotización.
 * Cascada: etiqueta de presentación múltiple → texto curado (_glo_presentacion_texto)
 * → peso del producto formateado → vacío.
 *
 * @param WC_Product|object $product Producto (debe exponer get_id() y get_weight()).
 * @param string            $pres_label Etiqueta de la capa múltiple si el item la trae.
 * @return string
 */
function glotracol_quote_presentacion_display( $product, $pres_label = '' ) {
	$pres_label = is_string( $pres_label ) ? trim( $pres_label ) : '';
	if ( $pres_label !== '' ) {
		return $pres_label;
	}
	if ( $product && method_exists( $product, 'get_id' ) ) {
		$curado = get_post_meta( $product->get_id(), '_glo_presentacion_texto', true );
		$curado = is_string( $curado ) ? trim( $curado ) : '';
		if ( $curado !== '' ) {
			return $curado;
		}
	}
	if ( $product && method_exists( $product, 'get_weight' ) ) {
		$w = $product->get_weight();
		if ( $w !== '' && $w !== null && is_numeric( $w ) ) {
			$w = (float) $w;
			if ( $w > 0 && $w < 1 ) {
				return number_format( $w * 1000, 0, ',', '.' ) . ' g';
			}
			if ( $w > 0 ) {
				$is_int = ( floor( $w ) == $w );
				return ( $is_int ? number_format( $w, 0, ',', '.' ) : rtrim( rtrim( number_format( $w, 2, ',', '.' ), '0' ), ',' ) ) . ' kg';
			}
		}
	}
	return '';
}
