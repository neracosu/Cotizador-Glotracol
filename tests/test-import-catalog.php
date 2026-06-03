<?php
/** wp eval-file tests/test-import-catalog.php — verifica import_catalog_prices (modo público). */
$ids = wc_get_products( [ 'limit' => 1, 'return' => 'ids', 'status' => 'publish' ] );
if ( ! $ids ) { echo "No hay productos.\n"; return; }
$pid = (int) $ids[0];
$prev = get_post_meta( $pid, '_glo_price', true );

$rows = [ [ 'id' => $pid, 'precio normal' => '999000', 'disponibilidad' => 'DISPONIBLE', '__line' => 2 ],
          [ 'id' => 99999999, 'precio normal' => '5000', '__line' => 3 ] ];
$rep = Glotracol_Quote_Importer::import_catalog_prices( $rows, [ 'mode' => 'publico', 'sync_stock' => false ] );
echo "Reporte: ins={$rep['inserted']} upd={$rep['updated']} skip={$rep['skipped']} err=" . count($rep['errors']) . "\n";
$got = (int) get_post_meta( $pid, '_glo_price', true );
echo ( $got === 999000 ? "[OK]" : "[FAIL]" ) . " _glo_price guardado = $got (esp 999000)\n";
echo ( $rep['skipped'] === 1 ? "[OK]" : "[FAIL]" ) . " 1 fila omitida (ID inexistente)\n";

if ( $prev !== '' && $prev !== null ) update_post_meta( $pid, '_glo_price', (int) $prev ); else delete_post_meta( $pid, '_glo_price' );
echo "listo\n";
