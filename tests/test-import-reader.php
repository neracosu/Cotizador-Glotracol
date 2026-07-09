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

// --- Task 4: auto-detección de tipo ---
$d = Glotracol_Quote_Import_Reader::detect_type( [ 'id', 'nombre', 'inventario', 'peso (kg)', 'precio normal', 'imágenes' ] );
chk( 'detecta precios_catalogo o lista_b', in_array( $d['type'], [ 'precios_catalogo', 'precios_lista_b' ], true ), true );
chk( 'catalogo vs lista_b es ambiguo', $d['ambiguous'], true ); // comparten id+precio
$d2 = Glotracol_Quote_Import_Reader::detect_type( [ 'nit', 'razon_social', 'email' ] );
chk( 'detecta clientes', $d2['type'], 'clientes' );
$d3 = Glotracol_Quote_Import_Reader::detect_type( [ 'foo', 'bar' ] );
chk( 'sin match => null', $d3['type'], null );
// El umbral >0 NO debe romper el caso real clientes_lista (required vacío).
$d4 = Glotracol_Quote_Import_Reader::detect_type( [ 'nit', 'lista' ] );
chk( 'detecta clientes_lista', $d4['type'], 'clientes_lista' );
// La ambigüedad catalogo/lista_b tiene a AMBOS como candidatos con score.
chk( 'ambos schemas puntúan', isset( $d['scores']['precios_catalogo'] ) && isset( $d['scores']['precios_lista_b'] ), true );

// --- Task 5: lector xlsx ---
$xlsx = GLOTRACOL_QUOTE_PATH . '../glotracol-quote/docs/CLIENTES FACTURA PRECIO DIFERENTE.xlsx';
if ( file_exists( $xlsx ) ) {
	$x = Glotracol_Quote_Import_Reader::read_xlsx( $xlsx );
	chk( 'xlsx sin error', $x['error'], null );
	chk( 'xlsx headers', $x['headers'], [ 'identificacion', 'nombre de la compañia', 'precio' ] );
	chk( 'xlsx ≥ 40 filas', count( $x['rows'] ) >= 40, true );
	chk( 'xlsx celda por referencia', $x['rows'][0]['identificacion'], '901882915' );
} else {
	echo "[SKIP] xlsx real no encontrado en $xlsx\n";
}

// Consistencia CSV/xlsx: un header con Ñ se minusculiza IGUAL en ambos lectores.
$tmpn = wp_tempnam( 'gloq-n' ) . '.csv';
file_put_contents( $tmpn, "NOMBRE DE LA COMPAÑIA,precio\nACME,100\n" );
$rn = Glotracol_Quote_Importer::read_delimited( $tmpn );
chk( 'CSV header Ñ -> ñ minúscula (consistente con xlsx)', $rn['headers'][0], 'nombre de la compañia' );
@unlink( $tmpn );

// --- Task 6: read() end-to-end ---
$csvA = GLOTRACOL_QUOTE_PATH . '../glotracol-quote/uploads/lista  a - Hoja 2 (1).csv';
if ( file_exists( $csvA ) ) {
	$r = Glotracol_Quote_Import_Reader::read( $csvA ); // sin forzar tipo
	chk( 'read sin error', $r['error'], null );
	chk( 'read detecta tipo con id+precio', in_array( $r['chosen_type'], [ 'precios_catalogo', 'precios_lista_b' ], true ), true );
	chk( 'read fila tiene clave id', isset( $r['rows'][0]['id'] ), true );
	chk( 'read normalizó precio a int-string', ctype_digit( (string) $r['rows'][0]['precio normal'] ) || $r['rows'][0]['precio normal'] === '', true );
} else { echo "[SKIP] CSV A real no encontrado\n"; }

// --- Task 7: candidatos ---
$cands = Glotracol_Quote_Import_Reader::suggest_candidates( 'MANI CON PIEL 70/80 SACO', 'product', 3 );
chk( 'sugiere al menos 1 candidato', count( $cands ) >= 1, true );
$labels = array_map( function ( $c ) { return $c['label']; }, $cands );
$hit = false; foreach ( $labels as $l ) { if ( stripos( $l, 'MANI CON PIEL 70/80' ) !== false ) $hit = true; }
chk( 'top candidato es MANI CON PIEL 70/80', $hit, true );
chk( 'score entre 0 y 100', ( $cands[0]['score'] >= 0 && $cands[0]['score'] <= 100 ), true );

echo $GLOBALS['gloq_fail'] === 0 ? "\nALL PASS\n" : "\n{$GLOBALS['gloq_fail']} FAILED\n";
