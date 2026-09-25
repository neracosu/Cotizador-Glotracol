<?php
/**
 * wp eval-file tests/test-carrito-cache.php
 *
 * Con cache de pagina (LiteSpeed en glotracol.com), el nonce impreso en el HTML vence y
 * el carrito flotante dejaba de cambiar cantidades. Un visitante anonimo con su cookie
 * de sesion de WooCommerce solo toca su propio carrito: se acepta sin nonce fresco.
 * Un usuario con sesion de WordPress sigue necesitando el nonce. Y hay tope de cantidad.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}
wp_set_current_user( 0 );
$_REQUEST['_wpnonce'] = 'vencido';
$_COOKIE = [];
chk( 'anonimo sin cookie de sesion y nonce vencido: rechazado', Glotracol_Quote_Form::verify_cart_request( 'gloq_update_qty' ) === false );
$_COOKIE[ 'wp_woocommerce_session_' . COOKIEHASH ] = 'abc';
chk( 'anonimo con cookie de sesion: aceptado aunque el nonce vencio', Glotracol_Quote_Form::verify_cart_request( 'gloq_update_qty' ) === true );
$_REQUEST['_wpnonce'] = wp_create_nonce( 'gloq_update_qty' );
chk( 'nonce valido: aceptado', Glotracol_Quote_Form::verify_cart_request( 'gloq_update_qty' ) === true );

$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
wp_set_current_user( (int) $admins[0] );
$_REQUEST['_wpnonce'] = 'vencido';
chk( 'usuario con sesion y nonce vencido: rechazado', Glotracol_Quote_Form::verify_cart_request( 'gloq_update_qty' ) === false );
wp_set_current_user( 0 );

chk( 'tope de cantidad', Glotracol_Quote_Form::clamp_qty( 999999999 ) === Glotracol_Quote_Form::max_qty() && Glotracol_Quote_Form::max_qty() > 0 );
chk( 'cantidad normal se conserva', Glotracol_Quote_Form::clamp_qty( 25 ) === 25 );
chk( 'negativa queda en 0', Glotracol_Quote_Form::clamp_qty( -3 ) === 0 );

// Cambiar la presentacion: si el alta de la nueva falla, la anterior se queda.
if ( function_exists( 'wc_load_cart' ) ) wc_load_cart();
$pid = wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Test swap' ] );
update_post_meta( $pid, '_glo_presentaciones', [
	[ 'idx' => 0, 'label' => 'A', 'sku' => 'SWA', 'peso_g' => 0, 'precio_publico' => 0 ],
	[ 'idx' => 1, 'label' => 'B', 'sku' => 'SWB', 'peso_g' => 0, 'precio_publico' => 0 ],
] );
WC()->cart->empty_cart();
$k = WC()->cart->add_to_cart( $pid, 3, 0, [], [ 'gloq_presentacion' => [ 'idx' => 0, 'label' => 'A', 'sku' => 'SWA' ] ] );
$swap = function ( $key, $idx ) {
	$_POST = $_REQUEST = [ 'key' => $key, 'idx' => $idx, '_wpnonce' => wp_create_nonce( 'gloq_swap_presentation' ) ];
	add_filter( 'wp_doing_ajax', '__return_true' );
	$h = function () { return function () { throw new Exception( 'fin' ); }; };
	add_filter( 'wp_die_ajax_handler', $h );
	ob_start();
	try { ( new ReflectionClass( 'Glotracol_Quote_Cart_Overrides' ) )->newInstanceWithoutConstructor()->ajax_swap_presentation(); } catch ( Exception $e ) {}
	$r = json_decode( ob_get_clean(), true );
	remove_filter( 'wp_die_ajax_handler', $h ); remove_filter( 'wp_doing_ajax', '__return_true' );
	return $r;
};
wp_update_post( [ 'ID' => $pid, 'post_status' => 'draft' ] );   // el alta nueva va a fallar
$r = $swap( $k, 1 );
$idxs = array_map( function ( $i ) { return $i['gloq_presentacion']['idx'] ?? null; }, WC()->cart->get_cart() );
chk( 'si el alta falla, la presentacion anterior sigue en el carrito', empty( $r['success'] ) && in_array( 0, $idxs, true ) );
wp_update_post( [ 'ID' => $pid, 'post_status' => 'publish' ] );
$r = $swap( $k, 1 );
$idxs = array_map( function ( $i ) { return $i['gloq_presentacion']['idx'] ?? null; }, WC()->cart->get_cart() );
chk( 'cambio normal: queda solo la nueva', ! empty( $r['success'] ) && $idxs && array_values( $idxs ) === [ 1 ] );
WC()->cart->empty_cart();
wp_delete_post( $pid, true );

exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
