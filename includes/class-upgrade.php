<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rutinas que corren una vez al actualizar el plugin. Una actualizacion desde el panel
 * no dispara el hook de activacion: lo que tenga que cambiar en la base de datos o en
 * los roles al subir de version va aqui, atado a la version de datos guardada.
 */
class Glotracol_Quote_Upgrade {

	const OPTION = 'glotracol_quote_db_version';

	public static function maybe_run() {
		$from = (string) get_option( self::OPTION, '0' );
		if ( version_compare( $from, GLOTRACOL_QUOTE_VERSION, '>=' ) ) return;
		if ( version_compare( $from, '2.17.0', '<' ) ) {
			self::migrate_all_client_pricing();
		}
		do_action( 'glotracol_quote_upgrade', $from, GLOTRACOL_QUOTE_VERSION );
		update_option( self::OPTION, GLOTRACOL_QUOTE_VERSION, false );
	}

	/** 2.17.0: precios negociados con clave SKU pasan a product_id o 'sku:<SKU>'. */
	public static function migrate_all_client_pricing() {
		$ids = get_posts( [ 'post_type' => 'glo_client', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] );
		foreach ( (array) $ids as $id ) self::migrate_client_pricing( (int) $id );
	}

	public static function migrate_client_pricing( $client_id ) {
		$pricing = get_post_meta( $client_id, '_glo_client_pricing', true );
		if ( ! is_array( $pricing ) || ! $pricing ) return;
		$out = [];
		$changed = false;
		foreach ( $pricing as $k => $price ) {
			if ( is_int( $k ) || strpos( (string) $k, 'sku:' ) === 0 ) {
				$out[ $k ] = $price;
				continue;
			}
			$r = Glotracol_Quote_Importer::resolve_ref( (string) $k );
			if ( $r['key'] === null ) {
				$out[ $k ] = $price; // no se pierde: queda visible en la ficha para resolverlo a mano
				Glotracol_Quote_Logger::log( 'warn', 'upgrade', sprintf( 'Cliente #%d: precio con referencia "%s" sin resolver', $client_id, $k ), [ 'client_id' => $client_id ] );
				continue;
			}
			$out[ $r['key'] ] = $price;
			$changed = true;
		}
		if ( $changed ) update_post_meta( $client_id, '_glo_client_pricing', $out );
	}
}
