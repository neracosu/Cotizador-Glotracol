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

// --- La copia sobrante es MAS NUEVA que la que corre (glotracol.com, 2026-09-25: la
// 2.16.0 cargaba primero y el aviso mandaba borrar la 2.17.1). El aviso debe comparar
// versiones y mandar borrar la vieja, que es la que corre.
exec( 'sed -i ' . escapeshellarg( 's/^ \* Version: .*/ * Version: 9.9.9/' ) . ' ' . escapeshellarg( $DUP . '/glotracol-quote.php' ) );
chk( 'la copia de prueba quedó en 9.9.9', strpos( file_get_contents( $DUP . '/glotracol-quote.php' ), 'Version: 9.9.9' ) !== false );
add_filter( 'option_active_plugins', function ( $v ) {
	$v = is_array( $v ) ? $v : [];
	$v[] = 'Cotizador-Glotracol-copia-prueba/glotracol-quote.php';
	return array_values( array_unique( $v ) );
} );
if ( method_exists( 'Glotracol_Quote_Plugin', 'copy_version' ) ) {
	chk( 'lee la versión de la otra copia', Glotracol_Quote_Plugin::copy_version( 'Cotizador-Glotracol-copia-prueba/glotracol-quote.php' ) === '9.9.9' );
} else {
	chk( 'existe Glotracol_Quote_Plugin::copy_version()', false );
}
ob_start(); Glotracol_Quote_Plugin::duplicate_notice(); $html = ob_get_clean();
chk( 'con la otra copia más nueva, manda borrar la carpeta que corre', strpos( $html, 'Sobra <code>' . basename( $SRC ) . '</code>' ) !== false );
chk( 'con la otra copia más nueva, NO manda borrar la nueva', strpos( $html, 'Sobra <code>Cotizador-Glotracol-copia-prueba' ) === false );
chk( 'dice la versión nueva y dónde está', strpos( $html, '9.9.9' ) !== false && strpos( $html, 'Cotizador-Glotracol-copia-prueba' ) !== false );
chk( 'dice que la nueva pasará a correr al borrar la vieja', mb_stripos( $html, 'pasa a correr' ) !== false );
chk( 'sin "Borrar datos al desinstalar" no avisa de eso', mb_stripos( $html, 'Borrar datos al desinstalar' ) === false );

// Con "Borrar datos al desinstalar" marcado, borrar cualquier copia ejecuta su uninstall.php
// y se lleva TODAS las cotizaciones: el aviso tiene que decir que se desmarque antes.
$settings_antes = get_option( 'glotracol_quote_settings', [] );
register_shutdown_function( function () use ( $settings_antes ) { update_option( 'glotracol_quote_settings', $settings_antes ); } );
$s = is_array( $settings_antes ) ? $settings_antes : [];
$s['delete_data_on_uninstall'] = 'yes';
update_option( 'glotracol_quote_settings', $s );
ob_start(); Glotracol_Quote_Plugin::duplicate_notice(); $html = ob_get_clean();
chk( 'con "Borrar datos al desinstalar" marcado, avisa que se desmarque antes de borrar', mb_stripos( $html, 'Borrar datos al desinstalar' ) !== false && mb_stripos( $html, 'desmar' ) !== false );
update_option( 'glotracol_quote_settings', $settings_antes );
remove_all_filters( 'option_active_plugins' );

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
