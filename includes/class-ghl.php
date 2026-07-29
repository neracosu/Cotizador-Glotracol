<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Integración con GoHighLevel vía API v2 (token de Integración Privada).
 *
 * Única responsabilidad: hablar con GHL. No conoce plantillas ni correos.
 * Todo el HTTP sale por request(), así que un cambio en la API se ajusta en un solo sitio.
 */
class Glotracol_Quote_GHL {

	const BASE      = 'https://services.leadconnectorhq.com';
	const API_VER   = '2021-07-28';
	const HOOK      = 'glotracol_quote_ghl_dispatch';
	const CACHE_KEY = 'gloq_ghl_pipelines';

	public function __construct() {
		add_action( 'glotracol_quote_created', [ $this, 'schedule_dispatch' ], 30, 2 );
		add_action( self::HOOK, [ $this, 'dispatch' ], 10, 1 );
	}

	public static function token() {
		return trim( (string) glotracol_quote_get_setting( 'ghl_token' ) );
	}

	public static function location_id() {
		return trim( (string) glotracol_quote_get_setting( 'ghl_location_id' ) );
	}

	/** Hay credenciales suficientes para intentar cualquier llamada. */
	public static function is_configured() {
		return self::token() !== '' && self::location_id() !== '';
	}

	/**
	 * Único punto de salida HTTP.
	 *
	 * @return array|WP_Error Cuerpo decodificado, o WP_Error con código
	 *                        ghl_auth | ghl_http | ghl_net.
	 */
	public static function request( $method, $path, $body = null ) {
		$args = [
			'method'  => strtoupper( $method ),
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'Bearer ' . self::token(),
				'Version'       => self::API_VER,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			],
		];
		if ( $body !== null ) {
			$args['body'] = wp_json_encode( $body );
		}

		$resp = wp_remote_request( self::BASE . $path, $args );

		if ( is_wp_error( $resp ) ) {
			// Fallo de red: reintentable.
			return new WP_Error( 'ghl_net', $resp->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );

		if ( $code === 401 || $code === 403 ) {
			// Credencial mala: reintentar no lo arregla.
			return new WP_Error( 'ghl_auth', 'Token rechazado por GoHighLevel (HTTP ' . $code . ')' );
		}
		if ( $code >= 400 ) {
			$msg = is_array( $data ) && ! empty( $data['message'] ) ? (string) $data['message'] : 'HTTP ' . $code;
			return new WP_Error( 'ghl_http', $msg, [ 'status' => $code ] );
		}

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Pipelines con sus etapas, normalizados e indexados por id.
	 *
	 * @return array [ id => [ 'name' => string, 'stages' => [ id => name ] ] ]
	 */
	public static function pipelines( $force = false ) {
		if ( ! $force ) {
			$cache = get_transient( self::CACHE_KEY );
			if ( is_array( $cache ) ) return $cache;
		}
		if ( ! self::is_configured() ) return [];

		$data = self::request( 'GET', '/opportunities/pipelines?locationId=' . rawurlencode( self::location_id() ) );
		if ( is_wp_error( $data ) ) {
			Glotracol_Quote_Logger::error( 'ghl', 'No se pudieron leer los pipelines: ' . $data->get_error_message() );
			return [];
		}

		$out = [];
		foreach ( (array) ( $data['pipelines'] ?? [] ) as $p ) {
			$pid = (string) ( $p['id'] ?? '' );
			if ( $pid === '' ) continue;
			$stages = [];
			foreach ( (array) ( $p['stages'] ?? [] ) as $s ) {
				$sid = (string) ( $s['id'] ?? '' );
				if ( $sid !== '' ) $stages[ $sid ] = (string) ( $s['name'] ?? $sid );
			}
			$out[ $pid ] = [ 'name' => (string) ( $p['name'] ?? $pid ), 'stages' => $stages ];
		}

		set_transient( self::CACHE_KEY, $out, HOUR_IN_SECONDS );
		return $out;
	}

	// Implementados en la Task 3; declarados ya para que el constructor no enganche a nada inexistente.
	public function schedule_dispatch( $quote_id, $payload ) {}

	public function dispatch( $quote_id ) {}
}
