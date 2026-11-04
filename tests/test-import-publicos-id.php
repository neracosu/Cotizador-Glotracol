<?php
/**
 * wp eval-file tests/test-import-publicos-id.php
 * Lista A rapida con encabezado "ID" (no "sku"): el lector debe pasar el ID como
 * referencia y el cotejo debe encontrar el producto. Antes la columna se descartaba
 * y todas las filas salian "Sin coincidencia".
 */
$GLOBALS['gloq_fails'] = 0;
function chk_pid( $label, $ok ) { if ( ! $ok ) $GLOBALS['gloq_fails']++; echo ( $ok ? '[OK] ' : '[FAIL] ' ) . $label . "\n"; }

$ids = wc_get_products( [ 'limit' => 2, 'return' => 'ids', 'status' => 'publish' ] );
if ( count( $ids ) < 2 ) { echo "No hay productos.\n"; return; }
list( $p1, $p2 ) = array_map( 'intval', $ids );

$tmp = wp_tempnam( 'gloq-id' );
foreach ( [ 'ID', 'Código', 'id producto' ] as $h ) {
	file_put_contents( $tmp, "$h,Precio\n$p1,15000\n$p2,\"$ 22.000\"\n" );
	$r = Glotracol_Quote_Import_Reader::read( $tmp, 'precios_publicos' );
	chk_pid( "\"$h\": se mapea como referencia", isset( $r['mapping']['sku'] ) );
	chk_pid( "\"$h\": la fila trae el ID", ( $r['rows'][0]['sku'] ?? '' ) === (string) $p1 );
	$d = Glotracol_Quote_Import_Diff::build( 'precios_publicos', $r['rows'] );
	chk_pid( "\"$h\": cero filas sin coincidencia (fue {$d['summary']['unmatched']})", $d['summary']['unmatched'] === 0 );
	chk_pid( "\"$h\": resuelve al producto correcto", ( $d['rows'][0]['product']['id'] ?? 0 ) === $p1 );
}

// Si vienen las dos columnas, manda SKU.
file_put_contents( $tmp, "ID,SKU,Precio\n$p1,ABC,15000\n" );
$r = Glotracol_Quote_Import_Reader::read( $tmp, 'precios_publicos' );
chk_pid( 'con ID y SKU, la referencia es la columna SKU', ( $r['mapping']['sku'] ?? '' ) === 'sku' );

// La deteccion automatica no cambia: "ID, Precio" sigue siendo catalogo.
file_put_contents( $tmp, "ID,Precio\n$p1,15000\n" );
$r = Glotracol_Quote_Import_Reader::read( $tmp );
chk_pid( 'deteccion de "ID, Precio" sigue en precios_catalogo (fue ' . $r['detected_type'] . ')', $r['detected_type'] === 'precios_catalogo' );

@unlink( $tmp );
echo $GLOBALS['gloq_fails'] ? "FALLAS: {$GLOBALS['gloq_fails']}\n" : "todo OK\n";
