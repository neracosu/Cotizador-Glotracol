<?php
/**
 * Verifica la forma del payload del webhook contra una cotización existente.
 * Uso: wp eval-file tests/test-webhook-payload.php <quote_id>
 * Si no se pasa id, toma la cotización glo_quote más reciente.
 */
$args = $GLOBALS['argv'] ?? [];
$qid  = 0;
foreach ( $args as $a ) { if ( ctype_digit( (string) $a ) ) { $qid = (int) $a; } }
if ( ! $qid ) {
	$q = get_posts( [ 'post_type' => 'glo_quote', 'post_status' => 'any', 'numberposts' => 1 ] );
	$qid = $q ? $q[0]->ID : 0;
}
if ( ! $qid ) { echo "No hay cotizaciones para probar.\n"; return; }

$captured = null;
add_filter( 'glotracol_quote_webhook_payload', function ( $p ) use ( &$captured ) { $captured = $p; return $p; }, 999 );
add_filter( 'pre_http_request', function () { return new WP_Error( 'test', 'bloqueado en test' ); }, 999 );

$wh = new Glotracol_Quote_Webhook();
$opt = get_option( 'glotracol_quote_settings', [] );
$prev = $opt['webhook_url'] ?? '';
$opt['webhook_url'] = 'https://example.invalid/hook';
update_option( 'glotracol_quote_settings', $opt );

$wh->dispatch( $qid );

$opt['webhook_url'] = $prev;
update_option( 'glotracol_quote_settings', $opt );

$req = [ 'event','quote_id','type','pricing_status','currency','total','units_total','weight_total_kg','size_tag','client','customer','items','admin_url' ];
$missing = array_values( array_filter( $req, function ( $k ) use ( $captured ) { return ! is_array( $captured ) || ! array_key_exists( $k, $captured ); } ) );
echo "Cotización #$qid\n";
if ( $missing ) { echo "[FAIL] faltan claves: " . implode( ', ', $missing ) . "\n"; }
else { echo "[OK] payload contiene todas las claves enriquecidas\n"; echo "event=" . $captured['event'] . " type=" . $captured['type'] . " total=" . $captured['total'] . "\n"; }
