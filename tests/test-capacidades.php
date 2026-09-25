<?php
/**
 * wp eval-file tests/test-capacidades.php
 *
 * Cotizaciones y clientes tienen capacidades propias: las ven administradores, gerentes
 * de tienda y editores (el equipo comercial), no autores ni colaboradores.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}
$pto = get_post_type_object( 'glo_quote' );
$pcl = get_post_type_object( 'glo_client' );
chk( 'glo_quote usa capacidad propia', $pto && $pto->cap->edit_posts === 'edit_glo_quotes' );
chk( 'glo_client usa capacidad propia', $pcl && $pcl->cap->edit_posts === 'edit_glo_clients' );

foreach ( [ 'administrator', 'editor', 'shop_manager' ] as $role ) {
	$r = get_role( $role );
	if ( ! $r ) { echo "[SKIP] no existe el rol $role\n"; continue; }
	chk( "$role edita cotizaciones de otros", $r->has_cap( 'edit_others_glo_quotes' ) );
	chk( "$role edita clientes", $r->has_cap( 'edit_glo_clients' ) );
}
foreach ( [ 'author', 'contributor', 'subscriber' ] as $role ) {
	$r = get_role( $role );
	if ( ! $r ) continue;
	chk( "$role NO ve cotizaciones", ! $r->has_cap( 'edit_glo_quotes' ) );
	chk( "$role NO ve clientes", ! $r->has_cap( 'edit_glo_clients' ) );
}

// A nivel de objeto, con un usuario autor de prueba.
$uid = wp_insert_user( [ 'user_login' => 'test_autor_' . wp_generate_password( 6, false, false ), 'user_pass' => wp_generate_password( 20 ), 'role' => 'author' ] );
$q   = wp_insert_post( [ 'post_type' => 'glo_quote', 'post_status' => 'glo-new', 'post_title' => 'Test caps' ] );
chk( 'un autor no puede abrir una cotizacion', ! user_can( $uid, 'edit_post', $q ) );
chk( 'un autor no ve el panel web', ! Glotracol_Quote_Frontend_Dashboard::can_view( $uid ) );
$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
chk( 'un administrador puede abrir la cotizacion', $admins && user_can( (int) $admins[0], 'edit_post', $q ) );
$eds = get_users( [ 'role' => 'editor', 'number' => 1, 'fields' => 'ID' ] );
if ( $eds ) chk( 'un editor ve el panel web', Glotracol_Quote_Frontend_Dashboard::can_view( (int) $eds[0] ) );
wp_delete_post( $q, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $uid );

exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
