<?php
/**
 * wp eval-file tests/test-rate-limit.php
 *
 * Limite de envios: contador atomico por ventana de una hora, con varios alcances
 * (IP, correo destino, global) y conteo de intentos.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}

chk( 'existe Glotracol_Quote_Rate_Limit::hit()', method_exists( 'Glotracol_Quote_Rate_Limit', 'hit' ) );
chk( 'existe Glotracol_Quote_Rate_Limit::count()', method_exists( 'Glotracol_Quote_Rate_Limit', 'count' ) );
if ( ! method_exists( 'Glotracol_Quote_Rate_Limit', 'hit' ) ) exit( 1 );

$key = 'test-' . wp_generate_password( 8, false );

// hit() devuelve true mientras no se supere el maximo, e incrementa siempre.
chk( 'intento 1 de 3 pasa', Glotracol_Quote_Rate_Limit::hit( 'prueba', $key, 3 ) === true );
chk( 'intento 2 de 3 pasa', Glotracol_Quote_Rate_Limit::hit( 'prueba', $key, 3 ) === true );
chk( 'intento 3 de 3 pasa', Glotracol_Quote_Rate_Limit::hit( 'prueba', $key, 3 ) === true );
chk( 'intento 4 se bloquea', Glotracol_Quote_Rate_Limit::hit( 'prueba', $key, 3 ) === false );
chk( 'el contador cuenta los 4 intentos', Glotracol_Quote_Rate_Limit::count( 'prueba', $key ) === 4 );

// Alcances y claves independientes.
chk( 'otra clave empieza en cero', Glotracol_Quote_Rate_Limit::count( 'prueba', $key . 'x' ) === 0 );
chk( 'otro alcance empieza en cero', Glotracol_Quote_Rate_Limit::count( 'otro', $key ) === 0 );

// max <= 0 desactiva el limite.
chk( 'max 0 no limita', Glotracol_Quote_Rate_Limit::hit( 'prueba', $key, 0 ) === true );

// Atomicidad: el incremento es una sola sentencia SQL (no leer-y-escribir).
global $wpdb;
$name = Glotracol_Quote_Rate_Limit::option_name( 'prueba', $key );
$raw  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
chk( 'el valor en la base coincide con count()', $raw === Glotracol_Quote_Rate_Limit::count( 'prueba', $key ) );
chk( 'la fila no se autocarga', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $name ) ) !== 'yes' );

// Limpieza de ventanas viejas.
$viejo = 'gloq_rl_prueba_' . md5( $key ) . '_2000010100';
$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '9', 'no')", $viejo ) );
Glotracol_Quote_Rate_Limit::purge();
chk( 'purge() borra ventanas viejas', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $viejo ) ) === '0' );
chk( 'purge() conserva la ventana actual', Glotracol_Quote_Rate_Limit::count( 'prueba', $key ) === 4 ); // el intento con max 0 no cuenta

// Limpieza de lo creado por el test.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $name ) );

// El ajuste global existe con valor por defecto 30.
chk( 'ajuste rate_limit_global_per_hour por defecto 30', (int) glotracol_quote_get_setting( 'rate_limit_global_per_hour', -1 ) === 30 );

exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
