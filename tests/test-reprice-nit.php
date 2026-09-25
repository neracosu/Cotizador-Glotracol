<?php
/**
 * wp eval-file tests/test-reprice-nit.php
 *
 * Recalculo de precios por NIT en el formulario: sin verificar el codigo, el NIT de un
 * cliente NO le muestra sus precios (el NIT es publico); verificado, si.
 * Crea sus propios datos (producto con Lista A y B, cliente Lista B) y los borra.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}
if ( function_exists( 'wc_load_cart' ) ) wc_load_cart();
add_filter( 'pre_wp_mail', function ( $ret, $atts ) { $GLOBALS['gloq_last_mail'] = $atts; return $ret; }, 1, 2 );
add_filter( 'gloq_otp_cooldown', '__return_zero' );

$tag = wp_generate_password( 6, false, false );
$ip  = '198.51.100.' . wp_rand( 1, 250 );
$nit = '9' . wp_rand( 10000000, 99999999 );

$pid = wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Test reprice ' . $tag ] );
update_post_meta( $pid, '_glo_price', 10000 );
update_post_meta( $pid, '_glo_price_b', 8000 );

$cid = wp_insert_post( [ 'post_type' => 'glo_client', 'post_status' => 'publish', 'post_title' => 'Cliente reprice ' . $tag ] );
update_post_meta( $cid, '_glo_client_nit', $nit );
update_post_meta( $cid, '_glo_client_email', "reprice-$tag@example.com" );
update_post_meta( $cid, '_glo_client_active', 'yes' );
update_post_meta( $cid, '_glo_price_list', 'B' );
Glotracol_Quote_Client_CPT::rebuild_nit_index_for_post_id( $cid );

// Llama al endpoint AJAX real y devuelve su JSON.
$call = function () use ( $nit ) {
	$_POST = $_REQUEST = [ 'action' => 'gloq_reprice_by_nit', '_wpnonce' => wp_create_nonce( 'gloq_reprice_by_nit' ), 'nit' => $nit ];
	add_filter( 'wp_doing_ajax', '__return_true' );
	$h = function () { return function () { throw new Exception( 'fin' ); }; };
	add_filter( 'wp_die_ajax_handler', $h );
	ob_start();
	try { ( new ReflectionClass( 'Glotracol_Quote_Form' ) )->newInstanceWithoutConstructor()->ajax_reprice_by_nit(); } catch ( Exception $e ) {}
	$json = json_decode( ob_get_clean(), true );
	remove_filter( 'wp_die_ajax_handler', $h );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	return $json;
};

// El carrito del test lleva el producto.
WC()->cart->empty_cart();
WC()->cart->add_to_cart( $pid, 2 );

$r = $call();
$key = $r ? array_key_first( (array) ( $r['data']['items'] ?? [] ) ) : null;
chk( 'el endpoint responde', ! empty( $r['success'] ) && $key !== null );
chk( 'sin verificar: precio publico 10000', (int) ( $r['data']['items'][ $key ]['unit'] ?? 0 ) === 10000 );
chk( 'sin verificar: no dice que es cliente B2B', empty( $r['data']['es_b2b'] ) );

// Verificar con el codigo que llega al correo registrado.
Glotracol_Quote_NIT_Verify::request_code( $nit, $ip );
preg_match( '/(?<!\d)(\d{6})(?!\d)/', wp_strip_all_tags( $GLOBALS['gloq_last_mail']['message'] ?? '' ), $m );
chk( 'verificacion con el codigo del correo', ! empty( $m[1] ) && Glotracol_Quote_NIT_Verify::verify_code( $nit, $m[1], $ip ) );

$r = $call();
chk( 'verificado: precio Lista B 8000', (int) ( $r['data']['items'][ $key ]['unit'] ?? 0 ) === 8000 );
chk( 'verificado: sello B2B', ! empty( $r['data']['es_b2b'] ) );

// Limpieza.
Glotracol_Quote_NIT_Verify::forget( $nit );
WC()->cart->empty_cart();
wp_delete_post( $pid, true );
wp_delete_post( $cid, true );
Glotracol_Quote_Client_CPT::rebuild_full_index();
global $wpdb;
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name IN (%s,%s,%s)",
	Glotracol_Quote_Rate_Limit::option_name( 'nit', Glotracol_Quote_Client_CPT::normalize_nit( $nit ) ),
	Glotracol_Quote_Rate_Limit::option_name( 'otp_ip', $ip ),
	Glotracol_Quote_Rate_Limit::option_name( 'otp_verify_ip', $ip ) ) );

exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
