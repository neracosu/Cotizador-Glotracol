<?php
/** wp eval-file tests/test-webhook-ghl.php — aplanado del payload para GoHighLevel. */
$GLOBALS['gloq_fail'] = 0;
function chk( $label, $cond ) {
	echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
	if ( ! $cond ) $GLOBALS['gloq_fail']++;
}

chk( 'el método existe', method_exists( 'Glotracol_Quote_Webhook', 'flatten_for_ghl' ) );

$payload = [
	'quote_id' => 42, 'reference' => 'COT-42', 'type' => 'quote', 'status' => 'glo-new',
	'pricing_status' => 'partial', 'currency' => 'COP', 'total' => 250000,
	'units_total' => 15, 'weight_total_kg' => 37.5, 'size_tag' => 'small',
	'created_at' => '2026-07-28T12:00:00+00:00', 'converted_at' => null,
	'client' => [ 'id' => 7, 'nit' => '900123', 'name' => 'ACME', 'is_b2b' => true ],
	'customer' => [ 'name' => 'Diana', 'email' => 'd@x.com', 'phone' => '300', 'company' => 'ACME', 'nit' => '900123', 'city' => 'Bogotá' ],
	'message' => 'Necesito precios',
	'items' => [
		[ 'name' => 'MANÍ', 'sku' => 'M1', 'quantity' => 10, 'unit_price' => 25000, 'subtotal' => 250000, 'price_source' => 'lista_a' ],
		[ 'name' => 'AVENA', 'sku' => 'A1', 'quantity' => 5, 'unit_price' => null, 'subtotal' => null, 'price_source' => 'pendiente' ],
	],
	'admin_url' => 'https://x/y',
];

$flat = Glotracol_Quote_Webhook::flatten_for_ghl( $payload );

$anidado = false;
foreach ( $flat as $v ) { if ( is_array( $v ) || is_object( $v ) ) $anidado = true; }
chk( 'no queda ningún valor anidado', $anidado === false );
chk( 'aplana customer_name', ( $flat['customer_name'] ?? '' ) === 'Diana' );
chk( 'aplana customer_email', ( $flat['customer_email'] ?? '' ) === 'd@x.com' );
chk( 'aplana customer_nit', ( $flat['customer_nit'] ?? '' ) === '900123' );
chk( 'aplana client_name', ( $flat['client_name'] ?? '' ) === 'ACME' );
chk( 'is_b2b se conserva', ! empty( $flat['is_b2b'] ) );
chk( 'items_count correcto', (int) ( $flat['items_count'] ?? 0 ) === 2 );
chk( 'items_text menciona ambos productos', strpos( $flat['items_text'] ?? '', 'MANÍ' ) !== false && strpos( $flat['items_text'] ?? '', 'AVENA' ) !== false );
chk( 'items_text marca el pendiente', strpos( $flat['items_text'] ?? '', 'A cotizar' ) !== false );
chk( 'conserva el total', (int) ( $flat['total'] ?? 0 ) === 250000 );
chk( 'conserva el peso', (float) ( $flat['weight_total_kg'] ?? 0 ) === 37.5 );
chk( 'conserva quote_id', (int) ( $flat['quote_id'] ?? 0 ) === 42 );
chk( 'el JSON resultante es válido', is_string( wp_json_encode( $flat ) ) );

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
