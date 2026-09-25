<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rutinas que corren una sola vez. Una actualizacion desde el panel no dispara el hook
 * de activacion, asi que lo que tenga que cambiar en la base o en los roles va aqui.
 * Cada rutina se marca como hecha en la opcion OPTION; no depende del numero de version.
 */
class Glotracol_Quote_Upgrade {

	const OPTION = 'glotracol_quote_migrations';

	/** Roles del equipo comercial: ven y editan cotizaciones y clientes. */
	const TEAM_ROLES = [ 'administrator', 'shop_manager', 'editor' ];

	public static function maybe_run() {
		$done = get_option( self::OPTION, [] );
		if ( ! is_array( $done ) ) $done = [];
		$tasks = [
			'caps_2170'         => [ __CLASS__, 'grant_caps' ],
			'pricing_keys_2170' => [ __CLASS__, 'migrate_all_client_pricing' ],
		];
		$changed = false;
		foreach ( $tasks as $key => $cb ) {
			if ( ! empty( $done[ $key ] ) ) continue;
			call_user_func( $cb );
			$done[ $key ] = time();
			$changed = true;
		}
		if ( $changed ) update_option( self::OPTION, $done, false );
	}

	/** Capacidades primitivas de un CPT con capability_type [singular, plural]. */
	public static function caps_for( $plural ) {
		return [
			"edit_$plural", "edit_others_$plural", "edit_private_$plural", "edit_published_$plural",
			"publish_$plural", "read_private_$plural",
			"delete_$plural", "delete_others_$plural", "delete_private_$plural", "delete_published_$plural",
		];
	}

	public static function grant_caps() {
		$caps = array_merge( self::caps_for( 'glo_quotes' ), self::caps_for( 'glo_clients' ) );
		foreach ( self::TEAM_ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) continue;
			foreach ( $caps as $cap ) $role->add_cap( $cap );
		}
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
