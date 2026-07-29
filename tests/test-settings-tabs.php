<?php
/**
 * wp eval-file tests/test-settings-tabs.php
 *
 * El formulario de Ajustes solo envía los campos de la pestaña visible. Este test cubre que
 * guardar una pestaña NUNCA borre los ajustes de las demás, y que el mapa TAB_FIELDS siga
 * coincidiendo con lo que cada pestaña pinta de verdad.
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

$S = new Glotracol_Quote_Admin_Settings();
$render = ( new ReflectionClass( $S ) )->getMethod( 'render_tab' );
$render->setAccessible( true );

$mapa = Glotracol_Quote_Admin_Settings::TAB_FIELDS;
$tabs = array_keys( $mapa );

// Con pipelines en caché se pintan también los selects de etapa, que son condicionales.
set_transient( Glotracol_Quote_GHL::CACHE_KEY, [
	'P1' => [ 'name' => 'Pipeline', 'stages' => [ 'S1' => 'Etapa' ] ],
], HOUR_IN_SECONDS );

// ---------- 1. El mapa coincide con el formulario ----------
$pintados_todos = [];
foreach ( $tabs as $t ) {
	ob_start();
	$render->invoke( $S, $t, glotracol_quote_get_settings() );
	$html = ob_get_clean();
	preg_match_all( '/name="' . preg_quote( $OPT, '/' ) . '\[([a-z0-9_]+)\]/i', $html, $mm );
	$pintados = array_values( array_unique( array_diff( $mm[1], [ '__tab' ] ) ) );
	$pintados_todos = array_merge( $pintados_todos, $pintados );

	$fuera = array_diff( $pintados, $mapa[ $t ] );
	chk( "pestaña '$t': todo lo que pinta está en el mapa" . ( $fuera ? ' — falta: ' . implode( ', ', $fuera ) : '' ), empty( $fuera ) );

	$sobra = array_diff( $mapa[ $t ], $pintados );
	chk( "pestaña '$t': el mapa no inventa campos" . ( $sobra ? ' — sobra: ' . implode( ', ', $sobra ) : '' ), empty( $sobra ) );
}

// Ningún ajuste puede quedar fuera de todas las pestañas: sería imposible de cambiar.
$huerfanos = array_diff( array_keys( glotracol_quote_get_settings() ), array_unique( $pintados_todos ) );
chk( 'ningún ajuste queda sin pestaña' . ( $huerfanos ? ': ' . implode( ', ', $huerfanos ) : '' ), empty( $huerfanos ) );

// ---------- 2. Guardar una pestaña no toca las demás ----------
// Todos los ajustes con valores propios, distintos de los de fábrica.
$lleno = array_merge( glotracol_quote_get_settings(), [
	'destination_emails'          => 'ventas@ejemplo.com',
	'bcc_emails'                  => 'copia@ejemplo.com',
	'sender_name'                 => 'Glotracol Ventas',
	'sender_email'                => 'no-responder@ejemplo.com',
	'admin_subject'               => 'ASUNTO ADMIN',
	'admin_intro'                 => 'INTRO ADMIN',
	'customer_subject'            => 'ASUNTO CLIENTE',
	'customer_intro'              => 'INTRO CLIENTE',
	'form_intro'                  => 'INTRO FORM',
	'terms_text'                  => 'TERMINOS',
	'thanks_message'              => 'GRACIAS',
	'webhook_url'                 => 'https://ejemplo.com/hook',
	'webhook_secret'              => 'SECRETO',
	'webhook_format'              => 'gohighlevel',
	'ghl_enabled'                 => 'yes',
	'ghl_token'                   => 'pit-TOKEN',
	'ghl_location_id'             => 'LOC123',
	'ghl_pipeline_id'             => 'P1',
	'ghl_stage_id'                => 'S1',
	'ghl_stage_id_pending'        => 'S1',
	'rate_limit_per_hour'         => 9,
	'delete_data_on_uninstall'    => 'yes',
	'size_threshold_medium_units' => 30,
	'size_threshold_large_units'  => 90,
	'size_threshold_medium_skus'  => 6,
	'size_threshold_large_skus'   => 15,
	'weight_threshold_large_kg'   => 250,
	'weight_threshold_tons_kg'    => 1200,
	'large_alert_enabled'         => 'yes',
	'large_alert_email'           => 'alertas@ejemplo.com',
	'auto_respond_enabled'        => 'yes',
	'appearance_inherit_elementor'=> 'yes',
	'appearance_elementor_slot'   => 'secondary',
	'mini_cart_enabled'           => 'yes',
	'mini_cart_position'          => 'top-right',
	'smtp_enabled'                => 'yes',
	'smtp_host'                   => 'mail.ejemplo.com',
	'smtp_port'                   => '465',
	'smtp_encryption'             => 'ssl',
	'smtp_username'               => 'usuario@ejemplo.com',
	'smtp_password'               => 'CLAVE',
	'smtp_from_name'              => 'Glotracol',
	'smtp_from_email'             => 'envios@ejemplo.com',
] );
update_option( $OPT, $lleno );
$base = glotracol_quote_get_settings();

foreach ( $tabs as $t ) {
	// Guardar esa pestaña sin cambiar nada suyo: el navegador manda solo el __tab y sus campos.
	$out = $S->sanitize( [ '__tab' => $t ] );
	$rotos = [];
	foreach ( $base as $k => $v ) {
		if ( in_array( $k, $mapa[ $t ], true ) ) continue;
		if ( ( $out[ $k ] ?? null ) !== $v ) $rotos[] = $k;
	}
	chk( "guardar '$t' no borra ajustes de otras pestañas" . ( $rotos ? ': ' . implode( ', ', $rotos ) : '' ), empty( $rotos ) );
}

// ---------- 3. Cada pestaña sí guarda LO SUYO ----------
$cambios = [
	'general'      => [ 'sender_name' => 'Otro Nombre' ],
	'emails'       => [ 'admin_subject' => 'OTRO ASUNTO' ],
	'form'         => [ 'thanks_message' => 'OTRO GRACIAS' ],
	'smtp'         => [ 'smtp_host' => 'otro.servidor.com' ],
	'integrations' => [ 'ghl_location_id' => 'OTRA-LOC' ],
	'rules'        => [ 'weight_threshold_large_kg' => 333 ],
	'appearance'   => [ 'mini_cart_position' => 'bottom-right' ],
	'advanced'     => [ 'rate_limit_per_hour' => 7 ],
];
foreach ( $cambios as $t => $par ) {
	$k = key( $par );
	$out = $S->sanitize( array_merge( [ '__tab' => $t ], $par ) );
	chk( "pestaña '$t' sí guarda su propio campo ($k)", (string) $out[ $k ] === (string) $par[ $k ] );
}

// ---------- 4. Las casillas se pueden apagar desde su pestaña ----------
$out = $S->sanitize( [ '__tab' => 'appearance', 'mini_cart_position' => 'top-right' ] ); // sin la casilla marcada
chk( 'una casilla se puede desmarcar en su propia pestaña', $out['mini_cart_enabled'] === 'no' );
$out = $S->sanitize( [ '__tab' => 'smtp' ] );
chk( 'desmarcar en una pestaña no apaga casillas de otras', $out['mini_cart_enabled'] === 'yes' && $out['auto_respond_enabled'] === 'yes' );

// ---------- 5. El caso que lo destapó todo ----------
$out = $S->sanitize( [ '__tab' => 'smtp', 'smtp_host' => 'mail.nuevo.com' ] );
chk( 'guardar SMTP conserva el token de GoHighLevel', $out['ghl_token'] === 'pit-TOKEN' );
chk( 'guardar SMTP conserva la URL del webhook', $out['webhook_url'] === 'https://ejemplo.com/hook' );
$out = $S->sanitize( [ '__tab' => 'integrations', 'ghl_token' => 'pit-NUEVO' ] );
chk( 'guardar Integraciones conserva el servidor SMTP', $out['smtp_host'] === 'mail.ejemplo.com' );
chk( 'guardar Integraciones conserva los umbrales de reglas', (int) $out['weight_threshold_large_kg'] === 250 );

// ---------- 6. La contraseña SMTP se conserva si viene vacía ----------
$out = $S->sanitize( [ '__tab' => 'smtp', 'smtp_host' => 'mail.ejemplo.com', 'smtp_password' => '' ] );
chk( 'la contraseña SMTP no se pierde al reguardar', $out['smtp_password'] === 'CLAVE' );

delete_transient( Glotracol_Quote_GHL::CACHE_KEY );
if ( false !== $ORIG ) update_option( $OPT, $ORIG ); else delete_option( $OPT );
chk( 'ajustes del sitio restaurados', get_option( $OPT ) === $ORIG );

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
