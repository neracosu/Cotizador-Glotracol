<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_SMTP {

	public function __construct() {
		add_action( 'phpmailer_init', [ $this, 'configure_phpmailer' ], 100 );
		add_action( 'wp_ajax_gloq_smtp_test', [ $this, 'ajax_send_test' ] );
	}

	public function configure_phpmailer( $phpmailer ) {
		$s = glotracol_quote_get_settings();
		if ( empty( $s['smtp_enabled'] ) || $s['smtp_enabled'] !== 'yes' ) return;
		if ( empty( $s['smtp_host'] ) || empty( $s['smtp_port'] ) ) return;

		$phpmailer->isSMTP();
		$phpmailer->Host     = $s['smtp_host'];
		$phpmailer->Port     = (int) $s['smtp_port'];
		$phpmailer->SMTPAuth = ! empty( $s['smtp_username'] );
		if ( $phpmailer->SMTPAuth ) {
			$phpmailer->Username = $s['smtp_username'];
			$phpmailer->Password = $s['smtp_password'];
		}
		$enc = $s['smtp_encryption'] ?? 'tls';
		if ( $enc === 'ssl' ) {
			$phpmailer->SMTPSecure = 'ssl';
		} elseif ( $enc === 'tls' ) {
			$phpmailer->SMTPSecure = 'tls';
		} else {
			$phpmailer->SMTPSecure = '';
			$phpmailer->SMTPAutoTLS = false;
		}

		// From override (no rompe si el caller ya seteó uno)
		if ( ! empty( $s['smtp_from_email'] ) && is_email( $s['smtp_from_email'] ) ) {
			$from_name = ! empty( $s['smtp_from_name'] ) ? $s['smtp_from_name'] : $s['smtp_from_email'];
			try {
				$phpmailer->setFrom( $s['smtp_from_email'], $from_name, false );
			} catch ( \Exception $e ) {
				// silencioso
			}
		}
	}

	public function ajax_send_test() {
		check_ajax_referer( 'gloq_smtp_test', '_wpnonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Sin permisos' ] );
		}
		$to = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );
		if ( ! is_email( $to ) ) {
			wp_send_json_error( [ 'message' => 'Email inválido' ] );
		}

		$body = '<!DOCTYPE html><html><body style="font-family:Arial;padding:20px"><h2 style="color:#0a4d3a">Test de SMTP — Glotracol Cotizador</h2><p>Este es un email de prueba enviado desde el plugin <strong>Glotracol Cotizador</strong> para verificar que tu configuración de envío funciona correctamente.</p><p><strong>Fecha:</strong> ' . esc_html( current_time( 'd/m/Y H:i:s' ) ) . '<br><strong>Servidor:</strong> ' . esc_html( $_SERVER['SERVER_NAME'] ?? '' ) . '</p><p>Si ves este mensaje, la configuración de envío funciona correctamente.</p><hr><p style="font-size:11px;color:#888">Plugin desarrollado por <a href="https://neracosu.com/">Neracosu</a></p></body></html>';

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Capturar errores de wp_mail
		$error_messages = [];
		$capture = function ( $wp_error ) use ( &$error_messages ) {
			$error_messages[] = $wp_error->get_error_message();
		};
		add_action( 'wp_mail_failed', $capture );

		$ok = wp_mail( $to, 'Test SMTP — Glotracol Cotizador', $body, $headers );

		remove_action( 'wp_mail_failed', $capture );

		if ( $ok ) {
			wp_send_json_success( [ 'message' => 'Email enviado a ' . $to . '. Revisa la bandeja (puede tardar unos segundos).' ] );
		} else {
			$detail = ! empty( $error_messages ) ? ' Detalle: ' . implode( ' · ', $error_messages ) : '';
			wp_send_json_error( [ 'message' => 'No se pudo enviar.' . $detail ] );
		}
	}

	/**
	 * Detecta si otro plugin SMTP está manejando el envío.
	 * Devuelve el nombre legible o null.
	 */
	public static function detect_external_smtp() {
		$candidates = [
			'SiteMailer\\Plugin'                  => 'Site Mailer',
			'Site_Mailer\\Plugin'                 => 'Site Mailer',
			'WPMailSMTP\\Core'                    => 'WP Mail SMTP',
			'WPMailSMTP\\Pro\\Pro'                => 'WP Mail SMTP Pro',
			'EasyWPSMTP\\Plugin'                  => 'Easy WP SMTP',
			'FluentMail\\App\\Application'        => 'FluentSMTP',
			'Post_SMTP_Mailer\\Postman'           => 'Post SMTP',
			'Wpo365\\Wpo365_Plugin'               => 'WPO365',
		];
		// Active plugin slug fallback
		if ( function_exists( 'is_plugin_active' ) ) {
			$active_slugs = [
				'site-mailer/plugin.php'                => 'Site Mailer',
				'wp-mail-smtp/wp_mail_smtp.php'         => 'WP Mail SMTP',
				'easy-wp-smtp/easy-wp-smtp.php'         => 'Easy WP SMTP',
				'fluent-smtp/fluent-smtp.php'           => 'FluentSMTP',
				'post-smtp/postman-smtp.php'            => 'Post SMTP',
			];
			foreach ( $active_slugs as $slug => $name ) {
				if ( is_plugin_active( $slug ) ) return $name;
			}
		}
		foreach ( $candidates as $cls => $name ) {
			if ( class_exists( $cls ) ) return $name;
		}
		// Función-based detection
		if ( function_exists( 'easy_wp_smtp' ) ) return 'Easy WP SMTP';
		if ( defined( 'POST_SMTP_VER' ) ) return 'Post SMTP';
		// Constante de WP-Mail-SMTP en plugins viejos
		if ( defined( 'WPMS_PLUGIN_VER' ) ) return 'WP Mail SMTP';
		return null;
	}
}
