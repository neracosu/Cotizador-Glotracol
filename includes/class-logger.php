<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Logger centralizado del plugin.
 *
 * Almacena entradas en option `glotracol_quote_log` como array rolling
 * (default 500 entradas). Cada entrada:
 *
 *   [ ts: 'Y-m-d H:i:s', level: 'debug|info|warn|error', cat: '...', msg: '...', context: array ]
 *
 * Diseño:
 *  - Lockless append + trim a tamaño máximo. No usa cron.
 *  - El option no es autoload (false) para no inflar memoria en cada request.
 *  - También escribe a `error_log()` cuando level >= 'warn' (mejor visibilidad).
 *
 * Uso:
 *   Glotracol_Quote_Logger::info( 'import', 'Importadas X filas', [ 'type' => 'clientes', 'count' => 12 ] );
 *   Glotracol_Quote_Logger::warn( 'wc_compat', 'WC()->cart no disponible' );
 *   Glotracol_Quote_Logger::error( 'email', 'wp_mail falló', [ 'to' => $to, 'reason' => $err ] );
 */
class Glotracol_Quote_Logger {

	const OPTION = 'glotracol_quote_log';
	const MAX_ENTRIES = 500;

	const LEVEL_DEBUG = 'debug';
	const LEVEL_INFO  = 'info';
	const LEVEL_WARN  = 'warn';
	const LEVEL_ERROR = 'error';

	/* Conveniencia */
	public static function debug( $cat, $msg, $context = [] ) { self::log( self::LEVEL_DEBUG, $cat, $msg, $context ); }
	public static function info( $cat, $msg, $context = [] )  { self::log( self::LEVEL_INFO, $cat, $msg, $context ); }
	public static function warn( $cat, $msg, $context = [] )  { self::log( self::LEVEL_WARN, $cat, $msg, $context ); }
	public static function error( $cat, $msg, $context = [] ) { self::log( self::LEVEL_ERROR, $cat, $msg, $context ); }

	/**
	 * Registra una entrada de log.
	 *
	 * @param string $level    debug|info|warn|error
	 * @param string $cat      Categoría libre: import, email, webhook, swap, conversion, wc_compat, etc.
	 * @param string $msg      Mensaje human-readable.
	 * @param array  $context  Array asociativo con datos adicionales (será serializado a JSON).
	 */
	public static function log( $level, $cat, $msg, $context = [] ) {
		$entry = [
			'ts'      => current_time( 'mysql' ),
			'level'   => in_array( $level, [ self::LEVEL_DEBUG, self::LEVEL_INFO, self::LEVEL_WARN, self::LEVEL_ERROR ], true ) ? $level : self::LEVEL_INFO,
			'cat'     => sanitize_key( $cat ),
			'msg'     => (string) $msg,
			'context' => is_array( $context ) ? $context : [],
			'user'    => get_current_user_id(),
		];

		$log = get_option( self::OPTION, [] );
		if ( ! is_array( $log ) ) $log = [];
		// Append + trim
		array_unshift( $log, $entry );
		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_ENTRIES );
		}
		update_option( self::OPTION, $log, false );

		// Mirror a error_log() para warn/error (visible en debug.log si WP_DEBUG_LOG)
		if ( $entry['level'] === self::LEVEL_WARN || $entry['level'] === self::LEVEL_ERROR ) {
			$ctx_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
			error_log( '[Glotracol ' . strtoupper( $entry['level'] ) . '][' . $entry['cat'] . '] ' . $entry['msg'] . $ctx_str );
		}

		do_action( 'glotracol_quote_logged', $entry );
	}

	/**
	 * Devuelve las últimas N entradas (más reciente primero), opcionalmente filtradas.
	 *
	 * @param int    $limit
	 * @param string $level_filter '' | 'debug' | 'info' | 'warn' | 'error'
	 * @param string $cat_filter
	 * @return array<int, array>
	 */
	public static function get_entries( $limit = 100, $level_filter = '', $cat_filter = '' ) {
		$log = get_option( self::OPTION, [] );
		if ( ! is_array( $log ) ) return [];
		if ( $level_filter || $cat_filter ) {
			$log = array_values( array_filter( $log, function ( $e ) use ( $level_filter, $cat_filter ) {
				if ( $level_filter && ( $e['level'] ?? '' ) !== $level_filter ) return false;
				if ( $cat_filter && ( $e['cat'] ?? '' ) !== $cat_filter ) return false;
				return true;
			} ) );
		}
		return array_slice( $log, 0, max( 1, (int) $limit ) );
	}

	/**
	 * Cuenta entradas por nivel (para badge en dashboard).
	 */
	public static function counts() {
		$log = get_option( self::OPTION, [] );
		if ( ! is_array( $log ) ) return [ 'total' => 0, 'debug' => 0, 'info' => 0, 'warn' => 0, 'error' => 0 ];
		$out = [ 'total' => count( $log ), 'debug' => 0, 'info' => 0, 'warn' => 0, 'error' => 0 ];
		foreach ( $log as $e ) {
			$lvl = $e['level'] ?? 'info';
			if ( isset( $out[ $lvl ] ) ) $out[ $lvl ]++;
		}
		return $out;
	}

	/**
	 * Lista de categorías presentes en el log (para el filtro de la UI).
	 */
	public static function categories() {
		$log = get_option( self::OPTION, [] );
		if ( ! is_array( $log ) ) return [];
		$cats = [];
		foreach ( $log as $e ) {
			$c = $e['cat'] ?? '';
			if ( $c ) $cats[ $c ] = ( $cats[ $c ] ?? 0 ) + 1;
		}
		ksort( $cats );
		return $cats;
	}

	/**
	 * Vacía el log entero.
	 */
	public static function clear() {
		update_option( self::OPTION, [], false );
	}
}
