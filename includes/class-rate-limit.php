<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Limites de uso por ventana de una hora.
 *
 * El contador es una fila de wp_options que se incrementa con una sola sentencia
 * (INSERT ... ON DUPLICATE KEY UPDATE): dos envios en paralelo no pueden leer el
 * mismo valor y pasar los dos. Se cuentan intentos, no solo envios exitosos.
 *
 * Alcances usados: 'ip', 'email' (correo destino), 'global', 'nit' y 'otp'.
 */
class Glotracol_Quote_Rate_Limit {

	const PREFIX    = 'gloq_rl_';
	const PURGE_HOOK = 'gloq_rate_limit_purge';

	public static function init() {
		add_action( self::PURGE_HOOK, [ __CLASS__, 'purge' ] );
		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}
	}

	/** Nombre de la fila para un alcance y una clave en la ventana actual. */
	public static function option_name( $scope, $key ) {
		return self::PREFIX . sanitize_key( $scope ) . '_' . md5( (string) $key ) . '_' . gmdate( 'YmdH' );
	}

	/**
	 * Registra un intento y dice si todavia esta dentro del limite.
	 *
	 * @param string $scope Alcance ('ip', 'email', 'global'...).
	 * @param string $key   Valor a limitar (la IP, el correo...).
	 * @param int    $max   Maximo por hora. <= 0 desactiva el limite (no cuenta).
	 * @return bool true si el intento esta permitido.
	 */
	public static function hit( $scope, $key, $max ) {
		$max = (int) $max;
		if ( $max <= 0 ) return true;
		global $wpdb;
		$name = self::option_name( $scope, $key );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no')
			 ON DUPLICATE KEY UPDATE option_value = option_value + 1",
			$name
		) );
		return self::count( $scope, $key ) <= $max;
	}

	/** Intentos registrados en la ventana actual. */
	public static function count( $scope, $key ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
			self::option_name( $scope, $key )
		) );
	}

	/** Borra las ventanas que no son la hora actual ni la anterior. */
	public static function purge() {
		global $wpdb;
		$keep = [ gmdate( 'YmdH' ), gmdate( 'YmdH', time() - HOUR_IN_SECONDS ) ];
		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( self::PREFIX ) . '%'
		) );
		foreach ( (array) $rows as $name ) {
			if ( ! in_array( substr( $name, -10 ), $keep, true ) ) {
				$wpdb->delete( $wpdb->options, [ 'option_name' => $name ] );
			}
		}
	}

	/**
	 * Limites del formulario: por IP, por correo destino y global. Registra el intento
	 * en los tres alcances y devuelve true solo si ninguno se paso.
	 */
	public static function allow_submit( $ip, $email ) {
		$per   = (int) glotracol_quote_get_setting( 'rate_limit_per_hour', 3 );
		$total = (int) glotracol_quote_get_setting( 'rate_limit_global_per_hour', 30 );
		$ok_ip     = self::hit( 'ip', $ip, $per );
		$ok_email  = $email === '' ? true : self::hit( 'email', strtolower( $email ), $per );
		$ok_global = self::hit( 'global', 'formulario', $total );
		return $ok_ip && $ok_email && $ok_global;
	}
}
