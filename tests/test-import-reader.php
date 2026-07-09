<?php
/** wp eval-file tests/test-import-reader.php — reader tolerante. */
$fail = 0;
function chk( $l, $g, $e ) { global $fail; $ok = ( $g === $e ); if ( ! $ok ) $fail++; echo ( $ok ? '[OK]   ' : '[FAIL] ' ) . "$l => got=" . var_export($g,true) . " exp=" . var_export($e,true) . "\n"; }

$tmp = wp_tempnam( 'gloq-test' ) . '.csv';
file_put_contents( $tmp, "Valor,Nombre\n123,ARROZ\n,SIN PRECIO\n" );

// read_delimited NO valida schema: lee headers crudos aunque no sean de ningún tipo.
$r = Glotracol_Quote_Importer::read_delimited( $tmp );
chk( 'delimited sin error', $r['error'], null );
chk( 'delimited headers', $r['headers'], [ 'valor', 'nombre' ] );
chk( 'delimited fila 1 valor', $r['rows'][0]['valor'], '123' );
chk( 'delimited __line', $r['rows'][0]['__line'], 2 );
chk( 'delimited nº filas', count( $r['rows'] ), 2 );

// parse_csv (sin cambios de comportamiento) sigue validando el schema:
$tmp2 = wp_tempnam( 'gloq-test2' ) . '.csv';
file_put_contents( $tmp2, "sku,precio\nALM-250,15000\n" );
$p = Glotracol_Quote_Importer::parse_csv( $tmp2, 'precios_publicos' );
chk( 'parse_csv válido sin error', $p['error'], null );
chk( 'parse_csv fila', $p['rows'][0]['precio'], '15000' );
$p2 = Glotracol_Quote_Importer::parse_csv( $tmp, 'precios_publicos' ); // faltan sku/precio
chk( 'parse_csv detecta headers faltantes', ( strpos( (string) $p2['error'], 'Faltan columnas' ) !== false ), true );

@unlink( $tmp ); @unlink( $tmp2 );

// --- Task 2: normalizadores ---
chk( 'norm_price $', Glotracol_Quote_Import_Reader::norm_price( '$ 805.392 COP' ), 805392 );
chk( 'norm_price vacío', Glotracol_Quote_Import_Reader::norm_price( '' ), 0 );
chk( 'norm_weight coma', Glotracol_Quote_Import_Reader::norm_weight( '22,68' ), '22.68' );
chk( 'norm_weight vacío', Glotracol_Quote_Import_Reader::norm_weight( '' ), '' );
chk( 'norm_text espacios', Glotracol_Quote_Import_Reader::norm_text( "  ARROZ   BLANCO \n" ), 'ARROZ BLANCO' );

echo $fail === 0 ? "\nTASK2 PASS\n" : "\n$fail FAILED\n";
