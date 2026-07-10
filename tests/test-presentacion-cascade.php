<?php
// wp eval-file tests/test-presentacion-cascade.php --allow-root
$GLOBALS['gloq_fail'] = 0;
function chk( $label, $got, $exp ) {
	$ok = ( $got === $exp );
	if ( ! $ok ) $GLOBALS['gloq_fail']++;
	echo ( $ok ? '[OK]   ' : '[FAIL] ' ) . $label . "  got=" . var_export( $got, true ) . " exp=" . var_export( $exp, true ) . "\n";
}

// Stub mínimo de producto con get_weight()
class Gloq_Test_Product {
	private $id; private $weight; private $pres;
	function __construct( $id, $weight, $pres ) { $this->id = $id; $this->weight = $weight; $this->pres = $pres; }
	function get_id() { return $this->id; }
	function get_weight() { return $this->weight; }
}
// Filtro para inyectar el meta _glo_presentacion_texto sin tocar BD
add_filter( 'get_post_metadata', function ( $val, $object_id, $meta_key ) {
	if ( $meta_key === '_glo_presentacion_texto' && $object_id === 9001 ) return [ 'Saco 25 kg' ];
	return $val;
}, 10, 3 );

$p_curado = new Gloq_Test_Product( 9001, 25, null ); // tiene texto curado
$p_peso   = new Gloq_Test_Product( 9002, 25, null ); // solo peso
$p_gramos = new Gloq_Test_Product( 9003, 0.5, null ); // < 1 kg
$p_vacio  = new Gloq_Test_Product( 9004, '', null );  // nada

chk( 'label múltiple gana',       glotracol_quote_presentacion_display( $p_peso, 'Caja x 12' ), 'Caja x 12' );
chk( 'texto curado 2º',           glotracol_quote_presentacion_display( $p_curado, '' ),        'Saco 25 kg' );
chk( 'peso entero → kg',          glotracol_quote_presentacion_display( $p_peso, '' ),          '25 kg' );
chk( 'peso < 1 → gramos',         glotracol_quote_presentacion_display( $p_gramos, '' ),        '500 g' );
chk( 'sin nada → vacío',          glotracol_quote_presentacion_display( $p_vacio, '' ),         '' );

echo ( $GLOBALS['gloq_fail'] === 0 ? "ALL PASS\n" : ( $GLOBALS['gloq_fail'] . " FAILED\n" ) );
