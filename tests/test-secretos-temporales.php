<?php
/**
 * wp eval-file tests/test-secretos-temporales.php
 *
 * El token de GoHighLevel y el secreto del webhook son de solo escritura (la pagina no
 * los reimprime y guardar vacio los conserva). El PDF temporal va en una carpeta privada
 * propia y se borra completo. Los archivos del importador no quedan dentro del sitio.
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
register_shutdown_function( function () use ( $OPT, $ORIG ) { if ( false !== $ORIG ) update_option( $OPT, $ORIG ); else delete_option( $OPT ); } );
$S = ( new ReflectionClass( 'Glotracol_Quote_Admin_Settings' ) )->newInstanceWithoutConstructor();

update_option( $OPT, array_merge( (array) $ORIG, [ 'ghl_token' => 'pit-SECRETO-1', 'webhook_secret' => 'whsec-SECRETO-2' ] ) );
add_filter( 'pre_http_request', function () { return new WP_Error( 'sin_red', 'test' ); } ); // la prueba de conexion no sale
$out = $S->sanitize( [ '__tab' => 'integrations', 'ghl_token' => '', 'webhook_secret' => '' ] );
chk( 'guardar con el token vacio lo conserva', ( $out['ghl_token'] ?? '' ) === 'pit-SECRETO-1' );
chk( 'guardar con el secreto vacio lo conserva', ( $out['webhook_secret'] ?? '' ) === 'whsec-SECRETO-2' );
$out = $S->sanitize( [ '__tab' => 'integrations', 'ghl_token' => 'pit-NUEVO' ] );
chk( 'un token nuevo reemplaza al anterior', ( $out['ghl_token'] ?? '' ) === 'pit-NUEVO' );

$render = ( new ReflectionClass( $S ) )->getMethod( 'render_tab' );
$render->setAccessible( true );
ob_start();
try { $render->invoke( $S, 'integrations', glotracol_quote_get_settings() ); } catch ( Throwable $e ) {}
$html = ob_get_clean();
chk( 'la pagina no imprime el token', strpos( $html, 'pit-SECRETO-1' ) === false );
chk( 'la pagina no imprime el secreto del webhook', strpos( $html, 'whsec-SECRETO-2' ) === false );

// PDF temporal.
$q = wp_insert_post( [ 'post_type' => 'glo_quote', 'post_status' => 'glo-new', 'post_title' => 'Test pdf temp' ] );
update_post_meta( $q, '_glo_qid', 'TESTPDF' );
update_post_meta( $q, '_glo_customer_name', 'Prueba' );
update_post_meta( $q, '_glo_items', [ [ 'product_id' => 0, 'name' => 'X', 'quantity' => 1, 'precio_unitario' => 1000, 'precio_subtotal' => 1000 ] ] );
$a = Glotracol_Quote_PDF::save_temp( $q );
$b = Glotracol_Quote_PDF::save_temp( $q );
chk( 'dos PDF de la misma cotizacion no comparten ruta', $a && $b && $a !== $b );
chk( 'el PDF queda con permisos privados', $a && ( fileperms( $a ) & 0077 ) === 0 );
chk( 'la carpeta del PDF es privada', $a && ( fileperms( dirname( $a ) ) & 0077 ) === 0 );
Glotracol_Quote_PDF::cleanup_temp( $a );
Glotracol_Quote_PDF::cleanup_temp( $b );
chk( 'cleanup borra archivo y carpeta', $a && ! file_exists( $a ) && ! file_exists( dirname( $a ) ) );
wp_delete_post( $q, true );

// Importador.
$dir = Glotracol_Quote_Importer_Admin::import_dir();
chk( 'la carpeta del importador no esta dentro del sitio', strpos( wp_normalize_path( $dir ), wp_normalize_path( ABSPATH ) ) !== 0 );
exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
