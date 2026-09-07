<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		new Glotracol_Quote_CPT();
		new Glotracol_Quote_Client_CPT();
		new Glotracol_Quote_Client_Admin();
		new Glotracol_Quote_Pricing_Admin();
		new Glotracol_Quote_Importer_Admin();
		new Glotracol_Quote_Presentations_Admin();
		new Glotracol_Quote_Reports();
		new Glotracol_Quote_Logger_Admin();
		new Glotracol_Quote_Product_Buttons();
		new Glotracol_Quote_Product_Tabs();
		new Glotracol_Quote_Cart_Overrides();
		new Glotracol_Quote_Mini_Cart();
		new Glotracol_Quote_Form();
		new Glotracol_Quote_Emails();
		new Glotracol_Quote_SMTP();
		new Glotracol_Quote_Webhook();
		new Glotracol_Quote_GHL();
		new Glotracol_Quote_Admin_Meta_Box();
		new Glotracol_Quote_Admin_Settings();
		new Glotracol_Quote_Admin_Dashboard();
		new Glotracol_Quote_Frontend_Dashboard();
		new Glotracol_Quote_Changelog_Admin();
		new Glotracol_Quote_Tour();

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'wp_head', [ $this, 'print_appearance_css' ], 99 );
		add_filter( 'plugin_action_links_' . GLOTRACOL_QUOTE_BASENAME, [ $this, 'plugin_action_links' ] );
		add_action( 'admin_post_gloq_download_pdf', [ $this, 'handle_download_pdf' ] );
		add_action( 'admin_notices', [ __CLASS__, 'duplicate_notice' ] );
	}

	/**
	 * Otras carpetas activas con este mismo plugin (glotracol-quote.php en otra
	 * carpeta). Quedan inertes porque las constantes son de la primera copia, pero
	 * muestran su version vieja en la lista de plugins y confunden.
	 *
	 * @return string[] basenames de las copias sobrantes.
	 */
	public static function duplicate_copies() {
		$out = [];
		foreach ( (array) get_option( 'active_plugins', [] ) as $basename ) {
			$basename = (string) $basename;
			if ( $basename === GLOTRACOL_QUOTE_BASENAME ) continue;
			if ( basename( $basename ) === basename( GLOTRACOL_QUOTE_FILE ) ) $out[] = $basename;
		}
		return $out;
	}

	public static function duplicate_notice() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$dups = self::duplicate_copies();
		if ( ! $dups ) return;
		$sobran = array_map( function ( $b ) { return '<code>' . esc_html( dirname( $b ) ) . '</code>'; }, $dups );
		echo '<div class="notice notice-error"><p><strong>Hay ' . ( count( $dups ) + 1 ) . ' copias del Cotizador Glotracol activas.</strong> '
			. 'La que funciona es la de la carpeta <code>' . esc_html( dirname( GLOTRACOL_QUOTE_BASENAME ) ) . '</code> (v' . esc_html( GLOTRACOL_QUOTE_VERSION ) . '). '
			. 'Sobra' . ( count( $dups ) > 1 ? 'n' : '' ) . ' ' . implode( ' y ', $sobran ) . ': no hace' . ( count( $dups ) > 1 ? 'n' : '' ) . ' nada, pero muestra' . ( count( $dups ) > 1 ? 'n' : '' ) . ' una versión vieja. '
			. '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Desactívala y bórrala desde Plugins</a>. '
			. 'Para actualizar no hace falta subir ningún ZIP: el plugin se actualiza solo desde GitHub.</p></div>';
	}

	/**
	 * Descarga del PDF de una cotización desde el panel. Se genera al vuelo, así
	 * que siempre refleja los precios actuales de la cotización.
	 */
	public function handle_download_pdf() {
		$quote_id = isset( $_GET['quote_id'] ) ? (int) $_GET['quote_id'] : 0;
		if ( ! $quote_id || ! current_user_can( 'edit_post', $quote_id ) ) {
			wp_die( 'Sin permisos para descargar esta cotización.', 'Error', [ 'response' => 403 ] );
		}
		check_admin_referer( 'gloq_pdf_' . $quote_id );

		$data = class_exists( 'Glotracol_Quote_PDF' ) ? Glotracol_Quote_PDF::render( $quote_id ) : '';
		if ( $data === '' ) {
			wp_die( 'No se pudo generar el PDF de esta cotización.', 'Error', [ 'response' => 500 ] );
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . Glotracol_Quote_PDF::filename( $quote_id ) . '"' );
		header( 'Content-Length: ' . strlen( $data ) );
		echo $data;
		exit;
	}

	/**
	 * Encola CSS + JS del admin en TODAS las pantallas del plugin (no solo el
	 * dashboard). Cubre los submenús bajo el CPT, las pantallas de edición de
	 * cotizaciones/clientes y el editor de producto (metabox de presentaciones).
	 */
	public function enqueue_admin_assets() {
		if ( ! $this->is_plugin_admin_screen() ) {
			return;
		}
		wp_enqueue_style( 'glotracol-quote-admin', GLOTRACOL_QUOTE_URL . 'assets/css/admin.css', [ 'dashicons' ], GLOTRACOL_QUOTE_VERSION );
		$deps = [ 'jquery' ];
		if ( isset( $_GET['page'] ) && $_GET['page'] === Glotracol_Quote_Admin_Settings::PAGE_SLUG ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_media();
			$deps[] = 'wp-color-picker';
		}
		wp_enqueue_script( 'glotracol-quote-admin', GLOTRACOL_QUOTE_URL . 'assets/js/admin.js', $deps, GLOTRACOL_QUOTE_VERSION, true );
		wp_localize_script( 'glotracol-quote-admin', 'GloqAdmin', [
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'smtpNonce'    => wp_create_nonce( 'gloq_smtp_test' ),
			'convertNonce' => wp_create_nonce( 'gloq_convert_to_order' ),
			'ghlNonce'     => wp_create_nonce( 'gloq_ghl_test' ),
			'resendNonce'  => wp_create_nonce( 'gloq_resend_customer' ),
			'i18n'         => [
				'confirmDeleteRow' => 'Quitar esta fila.',
				'sending'          => 'Enviando…',
				'converting'       => 'Convirtiendo…',
			],
		] );
	}

	/**
	 * True si la pantalla admin actual pertenece al plugin. Los submenús
	 * registrados bajo `edit.php?post_type=glo_quote` heredan post_type glo_quote
	 * en su WP_Screen, así que basta con mirar el post_type del screen.
	 */
	public function is_plugin_admin_screen() {
		// Páginas propias del plugin (submenús): su slug empieza por 'glotracol-quote-'.
		// Se detecta por el slug ANTES que por post_type porque en algunas cargas de
		// esos submenús (p. ej. el dashboard) el post_type del WP_Screen viene vacío,
		// y entonces admin.css no se encolaba en la primera carga.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( strpos( $page, 'glotracol-quote-' ) === 0 ) {
			return true;
		}
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		return in_array( $screen->post_type, [ 'glo_quote', 'glo_client', 'product' ], true );
	}

	public function enqueue_assets() {
		wp_register_style( 'glotracol-quote', GLOTRACOL_QUOTE_URL . 'assets/css/quote.css', [], GLOTRACOL_QUOTE_VERSION );
		wp_register_script( 'glotracol-quote', GLOTRACOL_QUOTE_URL . 'assets/js/quote.js', [ 'jquery' ], GLOTRACOL_QUOTE_VERSION, true );

		// Capa 2 del rename: JS DOM rewrite. Carga en TODA página de WC para
		// cubrir mini-cart, breadcrumbs y bloques widgets, no solo /carrito.
		wp_register_script( 'glotracol-cart-rename', GLOTRACOL_QUOTE_URL . 'assets/js/cart-rename.js', [], GLOTRACOL_QUOTE_VERSION, true );

		// Mini-cart: visible en todo el frontend (CSS reusa quote.css; JS propio liviano).
		wp_register_script( 'glotracol-mini-cart', GLOTRACOL_QUOTE_URL . 'assets/js/mini-cart.js', [ 'jquery' ], GLOTRACOL_QUOTE_VERSION, true );
		if ( ! is_admin() && glotracol_quote_get_setting( 'mini_cart_enabled', 'yes' ) === 'yes' ) {
			wp_enqueue_style( 'glotracol-quote' ); // contiene los estilos del FAB
			wp_enqueue_script( 'glotracol-mini-cart' );
			wp_localize_script( 'glotracol-mini-cart', 'GloqMiniCart', [
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'qtyNonce' => wp_create_nonce( 'gloq_update_qty' ),
				'formUrl'  => glotracol_quote_get_form_page_url(),
			] );
		}

		if ( $this->should_load_assets() ) {
			wp_enqueue_style( 'glotracol-quote' );
			wp_enqueue_script( 'glotracol-quote' );

			// Localiza datos AJAX para el editor de cantidades en /solicitar-cotizacion
			$form_page_id = (int) get_option( 'glotracol_quote_form_page_id' );
			if ( $form_page_id && is_page( $form_page_id ) ) {
				wp_localize_script( 'glotracol-quote', 'GloqAjax', [
					'url'   => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'gloq_update_qty' ),
				] );
			}
		}

		// El JS de rename se enqueua de forma más amplia que el resto de assets:
		// cualquier página que pueda mostrar carrito/mini-cart/widgets de WC.
		if ( $this->should_load_cart_rename() ) {
			wp_enqueue_script( 'glotracol-cart-rename' );
		}
	}

	/**
	 * Cuándo cargar el JS de rename (Capa 2). Más amplio que should_load_assets()
	 * porque mini-cart y widgets pueden aparecer en cualquier página.
	 */
	private function should_load_cart_rename() {
		// Front cualquiera que no sea admin. El JS es ~3KB; el coste de cargarlo
		// siempre es despreciable comparado con el riesgo de "Cart" filtrándose.
		return ! is_admin();
	}

	private function should_load_assets() {
		if ( is_cart() || is_shop() || is_product_category() || is_product_tag() || is_product() ) {
			return true;
		}
		$form_page_id = (int) get_option( 'glotracol_quote_form_page_id' );
		$thanks_page_id = (int) get_option( 'glotracol_quote_thanks_page_id' );
		return ( $form_page_id && is_page( $form_page_id ) ) || ( $thanks_page_id && is_page( $thanks_page_id ) );
	}

	public function plugin_action_links( $links ) {
		$dashboard_url = admin_url( 'edit.php?post_type=glo_quote&page=' . Glotracol_Quote_Admin_Dashboard::PAGE_SLUG );
		$settings_url  = admin_url( 'edit.php?post_type=glo_quote&page=' . Glotracol_Quote_Admin_Settings::PAGE_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $settings_url ) . '">Configuración</a>' );
		array_unshift( $links, '<a href="' . esc_url( $dashboard_url ) . '"><strong>Dashboard</strong></a>' );
		return $links;
	}

	/**
	 * Imprime la paleta de marca como variables CSS. Se calcula en PHP en cada
	 * carga (glotracol_quote_brand lee el kit de Elementor si la herencia esta
	 * activa), asi que el frontend, los correos y el PDF comparten el mismo color.
	 */
	public function print_appearance_css() {
		if ( is_admin() ) {
			return;
		}
		$b = glotracol_quote_brand();
		printf(
			"<style id='gloq-appearance'>:root{--gloq-brand:%s;--gloq-brand-dark:%s;--gloq-brand-tint:%s;--gloq-brand-line:%s;--gloq-brand-text:%s;}</style>\n",
			esc_attr( $b['color'] ), esc_attr( $b['dark'] ), esc_attr( $b['tint'] ), esc_attr( $b['line'] ), esc_attr( $b['text'] )
		);
	}
}
