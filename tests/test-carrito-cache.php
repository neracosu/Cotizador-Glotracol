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
exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
