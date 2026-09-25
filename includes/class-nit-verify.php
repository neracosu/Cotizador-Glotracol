<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Verificacion por codigo antes de mostrar precios negociados.
 *
 * Un NIT es publico: no puede servir de credencial. Cuando el cliente escribe su NIT y
 * pide el codigo, si ese NIT es de un cliente activo con correo registrado se le manda
 * un codigo de 6 digitos A ESE CORREO (nunca al que escribio en el formulario). Con el
 * codigo correcto, la sesion de WooCommerce queda verificada para ese cliente por 12 h
 * y recien ahi el formulario muestra y cotiza con sus precios.
 *
 * La respuesta publica es siempre la misma, exista o no el cliente, para que nadie
 * pueda averiguar que NIT son clientes. El codigo se guarda con hash.
 */
class Glotracol_Quote_NIT_Verify {

	const TTL          = 600;      // vida del codigo (10 min)
	const MAX_ATTEMPTS = 5;        // intentos por codigo
	const PER_NIT_HOUR = 5;        // codigos por NIT por hora
	const PER_IP_HOUR  = 10;       // pedidos de codigo por IP por hora
	const VERIFY_IP_HOUR = 30;     // intentos de verificacion por IP por hora
	const VERIFIED_FOR = 12 * HOUR_IN_SECONDS;
	const SESSION_KEY  = 'gloq_verified';

	public function __construct() {
		add_action( 'wp_ajax_gloq_nit_code_request', [ $this, 'ajax_request' ] );
		add_action( 'wp_ajax_nopriv_gloq_nit_code_request', [ $this, 'ajax_request' ] );
		add_action( 'wp_ajax_gloq_nit_code_verify', [ $this, 'ajax_verify' ] );
		add_action( 'wp_ajax_nopriv_gloq_nit_code_verify', [ $this, 'ajax_verify' ] );
	}

	/** Mensaje unico que ve el visitante al pedir el codigo. */
	public static function public_message() {
		return 'Si tu NIT corresponde a un cliente con precios acordados, te enviamos un código de 6 dígitos al correo registrado de tu empresa. Revisa también la carpeta de spam.';
	}

	public static function normalize( $nit ) {
		return Glotracol_Quote_Client_CPT::normalize_nit( $nit );
	}

