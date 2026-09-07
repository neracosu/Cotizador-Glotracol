<?php
/**
 * wp eval-file tests/test-ghl-config.php
 *
 * Configuración de GoHighLevel: saneado del Location ID, diagnóstico de la
 * configuración, verificación del token al guardar y aviso en el panel.
 * Todo el HTTP se intercepta: nunca se llama a GoHighLevel de verdad.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}

$OPT  = 'glotracol_quote_settings';
$ORIG = get_option( $OPT );
$LOG  = get_option( 'glotracol_quote_log' );
register_shutdown_function( function () use ( $OPT, $ORIG, $LOG ) {
	if ( false !== $ORIG ) update_option( $OPT, $ORIG ); else delete_option( $OPT );
	if ( false !== $LOG ) update_option( 'glotracol_quote_log', $LOG, false );
	delete_transient( Glotracol_Quote_GHL::CACHE_KEY );
} );

// --- Arnés HTTP ---
$GLOBALS['ghl_calls'] = [];
$GLOBALS['ghl_reply'] = [ 'code' => 200, 'body' => '{}' ];
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$GLOBALS['ghl_calls'][] = [ 'url' => $url, 'args' => $args ];
	$r = $GLOBALS['ghl_reply'];
	return [ 'headers' => [], 'body' => $r['body'], 'response' => [ 'code' => $r['code'], 'message' => '' ], 'cookies' => [], 'filename' => null ];
}, 10, 3 );
$PIPES_JSON = '{"pipelines":[{"id":"P1","name":"Cotizaciones","stages":[{"id":"S1","name":"Nueva"},{"id":"S2","name":"Cotizada"}]}]}';

// Ajustes en memoria, controlables por prueba.
$GLOBALS['gloq_cfg'] = [];
add_filter( 'glotracol_quote_setting', function ( $val, $key ) {
	return array_key_exists( $key, $GLOBALS['gloq_cfg'] ) ? $GLOBALS['gloq_cfg'][ $key ] : $val;
}, 10, 2 );

// ---------- 1. Location ID pegado como URL ----------
chk( 'existe normalize_location_id()', method_exists( 'Glotracol_Quote_GHL', 'normalize_location_id' ) );
if ( method_exists( 'Glotracol_Quote_GHL', 'normalize_location_id' ) ) {
	$n = [ 'Glotracol_Quote_GHL', 'normalize_location_id' ];
	chk( 'URL v2 completa → solo el id', $n( 'https://app.gohighlevel.com/v2/location/ayZEW6l6b2JFT7uRTAEf' ) === 'ayZEW6l6b2JFT7uRTAEf' );
	chk( 'URL con ruta después del id → solo el id', $n( 'https://app.gohighlevel.com/v2/location/ayZEW6l6b2JFT7uRTAEf/settings/integrations?x=1' ) === 'ayZEW6l6b2JFT7uRTAEf' );
	chk( 'URL con barra final → solo el id', $n( 'https://app.gohighlevel.com/v2/location/ayZEW6l6b2JFT7uRTAEf/' ) === 'ayZEW6l6b2JFT7uRTAEf' );
	chk( 'un id limpio se conserva', $n( 'ayZEW6l6b2JFT7uRTAEf' ) === 'ayZEW6l6b2JFT7uRTAEf' );
	chk( 'recorta espacios', $n( '  ayZEW6l6b2JFT7uRTAEf ' ) === 'ayZEW6l6b2JFT7uRTAEf' );
	chk( 'vacío sigue vacío', $n( '' ) === '' );
}

// ---------- 2. Diagnóstico de configuración ----------
chk( 'existe config_problem()', method_exists( 'Glotracol_Quote_GHL', 'config_problem' ) );
if ( method_exists( 'Glotracol_Quote_GHL', 'config_problem' ) ) {
	$GLOBALS['gloq_cfg'] = [ 'ghl_enabled' => 'no', 'ghl_token' => '', 'ghl_location_id' => '', 'ghl_pipeline_id' => '', 'ghl_stage_id' => '' ];
	chk( 'integración apagada → sin problema', Glotracol_Quote_GHL::config_problem() === null );

	$GLOBALS['gloq_cfg']['ghl_enabled'] = 'yes';
	$p = Glotracol_Quote_GHL::config_problem();
	chk( 'activa sin token → avisa del token', is_string( $p ) && stripos( $p, 'token' ) !== false );

	$GLOBALS['gloq_cfg']['ghl_token'] = 'pit-x'; $GLOBALS['gloq_cfg']['ghl_location_id'] = 'LOC';
	$p = Glotracol_Quote_GHL::config_problem();
	chk( 'activa sin pipeline → avisa del pipeline', is_string( $p ) && stripos( $p, 'pipeline' ) !== false );

	$GLOBALS['gloq_cfg']['ghl_pipeline_id'] = 'P1';
	$p = Glotracol_Quote_GHL::config_problem();
	chk( 'activa sin etapa → avisa de la etapa', is_string( $p ) && stripos( $p, 'etapa' ) !== false );

	$GLOBALS['gloq_cfg']['ghl_stage_id'] = 'S2';
	chk( 'todo configurado → sin problema', Glotracol_Quote_GHL::config_problem() === null );
}

// ---------- 3. Verificación del token contra GoHighLevel ----------
chk( 'existe verify()', method_exists( 'Glotracol_Quote_GHL', 'verify' ) );
if ( method_exists( 'Glotracol_Quote_GHL', 'verify' ) ) {
	$GLOBALS['gloq_cfg'] = [ 'ghl_token' => 'pit-GUARDADO', 'ghl_location_id' => 'LOC-GUARDADO' ];
	delete_transient( Glotracol_Quote_GHL::CACHE_KEY );

	$GLOBALS['ghl_calls'] = []; $GLOBALS['ghl_reply'] = [ 'code' => 401, 'body' => '{"message":"Invalid token"}' ];
	$r = Glotracol_Quote_GHL::verify( 'pit-NUEVO', 'LOC-NUEVO' );
	chk( '401 → WP_Error ghl_auth', is_wp_error( $r ) && $r->get_error_code() === 'ghl_auth' );
	$auth = $GLOBALS['ghl_calls'][0]['args']['headers']['Authorization'] ?? '';
	chk( 'usa el token que se le pasa, no el guardado', $auth === 'Bearer pit-NUEVO' );
	chk( 'consulta el location que se le pasa', strpos( $GLOBALS['ghl_calls'][0]['url'] ?? '', 'locationId=LOC-NUEVO' ) !== false );
	chk( 'con token rechazado no queda caché de pipelines', get_transient( Glotracol_Quote_GHL::CACHE_KEY ) === false );

	$GLOBALS['ghl_calls'] = []; $GLOBALS['ghl_reply'] = [ 'code' => 200, 'body' => $PIPES_JSON ];
	$r = Glotracol_Quote_GHL::verify( 'pit-NUEVO', 'LOC-NUEVO' );
	chk( '200 → devuelve los pipelines normalizados', is_array( $r ) && isset( $r['P1']['stages']['S2'] ) );
	$cache = get_transient( Glotracol_Quote_GHL::CACHE_KEY );
	chk( 'con token aceptado deja los pipelines en caché para el desplegable', is_array( $cache ) && isset( $cache['P1'] ) );
	delete_transient( Glotracol_Quote_GHL::CACHE_KEY );
}

// ---------- 4. Guardar la pestaña Integraciones ----------
$GLOBALS['gloq_cfg'] = [];
$S = new Glotracol_Quote_Admin_Settings();
$base = glotracol_quote_get_settings();
function gloq_errs( $code ) {
	return array_values( array_filter( get_settings_errors(), function ( $e ) use ( $code ) { return $e['code'] === $code; } ) );
}
function gloq_reset_errs() { $GLOBALS['wp_settings_errors'] = []; }

// 4a. Location ID como URL se guarda limpio.
gloq_reset_errs(); $GLOBALS['ghl_calls'] = []; $GLOBALS['ghl_reply'] = [ 'code' => 200, 'body' => $PIPES_JSON ];
$out = $S->sanitize( array_merge( $base, [ '__tab' => 'integrations', 'ghl_enabled' => 'yes', 'ghl_token' => 'pit-OK',
	'ghl_location_id' => 'https://app.gohighlevel.com/v2/location/ayZEW6l6b2JFT7uRTAEf', 'ghl_pipeline_id' => '', 'ghl_stage_id' => '' ] ) );
chk( 'al guardar, el Location ID pegado como URL queda solo el id', ( $out['ghl_location_id'] ?? '' ) === 'ayZEW6l6b2JFT7uRTAEf' );
chk( 'al guardar con token se prueba contra GoHighLevel (1 llamada)', count( $GLOBALS['ghl_calls'] ) === 1 );
chk( 'la prueba usa el id limpio', strpos( $GLOBALS['ghl_calls'][0]['url'] ?? '', 'locationId=ayZEW6l6b2JFT7uRTAEf' ) !== false );
$ok = gloq_errs( 'gloq_ghl_token' );
chk( 'token aceptado → aviso de éxito en pantalla', ! empty( $ok ) && $ok[0]['type'] === 'success' && stripos( $ok[0]['message'], 'acept' ) !== false );
chk( 'el aviso de éxito dice cuántos pipelines encontró', ! empty( $ok ) && strpos( $ok[0]['message'], '1 pipeline' ) !== false );
$w = gloq_errs( 'gloq_ghl_pipeline' );
chk( 'token aceptado pero sin pipeline → aviso de que falta elegirlo', ! empty( $w ) && $w[0]['type'] === 'warning' && stripos( $w[0]['message'], 'pipeline' ) !== false );
chk( 'los pipelines quedan en caché para que el desplegable salga lleno al recargar', is_array( get_transient( Glotracol_Quote_GHL::CACHE_KEY ) ) );

// 4b. Token rechazado.
gloq_reset_errs(); $GLOBALS['ghl_calls'] = []; $GLOBALS['ghl_reply'] = [ 'code' => 401, 'body' => '{"message":"Invalid token"}' ];
$out = $S->sanitize( array_merge( $base, [ '__tab' => 'integrations', 'ghl_enabled' => 'yes', 'ghl_token' => 'pit-MALO', 'ghl_location_id' => 'LOC', 'ghl_pipeline_id' => 'P1', 'ghl_stage_id' => 'S2' ] ) );
$err = gloq_errs( 'gloq_ghl_token' );
chk( 'token rechazado → aviso de error en pantalla', ! empty( $err ) && $err[0]['type'] === 'error' && stripos( $err[0]['message'], 'rechaz' ) !== false );
chk( 'el token rechazado se guarda igual (para que el usuario lo vea y lo corrija)', ( $out['ghl_token'] ?? '' ) === 'pit-MALO' );
chk( 'con token rechazado no se avisa del pipeline', empty( gloq_errs( 'gloq_ghl_pipeline' ) ) );

// 4c. Sin token no se llama a nadie.
gloq_reset_errs(); $GLOBALS['ghl_calls'] = [];
$S->sanitize( array_merge( $base, [ '__tab' => 'integrations', 'ghl_enabled' => 'no', 'ghl_token' => '', 'ghl_location_id' => '' ] ) );
chk( 'sin token no hay llamada a GoHighLevel', count( $GLOBALS['ghl_calls'] ) === 0 );
chk( 'sin token no hay aviso del token', empty( gloq_errs( 'gloq_ghl_token' ) ) );

// 4d. Guardar otra pestaña no prueba nada.
gloq_reset_errs(); $GLOBALS['ghl_calls'] = [];
$S->sanitize( array_merge( $base, [ '__tab' => 'general', 'ghl_token' => 'pit-OK', 'ghl_location_id' => 'LOC' ] ) );
chk( 'guardar la pestaña General no llama a GoHighLevel', count( $GLOBALS['ghl_calls'] ) === 0 );

// ---------- 5. Los avisos se ven en la pantalla de Ajustes ----------
$admin = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
if ( $admin ) {
	wp_set_current_user( (int) $admin[0] );
	gloq_reset_errs();
	add_settings_error( 'glotracol_quote_settings', 'gloq_ghl_token', 'MENSAJE-DE-PRUEBA-GHL', 'error' );
	$_GET['tab'] = 'integrations';
	ob_start(); $S->render_page(); $html = ob_get_clean();
	chk( 'la pantalla de Ajustes imprime los avisos de guardado', strpos( $html, 'MENSAJE-DE-PRUEBA-GHL' ) !== false );
	gloq_reset_errs();
} else {
	echo "[SKIP] sin administrador para probar la pantalla\n";
}

// ---------- 6. Aviso en el panel cuando falta configuración ----------
chk( 'existe admin_notice()', method_exists( 'Glotracol_Quote_GHL', 'admin_notice' ) );
if ( method_exists( 'Glotracol_Quote_GHL', 'admin_notice' ) && $admin ) {
	$_GET['page'] = 'glotracol-quote-dashboard';
	$GLOBALS['gloq_cfg'] = [ 'ghl_enabled' => 'yes', 'ghl_token' => 'pit-x', 'ghl_location_id' => 'LOC', 'ghl_pipeline_id' => '', 'ghl_stage_id' => '' ];
	ob_start(); Glotracol_Quote_GHL::admin_notice(); $html = ob_get_clean();
	chk( 'integración activa sin pipeline → aviso amarillo en el panel', strpos( $html, 'notice-warning' ) !== false && stripos( $html, 'pipeline' ) !== false );
	chk( 'el aviso enlaza a la pestaña Integraciones', strpos( $html, 'tab=integrations' ) !== false );

	$GLOBALS['gloq_cfg']['ghl_pipeline_id'] = 'P1'; $GLOBALS['gloq_cfg']['ghl_stage_id'] = 'S2';
	ob_start(); Glotracol_Quote_GHL::admin_notice(); $html = ob_get_clean();
	chk( 'todo configurado → sin aviso', trim( $html ) === '' );

	$GLOBALS['gloq_cfg']['ghl_pipeline_id'] = '';
	$_GET['page'] = 'otro-plugin';
	ob_start(); Glotracol_Quote_GHL::admin_notice(); $html = ob_get_clean();
	chk( 'fuera de las pantallas del plugin no molesta', trim( $html ) === '' );
	unset( $_GET['page'] );

	// El "Estado de configuración" del Inicio tambien lo lista.
	$GLOBALS['gloq_cfg'] = [ 'ghl_enabled' => 'yes', 'ghl_token' => 'pit-x', 'ghl_location_id' => 'LOC', 'ghl_pipeline_id' => '', 'ghl_stage_id' => '' ];
	set_transient( Glotracol_Quote_GHL::CACHE_KEY, [], HOUR_IN_SECONDS ); // que el pintado no salga a la red
	ob_start(); ( new Glotracol_Quote_Admin_Dashboard() )->render(); $html = ob_get_clean();
	chk( 'el Inicio lista GoHighLevel en el estado de configuración', strpos( $html, 'GoHighLevel' ) !== false && stripos( $html, 'Falta elegir el pipeline' ) !== false );
	delete_transient( Glotracol_Quote_GHL::CACHE_KEY );
}

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
