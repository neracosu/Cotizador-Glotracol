<?php
/**
 * wp eval-file tests/test-pricing-sku-id.php
 *
 * Precios negociados siempre asociados al producto correcto: por ID de producto, o por
 * 'sku:<SKU>' si es el SKU de una presentacion. Un valor que es a la vez ID de un
 * producto y SKU de otro es ambiguo y no se guarda. Guardar la ficha del cliente no
 * borra precios. La migracion convierte las claves SKU viejas.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}
$tag = strtoupper( wp_generate_password( 6, false, false ) );
$mkp = function ( $title, $sku = '' ) {
	$id = wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => $title ] );
	if ( $sku !== '' ) { $p = wc_get_product( $id ); $p->set_sku( $sku ); $p->save(); }
	return $id;
};
$p_alfa = $mkp( "Alfa $tag", "ABC-$tag" );
$p_id   = $mkp( "Por ID $tag" );
$p_amb  = $mkp( "Ambiguo $tag", (string) $p_id );   // su SKU es el ID de otro producto
$p_pres = $mkp( "Con pres $tag" );
update_post_meta( $p_pres, '_glo_presentaciones', [ [ 'idx' => 0, 'label' => 'Caja', 'sku' => "CJ-$tag", 'peso_g' => 0, 'precio_publico' => 0 ] ] );
wc_delete_product_transients();

// resolve_ref
$r = Glotracol_Quote_Importer::resolve_ref( "ABC-$tag" );
chk( 'SKU alfanumerico resuelve al ID del producto', $r['key'] === $p_alfa );
$r = Glotracol_Quote_Importer::resolve_ref( (string) $p_id );
chk( 'valor que es ID de un producto y SKU de otro: ambiguo', $r['key'] === null && $r['error'] !== '' );
$r = Glotracol_Quote_Importer::resolve_ref( "CJ-$tag" );
chk( 'SKU de presentacion resuelve a sku:', $r['key'] === "sku:CJ-$tag" );
$r = Glotracol_Quote_Importer::resolve_ref( "NOEXISTE-$tag" );
chk( 'referencia inexistente: error', $r['key'] === null && $r['error'] !== '' );
chk( 'resolve_product_id_by_ref no adivina en caso ambiguo', Glotracol_Quote_Importer::resolve_product_id_by_ref( (string) $p_id ) === 0 );

// import_b2b_pricing
$nit = '9' . wp_rand( 10000000, 99999999 );
$cid = wp_insert_post( [ 'post_type' => 'glo_client', 'post_status' => 'publish', 'post_title' => "Cliente sku $tag" ] );
update_post_meta( $cid, '_glo_client_nit', $nit );
update_post_meta( $cid, '_glo_client_active', 'yes' );
Glotracol_Quote_Client_CPT::rebuild_nit_index_for_post_id( $cid );
$rep = Glotracol_Quote_Importer::import_b2b_pricing( [
	[ '__line' => 2, 'nit' => $nit, 'sku' => "ABC-$tag", 'precio' => 1500 ],
	[ '__line' => 3, 'nit' => $nit, 'sku' => "CJ-$tag", 'precio' => 9000 ],
	[ '__line' => 4, 'nit' => $nit, 'sku' => (string) $p_id, 'precio' => 700 ],
	[ '__line' => 5, 'nit' => $nit, 'sku' => "NOEXISTE-$tag", 'precio' => 100 ],
] );
$pr = get_post_meta( $cid, '_glo_client_pricing', true );
chk( 'importa por ID de producto', ( $pr[ $p_alfa ] ?? 0 ) == 1500 );
chk( 'importa la presentacion como sku:', ( $pr[ "sku:CJ-$tag" ] ?? 0 ) == 9000 );
chk( 'no guarda la fila ambigua', ! isset( $pr[ $p_id ] ) && ! isset( $pr[ $p_amb ] ) );
chk( 'reporta las dos filas con problema', count( $rep['errors'] ) === 2 );

// Guardar la ficha no borra precios: la limpieza de filas conserva ID y sku:.
$rows = Glotracol_Quote_Client_Admin::sanitize_pricing_rows( [
	[ 'product_id' => (string) $p_alfa, 'price' => '1500' ],
	[ 'product_id' => "sku:CJ-$tag", 'price' => '9000' ],
	[ 'product_id' => "ABC-$tag", 'price' => '1600' ],
	[ 'product_id' => '', 'price' => '5' ],
] );
chk( 'la ficha conserva las claves sku:', ( $rows[ "sku:CJ-$tag" ] ?? 0 ) === 9000 );
chk( 'la ficha resuelve un SKU escrito a mano al ID', ( $rows[ $p_alfa ] ?? 0 ) === 1600 );
chk( 'la ficha descarta filas vacias', count( $rows ) === 2 );

// Migracion de claves viejas por SKU.
update_post_meta( $cid, '_glo_client_pricing', [ "ABC-$tag" => 1200, "CJ-$tag" => 8000 ] );
Glotracol_Quote_Upgrade::migrate_client_pricing( $cid );
$pr = get_post_meta( $cid, '_glo_client_pricing', true );
chk( 'migracion: SKU viejo pasa al ID', ( $pr[ $p_alfa ] ?? 0 ) == 1200 && ! isset( $pr[ "ABC-$tag" ] ) );
chk( 'migracion: SKU de presentacion pasa a sku:', ( $pr[ "sku:CJ-$tag" ] ?? 0 ) == 8000 );

// El resolvedor usa las claves nuevas.
$res = Glotracol_Quote_Pricing::resolve_items( [ [ 'product_id' => $p_alfa, 'quantity' => 1 ] ], $cid );
chk( 'precio negociado por ID aplicado', (int) $res['items'][0]['precio_unitario'] === 1200 );

foreach ( [ $p_alfa, $p_id, $p_amb, $p_pres, $cid ] as $id ) wp_delete_post( $id, true );
Glotracol_Quote_Client_CPT::rebuild_full_index();
exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
