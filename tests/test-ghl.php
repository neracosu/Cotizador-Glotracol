<?php
/** wp eval-file tests/test-ghl.php — cliente de la API de GoHighLevel (HTTP simulado). */
$GLOBALS['gloq_fail'] = 0;
function chk( $label, $cond ) {
	echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
	if ( ! $cond ) $GLOBALS['gloq_fail']++;
}

// Este test provoca envíos y fallos a propósito, y el logger escribe en el MISMO registro que ve
// el dueño en Cotizaciones → Registros. Se guarda para devolverlo intacto al terminar.
$GLOBALS['gloq_log_orig'] = get_option( 'glotracol_quote_log' );

chk( 'la clase existe', class_exists( 'Glotracol_Quote_GHL' ) );

// --- Arnés: intercepta TODO el HTTP saliente. Nunca se llama a GHL de verdad. ---
$GLOBALS['ghl_calls'] = [];
$GLOBALS['ghl_reply'] = [ 'code' => 200, 'body' => '{}' ];
$GLOBALS['ghl_queue'] = [];
add_filter( 'pre_http_request', function( $pre, $args, $url ) {
	$GLOBALS['ghl_calls'][] = [ 'url' => $url, 'args' => $args ];
	// Cola para los flujos de varias llamadas seguidas; si está vacía, se usa la respuesta fija.
	$r = ! empty( $GLOBALS['ghl_queue'] ) ? array_shift( $GLOBALS['ghl_queue'] ) : $GLOBALS['ghl_reply'];
	if ( ! empty( $r['wp_error'] ) ) return new WP_Error( 'http_request_failed', 'sin red' );
	return [ 'headers' => [], 'body' => $r['body'], 'response' => [ 'code' => $r['code'], 'message' => '' ], 'cookies' => [], 'filename' => null ];
}, 10, 3 );

