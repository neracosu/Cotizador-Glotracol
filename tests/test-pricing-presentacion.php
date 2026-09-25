<?php
/**
 * wp eval-file tests/test-pricing-presentacion.php
 *
 * Un item con presentacion se cotiza con el precio de ESA presentacion: negociado por
 * el SKU de la presentacion, luego su precio publico, y si no tiene, queda pendiente.
 * Nunca con el precio del producto base (un saco de 25 kg no vale lo de 250 g).
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}
$tag = wp_generate_password( 6, false, false );
$pid = wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Test pres ' . $tag ] );
update_post_meta( $pid, '_glo_price', 5000 );      // precio del producto base (250 g)
update_post_meta( $pid, '_glo_price_b', 4000 );
update_post_meta( $pid, '_glo_presentaciones', [
	[ 'idx' => 0, 'label' => '1 kg',  'sku' => "P1K-$tag",  'peso_g' => 1000,  'precio_publico' => 18000 ],
	[ 'idx' => 1, 'label' => '25 kg', 'sku' => "P25K-$tag", 'peso_g' => 25000, 'precio_publico' => 0 ],
] );
$cid = wp_insert_post( [ 'post_type' => 'glo_client', 'post_status' => 'publish', 'post_title' => 'Cliente pres ' . $tag ] );
update_post_meta( $cid, '_glo_price_list', 'B' );

$r = Glotracol_Quote_Pricing::resolve_items( [
	[ 'product_id' => $pid, 'quantity' => 2 ],
	[ 'product_id' => $pid, 'quantity' => 2, 'presentacion_idx' => 0, 'sku' => "P1K-$tag" ],
	[ 'product_id' => $pid, 'quantity' => 1, 'presentacion_idx' => 1, 'sku' => "P25K-$tag" ],
], 0 );
chk( 'sin presentacion: precio del producto 5000', (int) $r['items'][0]['precio_unitario'] === 5000 );
chk( 'presentacion 1 kg: su precio 18000', (int) $r['items'][1]['precio_unitario'] === 18000 );
chk( 'subtotal 1 kg x2 = 36000', (int) $r['items'][1]['precio_subtotal'] === 36000 );
chk( 'presentacion 25 kg sin precio: pendiente, no el del producto', $r['items'][2]['precio_unitario'] === null && $r['items'][2]['precio_origen'] === 'pendiente' );
chk( 'con una linea pendiente no esta todo cotizado', $r['all_priced'] === false );

// Cliente Lista B: la lista B es precio del producto base, no de la presentacion.
$rb = Glotracol_Quote_Pricing::resolve_items( [ [ 'product_id' => $pid, 'quantity' => 1, 'presentacion_idx' => 0, 'sku' => "P1K-$tag" ] ], $cid );
chk( 'cliente B con presentacion: precio de la presentacion 18000, no el B del producto', (int) $rb['items'][0]['precio_unitario'] === 18000 );

// Precio negociado por SKU de la presentacion.
update_post_meta( $cid, '_glo_client_pricing', [ "sku:P1K-$tag" => 15000 ] );
$rn = Glotracol_Quote_Pricing::resolve_items( [ [ 'product_id' => $pid, 'quantity' => 1, 'presentacion_idx' => 0, 'sku' => "P1K-$tag" ] ], $cid );
chk( 'negociado por SKU de presentacion: 15000', (int) $rn['items'][0]['precio_unitario'] === 15000 && $rn['items'][0]['precio_origen'] === 'b2b' );

// Presentacion que ya no existe: pendiente.
$rx = Glotracol_Quote_Pricing::resolve_items( [ [ 'product_id' => $pid, 'quantity' => 1, 'presentacion_idx' => 9 ] ], 0 );
chk( 'presentacion inexistente: pendiente', $rx['items'][0]['precio_unitario'] === null );

wp_delete_post( $pid, true );
wp_delete_post( $cid, true );
exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
