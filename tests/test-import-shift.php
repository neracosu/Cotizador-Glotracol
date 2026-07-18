<?php
/** wp eval-file tests/test-import-shift.php — depurador: detector de corrimiento, duplicados, realineado. */
$fails = 0;
function chk( $label, $cond ) { global $fails; echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n"; if ( ! $cond ) $fails++; }

// --- 1. detect_shift: secuencia corrida +1 (precio[i] == current del id vecino i+1) ---
// Simula el caso real de Diana: cada fila carga el precio actual del producto SIGUIENTE.
$seq = [
	[ 'id' => 441, 'incoming' => 141610, 'current' => 654500 ],
	[ 'id' => 493, 'incoming' => 279650, 'current' => 141610 ],
	[ 'id' => 448, 'incoming' => 202300, 'current' => 279650 ],
	[ 'id' => 426, 'incoming' => 321300, 'current' => 202300 ],
	[ 'id' => 418, 'incoming' => 401625, 'current' => 321300 ],
	[ 'id' => 419, 'incoming' => 279650, 'current' => 401625 ],
];
$shift = Glotracol_Quote_Import_Diff::detect_shift( $seq, 4 );
chk( 'detecta corrimiento en secuencia corrida', $shift !== null );
chk( 'dirección +1', $shift && $shift['dir'] === 1 );
// La última fila no tiene vecino i+1 que confirme, así que la corrida va de 0..4 (len 5).
chk( 'corrida cubre filas 0..4 (len 5)', $shift && $shift['from'] === 0 && $shift['to'] === 4 && $shift['len'] === 5 );

// --- 2. detect_shift: secuencia alineada normal NO dispara falso positivo ---
$ok_seq = [
	[ 'id' => 1, 'incoming' => 1000, 'current' => 900 ],
	[ 'id' => 2, 'incoming' => 2000, 'current' => 1900 ],
	[ 'id' => 3, 'incoming' => 3000, 'current' => 2900 ],
	[ 'id' => 4, 'incoming' => 4000, 'current' => 3900 ],
	[ 'id' => 5, 'incoming' => 5000, 'current' => 4900 ],
];
chk( 'sin corrimiento en secuencia alineada', Glotracol_Quote_Import_Diff::detect_shift( $ok_seq, 4 ) === null );

// --- 3. suggest_realign ACOTADO al tramo: reasigna el precio al id correcto ---
$re = Glotracol_Quote_Import_Diff::suggest_realign( $seq, [ 'dir'=>1, 'from'=>0, 'to'=>4 ] );
chk( 'realineado: fila 0 (441) queda sin precio', $re[0]['incoming'] === '' );
chk( 'realineado: 493 recibe 141610 (su precio real)', $re[1]['id'] === 493 && (int) $re[1]['incoming'] === 141610 );
chk( 'realineado: 448 recibe 279650', $re[2]['id'] === 448 && (int) $re[2]['incoming'] === 279650 );

// --- 3b. suggest_realign NO toca filas fuera del tramo (cabecera correcta) ---
$mixed = [
	[ 'id'=>10, 'incoming'=>1000, 'current'=>1000 ], // cabecera correcta (fuera del tramo)
	[ 'id'=>20, 'incoming'=>3000, 'current'=>2000 ], // tramo corrido from=1
	[ 'id'=>30, 'incoming'=>4000, 'current'=>3000 ],
	[ 'id'=>40, 'incoming'=>5000, 'current'=>4000 ],
	[ 'id'=>50, 'incoming'=>6000, 'current'=>5000 ],
];
$re2 = Glotracol_Quote_Import_Diff::suggest_realign( $mixed, [ 'dir'=>1, 'from'=>1, 'to'=>4 ] );
chk( 'realineado acotado: fila 0 (cabecera) intacta = 1000', (int) $re2[0]['incoming'] === 1000 );
chk( 'realineado acotado: fila 1 (origen) sin precio', $re2[1]['incoming'] === '' );
chk( 'realineado acotado: fila 2 recibe 3000 del vecino', (int) $re2[2]['incoming'] === 3000 );

// --- 4. detect_duplicates ---
$dups = Glotracol_Quote_Import_Diff::detect_duplicates( [ '652', '653', '652', '479', '653' ] );
chk( 'detecta 2 refs duplicadas', count( $dups ) === 2 );
chk( '652 marcado duplicado', isset( $dups['652'] ) );

// --- 5. Integración: build() de precios_publicos produce nombre de catálogo + precio ---
$ids = wc_get_products( [ 'limit' => 1, 'return' => 'ids', 'status' => 'publish' ] );
if ( $ids ) {
	$pid = (int) $ids[0];
	$cur = (int) get_post_meta( $pid, '_glo_price', true );
	$diff = Glotracol_Quote_Import_Diff::build( 'precios_publicos', [ [ 'sku' => (string) $pid, 'precio' => '123456', '__line' => 2 ] ], [] );
	$r0 = $diff['rows'][0] ?? null;
	chk( 'build precios_publicos: fila resuelta a producto', $r0 && ! empty( $r0['product']['id'] ) );
	chk( 'build precios_publicos: trae nombre de catálogo', $r0 && $r0['product']['name'] !== '' );
	chk( 'build precios_publicos: precio incoming=123456', $r0 && isset( $r0['fields']['precio'] ) && (int) $r0['fields']['precio']['incoming'] === 123456 );
	chk( 'build precios_publicos: precio current del catálogo', $r0 && (int) $r0['fields']['precio']['current'] === $cur );
}

echo ( $fails === 0 ? "TODO OK\n" : "HAY $fails FALLOS\n" );