// Ajustes de prueba en memoria (no tocan la config real del sitio).
add_filter( 'glotracol_quote_setting', function( $val, $key ) {
	$map = [
		'ghl_enabled'          => 'yes',
		'ghl_token'            => 'pit-TOKEN-DE-PRUEBA',
		'ghl_location_id'      => 'LOC123',
		'ghl_pipeline_id'      => 'P1',
		'ghl_stage_id'         => 'S2',
		'ghl_stage_id_pending' => 'S1',
	];
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

// ---------- Task 3: envío completo ----------
chk( 'existe send_quote', method_exists( 'Glotracol_Quote_GHL', 'send_quote' ) );

// Cotización de laboratorio, creada y borrada aquí mismo.
$qid = wp_insert_post( [ 'post_type' => 'glo_quote', 'post_status' => 'glo-new', 'post_title' => 'TEST GHL' ], true );
chk( 'se pudo crear la cotización de prueba', ! is_wp_error( $qid ) && $qid > 0 );
update_post_meta( $qid, '_glo_customer_name', 'Diana Ruiz Gómez' );
update_post_meta( $qid, '_glo_customer_email', 'diana@ejemplo.com' );
update_post_meta( $qid, '_glo_customer_company', 'Glotracol SAS' );
update_post_meta( $qid, '_glo_customer_city', 'Bogotá' );
update_post_meta( $qid, '_glo_customer_nit', '900123456' );
update_post_meta( $qid, '_glo_total', 250000 );
update_post_meta( $qid, '_glo_pricing_status', 'priced' );
update_post_meta( $qid, '_glo_items', [ [ 'product_id' => 0, 'name' => 'MANÍ', 'sku' => 'M1', 'quantity' => 2, 'precio_unitario' => 125000, 'precio_subtotal' => 250000 ] ] );

// Contacto, oportunidad y nota: un id distinto en cada respuesta.
$tres_ok = function () {
	$GLOBALS['ghl_queue'] = [
		[ 'code' => 201, 'body' => '{"contact":{"id":"C1"}}' ],
		[ 'code' => 201, 'body' => '{"opportunity":{"id":"O1"}}' ],
		[ 'code' => 201, 'body' => '{"note":{"id":"N1"}}' ],
	];
	$GLOBALS['ghl_calls'] = [];
};

$tres_ok();
$ok = Glotracol_Quote_GHL::send_quote( $qid );
chk( 'send_quote devuelve true', $ok === true );
chk( 'hizo exactamente 3 llamadas', count( $GLOBALS['ghl_calls'] ) === 3 );
chk( 'guardó el contactId', get_post_meta( $qid, '_glo_ghl_contact_id', true ) === 'C1' );
chk( 'guardó el opportunityId', get_post_meta( $qid, '_glo_ghl_opportunity_id', true ) === 'O1' );

$c1 = json_decode( $GLOBALS['ghl_calls'][0]['args']['body'] ?? '{}', true );
chk( 'parte el nombre en firstName', ( $c1['firstName'] ?? '' ) === 'Diana' );
chk( 'el resto va a lastName', ( $c1['lastName'] ?? '' ) === 'Ruiz Gómez' );
chk( 'manda el locationId', ( $c1['locationId'] ?? '' ) === 'LOC123' );
chk( 'sin teléfono no manda el campo phone', ! array_key_exists( 'phone', (array) $c1 ) );

$c2 = json_decode( $GLOBALS['ghl_calls'][1]['args']['body'] ?? '{}', true );
chk( 'la oportunidad usa pipelineStageId', array_key_exists( 'pipelineStageId', (array) $c2 ) );
chk( 'la oportunidad va como open', ( $c2['status'] ?? '' ) === 'open' );
chk( 'la oportunidad lleva el total', (int) ( $c2['monetaryValue'] ?? 0 ) === 250000 );
chk( 'la oportunidad enlaza el contacto', ( $c2['contactId'] ?? '' ) === 'C1' );
chk( 'con precios usa la etapa normal', ( $c2['pipelineStageId'] ?? '' ) === 'S2' );

$c3 = json_decode( $GLOBALS['ghl_calls'][2]['args']['body'] ?? '{}', true );
chk( 'la nota menciona el producto', strpos( $c3['body'] ?? '', 'MANÍ' ) !== false );
chk( 'la nota trae el NIT', strpos( $c3['body'] ?? '', '900123456' ) !== false );
chk( 'la nota va al contacto creado', strpos( $GLOBALS['ghl_calls'][2]['url'] ?? '', '/contacts/C1/notes' ) !== false );

// --- idempotencia: repetir no debe crear nada ---
$GLOBALS['ghl_calls'] = [];
$ok2 = Glotracol_Quote_GHL::send_quote( $qid );
chk( 'repetir devuelve true', $ok2 === true );
chk( 'repetir no hace ninguna llamada', count( $GLOBALS['ghl_calls'] ) === 0 );

// --- precios pendientes → la otra etapa ---
$qid3 = wp_insert_post( [ 'post_type' => 'glo_quote', 'post_status' => 'glo-new', 'post_title' => 'TEST GHL pendiente' ], true );
update_post_meta( $qid3, '_glo_customer_name', 'Carlos' );
update_post_meta( $qid3, '_glo_pricing_status', 'pending' );
update_post_meta( $qid3, '_glo_items', [ [ 'product_id' => 0, 'name' => 'AJONJOLÍ', 'quantity' => 1, 'precio_origen' => 'pendiente' ] ] );
$tres_ok();
Glotracol_Quote_GHL::send_quote( $qid3 );
$p2 = json_decode( $GLOBALS['ghl_calls'][1]['args']['body'] ?? '{}', true );
chk( 'sin precios usa la etapa de pendientes', ( $p2['pipelineStageId'] ?? '' ) === 'S1' );
chk( 'nombre de una sola palabra deja lastName vacío',
	( json_decode( $GLOBALS['ghl_calls'][0]['args']['body'] ?? '{}', true )['lastName'] ?? 'x' ) === '' );

// --- si falla la nota, la oportunidad ya creada se conserva y se reintenta solo la nota ---
$qid4 = wp_insert_post( [ 'post_type' => 'glo_quote', 'post_status' => 'glo-new', 'post_title' => 'TEST GHL nota' ], true );
update_post_meta( $qid4, '_glo_customer_name', 'Ana Pérez' );
update_post_meta( $qid4, '_glo_pricing_status', 'priced' );
$GLOBALS['ghl_calls'] = [];
$GLOBALS['ghl_queue'] = [
	[ 'code' => 201, 'body' => '{"contact":{"id":"C9"}}' ],
	[ 'code' => 201, 'body' => '{"opportunity":{"id":"O9"}}' ],
	[ 'code' => 500, 'body' => '{"message":"nota caída"}' ],
];
$rn = Glotracol_Quote_GHL::send_quote( $qid4 );
chk( 'la nota caída no tumba el envío', $rn === true );
chk( 'la oportunidad quedó guardada pese a la nota', get_post_meta( $qid4, '_glo_ghl_opportunity_id', true ) === 'O9' );
chk( 'la nota no se marca como hecha', get_post_meta( $qid4, '_glo_ghl_note_ok', true ) === '' );
$GLOBALS['ghl_calls'] = [];
$GLOBALS['ghl_queue'] = [ [ 'code' => 201, 'body' => '{"note":{"id":"N9"}}' ] ];
Glotracol_Quote_GHL::send_quote( $qid4 );
chk( 'al reintentar solo se reenvía la nota', count( $GLOBALS['ghl_calls'] ) === 1 );

// --- un fallo al crear el contacto no deja basura ---
$qid5 = wp_insert_post( [ 'post_type' => 'glo_quote', 'post_status' => 'glo-new', 'post_title' => 'TEST GHL fallo' ], true );
update_post_meta( $qid5, '_glo_customer_name', 'Luis' );
$GLOBALS['ghl_calls'] = [];
$GLOBALS['ghl_queue'] = [ [ 'code' => 500, 'body' => '{"message":"caído"}' ] ];
$rf = Glotracol_Quote_GHL::send_quote( $qid5 );
chk( 'si falla el contacto devuelve WP_Error', is_wp_error( $rf ) );
chk( 'si falla el contacto no crea la oportunidad', count( $GLOBALS['ghl_calls'] ) === 1 );
chk( 'si falla el contacto no guarda ningún id', get_post_meta( $qid5, '_glo_ghl_contact_id', true ) === '' );

// --- sin configurar no se llama a nada ---
$qid2 = wp_insert_post( [ 'post_type' => 'glo_quote', 'post_status' => 'glo-new', 'post_title' => 'TEST GHL 2' ], true );
add_filter( 'glotracol_quote_setting', function( $v, $k ) { return $k === 'ghl_token' ? '' : $v; }, 99, 2 );
$GLOBALS['ghl_calls'] = [];
$GLOBALS['ghl_queue'] = [];
$r = Glotracol_Quote_GHL::send_quote( $qid2 );
chk( 'sin token no hace llamadas', count( $GLOBALS['ghl_calls'] ) === 0 );
chk( 'sin token devuelve WP_Error', is_wp_error( $r ) );
chk( 'sin token el error es de configuración', is_wp_error( $r ) && $r->get_error_code() === 'ghl_config' );

foreach ( [ $qid, $qid2, $qid3, $qid4, $qid5 ] as $borrar ) {
	if ( ! is_wp_error( $borrar ) && $borrar ) {
		// Los envíos simulados dejan cron programado; sin esto quedan reintentos huérfanos
		// que luego fallan de verdad y ensucian el registro del sitio.
		wp_clear_scheduled_hook( Glotracol_Quote_GHL::HOOK, [ (int) $borrar ] );
		wp_clear_scheduled_hook( 'glotracol_quote_webhook_dispatch', [ (int) $borrar ] );
		wp_delete_post( $borrar, true );
	}
}

// El registro del sitio queda como estaba: este test no debe aparecer en Registros.
if ( false !== $GLOBALS['gloq_log_orig'] ) update_option( 'glotracol_quote_log', $GLOBALS['gloq_log_orig'], false );
else delete_option( 'glotracol_quote_log' );
chk( 'el test no deja rastro en el registro del sitio', get_option( 'glotracol_quote_log' ) === $GLOBALS['gloq_log_orig'] );

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
