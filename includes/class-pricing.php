<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sistema de resolución de precios.
 *
 * Fuentes posibles:
 *  1. Precio negociado B2B (meta `_glo_client_pricing` del CPT cliente).
 *  2. Precio público (option `glotracol_quote_public_pricing`).
 *  3. Sin precio → null (la cotización quedará en estado "pending-prices").
 *
 * El SKU es la key principal. En futuras fases con presentaciones, la SKU
 * efectiva será la de la presentación, no la del producto padre.
 */
class Glotracol_Quote_Pricing {

	const PUBLIC_OPTION = 'glotracol_quote_public_pricing';

	/**
	 * Resuelve el precio para un SKU dado.
	 *
	 * @param string $sku       SKU del producto/presentación.
	 * @param int    $client_id Si es > 0, busca primero override B2B del cliente.
	 * @return array{ price: int|null, source: string }
	 *         price: precio en COP (entero) o null si no hay precio disponible.
	 *         source: 'b2b' | 'publico' | 'pendiente'
	 */
	public static function resolve( $sku, $client_id = 0 ) {
		$sku = trim( (string) $sku );
		if ( $sku === '' ) {
			return [ 'price' => null, 'source' => 'pendiente' ];
		}

		// 1) Override B2B
		if ( $client_id > 0 ) {
			$pricing = get_post_meta( (int) $client_id, '_glo_client_pricing', true );
			if ( is_array( $pricing ) && isset( $pricing[ $sku ] ) && (int) $pricing[ $sku ] > 0 ) {
				return [ 'price' => (int) $pricing[ $sku ], 'source' => 'b2b' ];
			}
		}

		// 2) Lista pública
		$public = self::get_public_pricing();
		if ( isset( $public[ $sku ] ) && (int) $public[ $sku ] > 0 ) {
			return [ 'price' => (int) $public[ $sku ], 'source' => 'publico' ];
		}

		// 3) Sin precio
		return [ 'price' => null, 'source' => 'pendiente' ];
	}

