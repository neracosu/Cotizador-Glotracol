<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_Emails {

	public function __construct() {
		add_action( 'glotracol_quote_created', [ $this, 'send_emails' ], 10, 2 );
	}

	public function send_emails( $quote_id, $payload ) {
		$settings = glotracol_quote_get_settings();
		$customer = isset( $payload['customer'] ) ? $payload['customer'] : [];
		$items    = isset( $payload['items'] ) ? $payload['items'] : [];

		$placeholders = [
			'quote_id'         => $quote_id,
			'customer_name'    => $customer['name'] ?? '',
			'customer_email'   => $customer['email'] ?? '',
			'customer_phone'   => $customer['phone'] ?? '',
			'customer_company' => $customer['company'] ?? '',
			'site_name'        => get_bloginfo( 'name' ),
		];

		$from_name  = $settings['sender_name'] ?: get_bloginfo( 'name' );
		$from_email = is_email( $settings['sender_email'] ) ? $settings['sender_email'] : get_option( 'admin_email' );
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		];

		// Admin email
		$admin_recipients = glotracol_quote_emails_to_array( $settings['destination_emails'] );
		if ( empty( $admin_recipients ) ) {
			$admin_recipients = [ get_option( 'admin_email' ) ];
		}
		$admin_headers = $headers;
		$bcc = glotracol_quote_emails_to_array( $settings['bcc_emails'] );
		foreach ( $bcc as $b ) {
			$admin_headers[] = 'Bcc: ' . $b;
		}
		if ( ! empty( $customer['email'] ) ) {
			$admin_headers[] = 'Reply-To: ' . $customer['email'];
		}

		// F4 — branching para pedidos grandes
		$is_large = (int) get_post_meta( $quote_id, '_glo_is_large_alert', true ) === 1
			&& ( $settings['large_alert_enabled'] ?? 'yes' ) === 'yes';
		$units_total = (int) get_post_meta( $quote_id, '_glo_units_total', true );
		$skus_count  = is_array( $items ) ? count( $items ) : 0;

		// F6 (Fase D) — flags de tipo y pricing
		$type            = $payload['type'] ?? get_post_meta( $quote_id, '_glo_type', true ) ?: 'quote';
		$client_id       = (int) ( $payload['client_id'] ?? get_post_meta( $quote_id, '_glo_client_id', true ) );
		$pricing_status  = $payload['pricing']['status'] ?? get_post_meta( $quote_id, '_glo_pricing_status', true );
		$total           = (int) ( $payload['pricing']['total'] ?? get_post_meta( $quote_id, '_glo_total', true ) );
		$auto_respond    = ( $settings['auto_respond_enabled'] ?? 'yes' ) === 'yes';
		$client_name     = $client_id ? get_post_meta( $client_id, '_glo_client_name', true ) : '';

		// Si es large y hay un email destacado configurado, lo añadimos como recipient adicional
		if ( $is_large ) {
			$alert_email = trim( (string) ( $settings['large_alert_email'] ?? '' ) );
			if ( $alert_email && is_email( $alert_email ) ) {
				$alert_emails = glotracol_quote_emails_to_array( $alert_email );
				$admin_recipients = array_unique( array_merge( $admin_recipients, $alert_emails ) );
			}
		}

		// Decidir templates según pricing_status
		$is_pending = $pricing_status === 'partial' || $pricing_status === 'none';
		$is_auto_priced = $pricing_status === 'priced' && $auto_respond;

		$admin_subject = glotracol_quote_replace_placeholders( $settings['admin_subject'], $placeholders );
		// Prefix del subject según contexto
		$prefix = '';
		if ( $is_large ) $prefix .= '[GRANDE] ';
		if ( $is_pending ) $prefix .= '[PENDIENTE PRECIOS] ';
		elseif ( $is_auto_priced ) $prefix .= '[AUTO-COTIZADA] ';
		if ( $type === 'order' ) $prefix .= '[PEDIDO] ';
		$admin_subject = $prefix . $admin_subject;

		// Elegir template admin
		if ( $is_large ) {
			$admin_template = 'email-admin-large.php';
			$admin_log_type = 'admin-large';
		} elseif ( $is_pending ) {
			$admin_template = 'email-admin-pending-prices.php';
			$admin_log_type = 'admin-pending';
		} else {
			$admin_template = 'email-admin.php';
			$admin_log_type = 'admin';
		}

		$admin_body = glotracol_quote_load_template( $admin_template, [
			'quote_id'        => $quote_id,
			'customer'        => $customer,
			'items'           => $items,
			'message'         => $customer['message'] ?? '',
			'meta'            => $payload['meta'] ?? [],
			'intro'           => glotracol_quote_get_setting( 'admin_intro' ),
			'edit_url'        => admin_url( 'post.php?post=' . $quote_id . '&action=edit' ),
			'units_total'     => $units_total,
			'skus_count'      => $skus_count,
			'type'            => $type,
			'client_id'       => $client_id,
			'client_name'     => $client_name,
			'pricing_status'  => $pricing_status,
			'total'           => $total,
		] );
		$admin_body = apply_filters( 'glotracol_quote_email_admin_body', $admin_body, $quote_id, $payload );
		$admin_ok = wp_mail( $admin_recipients, $admin_subject, $admin_body, $admin_headers );
		$this->log( $quote_id, $admin_log_type, implode( ', ', $admin_recipients ), $admin_ok );
		if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
			Glotracol_Quote_Logger::log( $admin_ok ? 'info' : 'error', 'email', sprintf( 'Email %s al admin (%s) #%d %s', $admin_log_type, implode(', ', $admin_recipients), $quote_id, $admin_ok ? 'enviado' : 'FALLÓ' ), [
				'quote_id' => $quote_id, 'recipients' => $admin_recipients, 'template' => $admin_template, 'success' => $admin_ok,
			] );
		}

		// Customer email — branching según pricing
		if ( is_email( $customer['email'] ?? '' ) ) {
			if ( $is_auto_priced ) {
				$cust_subject = sprintf(
					'%s #%d — %s%s',
					$type === 'order' ? 'Confirmación de pedido' : 'Cotización formal',
					$quote_id,
					glotracol_quote_format_price( $total ),
					$type === 'order' ? '' : ''
				);
				$cust_template = 'email-customer-priced.php';
			} else {
				// Confirmación simple (cliente no recibe precios cuando hay pendientes)
				$cust_subject = glotracol_quote_replace_placeholders( $settings['customer_subject'], $placeholders );
				$cust_template = 'email-customer.php';
			}
			$cust_body = glotracol_quote_load_template( $cust_template, [
				'quote_id'    => $quote_id,
				'customer'    => $customer,
				'items'       => $items,
				'intro'       => glotracol_quote_replace_placeholders( $settings['customer_intro'], $placeholders ),
				'type'        => $type,
				'total'       => $total,
				'client_name' => $client_name,
			] );
			$cust_body = apply_filters( 'glotracol_quote_email_customer_body', $cust_body, $quote_id, $payload );
			$cust_ok = wp_mail( $customer['email'], $cust_subject, $cust_body, $headers );
			$this->log( $quote_id, $is_auto_priced ? 'customer-priced' : 'customer', $customer['email'], $cust_ok );
			if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
				Glotracol_Quote_Logger::log( $cust_ok ? 'info' : 'error', 'email', sprintf( 'Email cliente %s #%d %s', $is_auto_priced ? '(auto-priced)' : '(confirmación)', $quote_id, $cust_ok ? 'enviado' : 'FALLÓ' ), [
					'quote_id' => $quote_id, 'to' => $customer['email'], 'template' => $cust_template, 'success' => $cust_ok,
				] );
			}
		}
	}

	private function log( $quote_id, $type, $to, $success ) {
		$log = get_post_meta( $quote_id, '_glo_email_log', true );
		if ( ! is_array( $log ) ) $log = [];
		$log[] = [
			'type'    => $type,
			'to'      => $to,
			'sent_at' => current_time( 'mysql' ),
			'success' => (bool) $success,
		];
		update_post_meta( $quote_id, '_glo_email_log', $log );
	}
}
