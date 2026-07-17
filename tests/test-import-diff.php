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

// --- Lista B: coteja _glo_price_b y muestra Lista A como referencia ---
$pidb = wp_insert_post( [ 'post_type'=>'product', 'post_status'=>'publish', 'post_title'=>'DIFF B '.uniqid() ] );
update_post_meta( $pidb, '_glo_price', 1000 );    // Lista A
update_post_meta( $pidb, '_glo_price_b', 800 );   // Lista B actual
$db = Glotracol_Quote_Import_Diff::build( 'precios_lista_b', [ [ '__line'=>2, 'id'=>$pidb, 'precio normal'=>'750' ] ], [] );
chk( 'B precio current', $db['rows'][0]['fields']['precio']['current'], '800' );
chk( 'B precio incoming', $db['rows'][0]['fields']['precio']['incoming'], '750' );
chk( 'B precio change', $db['rows'][0]['fields']['precio']['state'], 'change' );
chk( 'B lista_a ref', $db['rows'][0]['fields']['lista_a']['state'], 'ref' );
chk( 'B lista_a current', $db['rows'][0]['fields']['lista_a']['current'], '1000' );

// --- Alerta global: catálogo donde casi todo baja (trampa Lista B) ---
$p1 = wp_insert_post( [ 'post_type'=>'product','post_status'=>'publish','post_title'=>'DROP1 '.uniqid() ] );
$p2 = wp_insert_post( [ 'post_type'=>'product','post_status'=>'publish','post_title'=>'DROP2 '.uniqid() ] );
update_post_meta( $p1, '_glo_price', 1000 ); update_post_meta( $p2, '_glo_price', 1000 );
$drop = Glotracol_Quote_Import_Diff::build( 'precios_catalogo', [
    [ '__line'=>2,'id'=>$p1,'precio normal'=>'700' ],
    [ '__line'=>3,'id'=>$p2,'precio normal'=>'720' ],
], [ 'mode'=>'publico' ] );
chk( 'alerta mostly_price_drop', in_array( 'mostly_price_drop', $drop['global_alerts'], true ), true );

wp_delete_post( $pidb, true ); wp_delete_post( $p1, true ); wp_delete_post( $p2, true );
echo "\nfails=" . $GLOBALS['fail'] . "\n";
