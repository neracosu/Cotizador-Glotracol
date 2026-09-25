<?php
/**
 * wp eval-file tests/test-envio-idempotente.php
 *
 * Un mismo formulario enviado dos veces (doble clic, reintento) crea una sola cotizacion.
 * Y si el formulario falla, los datos del cliente vuelven por la sesion, no por la URL.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}
if ( function_exists( 'wc_load_cart' ) ) wc_load_cart();

// Token de envio de un solo uso.
$t = Glotracol_Quote_Form::new_submit_token();
chk( 'el token es largo y aleatorio', strlen( $t ) >= 20 && $t !== Glotracol_Quote_Form::new_submit_token() );
$c1 = Glotracol_Quote_Form::claim_submit_token( $t );
chk( 'primer envio: se procesa', $c1['state'] === 'new' );
$c2 = Glotracol_Quote_Form::claim_submit_token( $t );
chk( 'segundo envio en curso: no se procesa', $c2['state'] === 'duplicate' );
Glotracol_Quote_Form::finish_submit_token( $t, 'QID123' );
$c3 = Glotracol_Quote_Form::claim_submit_token( $t );
chk( 'envio repetido despues: apunta a la cotizacion ya creada', $c3['state'] === 'duplicate' && $c3['qid'] === 'QID123' );
chk( 'sin token (formulario viejo en cache): se procesa', Glotracol_Quote_Form::claim_submit_token( '' )['state'] === 'new' );
Glotracol_Quote_Form::release_submit_token( $t );

// Estado del formulario por sesion, no por URL.
Glotracol_Quote_Form::stash_form_state( 'Faltan campos: nombre.', [ 'email' => 'x@example.com', 'phone' => '300' ] );
$url = Glotracol_Quote_Form::error_url( 'https://example.com/solicitar-cotizacion/' );
chk( 'la URL de error no lleva datos del cliente', strpos( $url, 'example.com%40' ) === false && strpos( $url, 'gloq_old' ) === false && strpos( $url, 'x%40example' ) === false );
chk( 'la URL de error no lleva el texto del mensaje', strpos( $url, 'Faltan' ) === false );
$st = Glotracol_Quote_Form::take_form_state();
chk( 'los datos vuelven por la sesion', ( $st['old']['email'] ?? '' ) === 'x@example.com' && $st['error'] === 'Faltan campos: nombre.' );
chk( 'se leen una sola vez', Glotracol_Quote_Form::take_form_state()['error'] === '' );

exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
