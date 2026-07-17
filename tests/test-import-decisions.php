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
    'gloq_include' => [ '2'=>'1', '4'=>'1' ],           // la 3 queda excluida
    'gloq_val'     => [ '2'=>[ 'precio'=>'1200' ] ],     // editar precio de la 2
    'gloq_resolve' => [ '4'=>'55' ],                     // asignar producto a la 4
];
$out = Glotracol_Quote_Importer_Admin::apply_row_decisions( $rows, $post );
chk( 'quedan 2 filas', count( $out ), 2 );
chk( 'linea 3 excluida', array_column( $out, '__line' ), [ 2, 4 ] );
chk( 'precio editado 2', $out[0]['precio normal'], '1200' );
chk( 'resolve aplicado 4', (int) $out[1]['id'], 55 );

// Sin cotejo (tipo viejo): no filtra nada.
$out2 = Glotracol_Quote_Importer_Admin::apply_row_decisions( $rows, [ 'gloq_resolve'=>[] ] );
chk( 'sin cotejo mantiene todas', count( $out2 ), 3 );

echo "\nfails=" . $GLOBALS['fail'] . "\n";
