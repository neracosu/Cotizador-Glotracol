<?php
/** wp eval-file tests/test-import-decisions.php — filtra/edita filas según decisiones del cotejo. */
$GLOBALS['fail'] = 0;
function chk( $l, $g, $e ) { $ok=($g===$e); if(!$ok)$GLOBALS['fail']++; echo ($ok?'[OK]   ':'[FAIL] ')."$l => got=".var_export($g,true)." exp=".var_export($e,true)."\n"; }

$rows = [
    [ '__line'=>2, 'id'=>10, 'precio normal'=>'1000' ],
    [ '__line'=>3, 'id'=>11, 'precio normal'=>'2000' ],
    [ '__line'=>4, 'id'=>0,  'precio normal'=>'3000', 'nombre'=>'X' ],
];
$post = [
    'gloq_cotejo'  => '1',
    'gloq_include' => [ '2'=>'1' ],                      // la 3 queda excluida; la 4 no trae include (unmatched resuelta)
    'gloq_val'     => [ '2'=>[ 'precio'=>'1200' ] ],     // editar precio de la 2
    'gloq_resolve' => [ '4'=>'55' ],                     // asignar producto a la 4
];
$out = Glotracol_Quote_Importer_Admin::apply_row_decisions( $rows, $post );
chk( 'quedan 2 filas', count( $out ), 2 );
chk( 'linea 3 excluida', array_column( $out, '__line' ), [ 2, 4 ] );
chk( 'precio editado 2', $out[0]['precio normal'], '1200' );
chk( 'precio editado en precio normal', $out[0]['precio normal'], '1200' );
chk( 'precio editado tambien en precio (Lista B)', $out[0]['precio'], '1200' );
chk( 'resolve aplicado 4', (int) $out[1]['id'], 55 );

// Sin cotejo (tipo viejo): no filtra nada.
$out2 = Glotracol_Quote_Importer_Admin::apply_row_decisions( $rows, [ 'gloq_resolve'=>[] ] );
chk( 'sin cotejo mantiene todas', count( $out2 ), 3 );


// --- Decisiones en un solo campo JSON (no se pierden filas por max_input_vars) ---
$json_post = [
    'gloq_cotejo'     => '1',
    'gloq_rows_total' => '3',
    'gloq_rows_seen'  => '3',
    'gloq_decisions'  => wp_json_encode( [ 'include' => [ '2'=>'1' ], 'val' => [ '2'=>[ 'precio'=>'1200' ] ], 'resolve' => [ '4'=>'55' ] ] ),
];
[ $exp, $err ] = Glotracol_Quote_Importer_Admin::expand_decisions( $json_post );
chk( 'JSON: sin error', $err, '' );
$out2 = Glotracol_Quote_Importer_Admin::apply_row_decisions( $rows, $exp );
chk( 'JSON: mismas filas que el formulario clasico', array_column( $out2, '__line' ), [ 2, 4 ] );
chk( 'JSON: precio editado', $out2[0]['precio normal'], '1200' );
[ , $err ] = Glotracol_Quote_Importer_Admin::expand_decisions( array_merge( $json_post, [ 'gloq_rows_seen' => '2' ] ) );
chk( 'JSON: si faltan filas, error', $err !== '', true );
$big = [ 'gloq_cotejo' => '1' ];
for ( $i = 0; $i < (int) ini_get( 'max_input_vars' ); $i++ ) $big['gloq_include'][ (string) $i ] = '1';
[ , $err ] = Glotracol_Quote_Importer_Admin::expand_decisions( $big );
chk( 'sin JSON y en el limite de max_input_vars: error', $err !== '', true );

echo "\nfails=" . $GLOBALS['fail'] . "\n";
