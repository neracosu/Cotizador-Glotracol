<?php
/**
 * wp eval-file tests/test-resend.php
 *
 * "Reenviar correo al cliente" desde la cotización: vuelve a mandar el correo
 * del cliente con el PDF adjunto y lo anota en el log de envíos. El correo se
 * intercepta antes de salir.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}

$LOG = get_option( 'glotracol_quote_log' );
$q = get_posts( [ 'post_type' => 'glo_quote', 'post_status' => 'any', 'numberposts' => 1, 'orderby' => 'date', 'order' => 'DESC',
	'meta_query' => [ [ 'key' => '_glo_customer_email', 'value' => '@', 'compare' => 'LIKE' ] ] ] );
if ( ! $q ) { echo "[SKIP] no hay cotizaciones con correo en la BD\n"; return; }
$QID   = (int) $q[0]->ID;
$EMAIL = (string) get_post_meta( $QID, '_glo_customer_email', true );
$ELOG  = get_post_meta( $QID, '_glo_email_log', true );
register_shutdown_function( function () use ( $LOG, $QID, $ELOG ) {
	if ( false !== $LOG ) update_option( 'glotracol_quote_log', $LOG, false );
	if ( $ELOG === '' || $ELOG === false ) delete_post_meta( $QID, '_glo_email_log' ); else update_post_meta( $QID, '_glo_email_log', $ELOG );
} );

// Intercepta wp_mail antes que el mu-plugin del clon (prioridad 1).
$GLOBALS['mails'] = [];
add_filter( 'pre_wp_mail', function ( $pre, $atts ) {
	$atts['attachments_existian'] = array_map( 'file_exists', (array) ( $atts['attachments'] ?? [] ) );
	$GLOBALS['mails'][] = $atts;
	return true;
}, 0, 2 );

chk( 'existe Glotracol_Quote_Emails::resend_customer()', method_exists( 'Glotracol_Quote_Emails', 'resend_customer' ) );
if ( method_exists( 'Glotracol_Quote_Emails', 'resend_customer' ) ) {
	$r = Glotracol_Quote_Emails::resend_customer( $QID );
	chk( "reenvía la #$QID sin error", $r === true );
	chk( 'salió exactamente un correo', count( $GLOBALS['mails'] ) === 1 );
	$m = $GLOBALS['mails'][0] ?? [];
	$to = is_array( $m['to'] ?? null ) ? implode( ',', $m['to'] ) : (string) ( $m['to'] ?? '' );
	chk( 'va al correo del cliente', $to === $EMAIL );
	chk( 'el asunto lleva el número de cotización', strpos( (string) ( $m['subject'] ?? '' ), '#' . $QID ) !== false );
	chk( 'el cuerpo es la plantilla del cliente', strpos( (string) ( $m['message'] ?? '' ), 'Datos que nos enviaste' ) !== false );
	$att = (array) ( $m['attachments'] ?? [] );
	chk( 'adjunta un PDF', count( $att ) === 1 && substr( $att[0], -4 ) === '.pdf' );
	chk( 'el PDF existía al enviar', ! empty( $m['attachments_existian'][0] ) );
	chk( 'el PDF temporal se borra después', ! empty( $att ) && ! file_exists( $att[0] ) );

	$elog = get_post_meta( $QID, '_glo_email_log', true );
	$last = is_array( $elog ) && $elog ? end( $elog ) : [];
	chk( 'queda anotado en el log de envíos de la cotización como reenvío', ( $last['type'] ?? '' ) === 'customer-resent' && ! empty( $last['success'] ) && ( $last['to'] ?? '' ) === $EMAIL );

	$glog = get_option( 'glotracol_quote_log', [] );
	$hit = false;
	foreach ( (array) $glog as $e ) { if ( ( $e['cat'] ?? '' ) === 'email' && ( $e['context']['quote_id'] ?? 0 ) === $QID && stripos( $e['msg'] ?? '', 'reenv' ) !== false ) { $hit = true; break; } }
	chk( 'queda anotado en Registros', $hit );

	$r = Glotracol_Quote_Emails::resend_customer( 999999999 );
	chk( 'cotización inexistente → WP_Error', is_wp_error( $r ) );

	// Sin correo de cliente no hay a quién reenviar.
	$tmp = wp_insert_post( [ 'post_type' => 'glo_quote', 'post_status' => 'glo-new', 'post_title' => 'prueba reenvío' ] );
	$GLOBALS['mails'] = [];
	$r = Glotracol_Quote_Emails::resend_customer( $tmp );
	chk( 'sin correo del cliente → WP_Error y no sale nada', is_wp_error( $r ) && count( $GLOBALS['mails'] ) === 0 );
	wp_delete_post( $tmp, true );
}

// Botón en la caja "Log de envíos" y acción AJAX registrada.
$MB = new Glotracol_Quote_Admin_Meta_Box();
ob_start(); $MB->render_email_log( get_post( $QID ) ); $html = ob_get_clean();
chk( 'la caja Log de envíos trae el botón Reenviar correo al cliente', strpos( $html, 'id="gloq-resend-btn"' ) !== false && strpos( $html, 'data-post-id="' . $QID . '"' ) !== false && stripos( $html, 'Reenviar correo al cliente' ) !== false );
chk( 'hay acción AJAX gloq_resend_customer', has_action( 'wp_ajax_gloq_resend_customer' ) !== false );
chk( 'admin.js maneja el botón', strpos( file_get_contents( GLOTRACOL_QUOTE_PATH . 'assets/js/admin.js' ), 'gloq_resend_customer' ) !== false );

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