	/**
	 * Resuelve el precio por ID de producto (camino principal v2.2.0).
	 * Cascada: B2B (cliente, por product_id; compat por SKU) → Lista B (_glo_price_b)
	 * → público (_glo_price; compat lista pública por SKU) → pendiente.
	 *
	 * @return array{ price: int|null, source: string }  source: b2b|lista_b|publico|pendiente
	 */
	public static function resolve_by_product_id( $product_id, $client_id = 0 ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return [ 'price' => null, 'source' => 'pendiente' ];
		}
		// 1) B2B negociado
		if ( $client_id > 0 ) {
			$pricing = get_post_meta( (int) $client_id, '_glo_client_pricing', true );
			if ( is_array( $pricing ) ) {
				if ( isset( $pricing[ $product_id ] ) && (int) $pricing[ $product_id ] > 0 ) {
					return [ 'price' => (int) $pricing[ $product_id ], 'source' => 'b2b' ];
				}
				$sku = (string) get_post_meta( $product_id, '_sku', true );
				if ( $sku !== '' && isset( $pricing[ $sku ] ) && (int) $pricing[ $sku ] > 0 ) {
					return [ 'price' => (int) $pricing[ $sku ], 'source' => 'b2b' ];
				}
			}
		}
		// 2) Lista B: cliente marcado B y producto con precio B > 0.
		if ( $client_id > 0 && glotracol_quote_get_client_price_list( $client_id ) === 'B' ) {
			$pb = (int) get_post_meta( $product_id, '_glo_price_b', true );
			if ( $pb > 0 ) {
				return [ 'price' => $pb, 'source' => 'lista_b' ];
			}
		}
		// 3) Público por producto (_glo_price)  — fallback de un cliente B sin precio B.
		$pub = (int) get_post_meta( $product_id, '_glo_price', true );
		if ( $pub > 0 ) {
			return [ 'price' => $pub, 'source' => 'publico' ];
		}
		// 4) Compat: lista pública por SKU
		$sku = (string) get_post_meta( $product_id, '_sku', true );
		if ( $sku !== '' ) {
			$legacy = self::get_public_pricing();
			if ( isset( $legacy[ $sku ] ) && (int) $legacy[ $sku ] > 0 ) {
				return [ 'price' => (int) $legacy[ $sku ], 'source' => 'publico' ];
			}
		}
		return [ 'price' => null, 'source' => 'pendiente' ];
	}

	/**
	 * Resuelve precios para todos los items de una cotización.
	 *
	 * @param array $items     Lista de items con al menos `sku` y `quantity`.
	 * @param int   $client_id Cliente B2B asociado (0 si no hay match por NIT).
	 * @return array{ items: array, all_priced: bool, total: int, sources: array }
	 */
	public static function resolve_items( $items, $client_id = 0 ) {
		$out_items = [];
		$total = 0;
		$all_priced = true;
		$sources = [ 'b2b' => 0, 'lista_b' => 0, 'publico' => 0, 'pendiente' => 0 ];
		foreach ( (array) $items as $item ) {
			$pid = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$qty = isset( $item['quantity'] ) ? max( 0, (int) $item['quantity'] ) : 0;
			if ( $pid > 0 ) {
				$resolved = self::resolve_by_product_id( $pid, $client_id );
			} else {
				$resolved = self::resolve( isset( $item['sku'] ) ? (string) $item['sku'] : '', $client_id );
			}
			$item['precio_unitario'] = $resolved['price'];
			$item['precio_origen']   = $resolved['source'];
			if ( $resolved['price'] !== null && $qty > 0 ) {
				$item['precio_subtotal'] = $resolved['price'] * $qty;
				$total += $item['precio_subtotal'];
			} else {
				$item['precio_subtotal'] = null;
				$all_priced = false;
			}
			if ( ! isset( $sources[ $resolved['source'] ] ) ) $sources[ $resolved['source'] ] = 0;
			$sources[ $resolved['source'] ]++;
			$out_items[] = $item;
		}
		return [
			'items'      => $out_items,
			'all_priced' => $all_priced,
			'total'      => $total,
			'sources'    => $sources,
		];
	}

	/**
	 * Devuelve la lista pública completa: array { sku => precio_cop }.
	 */
	public static function get_public_pricing() {
		$pricing = get_option( self::PUBLIC_OPTION, [] );
		return is_array( $pricing ) ? $pricing : [];
	}

	/**
	 * Setea/actualiza el precio público de un SKU. Si $price <= 0, elimina la entrada.
	 */
	public static function set_public_price( $sku, $price ) {
		$sku = trim( (string) $sku );
		if ( $sku === '' ) return false;
		$pricing = self::get_public_pricing();
		$price = (int) $price;
		if ( $price <= 0 ) {
			unset( $pricing[ $sku ] );
		} else {
			$pricing[ $sku ] = $price;
		}
		update_option( self::PUBLIC_OPTION, $pricing, false );
		return true;
	}

	/**
	 * Reemplaza la lista pública completa (útil para imports). Devuelve cuántos
	 * SKUs quedaron en la lista final.
	 */
	public static function replace_public_pricing( $new_pricing ) {
		if ( ! is_array( $new_pricing ) ) $new_pricing = [];
		$clean = [];
		foreach ( $new_pricing as $sku => $price ) {
			$sku = trim( (string) $sku );
			$price = (int) $price;
			if ( $sku !== '' && $price > 0 ) {
				$clean[ $sku ] = $price;
			}
		}
		update_option( self::PUBLIC_OPTION, $clean, false );
		return count( $clean );
	}

	/**
	 * Merge: agrega/actualiza SKUs sin tocar los demás. Devuelve [updated, inserted].
	 */
	public static function merge_public_pricing( $rows ) {
		$current = self::get_public_pricing();
		$updated = 0; $inserted = 0;
		foreach ( (array) $rows as $sku => $price ) {
			$sku = trim( (string) $sku );
			$price = (int) $price;
			if ( $sku === '' || $price <= 0 ) continue;
			if ( isset( $current[ $sku ] ) ) $updated++;
			else $inserted++;
			$current[ $sku ] = $price;
		}
		update_option( self::PUBLIC_OPTION, $current, false );
		return [ 'updated' => $updated, 'inserted' => $inserted ];
	}

	/**
	 * Conteo: cuántos SKUs hay en la lista pública.
	 */
	public static function count_public_skus() {
		return count( self::get_public_pricing() );
	}
}
