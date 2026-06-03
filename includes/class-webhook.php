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

		$payload = [
			'quote_id'   => (int) $quote_id,
			'reference'  => get_post_meta( $quote_id, '_glo_qid', true ),
			'status'     => $post->post_status,
			'created_at' => mysql2date( 'c', $post->post_date_gmt, false ),
			'customer'   => [
				'name'    => get_post_meta( $quote_id, '_glo_customer_name', true ),
				'email'   => get_post_meta( $quote_id, '_glo_customer_email', true ),
				'phone'   => get_post_meta( $quote_id, '_glo_customer_phone', true ),
				'company' => get_post_meta( $quote_id, '_glo_customer_company', true ),
				'nit'     => get_post_meta( $quote_id, '_glo_customer_nit', true ),
				'city'    => get_post_meta( $quote_id, '_glo_customer_city', true ),
			],
			'message'    => get_post_meta( $quote_id, '_glo_customer_message', true ),
			'items'      => get_post_meta( $quote_id, '_glo_items', true ) ?: [],
			'admin_url'  => admin_url( 'post.php?post=' . $quote_id . '&action=edit' ),
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

		// Reintento simple si falló y aún no hemos reintentado
		if ( ! $ok && ! get_post_meta( $quote_id, '_glo_webhook_retried', true ) ) {
			update_post_meta( $quote_id, '_glo_webhook_retried', 1 );
			wp_schedule_single_event( time() + 60, self::HOOK, [ $quote_id ] );
			if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
				Glotracol_Quote_Logger::info( 'webhook', 'Reintento programado en 60s', [ 'quote_id' => $quote_id ] );
			}
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
