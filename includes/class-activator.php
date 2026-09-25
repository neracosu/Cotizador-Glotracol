<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_Activator {

	public static function activate() {
		Glotracol_Quote_Upgrade::grant_caps();
		self::ensure_page( 'glotracol_quote_form_page_id', 'Solicitar cotización', 'solicitar-cotizacion', '[glotracol_quote_form]' );
		self::ensure_page( 'glotracol_quote_thanks_page_id', 'Cotización enviada', 'cotizacion-enviada', '[glotracol_quote_thanks]' );
		self::ensure_page( 'glotracol_quote_dashboard_page_id', 'Panel de cotizaciones', 'panel-cotizaciones', '[glotracol_quote_dashboard]' );

		if ( ! get_option( 'glotracol_quote_settings' ) ) {
			update_option( 'glotracol_quote_settings', glotracol_quote_get_settings() );
		}

		Glotracol_Quote_CPT::register_post_type_static();
		Glotracol_Quote_CPT::register_statuses_static();
		if ( class_exists( 'Glotracol_Quote_Client_CPT' ) ) {
			Glotracol_Quote_Client_CPT::register_post_type_static();
		}
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Devuelve la pagina del plugin para $option_key, creandola si hace falta.
	 *
	 * - Si ya hay una pagina con ese slug que lleva el shortcode (en el contenido o en los
	 *   datos de Elementor), se adopta sin tocarla.
	 * - Si el slug lo ocupa una pagina ajena, NO se modifica: se crea otra (WordPress le
	 *   pone un slug unico). Antes se le reemplazaba el contenido por el shortcode.
	 * - Si la opcion ya apuntaba a una pagina que el administrador borro, solo se recrea
	 *   con $recreate (activacion); en cada carga del admin no.
	 */
	public static function ensure_page( $option_key, $title, $slug, $content, $recreate = true ) {
		$stored = get_option( $option_key, null );
		$existing_id = (int) $stored;
		if ( $existing_id && get_post( $existing_id ) ) {
			return $existing_id;
		}
		if ( $stored !== null && ! $recreate ) {
			return 0;
		}
		$page = get_page_by_path( $slug );
		if ( $page && self::page_has_shortcode( $page->ID, $content ) ) {
			update_option( $option_key, $page->ID );
			return $page->ID;
		}
		$id = wp_insert_post( [
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		] );
		if ( ! is_wp_error( $id ) && $id ) {
			update_option( $option_key, $id );
			return $id;
		}
		return 0;
	}

	private static function page_has_shortcode( $page_id, $shortcode ) {
		if ( strpos( (string) get_post_field( 'post_content', $page_id ), $shortcode ) !== false ) return true;
		$el = (string) get_post_meta( $page_id, '_elementor_data', true );
		// En _elementor_data los corchetes pueden venir escapados en JSON.
		return $el !== '' && ( strpos( $el, $shortcode ) !== false || strpos( wp_unslash( $el ), $shortcode ) !== false );
	}
}
