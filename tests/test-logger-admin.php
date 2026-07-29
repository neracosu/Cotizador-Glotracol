<?php
/**
 * wp eval-file tests/test-logger-admin.php
 *
 * La pantalla de Registros: el botón "Vaciar log" tiene que ser un formulario propio.
 * Estuvo anidado dentro del formulario de filtros, y el navegador lo descartaba: al pulsarlo
 * solo se recargaba la página con los filtros aplicados y el log seguía intacto.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}

$ORIG_LOG = get_option( 'glotracol_quote_log' );

// Algo que mostrar en la pantalla.
Glotracol_Quote_Logger::info( 'test', 'entrada de prueba para el render' );

$admin = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
if ( ! empty( $admin ) ) wp_set_current_user( $admin[0]->ID );

$A = new Glotracol_Quote_Logger_Admin();
ob_start();
$A->render();
$html = ob_get_clean();

chk( 'la pantalla pinta el botón de vaciar', strpos( $html, 'value="Vaciar log"' ) !== false );
chk( 'el botón manda la acción gloq_logs_clear', strpos( $html, 'value="gloq_logs_clear"' ) !== false );
chk( 'el botón lleva nonce', preg_match( '/name="_wpnonce"/', $html ) === 1 );

// Ningún <form> puede abrirse antes de que se cierre el anterior.
$profundidad = 0;
$max = 0;
preg_match_all( '/<\/?form\b/i', $html, $mm );
foreach ( $mm[0] as $tag ) {
	$profundidad += ( stripos( $tag, '</' ) === 0 ) ? -1 : 1;
	$max = max( $max, $profundidad );
}
chk( 'no hay formularios anidados en la pantalla (max profundidad 1, hubo ' . $max . ')', $max <= 1 );
chk( 'los formularios abren y cierran parejos', $profundidad === 0 );

// El botón de vaciar tiene que quedar FUERA del formulario de filtros (que es GET:
// dentro de él, el submit nunca llegaría a admin-post.php).
$pos_cierre_filtros = strpos( $html, '</form>' );
$pos_boton_vaciar   = strpos( $html, 'value="gloq_logs_clear"' );
chk( 'el botón de vaciar va después de cerrarse el formulario de filtros',
	$pos_cierre_filtros !== false && $pos_boton_vaciar !== false && $pos_boton_vaciar > $pos_cierre_filtros );

// Y el vaciado en sí funciona.
Glotracol_Quote_Logger::info( 'test', 'otra entrada' );
chk( 'hay entradas antes de vaciar', count( (array) get_option( 'glotracol_quote_log', [] ) ) > 0 );
Glotracol_Quote_Logger::clear();
chk( 'clear() deja el log vacío', get_option( 'glotracol_quote_log', [] ) === [] );

if ( false !== $ORIG_LOG ) update_option( 'glotracol_quote_log', $ORIG_LOG, false );
else delete_option( 'glotracol_quote_log' );
chk( 'log del sitio restaurado', get_option( 'glotracol_quote_log' ) === $ORIG_LOG );

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
