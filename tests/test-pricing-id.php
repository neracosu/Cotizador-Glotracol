<?php
/** wp eval-file tests/test-pricing-id.php  — verifica resolución por product_id. */
$fail = 0;
function chk( $l, $g, $e ) { global $fail; $ok = ( $g === $e ); if ( ! $ok ) $fail++; echo ( $ok ? '[OK]   ' : '[FAIL] ' ) . "$l => got=" . var_export($g,true) . " exp=" . var_export($e,true) . "\n"; }

$ids = wc_get_products( [ 'limit' => 1, 'return' => 'ids', 'status' => 'publish' ] );
if ( ! $ids ) { echo "No hay productos para probar.\n"; return; }
$pid = (int) $ids[0];
$prev = get_post_meta( $pid, '_glo_price', true );

glotracol_quote_set_product_price( $pid, 12345 );
chk( 'get_product_price', glotracol_quote_get_product_price( $pid ), 12345 );
$r = Glotracol_Quote_Pricing::resolve_by_product_id( $pid, 0 );
chk( 'resolve publico price', $r['price'], 12345 );
chk( 'resolve publico source', $r['source'], 'publico' );

$res = Glotracol_Quote_Pricing::resolve_items( [ [ 'product_id' => $pid, 'quantity' => 2 ] ], 0 );
chk( 'resolve_items total', $res['total'], 24690 );
chk( 'resolve_items all_priced', $res['all_priced'], true );

glotracol_quote_set_product_price( $pid, 0 );
$r2 = Glotracol_Quote_Pricing::resolve_by_product_id( $pid, 0 );
chk( 'sin precio source', $r2['source'], 'pendiente' );
chk( 'sin precio price', $r2['price'], null );

if ( $prev !== '' && $prev !== null ) { update_post_meta( $pid, '_glo_price', (int) $prev ); }
echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILED\n";
