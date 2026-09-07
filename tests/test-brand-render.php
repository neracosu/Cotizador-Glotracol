<?php
/**
 * wp eval-file tests/test-brand-render.php
 *
 * Los correos y el PDF usan el color de marca y el logo configurados, y ya no
 * llevan el verde antiguo escrito a mano.
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
// Si una aserción revienta con fatal, los ajustes del sitio se restauran igual.
register_shutdown_function( function () use ( $OPT, $ORIG ) { if ( false !== $ORIG ) update_option( $OPT, $ORIG ); else delete_option( $OPT ); } );

$qid = (int) $GLOBALS['wpdb']->get_var( "SELECT ID FROM {$GLOBALS['wpdb']->posts} WHERE post_type='glo_quote' ORDER BY ID DESC LIMIT 1" );
chk( 'hay una cotización para probar', $qid > 0 );

$s = glotracol_quote_get_settings();
$s['brand_color'] = '#3355aa';
$s['appearance_inherit_elementor'] = 'no';
update_option( $OPT, $s );
$brand = glotracol_quote_brand();
$items = glotracol_quote_enrich_items( get_post_meta( $qid, '_glo_items', true ) ?: [] );
$customer = [
	'name' => 'Prueba Marca', 'company' => 'ACME', 'nit' => '900', 'city' => 'Bogotá',
	'email' => 'prueba@ejemplo.com', 'phone' => '3000000000', 'message' => '',
];
$vars = [
	'quote_id' => $qid, 'customer' => $customer, 'items' => $items, 'intro' => '', 'type' => 'quote',
	'total' => 1000, 'client_name' => '', 'weight_total' => 10,
	'meta' => [], 'edit_url' => '#', 'units_total' => 1, 'skus_count' => 1, 'client_id' => 0,
	'pricing_status' => 'priced', 'size_tag' => 'small', 'message' => '',
];

$cust = glotracol_quote_load_template( 'email-customer.php', $vars );
chk( 'correo cliente: usa el color de marca', strpos( $cust, '#3355aa' ) !== false );
chk( 'correo cliente: no queda el verde antiguo', strpos( $cust, '#0a4d3a' ) === false && strpos( $cust, '#13855e' ) === false );
chk( 'correo cliente: lleva el logo', $brand['logo_url'] === '' || strpos( $cust, esc_url( $brand['logo_url'] ) ) !== false );
chk( 'correo cliente: conserva el número de cotización', strpos( $cust, '#' . $qid ) !== false );
chk( 'correo cliente: conserva la nota de 7 días', strpos( $cust, 'válido por 7 días' ) !== false );

$adm = glotracol_quote_load_template( 'email-admin.php', $vars );
chk( 'correo equipo: usa el color de marca', strpos( $adm, '#3355aa' ) !== false );
chk( 'correo equipo: no queda el verde antiguo', strpos( $adm, '#0a4d3a' ) === false && strpos( $adm, '#13855e' ) === false );
$vars_large = array_merge( $vars, [ 'size_tag' => 'large' ] );
$adm_large = glotracol_quote_load_template( 'email-admin.php', $vars_large );
chk( 'correo equipo grande: conserva el rojo de alerta', strpos( $adm_large, '#dc3545' ) !== false );

$pdf = Glotracol_Quote_PDF::render( $qid );
chk( 'pdf: se genera', substr( $pdf, 0, 4 ) === '%PDF' );
chk( 'pdf: incrusta el logo como imagen', $brand['logo_path'] === '' || strpos( $pdf, '/Subtype /Image' ) !== false );
// FPDF escribe el color de relleno como "r g b rg" en el flujo; con compresion no se puede leer,
// asi que se comprueba con una cotizacion renderizada sin comprimir via el filtro de marca.
$rgb = $brand['rgb'];
$esperado = sprintf( '%.3F %.3F %.3F rg', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255 );
$pdf_plano = Glotracol_Quote_PDF::render( $qid, [ 'compress' => false ] );
chk( 'pdf: pinta con el color de marca', strpos( $pdf_plano, $esperado ) !== false );
chk( 'pdf: ya no pinta el verde antiguo', strpos( $pdf_plano, '0.039 0.302 0.227 rg' ) === false );

if ( false !== $ORIG ) update_option( $OPT, $ORIG ); else delete_option( $OPT );
chk( 'ajustes del sitio restaurados', get_option( $OPT ) === $ORIG );
echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
