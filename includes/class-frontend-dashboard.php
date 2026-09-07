<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Panel web: el resumen del cotizador en el frontend, para que el equipo lo vea
 * con su usuario de WordPress sin entrar al escritorio.
 *
 * Shortcode [glotracol_quote_dashboard recientes="10"]. Sin sesion pinta el
 * formulario de acceso; con sesion sin permiso, un aviso. La pagina
 * /panel-cotizaciones se crea sola.
 */
class Glotracol_Quote_Frontend_Dashboard {

	const SHORTCODE  = 'glotracol_quote_dashboard';
	const PAGE_OPTION = 'glotracol_quote_dashboard_page_id';

	public function __construct() {
		add_shortcode( self::SHORTCODE, [ $this, 'render_shortcode' ] );
		add_action( 'admin_init', [ __CLASS__, 'ensure_page' ] );
		add_action( 'template_redirect', [ __CLASS__, 'nocache_for_dynamic_pages' ] );
	}

	/**
	 * Las paginas del plugin cambian segun la sesion (panel) o el carrito (formulario,
	 * gracias). El servidor manda Cache-Control de 30 dias para todo el HTML y el
	 * navegador le serviria al equipo la copia sin sesion. Aqui se pide lo contrario.
	 */
	public static function nocache_for_dynamic_pages() {
		$ids = array_filter( [
			(int) get_option( self::PAGE_OPTION ),
			(int) get_option( 'glotracol_quote_form_page_id' ),
			(int) get_option( 'glotracol_quote_thanks_page_id' ),
		] );
		if ( ! $ids || ! is_page( $ids ) ) return;
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
		do_action( 'litespeed_control_set_nocache', 'glotracol-quote: pagina dinamica' );
	}

	/** Editores y administradores. Filtrable por si el cliente quiere otro rol. */
	public static function can_view( $user_id = null ) {
		$cap = apply_filters( 'glotracol_quote_dashboard_cap', 'edit_others_posts' );
		return $user_id === null ? current_user_can( $cap ) : user_can( $user_id, $cap );
	}

	/** Crea (una sola vez) la pagina que lleva el shortcode y devuelve su id. */
	public static function ensure_page() {
		return Glotracol_Quote_Activator::ensure_page( self::PAGE_OPTION, 'Panel de cotizaciones', 'panel-cotizaciones', '[' . self::SHORTCODE . ']' );
	}

	public static function page_url() {
		$id = (int) get_option( self::PAGE_OPTION );
		return $id && get_post( $id ) ? get_permalink( $id ) : '';
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( [ 'recientes' => 10 ], $atts, self::SHORTCODE );
		$recent_limit = max( 1, min( 50, (int) $atts['recientes'] ) );

		if ( ! wp_style_is( 'glotracol-quote', 'registered' ) ) {
			wp_register_style( 'glotracol-quote', GLOTRACOL_QUOTE_URL . 'assets/css/quote.css', [], GLOTRACOL_QUOTE_VERSION );
		}
		wp_enqueue_style( 'glotracol-quote' );

		$current_url = self::page_url() ?: home_url( add_query_arg( [] ) );

		if ( ! is_user_logged_in() ) {
			return glotracol_quote_load_template( 'dashboard-login.php', [
				'login_form' => wp_login_form( [
					'echo'           => false,
					'redirect'       => $current_url,
					'form_id'        => 'gloq-fd-loginform',
					'label_username' => 'Usuario o correo',
					'label_password' => 'Contraseña',
					'label_remember' => 'Recordarme',
					'label_log_in'   => 'Entrar',
					'remember'       => true,
				] ),
				'lost_url'   => wp_lostpassword_url( $current_url ),
			] );
		}

		$user       = wp_get_current_user();
		$logout_url = wp_logout_url( $current_url );

		if ( ! self::can_view() ) {
			return glotracol_quote_load_template( 'dashboard-denied.php', [
				'user'       => $user,
				'logout_url' => $logout_url,
			] );
		}

		$list_url = admin_url( 'edit.php?post_type=glo_quote' );
		return glotracol_quote_load_template( 'dashboard.php', [
			'stats'        => Glotracol_Quote_Admin_Dashboard::get_stats( $recent_limit ),
			'user'         => $user,
			'logout_url'   => $logout_url,
			'list_url'     => $list_url,
			'reports_url'  => admin_url( 'edit.php?post_type=glo_quote&page=glotracol-quote-reports' ),
			'recent_limit' => $recent_limit,
			'status_tiles' => [
				'new'            => [ 'label' => 'Nuevas',              'status' => 'glo-new' ],
				'pending_prices' => [ 'label' => 'Pendiente de precios', 'status' => 'glo-pending-prices' ],
				'auto_priced'    => [ 'label' => 'Auto-cotizadas',      'status' => 'glo-auto-priced' ],
				'processing'     => [ 'label' => 'En proceso',          'status' => 'glo-processing' ],
				'responded'      => [ 'label' => 'Respondidas',         'status' => 'glo-responded' ],
				'closed'         => [ 'label' => 'Cerradas',            'status' => 'glo-closed' ],
			],
		] );
	}
}
