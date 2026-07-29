<?php
/**
 * Verifica la forma del payload del webhook.
 * Uso: wp eval-file tests/test-webhook-payload.php [quote_id]
 *
 * Sin argumento se crea una cotización de laboratorio y se borra al terminar. Antes se tomaba la
 * cotización REAL más reciente: eso disparaba el webhook contra un pedido de verdad, dejaba
 * reintentos programados y llenaba Cotizaciones → Registros de errores que parecían reales.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}

// El webhook falla a propósito aquí; el registro del sitio no tiene por qué enterarse.
$LOG_ORIG = get_option( 'glotracol_quote_log' );

$args = $GLOBALS['argv'] ?? [];
$qid  = 0;
foreach ( $args as $a ) { if ( ctype_digit( (string) $a ) ) { $qid = (int) $a; } }

$propia = false;
if ( ! $qid ) {
	$propia = true;
	$qid = wp_insert_post( [ 'post_type' => 'glo_quote', 'post_status' => 'glo-new', 'post_title' => 'TEST payload webhook' ], true );
	if ( is_wp_error( $qid ) ) { echo "[FAIL] no se pudo crear la cotización de prueba\n"; return; }
	update_post_meta( $qid, '_glo_customer_name', 'Cliente Prueba' );
	update_post_meta( $qid, '_glo_customer_email', 'prueba@ejemplo.com' );
	update_post_meta( $qid, '_glo_total', 150000 );
	update_post_meta( $qid, '_glo_pricing_status', 'priced' );
	update_post_meta( $qid, '_glo_weight_total_kg', 12.5 );
	update_post_meta( $qid, '_glo_items', [ [
		'product_id' => 0, 'name' => 'MANÍ TOSTADO', 'sku' => 'MT1', 'quantity' => 3,
		'precio_unitario' => 50000, 'precio_subtotal' => 150000,
	] ] );
}

$captured = null;
add_filter( 'glotracol_quote_webhook_payload', function ( $p ) use ( &$captured ) { $captured = $p; return $p; }, 999 );
add_filter( 'pre_http_request', function () { return new WP_Error( 'test', 'bloqueado en test' ); }, 999 );

$wh   = new Glotracol_Quote_Webhook();
$opt  = get_option( 'glotracol_quote_settings', [] );
$prev = $opt['webhook_url'] ?? '';
$opt['webhook_url'] = 'https://example.invalid/hook';
update_option( 'glotracol_quote_settings', $opt );

$wh->dispatch( $qid );

$opt['webhook_url'] = $prev;
update_option( 'glotracol_quote_settings', $opt );

echo "Cotización #$qid" . ( $propia ? " (de laboratorio)\n" : " (indicada a mano)\n" );

$req = [ 'event','quote_id','type','pricing_status','currency','total','units_total','weight_total_kg','size_tag','client','customer','items','admin_url' ];
$missing = array_values( array_filter( $req, function ( $k ) use ( $captured ) { return ! is_array( $captured ) || ! array_key_exists( $k, $captured ); } ) );
chk( 'el payload trae todas las claves enriquecidas' . ( $missing ? ' — faltan: ' . implode( ', ', $missing ) : '' ), empty( $missing ) );
if ( ! $missing ) echo "     event={$captured['event']} type={$captured['type']} total={$captured['total']}\n";

// El fallo del envío deja un reintento programado: se limpia para no dejar crons huérfanos.
wp_clear_scheduled_hook( Glotracol_Quote_Webhook::HOOK, [ (int) $qid ] );
chk( 'no deja reintentos programados', ! wp_next_scheduled( Glotracol_Quote_Webhook::HOOK, [ (int) $qid ] ) );

if ( $propia ) {
	delete_post_meta( $qid, '_glo_webhook_attempts' );
	wp_delete_post( $qid, true );
	chk( 'la cotización de laboratorio queda borrada', ! get_post( $qid ) );
}

if ( false !== $LOG_ORIG ) update_option( 'glotracol_quote_log', $LOG_ORIG, false );
else delete_option( 'glotracol_quote_log' );
chk( 'el test no deja rastro en el registro del sitio', get_option( 'glotracol_quote_log' ) === $LOG_ORIG );

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
