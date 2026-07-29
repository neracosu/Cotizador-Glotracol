<?php
/** wp eval-file tests/test-ghl.php — cliente de la API de GoHighLevel (HTTP simulado). */
$GLOBALS['gloq_fail'] = 0;
function chk( $label, $cond ) {
	echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
	if ( ! $cond ) $GLOBALS['gloq_fail']++;
}

chk( 'la clase existe', class_exists( 'Glotracol_Quote_GHL' ) );

// --- Arnés: intercepta TODO el HTTP saliente. Nunca se llama a GHL de verdad. ---
$GLOBALS['ghl_calls'] = [];
$GLOBALS['ghl_reply'] = [ 'code' => 200, 'body' => '{}' ];
add_filter( 'pre_http_request', function( $pre, $args, $url ) {
	$GLOBALS['ghl_calls'][] = [ 'url' => $url, 'args' => $args ];
	$r = $GLOBALS['ghl_reply'];
	if ( ! empty( $r['wp_error'] ) ) return new WP_Error( 'http_request_failed', 'sin red' );
	return [ 'headers' => [], 'body' => $r['body'], 'response' => [ 'code' => $r['code'], 'message' => '' ], 'cookies' => [], 'filename' => null ];
}, 10, 3 );

// Ajustes de prueba en memoria (no tocan la config real del sitio).
add_filter( 'glotracol_quote_setting', function( $val, $key ) {
	$map = [ 'ghl_enabled' => 'yes', 'ghl_token' => 'pit-TOKEN-DE-PRUEBA', 'ghl_location_id' => 'LOC123' ];
	return array_key_exists( $key, $map ) ? $map[ $key ] : $val;
}, 10, 2 );

chk( 'is_configured detecta la config', Glotracol_Quote_GHL::is_configured() === true );

// --- 1. cabeceras y URL ---
$GLOBALS['ghl_calls'] = [];
$GLOBALS['ghl_reply'] = [ 'code' => 200, 'body' => '{"ok":true}' ];
$r = Glotracol_Quote_GHL::request( 'GET', '/ping' );
$call = $GLOBALS['ghl_calls'][0] ?? [];
chk( 'usa la base URL correcta', strpos( $call['url'] ?? '', 'https://services.leadconnectorhq.com/ping' ) === 0 );
$h = $call['args']['headers'] ?? [];
chk( 'manda Authorization Bearer', ( $h['Authorization'] ?? '' ) === 'Bearer pit-TOKEN-DE-PRUEBA' );
chk( 'manda Version 2021-07-28', ( $h['Version'] ?? '' ) === '2021-07-28' );
chk( 'manda Content-Type json', ( $h['Content-Type'] ?? '' ) === 'application/json' );
chk( 'devuelve el cuerpo decodificado', is_array( $r ) && ! empty( $r['ok'] ) );

// --- 2. errores tipificados ---
$GLOBALS['ghl_reply'] = [ 'code' => 401, 'body' => '{"message":"no autorizado"}' ];
$e = Glotracol_Quote_GHL::request( 'GET', '/ping' );
chk( '401 devuelve WP_Error', is_wp_error( $e ) );
chk( '401 se marca como ghl_auth', is_wp_error( $e ) && $e->get_error_code() === 'ghl_auth' );

$GLOBALS['ghl_reply'] = [ 'code' => 500, 'body' => '{}' ];
$e2 = Glotracol_Quote_GHL::request( 'GET', '/ping' );
chk( '500 se marca como ghl_http', is_wp_error( $e2 ) && $e2->get_error_code() === 'ghl_http' );

$GLOBALS['ghl_reply'] = [ 'wp_error' => true ];
$e3 = Glotracol_Quote_GHL::request( 'GET', '/ping' );
chk( 'fallo de red se marca como ghl_net', is_wp_error( $e3 ) && $e3->get_error_code() === 'ghl_net' );

// --- 3. pipelines: forma real de la respuesta de GHL ---
delete_transient( 'gloq_ghl_pipelines' );
$GLOBALS['ghl_reply'] = [ 'code' => 200, 'body' => wp_json_encode( [ 'pipelines' => [
	[ 'id' => 'P1', 'name' => 'Pagina web', 'stages' => [
		[ 'id' => 'S1', 'name' => 'Nuevo lead' ],
		[ 'id' => 'S2', 'name' => 'Cotización enviada' ],
	] ],
] ] ) ];
$p = Glotracol_Quote_GHL::pipelines( true );
chk( 'pipelines devuelve el pipeline', isset( $p['P1'] ) );
chk( 'pipelines trae el nombre', ( $p['P1']['name'] ?? '' ) === 'Pagina web' );
chk( 'pipelines trae las etapas indexadas por id', ( $p['P1']['stages']['S2'] ?? '' ) === 'Cotización enviada' );

// --- 4. el caché evita una segunda llamada ---
$GLOBALS['ghl_calls'] = [];
Glotracol_Quote_GHL::pipelines();
chk( 'la segunda llamada sale del caché', count( $GLOBALS['ghl_calls'] ) === 0 );
delete_transient( 'gloq_ghl_pipelines' );

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