	/** Carga sesion y carrito de WooCommerce en admin-ajax/admin-post. */
	public static function ensure_session() {
		if ( ! function_exists( 'WC' ) ) return false;
		if ( ( ! WC()->session || ! WC()->cart ) && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! WC()->session ) return false;
		if ( method_exists( WC()->session, 'has_session' ) && ! WC()->session->has_session() && ! headers_sent() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		return true;
	}

	private static function session_id() {
		return ( function_exists( 'WC' ) && WC()->session ) ? (string) WC()->session->get_customer_id() : '';
	}

	/** Clave del transient del codigo: por sesion y por NIT. */
	public static function otp_key( $nit ) {
		return 'gloq_otp_' . md5( self::session_id() . '|' . self::normalize( $nit ) );
	}

	private static function cooldown_key( $nit ) {
		return 'gloq_otp_cd_' . md5( self::session_id() . '|' . self::normalize( $nit ) );
	}

	private static function hash_code( $code ) {
		return hash_hmac( 'sha256', (string) $code, wp_salt( 'auth' ) );
	}

	/**
	 * Pide un codigo. Devuelve siempre el mismo mensaje. El correo sale solo si el NIT
	 * es de un cliente activo con correo, y dentro de los limites.
	 *
	 * @return array{message:string}
	 */
	public static function request_code( $nit, $ip ) {
		$out  = [ 'message' => self::public_message() ];
		$norm = self::normalize( $nit );
		if ( strlen( $norm ) < 5 ) return $out;

		// Espera entre pedidos: se marca exista o no el cliente, para no delatarlo.
		$cooldown = (int) apply_filters( 'gloq_otp_cooldown', 60 );
		if ( $cooldown > 0 ) {
			if ( get_transient( self::cooldown_key( $norm ) ) ) return $out;
			set_transient( self::cooldown_key( $norm ), 1, $cooldown );
		}
		// Limites: se cuentan para cualquier NIT.
		$ok_ip  = Glotracol_Quote_Rate_Limit::hit( 'otp_ip', $ip, self::PER_IP_HOUR );
		$ok_nit = Glotracol_Quote_Rate_Limit::hit( 'nit', $norm, self::PER_NIT_HOUR );
		if ( ! $ok_ip || ! $ok_nit ) return $out;

		$client_id = (int) glotracol_quote_find_client_by_nit( $norm );
		if ( ! $client_id ) return $out;
		$email = sanitize_email( (string) get_post_meta( $client_id, '_glo_client_email', true ) );
		if ( ! is_email( $email ) ) return $out;

		$code = (string) random_int( 100000, 999999 );
		set_transient( self::otp_key( $norm ), [
			'hash'     => self::hash_code( $code ),
			'client'   => $client_id,
			'attempts' => 0,
			'exp'      => time() + self::TTL,
		], self::TTL );

		$ok = self::send_code_email( $email, $code, $client_id );
		Glotracol_Quote_Logger::log( $ok ? 'info' : 'error', 'nit_verify', sprintf( 'Código de verificación %s al cliente #%d', $ok ? 'enviado' : 'NO enviado', $client_id ), [ 'client_id' => $client_id ] );
		return $out;
	}

	/** Verifica el codigo. true si es correcto; deja la sesion verificada para ese cliente. */
	public static function verify_code( $nit, $code, $ip ) {
		$norm = self::normalize( $nit );
		$code = preg_replace( '/\D/', '', (string) $code );
		if ( $norm === '' || strlen( $code ) !== 6 ) return false;
		if ( ! Glotracol_Quote_Rate_Limit::hit( 'otp_verify_ip', $ip, self::VERIFY_IP_HOUR ) ) return false;

		$key = self::otp_key( $norm );
		$st  = get_transient( $key );
		if ( ! is_array( $st ) || empty( $st['hash'] ) ) return false;
		if ( (int) ( $st['exp'] ?? 0 ) < time() || (int) ( $st['attempts'] ?? 0 ) >= self::MAX_ATTEMPTS ) {
			delete_transient( $key );
			return false;
		}
		if ( ! hash_equals( (string) $st['hash'], self::hash_code( $code ) ) ) {
			$st['attempts'] = (int) $st['attempts'] + 1;
			if ( $st['attempts'] >= self::MAX_ATTEMPTS ) {
				delete_transient( $key );
			} else {
				set_transient( $key, $st, max( 1, (int) $st['exp'] - time() ) );
			}
			return false;
		}
		delete_transient( $key );

		// El cliente pudo desactivarse o cambiar de NIT mientras tanto.
		$client_id = (int) $st['client'];
		if ( (int) glotracol_quote_find_client_by_nit( $norm ) !== $client_id ) return false;

		if ( function_exists( 'WC' ) && WC()->session ) {
			$v = (array) WC()->session->get( self::SESSION_KEY, [] );
			$v[ $norm ] = [ 'client' => $client_id, 'until' => time() + self::VERIFIED_FOR ];
			WC()->session->set( self::SESSION_KEY, $v );
		}
		Glotracol_Quote_Logger::log( 'info', 'nit_verify', sprintf( 'Cliente #%d verificado por código', $client_id ), [ 'client_id' => $client_id ] );
		return true;
	}

	/**
	 * Cliente verificado en esta sesion para ese NIT, o 0. Solo esto habilita precios
	 * negociados en el formulario, en el recalculo y al guardar la cotizacion.
	 */
	public static function verified_client_id( $nit ) {
		$norm = self::normalize( $nit );
		if ( $norm === '' || ! function_exists( 'WC' ) || ! WC()->session ) return 0;
		$v = (array) WC()->session->get( self::SESSION_KEY, [] );
		if ( empty( $v[ $norm ] ) || (int) ( $v[ $norm ]['until'] ?? 0 ) < time() ) return 0;
		$client_id = (int) $v[ $norm ]['client'];
		// Debe seguir siendo el cliente activo de ese NIT.
		return (int) glotracol_quote_find_client_by_nit( $norm ) === $client_id ? $client_id : 0;
	}

	/** Olvida la verificacion y el codigo pendiente de ese NIT en esta sesion. */
	public static function forget( $nit ) {
		$norm = self::normalize( $nit );
		delete_transient( self::otp_key( $norm ) );
		delete_transient( self::cooldown_key( $norm ) );
		if ( function_exists( 'WC' ) && WC()->session ) {
			$v = (array) WC()->session->get( self::SESSION_KEY, [] );
			unset( $v[ $norm ] );
			WC()->session->set( self::SESSION_KEY, $v );
		}
	}

	private static function send_code_email( $to, $code, $client_id ) {
		$settings   = glotracol_quote_get_settings();
		$site       = get_bloginfo( 'name' );
		$from_name  = $settings['sender_name'] ?: $site;
		$from_email = is_email( $settings['sender_email'] ) ? $settings['sender_email'] : get_option( 'admin_email' );
		$brand      = glotracol_quote_brand();
		$minutes    = (int) ( self::TTL / 60 );
		$body = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;color:#1a1a1a">'
			. '<p>Hola,</p>'
			. '<p>Alguien pidió ver los precios acordados de tu empresa en el cotizador de ' . esc_html( $site ) . '. Tu código es:</p>'
			. '<p style="font-size:28px;font-weight:bold;letter-spacing:6px;text-align:center;background:' . esc_attr( $brand['tint'] ) . ';border:1px solid ' . esc_attr( $brand['line'] ) . ';padding:14px;border-radius:6px">' . esc_html( $code ) . '</p>'
			. '<p>Vence en ' . $minutes . ' minutos. Si no fuiste tú, ignora este mensaje: sin el código nadie puede ver tus precios.</p>'
			. '<p style="color:#666;font-size:12px">' . esc_html( $site ) . '</p></div>';
		$headers = [ 'Content-Type: text/html; charset=UTF-8', sprintf( 'From: %s <%s>', $from_name, $from_email ) ];
		return (bool) wp_mail( $to, sprintf( 'Tu código para ver tus precios — %s', $site ), $body, $headers );
	}

	public function ajax_request() {
		check_ajax_referer( 'gloq_nit_verify', '_wpnonce' );
		self::ensure_session();
		$nit = isset( $_POST['nit'] ) ? sanitize_text_field( wp_unslash( $_POST['nit'] ) ) : '';
		wp_send_json_success( self::request_code( $nit, glotracol_quote_get_client_ip() ) );
	}

	public function ajax_verify() {
		check_ajax_referer( 'gloq_nit_verify', '_wpnonce' );
		self::ensure_session();
		$nit  = isset( $_POST['nit'] ) ? sanitize_text_field( wp_unslash( $_POST['nit'] ) ) : '';
		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		if ( ! self::verify_code( $nit, $code, glotracol_quote_get_client_ip() ) ) {
			wp_send_json_error( [ 'message' => 'El código no es correcto o ya venció. Pide uno nuevo.' ] );
		}
		wp_send_json_success( [ 'message' => 'Listo: ya ves los precios acordados de tu empresa.' ] );
	}
}
