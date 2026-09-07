<?php
/**
 * wp eval-file tests/test-duplicado.php
 *
 * Copias duplicadas del plugin (cada ZIP subido a mano crea una carpeta nueva y
 * el cliente llegó a tener tres activas). La segunda copia no debe cargar nada
 * ni registrar hooks, y el admin debe ver un aviso con la carpeta sobrante.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}

$SRC  = untrailingslashit( GLOTRACOL_QUOTE_PATH );
$DUP  = WP_PLUGIN_DIR . '/Cotizador-Glotracol-copia-prueba';
$USER = get_current_user_id();
register_shutdown_function( function () use ( $DUP, $USER ) {
	if ( is_dir( $DUP ) ) exec( 'rm -rf ' . escapeshellarg( $DUP ) );
	wp_set_current_user( $USER );
} );

// Copia real del plugin en otra carpeta, como la deja "Subir plugin" con el ZIP de GitHub.
exec( 'cp -r ' . escapeshellarg( $SRC ) . ' ' . escapeshellarg( $DUP ) );
chk( 'se creó la copia de prueba', file_exists( $DUP . '/glotracol-quote.php' ) );

// La copia figura como activa, después de la que corre.
add_filter( 'option_active_plugins', function ( $v ) {
	$v = is_array( $v ) ? $v : [];
	$v[] = 'Cotizador-Glotracol-copia-prueba/glotracol-quote.php';
	return array_values( array_unique( $v ) );
} );

$hooks_antes = count( $GLOBALS['wp_filter']['pre_set_site_transient_update_plugins']->callbacks[10] ?? [] );
$path_antes  = GLOTRACOL_QUOTE_PATH;

ob_start();
include $DUP . '/glotracol-quote.php';
$salida = ob_get_clean();

chk( 'la segunda copia carga sin fatal', true );
chk( 'la segunda copia no imprime nada', trim( $salida ) === '' );
chk( 'las constantes siguen apuntando a la copia que corre', GLOTRACOL_QUOTE_PATH === $path_antes );
$hooks_despues = count( $GLOBALS['wp_filter']['pre_set_site_transient_update_plugins']->callbacks[10] ?? [] );
chk( 'la segunda copia no registra otro updater', $hooks_despues === $hooks_antes );

// Aviso en el admin.
chk( 'existe Glotracol_Quote_Plugin::duplicate_copies()', method_exists( 'Glotracol_Quote_Plugin', 'duplicate_copies' ) );
if ( method_exists( 'Glotracol_Quote_Plugin', 'duplicate_copies' ) ) {
	$dups = Glotracol_Quote_Plugin::duplicate_copies();
	chk( 'detecta la carpeta sobrante', $dups === [ 'Cotizador-Glotracol-copia-prueba/glotracol-quote.php' ] );

	$admin = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
	if ( $admin ) {
		wp_set_current_user( (int) $admin[0] );
		ob_start(); Glotracol_Quote_Plugin::duplicate_notice(); $html = ob_get_clean();
		chk( 'el admin ve un aviso rojo', strpos( $html, 'notice-error' ) !== false );
		chk( 'el aviso nombra la carpeta sobrante', strpos( $html, 'Cotizador-Glotracol-copia-prueba' ) !== false );
		chk( 'el aviso nombra la carpeta que sí corre', strpos( $html, basename( $SRC ) ) !== false );
		chk( 'el aviso dice qué hacer (desactivar y borrar)', mb_stripos( $html, 'desactív' ) !== false && mb_stripos( $html, 'bórr' ) !== false );
		chk( 'el aviso enlaza a la lista de plugins', strpos( $html, 'plugins.php' ) !== false );

		remove_all_filters( 'option_active_plugins' );
		ob_start(); Glotracol_Quote_Plugin::duplicate_notice(); $html = ob_get_clean();
		chk( 'sin copias sobrantes no hay aviso', trim( $html ) === '' );
	}
	chk( 'el aviso está enganchado a admin_notices', has_action( 'admin_notices', [ 'Glotracol_Quote_Plugin', 'duplicate_notice' ] ) !== false );
}

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
