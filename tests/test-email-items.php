<?php
/** wp eval-file tests/test-email-items.php — helper de enriquecimiento de items para correos y PDF. */
$GLOBALS['gloq_fail'] = 0;
function chk( $label, $cond ) {
	echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
	if ( ! $cond ) $GLOBALS['gloq_fail']++;
}

// Producto real del catálogo que tenga precio, para no inventar fixtures.
$pid = (int) $GLOBALS['wpdb']->get_var(
	"SELECT post_id FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key='_glo_price' AND meta_value NOT IN ('','0') LIMIT 1"
);
chk( 'hay un producto con precio para probar', $pid > 0 );

// --- 1. item con precio ---
$r = glotracol_quote_enrich_items( [ [
	'product_id' => $pid, 'name' => 'X', 'quantity' => 3,
	'precio_unitario' => 1000, 'precio_subtotal' => 3000, 'precio_origen' => 'lista_a',
] ] );
chk( 'devuelve un item', count( $r ) === 1 );
chk( 'no marca pendiente si hay precio', $r[0]['es_pendiente'] === false );
chk( 'formatea el precio unitario', strpos( $r[0]['precio_unit_fmt'], '1.000' ) !== false );
chk( 'formatea el subtotal', strpos( $r[0]['precio_sub_fmt'], '3.000' ) !== false );
chk( 'conserva las claves originales', $r[0]['quantity'] === 3 && $r[0]['name'] === 'X' );

// --- 2. item sin precio ---
$r2 = glotracol_quote_enrich_items( [ [
	'product_id' => $pid, 'name' => 'Y', 'quantity' => 1,
	'precio_unitario' => null, 'precio_origen' => 'pendiente',
] ] );
chk( 'marca pendiente si no hay precio', $r2[0]['es_pendiente'] === true );
chk( 'muestra "A cotizar"', $r2[0]['precio_unit_fmt'] === 'A cotizar' );
chk( 'subtotal en raya', $r2[0]['precio_sub_fmt'] === '—' );

// --- 3. empaque vacío cae a raya (hoy 0 productos lo tienen cargado) ---
chk( 'empaque vacío cae a raya', $r[0]['empaque'] === '—' );

// --- 4. producto inexistente no revienta ---
$r3 = glotracol_quote_enrich_items( [ [ 'product_id' => 999999999, 'name' => 'Z', 'quantity' => 1 ] ] );
chk( 'producto inexistente no revienta', count( $r3 ) === 1 && $r3[0]['presentacion'] === '—' );
chk( 'producto inexistente marca pendiente', $r3[0]['es_pendiente'] === true );

// --- 5. peso viene del producto ---
chk( 'peso_kg es float o null', $r[0]['peso_kg'] === null || is_float( $r[0]['peso_kg'] ) );

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
