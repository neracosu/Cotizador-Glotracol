<?php
/**
 * wp eval-file tests/test-nit-verify.php
 *
 * Verificacion por codigo al correo registrado antes de mostrar precios negociados.
 * Crea sus propios clientes y productos y los borra al terminar. Los correos no salen:
 * el mu-plugin de desarrollo los bloquea; aqui se capturan con pre_wp_mail.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}
if ( ! class_exists( 'Glotracol_Quote_NIT_Verify' ) ) {
	chk( 'existe Glotracol_Quote_NIT_Verify', false );
	exit( 1 );
}
if ( function_exists( 'wc_load_cart' ) ) wc_load_cart();

// Captura de correos.
$GLOBALS['gloq_mails'] = [];
add_filter( 'pre_wp_mail', function ( $ret, $atts ) { $GLOBALS['gloq_mails'][] = $atts; return $ret; }, 1, 2 );
add_filter( 'gloq_otp_cooldown', '__return_zero' );
$code_from_last_mail = function () {
	$m = end( $GLOBALS['gloq_mails'] );
	return ( $m && preg_match( '/(?<!\d)(\d{6})(?!\d)/', wp_strip_all_tags( $m['message'] ), $x ) ) ? $x[1] : '';
};

$tag = wp_generate_password( 6, false, false );
$ip  = '203.0.113.' . wp_rand( 1, 250 );
$nit_ok   = '9' . wp_rand( 10000000, 99999999 );
$nit_otro = '8' . wp_rand( 10000000, 99999999 );
$nit_inac = '7' . wp_rand( 10000000, 99999999 );

$pid = wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Test OTP ' . $tag ] );
update_post_meta( $pid, '_glo_price', 10000 );

$mk = function ( $nit, $email, $active ) use ( $pid, $tag ) {
	$c = wp_insert_post( [ 'post_type' => 'glo_client', 'post_status' => 'publish', 'post_title' => 'Cliente OTP ' . $tag ] );
	update_post_meta( $c, '_glo_client_nit', $nit );
	update_post_meta( $c, '_glo_client_name', 'Cliente OTP ' . $tag );
	update_post_meta( $c, '_glo_client_email', $email );
	update_post_meta( $c, '_glo_client_active', $active );
	update_post_meta( $c, '_glo_client_pricing', [ $pid => 7000 ] );
	Glotracol_Quote_Client_CPT::rebuild_nit_index_for_post_id( $c );
	return $c;
};
$c_ok   = $mk( $nit_ok, "registrado-$tag@example.com", 'yes' );
$c_inac = $mk( $nit_inac, "inactivo-$tag@example.com", 'no' );

// 1. Respuesta identica exista o no el cliente.
$GLOBALS['gloq_mails'] = [];
$r_no = Glotracol_Quote_NIT_Verify::request_code( $nit_otro, $ip );
chk( 'NIT que no es cliente: no sale correo', count( $GLOBALS['gloq_mails'] ) === 0 );
$r_si = Glotracol_Quote_NIT_Verify::request_code( $nit_ok, $ip );
chk( 'NIT de cliente: sale un correo', count( $GLOBALS['gloq_mails'] ) === 1 );
chk( 'la respuesta publica es la misma en los dos casos', $r_no['message'] === $r_si['message'] && ! isset( $r_no['sent'] ) === ! isset( $r_si['sent'] ) );
$to = (array) ( $GLOBALS['gloq_mails'][0]['to'] ?? [] );
chk( 'el codigo va al correo registrado del cliente', in_array( "registrado-$tag@example.com", $to, true ) || ( $GLOBALS['gloq_mails'][0]['to'] ?? '' ) === "registrado-$tag@example.com" );
$code = $code_from_last_mail();
chk( 'el correo trae un codigo de 6 digitos', strlen( $code ) === 6 );

// Cliente inactivo: no recibe codigo.
$GLOBALS['gloq_mails'] = [];
Glotracol_Quote_NIT_Verify::request_code( $nit_inac, $ip );
chk( 'cliente inactivo: no sale correo', count( $GLOBALS['gloq_mails'] ) === 0 );

// 2. Antes de verificar, el precio negociado no se aplica.
chk( 'sin verificar no hay cliente verificado', Glotracol_Quote_NIT_Verify::verified_client_id( $nit_ok ) === 0 );

// 3. El codigo se guarda con hash, no en texto plano.
$stored = get_transient( Glotracol_Quote_NIT_Verify::otp_key( $nit_ok ) );
chk( 'el codigo no se guarda en texto plano', is_array( $stored ) && strpos( wp_json_encode( $stored ), $code ) === false );

// 4. Codigo incorrecto, y variantes de formato del NIT al verificar.
chk( 'codigo incorrecto falla', Glotracol_Quote_NIT_Verify::verify_code( $nit_ok, '000000' === $code ? '111111' : '000000', $ip ) === false );
$nit_con_formato = substr( $nit_ok, 0, 3 ) . '.' . substr( $nit_ok, 3, 3 ) . '.' . substr( $nit_ok, 6 );
chk( 'codigo correcto con el NIT escrito con puntos funciona', Glotracol_Quote_NIT_Verify::verify_code( $nit_con_formato, $code, $ip ) === true );
chk( 'queda verificado para ese NIT', Glotracol_Quote_NIT_Verify::verified_client_id( $nit_ok ) === $c_ok );
chk( 'el codigo usado no sirve dos veces', Glotracol_Quote_NIT_Verify::verify_code( $nit_ok, $code, $ip ) === false );
chk( 'la verificacion no vale para otro NIT', Glotracol_Quote_NIT_Verify::verified_client_id( $nit_otro ) === 0 );

// 5. Con la verificacion, el recalculo aplica el precio negociado.
$ctx = Glotracol_Quote_Pricing::resolve_items( [ [ 'product_id' => $pid, 'quantity' => 1 ] ], Glotracol_Quote_NIT_Verify::verified_client_id( $nit_ok ) );
chk( 'verificado: precio negociado 7000', (int) $ctx['items'][0]['precio_unitario'] === 7000 );

// 6. Cinco intentos fallidos queman el codigo.
Glotracol_Quote_NIT_Verify::forget( $nit_ok );
$GLOBALS['gloq_mails'] = [];
Glotracol_Quote_NIT_Verify::request_code( $nit_ok, $ip );
$code2 = $code_from_last_mail();
for ( $i = 0; $i < 5; $i++ ) Glotracol_Quote_NIT_Verify::verify_code( $nit_ok, $code2 === '222222' ? '333333' : '222222', $ip );
chk( 'tras 5 intentos fallidos, el codigo correcto ya no sirve', Glotracol_Quote_NIT_Verify::verify_code( $nit_ok, $code2, $ip ) === false );

// 7. Codigo vencido.
$GLOBALS['gloq_mails'] = [];
Glotracol_Quote_NIT_Verify::request_code( $nit_ok, $ip );
$code3 = $code_from_last_mail();
$st = get_transient( Glotracol_Quote_NIT_Verify::otp_key( $nit_ok ) );
$st['exp'] = time() - 1;
set_transient( Glotracol_Quote_NIT_Verify::otp_key( $nit_ok ), $st, 600 );
chk( 'codigo vencido no sirve', Glotracol_Quote_NIT_Verify::verify_code( $nit_ok, $code3, $ip ) === false );

// 8. Maximo 5 codigos por NIT por hora (ya se pidieron 3 para nit_ok).
$GLOBALS['gloq_mails'] = [];
Glotracol_Quote_NIT_Verify::request_code( $nit_ok, $ip );
Glotracol_Quote_NIT_Verify::request_code( $nit_ok, $ip );
Glotracol_Quote_NIT_Verify::request_code( $nit_ok, $ip );
chk( 'el sexto pedido de la hora no envia correo', count( $GLOBALS['gloq_mails'] ) === 2 );

// Limpieza.
Glotracol_Quote_NIT_Verify::forget( $nit_ok );
wp_delete_post( $pid, true );
wp_delete_post( $c_ok, true );
wp_delete_post( $c_inac, true );
Glotracol_Quote_Client_CPT::rebuild_full_index();
global $wpdb;
foreach ( [ $nit_ok, $nit_otro, $nit_inac ] as $n ) {
	foreach ( [ 'nit', 'otp' ] as $scope ) {
		$wpdb->delete( $wpdb->options, [ 'option_name' => Glotracol_Quote_Rate_Limit::option_name( $scope, Glotracol_Quote_Client_CPT::normalize_nit( $n ) ) ] );
	}
}
foreach ( [ 'otp_ip', 'otp_verify_ip' ] as $scope ) {
	$wpdb->delete( $wpdb->options, [ 'option_name' => Glotracol_Quote_Rate_Limit::option_name( $scope, $ip ) ] );
}

exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
