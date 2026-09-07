<?php
/**
 * wp eval-file tests/test-frontend-dashboard.php
 *
 * Panel web: shortcode [glotracol_quote_dashboard] que muestra el resumen del
 * cotizador en el frontend a los usuarios con permiso (editores y
 * administradores), con formulario de acceso para quien no ha entrado.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}
$USER_ORIG = get_current_user_id();
register_shutdown_function( function () use ( $USER_ORIG ) { wp_set_current_user( $USER_ORIG ); } );

chk( 'existe la clase Glotracol_Quote_Frontend_Dashboard', class_exists( 'Glotracol_Quote_Frontend_Dashboard' ) );
chk( 'el shortcode glotracol_quote_dashboard está registrado', shortcode_exists( 'glotracol_quote_dashboard' ) );

// ---------- 1. Las estadísticas del Inicio se pueden pedir desde fuera ----------
$m = new ReflectionMethod( 'Glotracol_Quote_Admin_Dashboard', 'get_stats' );
chk( 'get_stats() es public static', $m->isPublic() && $m->isStatic() );
if ( $m->isPublic() && $m->isStatic() ) {
	$stats = Glotracol_Quote_Admin_Dashboard::get_stats( 3 );
	chk( 'get_stats() trae las claves del panel', isset( $stats['month_count'], $stats['new'], $stats['recent'], $stats['total'] ) );
	chk( 'get_stats(3) limita las recientes a 3', count( $stats['recent'] ) <= 3 );
}

if ( ! class_exists( 'Glotracol_Quote_Frontend_Dashboard' ) ) { echo "HAY {$GLOBALS['gloq_fail']} FALLOS\n"; return; }

// ---------- 2. Sin sesión: formulario de acceso, cero datos ----------
wp_set_current_user( 0 );
$html = do_shortcode( '[glotracol_quote_dashboard]' );
chk( 'sin sesión pinta el formulario de acceso', strpos( $html, '<form' ) !== false && strpos( $html, 'name="log"' ) !== false && strpos( $html, 'name="pwd"' ) !== false );
chk( 'sin sesión no muestra estadísticas', strpos( $html, 'gloq-fd-stats' ) === false );

// ---------- 3. Con sesión sin permiso: mensaje, cero datos ----------
$sub = wp_insert_user( [ 'user_login' => 'gloq_prueba_sub_' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password( 24 ), 'role' => 'subscriber' ] );
if ( ! is_wp_error( $sub ) ) {
	wp_set_current_user( $sub );
	$html = do_shortcode( '[glotracol_quote_dashboard]' );
	chk( 'un suscriptor ve que no tiene permiso', stripos( $html, 'permiso' ) !== false && strpos( $html, 'gloq-fd-stats' ) === false );
	chk( 'y tiene enlace para salir', strpos( $html, 'action=logout' ) !== false );
	wp_delete_user( $sub );
}

// ---------- 4. Editor: el panel completo ----------
$editors = get_users( [ 'role' => 'editor', 'number' => 1, 'fields' => 'ID' ] );
if ( $editors ) {
	wp_set_current_user( (int) $editors[0] );
	chk( 'un editor puede ver el panel', Glotracol_Quote_Frontend_Dashboard::can_view() === true );
	$stats = Glotracol_Quote_Admin_Dashboard::get_stats( 10 );
	$html = do_shortcode( '[glotracol_quote_dashboard]' );
	chk( 'un editor ve las estadísticas', strpos( $html, 'gloq-fd-stats' ) !== false );
	chk( 'muestra el total del mes', strpos( $html, '>' . (int) $stats['month_count'] . '<' ) !== false );
	chk( 'muestra las nuevas', strpos( $html, 'gloq-fd-stat-new' ) !== false );
	preg_match_all( '/<tr class="gloq-fd-row"/', $html, $rows );
	chk( 'lista las últimas cotizaciones (hasta 10)', count( $rows[0] ) === min( 10, (int) $stats['total'] ) );
	if ( ! empty( $stats['recent'] ) ) {
		$first = $stats['recent'][0];
		$html  = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5 ); // esc_url escribe & como &#038;
		chk( 'la fila trae el número de la cotización', strpos( $html, '#' . (int) $first->ID ) !== false );
		chk( 'la fila enlaza al PDF', strpos( $html, 'gloq_download_pdf&quote_id=' . (int) $first->ID ) !== false );
		chk( 'la fila enlaza a la cotización en el panel', strpos( $html, 'post=' . (int) $first->ID . '&action=edit' ) !== false );
	}
	chk( 'tiene enlace para salir', strpos( $html, 'action=logout' ) !== false );
	chk( 'no incluye el formulario de acceso', strpos( $html, 'name="pwd"' ) === false );
	chk( 'el atributo recientes limita la tabla', substr_count( do_shortcode( '[glotracol_quote_dashboard recientes="2"]' ), '<tr class="gloq-fd-row"' ) === min( 2, (int) $stats['total'] ) );
	chk( 'encola la hoja de estilos del frontend', wp_style_is( 'glotracol-quote', 'enqueued' ) );
} else {
	echo "[SKIP] no hay editores para probar el panel\n";
}

// ---------- 5. La página del panel existe y el Inicio la lista ----------
chk( 'existe ensure_page()', method_exists( 'Glotracol_Quote_Frontend_Dashboard', 'ensure_page' ) );
if ( method_exists( 'Glotracol_Quote_Frontend_Dashboard', 'ensure_page' ) ) {
	$pid = Glotracol_Quote_Frontend_Dashboard::ensure_page();
	$page = $pid ? get_post( $pid ) : null;
	chk( 'la página del panel existe y publica el shortcode', $page && $page->post_status === 'publish' && strpos( $page->post_content, '[glotracol_quote_dashboard]' ) !== false );
	chk( 'queda guardada en la opción glotracol_quote_dashboard_page_id', (int) get_option( 'glotracol_quote_dashboard_page_id' ) === (int) $pid );
	chk( 'llamarla dos veces no duplica la página', Glotracol_Quote_Frontend_Dashboard::ensure_page() === $pid );

	$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
	if ( $admins ) {
		wp_set_current_user( (int) $admins[0] );
		set_transient( Glotracol_Quote_GHL::CACHE_KEY, [], HOUR_IN_SECONDS );
		ob_start(); ( new Glotracol_Quote_Admin_Dashboard() )->render(); $html = ob_get_clean();
		delete_transient( Glotracol_Quote_GHL::CACHE_KEY );
		chk( 'el Inicio lista la página del panel web con su URL', strpos( $html, 'Panel web' ) !== false && strpos( $html, get_permalink( $pid ) ) !== false );
	}
}

// ---------- 6. La página del panel no se cachea ----------
// El servidor manda Cache-Control: max-age de 30 días para todo el HTML; sin esto el
// navegador le sirve al equipo la copia sin sesión (formulario de acceso) aunque ya entró.
chk( 'existe nocache_for_dynamic_pages()', method_exists( 'Glotracol_Quote_Frontend_Dashboard', 'nocache_for_dynamic_pages' ) );
if ( method_exists( 'Glotracol_Quote_Frontend_Dashboard', 'nocache_for_dynamic_pages' ) ) {
	$pid = (int) get_option( 'glotracol_quote_dashboard_page_id' );
	$GLOBALS['gloq_nocache_fired'] = 0;
	add_filter( 'nocache_headers', function ( $h ) { $GLOBALS['gloq_nocache_fired']++; return $h; } );

	$q_orig = $GLOBALS['wp_query'];
	$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query( [ 'page_id' => $pid ] );
	Glotracol_Quote_Frontend_Dashboard::nocache_for_dynamic_pages();
	chk( 'en la página del panel pide cabeceras de no-caché', $GLOBALS['gloq_nocache_fired'] === 1 );
	chk( 'y marca DONOTCACHEPAGE para los plugins de caché', defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );

	$otra = get_posts( [ 'post_type' => 'page', 'numberposts' => 1, 'fields' => 'ids', 'exclude' => [ $pid, (int) get_option( 'glotracol_quote_form_page_id' ), (int) get_option( 'glotracol_quote_thanks_page_id' ) ] ] );
	if ( $otra ) {
		$GLOBALS['gloq_nocache_fired'] = 0;
		$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query( [ 'page_id' => (int) $otra[0] ] );
		Glotracol_Quote_Frontend_Dashboard::nocache_for_dynamic_pages();
		chk( 'en cualquier otra página no toca las cabeceras', $GLOBALS['gloq_nocache_fired'] === 0 );
	}
	$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = $q_orig;
	chk( 'está enganchado a template_redirect', has_action( 'template_redirect', [ 'Glotracol_Quote_Frontend_Dashboard', 'nocache_for_dynamic_pages' ] ) !== false );
}

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
