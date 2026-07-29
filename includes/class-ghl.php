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
		add_action( 'wp_ajax_gloq_ghl_test', [ $this, 'ajax_test' ] );
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

	/** Parte el nombre del formulario: primera palabra nombre, el resto apellido. */
	private static function split_name( $full ) {
		$full = trim( preg_replace( '/\s+/', ' ', (string) $full ) );
		if ( $full === '' ) return [ '', '' ];
		$pos = strpos( $full, ' ' );
		return $pos === false ? [ $full, '' ] : [ substr( $full, 0, $pos ), substr( $full, $pos + 1 ) ];
	}

	/** @return string|WP_Error contactId */
	public static function upsert_contact( $customer ) {
		list( $first, $last ) = self::split_name( $customer['name'] ?? '' );
		$body = [
			'firstName'   => $first,
			'lastName'    => $last,
			'email'       => (string) ( $customer['email'] ?? '' ),
			'companyName' => (string) ( $customer['company'] ?? '' ),
			'city'        => (string) ( $customer['city'] ?? '' ),
			'locationId'  => self::location_id(),
		];
		$phone = trim( (string) ( $customer['phone'] ?? '' ) );
		if ( $phone !== '' ) $body['phone'] = $phone;

		$r = self::request( 'POST', '/contacts/upsert', $body );
		if ( is_wp_error( $r ) ) return $r;
		$id = (string) ( $r['contact']['id'] ?? '' );
		return $id !== '' ? $id : new WP_Error( 'ghl_http', 'GoHighLevel no devolvió el id del contacto' );
	}

	/**
	 * @param array $datos [ 'name' => string, 'monetary_value' => int, 'stage_id' => string ]
	 * @return string|WP_Error opportunityId
	 */
	public static function create_opportunity( $contact_id, $datos ) {
		$body = [
			'pipelineId'      => (string) glotracol_quote_get_setting( 'ghl_pipeline_id' ),
			'locationId'      => self::location_id(),
			'name'            => (string) $datos['name'],
			'pipelineStageId' => (string) $datos['stage_id'],
			'status'          => 'open',
			'contactId'       => (string) $contact_id,
			'monetaryValue'   => (int) $datos['monetary_value'],
		];
		$r = self::request( 'POST', '/opportunities/upsert', $body );
		if ( is_wp_error( $r ) ) return $r;
		$id = (string) ( $r['opportunity']['id'] ?? '' );
		return $id !== '' ? $id : new WP_Error( 'ghl_http', 'GoHighLevel no devolvió el id de la oportunidad' );
	}

	/** @return true|WP_Error */
	public static function add_note( $contact_id, $texto ) {
		$r = self::request( 'POST', '/contacts/' . rawurlencode( $contact_id ) . '/notes', [ 'body' => $texto ] );
		return is_wp_error( $r ) ? $r : true;
	}

	/** Detalle de la cotización en texto plano, para pegar en la nota de GHL. */
	public static function note_text( $quote_id ) {
		$quote_id = (int) $quote_id;
		$items = glotracol_quote_enrich_items( get_post_meta( $quote_id, '_glo_items', true ) ?: [] );
		$total = (int) get_post_meta( $quote_id, '_glo_total', true );
		$peso  = (float) get_post_meta( $quote_id, '_glo_weight_total_kg', true );
		$nit   = (string) get_post_meta( $quote_id, '_glo_customer_nit', true );

		$l = [ 'Cotización #' . $quote_id ];
		if ( $nit !== '' ) $l[] = 'NIT: ' . $nit;
		$l[] = '';
		foreach ( $items as $it ) {
			$l[] = sprintf(
				'%d x %s | %s | %s | subtotal %s',
				(int) ( $it['quantity'] ?? 0 ),
				(string) ( $it['name'] ?? '' ),
				(string) ( $it['empaque'] ?? '—' ),
				(string) ( $it['presentacion'] ?? '—' ),
				(string) ( $it['precio_sub_fmt'] ?? '—' )
			);
		}
		$l[] = '';
		if ( $peso > 0 ) $l[] = 'Peso total: ' . number_format( $peso, 2, ',', '.' ) . ' kg';
		$l[] = 'TOTAL: ' . glotracol_quote_format_price( $total );
		$l[] = '';
		$l[] = 'Ver en el panel: ' . admin_url( 'post.php?post=' . $quote_id . '&action=edit' );
		return implode( "\n", $l );
	}

	/**
	 * Orquesta los tres pasos. Cada uno guarda su resultado, así un reintento
	 * retoma donde falló en vez de duplicar lo ya creado.
	 *
	 * @return true|WP_Error
	 */
	public static function send_quote( $quote_id ) {
		$quote_id = (int) $quote_id;
		$post = get_post( $quote_id );
		if ( ! $post || $post->post_type !== 'glo_quote' ) {
			return new WP_Error( 'ghl_config', 'La cotización no existe' );
		}
		if ( ! self::is_configured() ) {
			return new WP_Error( 'ghl_config', 'Falta el token o el Location ID de GoHighLevel' );
		}
		$pipeline = (string) glotracol_quote_get_setting( 'ghl_pipeline_id' );
		if ( $pipeline === '' ) {
			return new WP_Error( 'ghl_config', 'Falta elegir el pipeline de GoHighLevel' );
		}

		// Etapa según el estado de precios.
		$pricing = (string) get_post_meta( $quote_id, '_glo_pricing_status', true );
		$stage   = (string) glotracol_quote_get_setting( 'ghl_stage_id' );
		if ( $pricing !== 'priced' ) {
			$pend = (string) glotracol_quote_get_setting( 'ghl_stage_id_pending' );
			if ( $pend !== '' ) $stage = $pend;
		}
		if ( $stage === '' ) {
			return new WP_Error( 'ghl_config', 'Falta elegir la etapa de GoHighLevel' );
		}

		// --- Paso 1: contacto ---
		$contact_id = (string) get_post_meta( $quote_id, '_glo_ghl_contact_id', true );
		if ( $contact_id === '' ) {
			$r = self::upsert_contact( [
				'name'    => get_post_meta( $quote_id, '_glo_customer_name', true ),
				'email'   => get_post_meta( $quote_id, '_glo_customer_email', true ),
				'phone'   => get_post_meta( $quote_id, '_glo_customer_phone', true ),
				'company' => get_post_meta( $quote_id, '_glo_customer_company', true ),
				'city'    => get_post_meta( $quote_id, '_glo_customer_city', true ),
			] );
			if ( is_wp_error( $r ) ) return $r;
			$contact_id = $r;
			update_post_meta( $quote_id, '_glo_ghl_contact_id', $contact_id );
		}

		// --- Paso 2: oportunidad ---
		$opp_id = (string) get_post_meta( $quote_id, '_glo_ghl_opportunity_id', true );
		if ( $opp_id === '' ) {
			$nombre = sprintf(
				'%s #%d — %s',
				get_post_meta( $quote_id, '_glo_type', true ) === 'order' ? 'Pedido' : 'Cotización',
				$quote_id,
				(string) get_post_meta( $quote_id, '_glo_customer_name', true )
			);
			$r = self::create_opportunity( $contact_id, [
				'name'           => $nombre,
				'monetary_value' => (int) get_post_meta( $quote_id, '_glo_total', true ),
				'stage_id'       => $stage,
			] );
			if ( is_wp_error( $r ) ) return $r;
			$opp_id = $r;
			update_post_meta( $quote_id, '_glo_ghl_opportunity_id', $opp_id );
		}

		// --- Paso 3: nota. Si falla, la oportunidad ya existe y es lo importante. ---
		if ( ! get_post_meta( $quote_id, '_glo_ghl_note_ok', true ) ) {
			$r = self::add_note( $contact_id, self::note_text( $quote_id ) );
			if ( is_wp_error( $r ) ) {
				Glotracol_Quote_Logger::warn( 'ghl', 'La oportunidad se creó pero la nota falló: ' . $r->get_error_message(), [ 'quote_id' => $quote_id ] );
			} else {
				update_post_meta( $quote_id, '_glo_ghl_note_ok', 1 );
			}
		}

		Glotracol_Quote_Logger::info( 'ghl', sprintf( 'Cotización #%d enviada a GoHighLevel', $quote_id ), [
			'quote_id' => $quote_id, 'contact_id' => $contact_id, 'opportunity_id' => $opp_id,
		] );
		return true;
	}

	public function schedule_dispatch( $quote_id, $payload ) {
		if ( glotracol_quote_get_setting( 'ghl_enabled' ) !== 'yes' ) return;
		if ( ! self::is_configured() ) return;
		// Async a 5 segundos: el cliente no espera a GHL para ver su página de gracias.
		wp_schedule_single_event( time() + 5, self::HOOK, [ (int) $quote_id ] );
	}

	/** Prueba la conexión desde la pantalla de Ajustes. Nunca devuelve el token. */
	public function ajax_test() {
		check_ajax_referer( 'gloq_ghl_test', '_wpnonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Sin permisos' ] );
		}
		if ( ! self::is_configured() ) {
			wp_send_json_error( [ 'message' => 'Falta el token o el Location ID. Guarda los ajustes primero.' ] );
		}
		$p = self::pipelines( true );
		if ( empty( $p ) ) {
			wp_send_json_error( [ 'message' => 'GoHighLevel no devolvió pipelines. Revisa que el token tenga el permiso opportunities.readonly y que el Location ID sea el correcto.' ] );
		}
		$nombres = array_map( function ( $x ) { return $x['name']; }, array_values( $p ) );
		wp_send_json_success( [
			'message' => sprintf( 'Conexión correcta. %d pipelines encontrados: %s', count( $p ), implode( ', ', $nombres ) ),
		] );
	}

	public function dispatch( $quote_id ) {
		if ( glotracol_quote_get_setting( 'ghl_enabled' ) !== 'yes' ) return;

		$r = self::send_quote( (int) $quote_id );
		if ( $r === true ) {
			delete_post_meta( $quote_id, '_glo_ghl_attempts' );
			return;
		}

		$code = $r->get_error_code();
		Glotracol_Quote_Logger::error( 'ghl', 'Fallo enviando a GoHighLevel: ' . $r->get_error_message(), [
			'quote_id' => $quote_id, 'code' => $code,
		] );

		// Credencial mala o configuración incompleta: reintentar no lo arregla.
		if ( $code === 'ghl_auth' || $code === 'ghl_config' ) return;

		$attempts = (int) get_post_meta( $quote_id, '_glo_ghl_attempts', true ) + 1;
		update_post_meta( $quote_id, '_glo_ghl_attempts', $attempts );
		$backoffs = [ 60, 300, 900 ];
		if ( isset( $backoffs[ $attempts - 1 ] ) ) {
			wp_schedule_single_event( time() + $backoffs[ $attempts - 1 ], self::HOOK, [ (int) $quote_id ] );
			Glotracol_Quote_Logger::info( 'ghl', sprintf( 'Reintento #%d programado en %ds', $attempts, $backoffs[ $attempts - 1 ] ), [ 'quote_id' => $quote_id ] );
		} else {
			Glotracol_Quote_Logger::error( 'ghl', 'GoHighLevel agotó los reintentos', [ 'quote_id' => $quote_id, 'attempts' => $attempts ] );
		}
	}
}
