<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_Rate_Limit {

	public static function check( $ip ) {
		$max = (int) glotracol_quote_get_setting( 'rate_limit_per_hour', 3 );
		if ( $max <= 0 ) return true;
		$key = 'gloq_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		return $count < $max;
	}

	public static function record( $ip ) {
		$key = 'gloq_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	}
}
