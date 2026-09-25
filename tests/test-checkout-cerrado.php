<?php
/**
 * wp eval-file tests/test-checkout-cerrado.php
 *
 * La tienda funciona por cotizacion: no se crean pedidos por ninguna via publica
 * (checkout clasico, wc-ajax y Store API), y solo los productos publicados se pueden
 * agregar a la cotizacion.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}
if ( function_exists( 'wc_load_cart' ) ) wc_load_cart();

// 1. Solo productos publicados son "comprables" (agregables a la cotizacion).
$pub = wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Test publicado' ] );
$bor = wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'draft', 'post_title' => 'Test borrador' ] );
$pri = wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'private', 'post_title' => 'Test privado' ] );
chk( 'producto publicado sin precio se puede cotizar', wc_get_product( $pub )->is_purchasable() === true );
chk( 'producto en borrador no se puede cotizar', wc_get_product( $bor )->is_purchasable() === false );
chk( 'producto privado no se puede cotizar', wc_get_product( $pri )->is_purchasable() === false );

// 2. Store API: crear pedido responde 403.
$req = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
$res = rest_do_request( $req );
chk( 'Store API checkout bloqueado (403)', $res->get_status() === 403 );
$err = $res->as_error();
chk( 'con el codigo gloq_checkout_closed', $err && $err->get_error_code() === 'gloq_checkout_closed' );
$req2 = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );
chk( 'la lectura del carrito por Store API sigue funcionando', rest_do_request( $req2 )->get_status() !== 403 );

// 3. Checkout clasico y wc-ajax=checkout: woocommerce_checkout_process deja un error.
wc_clear_notices();
do_action( 'woocommerce_checkout_process' );
chk( 'checkout clasico rechazado con aviso de error', wc_notice_count( 'error' ) > 0 );
wc_clear_notices();

foreach ( [ $pub, $bor, $pri ] as $id ) wp_delete_post( $id, true );
exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
