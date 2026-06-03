<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CPT `glo_client` — clientes B2B identificados por NIT/Cédula.
 *
 * Cada cliente tiene:
 *  - Datos básicos (nombre, NIT, email, teléfono, etc.)
 *  - Precios negociados (meta `_glo_client_pricing` keyed by SKU)
 *  - Historial de cotizaciones (computado vía meta `_glo_client_id` en glo_quote)
 *
 * Lookup por NIT: option `glotracol_quote_nit_index` mantenida por hooks
 * de save/delete. Permite resolver client_id en O(1) sin meta_query costosa.
 */
class Glotracol_Quote_Client_CPT {

	const POST_TYPE = 'glo_client';
	const NIT_INDEX_OPTION = 'glotracol_quote_nit_index';

	public function __construct() {
		add_action( 'init', [ __CLASS__, 'register_post_type_static' ] );
		add_filter( 'manage_glo_client_posts_columns', [ $this, 'columns' ] );
		add_action( 'manage_glo_client_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_filter( 'manage_edit-glo_client_sortable_columns', [ $this, 'sortable_columns' ] );

		// Index NIT
		add_action( 'save_post_' . self::POST_TYPE, [ __CLASS__, 'rebuild_nit_index_for_post' ], 20, 2 );
		add_action( 'before_delete_post', [ __CLASS__, 'remove_from_nit_index' ], 20, 1 );
		add_action( 'wp_trash_post', [ __CLASS__, 'remove_from_nit_index' ], 20, 1 );
		add_action( 'untrash_post', [ __CLASS__, 'rebuild_nit_index_for_post_id' ], 20, 1 );
	}

	public static function register_post_type_static() {
		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'                  => 'Clientes B2B',
				'singular_name'         => 'Cliente B2B',
				'menu_name'             => 'Clientes B2B',
				'add_new'               => 'Añadir cliente',
				'add_new_item'          => 'Nuevo cliente B2B',
				'edit_item'             => 'Editar cliente B2B',
				'view_item'             => 'Ver cliente',
				'search_items'          => 'Buscar clientes',
				'not_found'             => 'No se encontraron clientes',
				'not_found_in_trash'    => 'No hay clientes en la papelera',
				'all_items'             => 'Todos los clientes',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=glo_quote',
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'menu_icon'           => 'dashicons-businessman',
			'supports'            => [ 'title' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'exclude_from_search' => true,
		] );
	}

	public function columns( $columns ) {
		return [
			'cb'             => $columns['cb'] ?? '',
			'title'          => 'Razón social',
			'glo_nit'        => 'NIT / Cédula',
			'glo_email'      => 'Email',
			'glo_phone'      => 'Teléfono',
			'glo_city'       => 'Ciudad',
			'glo_pricing'    => 'Precios B2B',
			'glo_history'    => 'Cotizaciones',
			'glo_active'     => 'Estado',
		];
	}

	public function render_column( $col, $post_id ) {
		switch ( $col ) {
			case 'glo_nit':
				$nit = get_post_meta( $post_id, '_glo_client_nit', true );
				echo $nit ? '<code>' . esc_html( $nit ) . '</code>' : '—';
				break;
			case 'glo_email':
				$email = get_post_meta( $post_id, '_glo_client_email', true );
				echo $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '—';
				break;
			case 'glo_phone':
				echo esc_html( get_post_meta( $post_id, '_glo_client_phone', true ) ?: '—' );
				break;
			case 'glo_city':
				echo esc_html( get_post_meta( $post_id, '_glo_client_city', true ) ?: '—' );
				break;
			case 'glo_pricing':
				$pricing = get_post_meta( $post_id, '_glo_client_pricing', true );
				$count = is_array( $pricing ) ? count( array_filter( $pricing ) ) : 0;
				echo $count > 0
					? '<strong>' . (int) $count . '</strong> SKUs con precio'
					: '<span style="color:#999">— sin precios negociados</span>';
				break;
			case 'glo_history':
				$count = self::count_quotes_for_client( $post_id );
				if ( $count > 0 ) {
					$url = admin_url( 'edit.php?post_type=glo_quote&meta_key=_glo_client_id&meta_value=' . $post_id );
					echo '<a href="' . esc_url( $url ) . '"><strong>' . (int) $count . '</strong> cotización' . ( $count === 1 ? '' : 'es' ) . '</a>';
				} else {
					echo '<span style="color:#999">0</span>';
				}
				break;
			case 'glo_active':
				$active = get_post_meta( $post_id, '_glo_client_active', true );
				$is_active = $active !== 'no'; // default activo
				echo $is_active
					? '<span class="glo-status glo-status-glo-new">Activo</span>'
					: '<span class="glo-status glo-status-glo-closed">Inactivo</span>';
				break;
		}
	}

