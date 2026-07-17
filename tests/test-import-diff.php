<?php
/** wp eval-file tests/test-import-diff.php — verifica el motor de cotejo. */
$GLOBALS['fail'] = 0;
function chk( $l, $g, $e ) {
    $ok = ( $g === $e ); if ( ! $ok ) $GLOBALS['fail']++;
    echo ( $ok ? '[OK]   ' : '[FAIL] ' ) . "$l => got=" . var_export($g,true) . " exp=" . var_export($e,true) . "\n";
}

// Producto de prueba con precio A actual = 1000, presentacion vacía.
$pid = wp_insert_post( [ 'post_type'=>'product', 'post_status'=>'publish', 'post_title'=>'DIFF TEST '.uniqid() ] );
update_post_meta( $pid, '_glo_price', 1000 );

// Fila que sube el precio y agrega presentacion.
$rows = [ [ '__line'=>2, 'id'=>$pid, 'precio normal'=>'1500', 'presentacion'=>'Saco 25 kg', 'empaque'=>'' ] ];
$d = Glotracol_Quote_Import_Diff::build( 'precios_catalogo', $rows, [ 'mode'=>'publico' ] );

$r0 = $d['rows'][0];
chk( 'status change', $r0['status'], 'change' );
chk( 'precio current', $r0['fields']['precio']['current'], '1000' );
chk( 'precio incoming', $r0['fields']['precio']['incoming'], '1500' );
chk( 'precio state', $r0['fields']['precio']['state'], 'change' );
chk( 'presentacion fill', $r0['fields']['presentacion']['state'], 'fill' );
chk( 'empaque skip (vacío no toca)', $r0['fields']['empaque']['state'], 'skip' );
chk( 'summary change=1', $d['summary']['change'], 1 );

// Fila idéntica al actual → same.
update_post_meta( $pid, '_glo_price', 1500 );
update_post_meta( $pid, '_glo_presentacion_texto', 'Saco 25 kg' );
$d2 = Glotracol_Quote_Import_Diff::build( 'precios_catalogo', $rows, [ 'mode'=>'publico' ] );
chk( 'status same', $d2['rows'][0]['status'], 'same' );

// Fila con ID inexistente → unmatched.
$d3 = Glotracol_Quote_Import_Diff::build( 'precios_catalogo', [ [ '__line'=>3, 'id'=>99999999, 'precio normal'=>'500' ] ], [ 'mode'=>'publico' ] );
chk( 'unmatched', $d3['rows'][0]['status'], 'unmatched' );

wp_delete_post( $pid, true );
echo "\nfails=" . $GLOBALS['fail'] . "\n";
