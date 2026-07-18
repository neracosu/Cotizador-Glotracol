<?php
/** wp eval-file tests/test-import-publicos.php — verifica que import_public_pricing escribe _glo_price por ID/SKU. */
$ids = wc_get_products( [ 'limit' => 1, 'return' => 'ids', 'status' => 'publish' ] );
if ( ! $ids ) { echo "No hay productos.\n"; return; }
$pid  = (int) $ids[0];
$prev = get_post_meta( $pid, '_glo_price', true );

// Fila con ID de producto real (como el archivo de Diana: col. sku = ID) + fila con ID inexistente.
$rows = [
	[ 'sku' => (string) $pid, 'precio' => '777000', '__line' => 2 ],
	[ 'sku' => '99999999',    'precio' => '5000',   '__line' => 3 ],
	[ 'sku' => '',            'precio' => '5000',   '__line' => 4 ],
];
$rep = Glotracol_Quote_Importer::import_public_pricing( $rows );
echo "Reporte: ins={$rep['inserted']} upd={$rep['updated']} skip={$rep['skipped']} err=" . count( $rep['errors'] ) . "\n";

$got = (int) get_post_meta( $pid, '_glo_price', true );
echo ( $got === 777000 ? "[OK]" : "[FAIL]" ) . " _glo_price guardado = $got (esp 777000)\n";
echo ( $rep['skipped'] === 2 ? "[OK]" : "[FAIL]" ) . " 2 filas omitidas (ID inexistente + sku vacío), fue {$rep['skipped']}\n";
$has_err = false;
foreach ( $rep['errors'] as $e ) { if ( strpos( $e, '99999999' ) !== false ) $has_err = true; }
echo ( $has_err ? "[OK]" : "[FAIL]" ) . " error claro para ID inexistente\n";

// Restaurar
if ( $prev !== '' && $prev !== null ) update_post_meta( $pid, '_glo_price', (int) $prev ); else delete_post_meta( $pid, '_glo_price' );
echo "listo\n";
