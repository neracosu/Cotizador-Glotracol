<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_Webhook {

	const HOOK = 'glotracol_quote_webhook_dispatch';

	public function __construct() {
		add_action( 'glotracol_quote_created', [ $this, 'schedule_dispatch' ], 20, 2 );
		add_action( self::HOOK, [ $this, 'dispatch' ], 10, 1 );
	}

	public function schedule_dispatch( $quote_id, $payload ) {
		$url = trim( (string) glotracol_quote_get_setting( 'webhook_url' ) );
		if ( ! $url ) return;
		// Async: schedule single event 5 seconds out so the request doesn't block the redirect.
		wp_schedule_single_event( time() + 5, self::HOOK, [ (int) $quote_id ] );
	}

	public function dispatch( $quote_id ) {
		$url = trim( (string) glotracol_quote_get_setting( 'webhook_url' ) );
		if ( ! $url ) return;

		$post = get_post( $quote_id );
		if ( ! $post || $post->post_type !== 'glo_quote' ) return;

		$type           = get_post_meta( $quote_id, '_glo_type', true ) ?: 'quote';
		$pricing_status = get_post_meta( $quote_id, '_glo_pricing_status', true ) ?: 'none';
		$converted_at   = get_post_meta( $quote_id, '_glo_converted_at', true );
		$client_id      = (int) get_post_meta( $quote_id, '_glo_client_id', true );
		$raw_items      = get_post_meta( $quote_id, '_glo_items', true ) ?: [];

		$items = [];
		foreach ( (array) $raw_items as $it ) {
			$items[] = [
				'product_id'         => (int) ( $it['product_id'] ?? 0 ),
				'sku'                => (string) ( $it['sku'] ?? '' ),
				'sku_producto'       => (string) ( $it['sku_producto'] ?? ( $it['sku'] ?? '' ) ),
				'name'               => (string) ( $it['name'] ?? '' ),
				'presentacion_label' => (string) ( $it['presentacion_label'] ?? '' ),
				'quantity'           => (int) ( $it['quantity'] ?? 0 ),
				'unit_price'         => isset( $it['precio_unitario'] ) ? ( $it['precio_unitario'] === null ? null : (int) $it['precio_unitario'] ) : null,
				'subtotal'           => isset( $it['precio_subtotal'] ) ? ( $it['precio_subtotal'] === null ? null : (int) $it['precio_subtotal'] ) : null,
				'price_source'       => (string) ( $it['precio_origen'] ?? 'pendiente' ),
			];
		}

		$client = [ 'id' => 0, 'nit' => '', 'name' => '', 'is_b2b' => false ];
		if ( $client_id > 0 ) {
			$client = [
				'id'     => $client_id,
				'nit'    => get_post_meta( $client_id, '_glo_client_nit', true ),
				'name'   => get_post_meta( $client_id, '_glo_client_name', true ),
				'is_b2b' => true,
			];
		}

		$payload = [
			'event'           => $converted_at ? 'converted' : 'created',
			'quote_id'        => (int) $quote_id,
			'reference'       => get_post_meta( $quote_id, '_glo_qid', true ),
			'type'            => $type,
			'status'          => $post->post_status,
			'pricing_status'  => $pricing_status,
			'currency'        => 'COP',
			'total'           => (int) get_post_meta( $quote_id, '_glo_total', true ),
			'units_total'     => (int) get_post_meta( $quote_id, '_glo_units_total', true ),
			'weight_total_kg' => (float) get_post_meta( $quote_id, '_glo_weight_total_kg', true ),
			'size_tag'        => get_post_meta( $quote_id, '_glo_size_tag', true ) ?: 'small',
			'created_at'      => mysql2date( 'c', $post->post_date_gmt, false ),
			'converted_at'    => $converted_at ? mysql2date( 'c', get_gmt_from_date( $converted_at ), false ) : null,
			'client'          => $client,
			'customer'        => [
				'name'    => get_post_meta( $quote_id, '_glo_customer_name', true ),
				'email'   => get_post_meta( $quote_id, '_glo_customer_email', true ),
				'phone'   => get_post_meta( $quote_id, '_glo_customer_phone', true ),
				'company' => get_post_meta( $quote_id, '_glo_customer_company', true ),
				'nit'     => get_post_meta( $quote_id, '_glo_customer_nit', true ),
				'city'    => get_post_meta( $quote_id, '_glo_customer_city', true ),
			],
			'message'         => get_post_meta( $quote_id, '_glo_customer_message', true ),
			'items'           => $items,
			'admin_url'       => admin_url( 'post.php?post=' . $quote_id . '&action=edit' ),
		];

		$payload = apply_filters( 'glotracol_quote_webhook_payload', $payload, $quote_id );
		$body    = wp_json_encode( $payload );

		$secret  = (string) glotracol_quote_get_setting( 'webhook_secret' );
		$headers = [ 'Content-Type' => 'application/json; charset=utf-8' ];
		if ( $secret !== '' ) {
			$headers['X-Glotracol-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
		}

		$resp = wp_remote_post( $url, [
			'timeout' => 10,
			'headers' => $headers,
			'body'    => $body,
		] );

		$ok = ! is_wp_error( $resp ) && (int) wp_remote_retrieve_response_code( $resp ) >= 200 && (int) wp_remote_retrieve_response_code( $resp ) < 300;
		$this->log( $quote_id, $url, $ok, $resp );

		if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
			$status_code = is_wp_error( $resp ) ? 0 : (int) wp_remote_retrieve_response_code( $resp );
			$err_msg = is_wp_error( $resp ) ? $resp->get_error_message() : '';
			Glotracol_Quote_Logger::log( $ok ? 'info' : 'error', 'webhook', sprintf( 'Webhook %s para cotización #%d', $ok ? 'OK' : 'FAIL', $quote_id ), [
				'quote_id'    => $quote_id,
				'url'         => $url,
				'status_code' => $status_code,
				'error'       => $err_msg,
			] );
		}

		// Reintentos con backoff: 1m, 5m, 15m (hasta 3 reintentos tras el intento inicial).
		if ( ! $ok ) {
			$attempts = (int) get_post_meta( $quote_id, '_glo_webhook_attempts', true ) + 1;
			update_post_meta( $quote_id, '_glo_webhook_attempts', $attempts );
			$backoffs = [ 60, 300, 900 ];
			if ( isset( $backoffs[ $attempts - 1 ] ) ) {
				$delay = $backoffs[ $attempts - 1 ];
				wp_schedule_single_event( time() + $delay, self::HOOK, [ $quote_id ] );
				if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
					Glotracol_Quote_Logger::info( 'webhook', sprintf( 'Reintento #%d programado en %ds', $attempts, $delay ), [ 'quote_id' => $quote_id ] );
				}
			} elseif ( class_exists( 'Glotracol_Quote_Logger' ) ) {
				Glotracol_Quote_Logger::error( 'webhook', 'Webhook agotó reintentos sin éxito', [ 'quote_id' => $quote_id, 'attempts' => $attempts ] );
			}
		} else {
			// Éxito: limpiar el contador para no arrastrar estado de fallos previos.
			delete_post_meta( $quote_id, '_glo_webhook_attempts' );
		}
	}

	private function log( $quote_id, $url, $ok, $resp ) {
		$log = get_post_meta( $quote_id, '_glo_email_log', true );
		if ( ! is_array( $log ) ) $log = [];
		$log[] = [
			'type'    => 'webhook',
			'to'      => $url,
			'sent_at' => current_time( 'mysql' ),
			'success' => (bool) $ok,
		];
		update_post_meta( $quote_id, '_glo_email_log', $log );
	}
}
