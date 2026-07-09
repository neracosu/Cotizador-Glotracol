<?php
/** wp eval-file tests/test-pricing-listas.php — verifica la cascada Lista A/B. */
$fail = 0;
function chk( $l, $g, $e ) { global $fail; $ok = ( $g === $e ); if ( ! $ok ) $fail++; echo ( $ok ? '[OK]   ' : '[FAIL] ' ) . "$l => got=" . var_export($g,true) . " exp=" . var_export($e,true) . "\n"; }

$ids = wc_get_products( [ 'limit' => 1, 'return' => 'ids', 'status' => 'publish' ] );
if ( ! $ids ) { echo "No hay productos para probar.\n"; return; }
$pid = (int) $ids[0];
$prev_a = get_post_meta( $pid, '_glo_price', true );
$prev_b = get_post_meta( $pid, '_glo_price_b', true );

// Cliente temporal marcado Lista B.
$cid = wp_insert_post( [ 'post_type' => Glotracol_Quote_Client_CPT::POST_TYPE, 'post_status' => 'publish', 'post_title' => 'TEST AB' ] );
update_post_meta( $cid, '_glo_client_nit', '999000111' );
update_post_meta( $cid, '_glo_client_active', 'yes' );
update_post_meta( $cid, '_glo_price_list', 'B' );
Glotracol_Quote_Client_CPT::rebuild_full_index();

// Helpers básicos.
glotracol_quote_set_product_price_b( $pid, 5000 );
chk( 'get_price_b', glotracol_quote_get_product_price_b( $pid ), 5000 );
chk( 'client_price_list B', glotracol_quote_get_client_price_list( $cid ), 'B' );

// A=8000, B=5000. Cliente B con B disponible → lista_b.
glotracol_quote_set_product_price( $pid, 8000 );
$r = Glotracol_Quote_Pricing::resolve_by_product_id( $pid, $cid );
chk( 'B usa lista_b', $r['source'], 'lista_b' );
chk( 'B precio lista_b', $r['price'], 5000 );

// Cliente A (client_id 0) → publico (Lista A).
$ra = Glotracol_Quote_Pricing::resolve_by_product_id( $pid, 0 );
chk( 'A usa publico', $ra['source'], 'publico' );
chk( 'A precio publico', $ra['price'], 8000 );

// Fallback: cliente B sin precio B para el producto → publico (Lista A).
glotracol_quote_set_product_price_b( $pid, 0 );
chk( 'B borrado', glotracol_quote_get_product_price_b( $pid ), null );
$rf = Glotracol_Quote_Pricing::resolve_by_product_id( $pid, $cid );
chk( 'fallback B->publico', $rf['source'], 'publico' );
chk( 'fallback precio A', $rf['price'], 8000 );

// Individual B2B gana sobre Lista B.
glotracol_quote_set_product_price_b( $pid, 5000 );
update_post_meta( $cid, '_glo_client_pricing', [ $pid => 3000 ] );
$ri = Glotracol_Quote_Pricing::resolve_by_product_id( $pid, $cid );
chk( 'individual gana', $ri['source'], 'b2b' );
chk( 'individual precio', $ri['price'], 3000 );

// Limpieza.
wp_delete_post( $cid, true );
if ( $prev_a !== '' && $prev_a !== null ) update_post_meta( $pid, '_glo_price', (int) $prev_a ); else delete_post_meta( $pid, '_glo_price' );
if ( $prev_b !== '' && $prev_b !== null ) update_post_meta( $pid, '_glo_price_b', (int) $prev_b ); else delete_post_meta( $pid, '_glo_price_b' );
Glotracol_Quote_Client_CPT::rebuild_full_index();
echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILED\n";
