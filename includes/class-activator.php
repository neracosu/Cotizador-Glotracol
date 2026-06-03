<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_Activator {

	public static function activate() {
		self::ensure_page( 'glotracol_quote_form_page_id', 'Solicitar cotización', 'solicitar-cotizacion', '[glotracol_quote_form]' );
		self::ensure_page( 'glotracol_quote_thanks_page_id', 'Cotización enviada', 'cotizacion-enviada', '[glotracol_quote_thanks]' );

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

	private static function ensure_page( $option_key, $title, $slug, $content ) {
		$existing_id = (int) get_option( $option_key );
		if ( $existing_id && get_post( $existing_id ) ) {
			return $existing_id;
		}
		$page = get_page_by_path( $slug );
		if ( $page ) {
			update_option( $option_key, $page->ID );
			if ( strpos( (string) $page->post_content, $content ) === false ) {
				wp_update_post( [
					'ID'           => $page->ID,
					'post_content' => $content,
				] );
			}
			return $page->ID;
		}
		$id = wp_insert_post( [
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		] );
		if ( ! is_wp_error( $id ) ) {
			update_option( $option_key, $id );
			return $id;
		}
		return 0;
	}
}
