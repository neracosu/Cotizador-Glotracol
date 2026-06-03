<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

$settings = get_option( 'glotracol_quote_settings', [] );
$delete_data = is_array( $settings ) && isset( $settings['delete_data_on_uninstall'] ) && $settings['delete_data_on_uninstall'] === 'yes';

if ( ! $delete_data ) {
	return;
}

$ids = get_posts( [
	'post_type'   => 'glo_quote',
	'post_status' => 'any',
	'numberposts' => -1,
	'fields'      => 'ids',
] );
foreach ( (array) $ids as $id ) {
	wp_delete_post( $id, true );
}

// CPT clientes B2B (v1.3.0+)
$client_ids = get_posts( [
	'post_type'   => 'glo_client',
	'post_status' => 'any',
	'numberposts' => -1,
	'fields'      => 'ids',
] );
foreach ( (array) $client_ids as $id ) {
	wp_delete_post( $id, true );
}

delete_option( 'glotracol_quote_settings' );
delete_option( 'glotracol_quote_form_page_id' );
delete_option( 'glotracol_quote_thanks_page_id' );
delete_option( 'glotracol_quote_public_pricing' );
delete_option( 'glotracol_quote_nit_index' );
delete_option( 'glotracol_quote_log' );
delete_transient( 'glotracol_quote_wc_load_failure' );
delete_transient( 'gloq_import_last_report' );
