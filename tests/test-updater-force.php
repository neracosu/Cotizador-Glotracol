<?php
/**
 * wp eval-file tests/test-updater-force.php
 *
 * "Comprobar de nuevo" en Escritorio -> Actualizaciones (update-core.php?force-check=1)
 * borra la cache del actualizador para consultar GitHub en el momento. Antes la cache de
 * 6 h seguia ofreciendo la version anterior aunque ya hubiera un tag nuevo.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}
$KEY  = Glotracol_Quote_Updater::CACHE_KEY;
$orig = get_transient( $KEY );
$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );

set_transient( $KEY, [ 'version' => '0.0.1' ], HOUR_IN_SECONDS );
$_GET = [];
wp_set_current_user( (int) $admins[0] );
do_action( 'load-update-core.php' );
chk( 'sin force-check la cache se conserva', is_array( get_transient( $KEY ) ) );

$_GET['force-check'] = '1';
wp_set_current_user( 0 );
do_action( 'load-update-core.php' );
chk( 'un usuario sin permisos no borra la cache', is_array( get_transient( $KEY ) ) );

wp_set_current_user( (int) $admins[0] );
do_action( 'load-update-core.php' );
chk( 'Comprobar de nuevo borra la cache del actualizador', get_transient( $KEY ) === false );

$_GET = [];
if ( false !== $orig ) set_transient( $KEY, $orig, HOUR_IN_SECONDS ); else delete_transient( $KEY );
exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
