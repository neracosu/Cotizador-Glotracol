<?php
// wp eval-file tests/test-reprice-nit.php --allow-root
$GLOBALS['gloq_fail'] = 0;
function chk( $label, $cond ) {
	if ( ! $cond ) $GLOBALS['gloq_fail']++;
	echo ( $cond ? '[OK]   ' : '[FAIL] ' ) . $label . "\n";
}

// Buscar un producto con precio A y B
$q = new WP_Query( [ 'post_type'=>'product', 'posts_per_page'=>1, 'fields'=>'ids',
	'meta_query'=>[ 'relation'=>'AND',
		[ 'key'=>'_glo_price', 'value'=>0, 'compare'=>'>', 'type'=>'NUMERIC' ],
		[ 'key'=>'_glo_price_b', 'value'=>0, 'compare'=>'>', 'type'=>'NUMERIC' ] ] ] );
$pid = ! empty( $q->posts ) ? (int) $q->posts[0] : 0;
chk( 'hay producto con precio A y B', $pid > 0 );
if ( $pid ) {
	$a = (int) get_post_meta( $pid, '_glo_price', true );
	$b = (int) get_post_meta( $pid, '_glo_price_b', true );
	$items = [ [ 'key'=>'k1', 'product_id'=>$pid, 'quantity'=>2, 'sku'=>get_post_meta($pid,'_sku',true) ] ];

	$pub = Glotracol_Quote_Pricing::resolve_items( $items, 0 );
	chk( 'público usa Lista A', $pub['items'][0]['precio_unitario'] === $a );
	chk( 'subtotal público = A x 2', $pub['items'][0]['precio_subtotal'] === $a * 2 );

	// Cliente B2B real
	$cli = new WP_Query( [ 'post_type'=>'glo_client', 'posts_per_page'=>1, 'fields'=>'ids',
		'meta_query'=>[ [ 'key'=>'_glo_price_list', 'value'=>'B' ] ] ] );
	$cid = ! empty( $cli->posts ) ? (int) $cli->posts[0] : 0;
	chk( 'hay cliente B2B', $cid > 0 );
	if ( $cid ) {
		$b2b = Glotracol_Quote_Pricing::resolve_items( $items, $cid );
		chk( 'cliente B usa Lista B (o cae a A si no tiene B)', $b2b['items'][0]['precio_unitario'] === $b || $b2b['items'][0]['precio_unitario'] === $a );
	}
}
echo ( $GLOBALS['gloq_fail'] === 0 ? "ALL PASS\n" : ( $GLOBALS['gloq_fail'] . " FAILED\n" ) );
