<?php
/** wp eval-file tests/test-import-reader.php — reader tolerante. */
// Contador en $GLOBALS: con `wp eval-file` el scope de tope de archivo es local,
// así que chk() y el resumen deben referirse al MISMO global explícito.
$GLOBALS['gloq_fail'] = 0;
function chk( $l, $g, $e ) { $ok = ( $g === $e ); if ( ! $ok ) $GLOBALS['gloq_fail']++; echo ( $ok ? '[OK]   ' : '[FAIL] ' ) . "$l => got=" . var_export($g,true) . " exp=" . var_export($e,true) . "\n"; }

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

// --- Task 3: sinónimos y mapeo ---
$schemas = Glotracol_Quote_Importer::get_schemas();
$cat = $schemas['precios_catalogo'];
// Archivo con 'Valor' en vez de 'Precio normal' y 'Código' en vez de 'ID':
$m = Glotracol_Quote_Import_Reader::map_headers_to_schema( [ 'código', 'nombre', 'valor', 'inventario' ], $cat );
chk( 'mapea código->id', $m['map']['id'], 'código' );
chk( 'mapea valor->precio normal', $m['map']['precio normal'], 'valor' );
chk( 'mapea inventario->disponibilidad', $m['map']['disponibilidad'], 'inventario' );
$cl = $schemas['clientes_lista'];
$m2 = Glotracol_Quote_Import_Reader::map_headers_to_schema( [ 'identificacion', 'nombre de la compañia', 'precio' ], $cl );
chk( 'mapea identificacion->nit', $m2['map']['nit'], 'identificacion' );
// Regresión: desempate precio/precio normal. En precios_catalogo (que tiene AMBAS),
// un header crudo 'precio' debe caer en 'precio normal' (gana por orden del schema),
// no dejar 'precio normal' sin mapear.
$mp = Glotracol_Quote_Import_Reader::map_headers_to_schema( [ 'id', 'precio' ], $cat );
chk( 'precio crudo -> precio normal', $mp['map']['precio normal'] ?? null, 'precio' );
// Cobertura del caso real: 'nombre de la compañia' -> razon_social en el schema 'clientes'.
$mc = Glotracol_Quote_Import_Reader::map_headers_to_schema( [ 'identificacion', 'nombre de la compañia', 'email' ], $schemas['clientes'] );
chk( 'mapea compañia->razon_social', $mc['map']['razon_social'] ?? null, 'nombre de la compañia' );
chk( 'mapea identificacion->nit (clientes)', $mc['map']['nit'] ?? null, 'identificacion' );

echo $GLOBALS['gloq_fail'] === 0 ? "\nTASK3 PASS\n" : "\n{$GLOBALS['gloq_fail']} FAILED\n";