	public function sortable_columns( $cols ) {
		$cols['glo_nit']  = '_glo_client_nit';
		$cols['glo_city'] = '_glo_client_city';
		return $cols;
	}

	/**
	 * Cuenta cotizaciones asociadas a este cliente.
	 */
	public static function count_quotes_for_client( $client_id ) {
		$ids = get_posts( [
			'post_type'   => 'glo_quote',
			'post_status' => 'any',
			'numberposts' => -1,
			'meta_key'    => '_glo_client_id',
			'meta_value'  => (int) $client_id,
			'fields'      => 'ids',
		] );
		return is_array( $ids ) ? count( $ids ) : 0;
	}

	/* -------------------------------------------------------------------------
	 * NIT INDEX
	 * Mantenido como option `{ "<nit>": <client_post_id> }` para lookup O(1).
	 * ---------------------------------------------------------------------- */

	/**
	 * Rebuild trigger desde save_post hook.
	 */
	public static function rebuild_nit_index_for_post( $post_id, $post = null ) {
		if ( wp_is_post_revision( $post_id ) ) return;
		if ( $post && $post->post_status === 'auto-draft' ) return;
		self::rebuild_nit_index_for_post_id( (int) $post_id );
	}

	public static function rebuild_nit_index_for_post_id( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) return;
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) return;

		$index = self::get_nit_index();
		// Quita entradas previas que apunten a este post_id (por si cambió el NIT)
		foreach ( $index as $k => $v ) {
			if ( (int) $v === $post_id ) unset( $index[ $k ] );
		}
		$nit = get_post_meta( $post_id, '_glo_client_nit', true );
		$nit_clean = self::normalize_nit( $nit );
		if ( $nit_clean && $post->post_status !== 'trash' ) {
			$index[ $nit_clean ] = $post_id;
		}
		update_option( self::NIT_INDEX_OPTION, $index, false );
	}

	public static function remove_from_nit_index( $post_id ) {
		$post_id = (int) $post_id;
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) return;
		$index = self::get_nit_index();
		foreach ( $index as $k => $v ) {
			if ( (int) $v === $post_id ) unset( $index[ $k ] );
		}
		update_option( self::NIT_INDEX_OPTION, $index, false );
	}

	public static function get_nit_index() {
		$idx = get_option( self::NIT_INDEX_OPTION, [] );
		return is_array( $idx ) ? $idx : [];
	}

	/**
	 * Normaliza un NIT/Cédula para indexar y comparar:
	 *  - Quita espacios, guiones, puntos, comas.
	 *  - Lowercase (por si hay letras V/E/J en NITs venezolanos o RUT chilenos).
	 *  - Si el resultado tiene <= 5 chars, devuelve null (probable error de captura).
	 */
	public static function normalize_nit( $nit ) {
		$nit = (string) $nit;
		$clean = preg_replace( '/[^a-zA-Z0-9]/', '', $nit );
		$clean = strtolower( (string) $clean );
		return strlen( $clean ) >= 5 ? $clean : null;
	}

	/**
	 * Reconstruye el index completo recorriendo todos los CPTs glo_client.
	 * Útil tras un import masivo o para reparar.
	 */
	public static function rebuild_full_index() {
		$ids = get_posts( [
			'post_type'   => self::POST_TYPE,
			'post_status' => [ 'publish', 'draft', 'private' ],
			'numberposts' => -1,
			'fields'      => 'ids',
		] );
		$index = [];
		foreach ( (array) $ids as $id ) {
			$nit = get_post_meta( $id, '_glo_client_nit', true );
			$clean = self::normalize_nit( $nit );
			if ( $clean ) {
				$index[ $clean ] = (int) $id;
			}
		}
		update_option( self::NIT_INDEX_OPTION, $index, false );
		return count( $index );
	}
}
