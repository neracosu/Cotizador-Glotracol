<?php
/**
 * Verificación del semáforo. Ejecutar: wp eval-file tests/test-semaforo.php
 */
$fail = 0;
function chk( $label, $got, $exp ) {
	global $fail;
	$ok = ( $got === $exp );
	if ( ! $ok ) $fail++;
	echo ( $ok ? '[OK]   ' : '[FAIL] ' ) . $label . " => got=" . var_export( $got, true ) . " exp=" . var_export( $exp, true ) . "\n";
}

// Semáforo por peso (umbrales por defecto: large=200, tons=1000).
chk( 'peso 50kg', glotracol_quote_semaforo( 50, 1, 1 ), 'small' );
chk( 'peso 250kg', glotracol_quote_semaforo( 250, 1, 1 ), 'large' );
chk( 'peso 1500kg', glotracol_quote_semaforo( 1500, 1, 1 ), 'tons' );
chk( 'peso exacto 200kg', glotracol_quote_semaforo( 200, 1, 1 ), 'large' );
chk( 'peso exacto 1000kg', glotracol_quote_semaforo( 1000, 1, 1 ), 'tons' );

// Fallback por unidades cuando no hay peso (defaults: medium=25u, large=80u).
chk( 'sin peso, 5 unidades', glotracol_quote_semaforo( 0, 5, 1 ), 'small' );
chk( 'sin peso, 30 unidades', glotracol_quote_semaforo( 0, 30, 1 ), 'large' );  // medium→large
chk( 'sin peso, 100 unidades', glotracol_quote_semaforo( 0, 100, 1 ), 'tons' ); // large→tons

// Peso total de items (sin product_id real → 0).
$items = [ [ 'product_id' => 0, 'quantity' => 4, 'presentacion_idx' => null ] ];
chk( 'weight_total sin peso', glotracol_quote_weight_total( $items ), 0.0 );

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILED\n";
