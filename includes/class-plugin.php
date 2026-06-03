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
		new Glotracol_Quote_Form();
		new Glotracol_Quote_Emails();
		new Glotracol_Quote_SMTP();
		new Glotracol_Quote_Webhook();
		new Glotracol_Quote_Admin_Meta_Box();
		new Glotracol_Quote_Admin_Settings();
		new Glotracol_Quote_Admin_Dashboard();

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'plugin_action_links_' . GLOTRACOL_QUOTE_BASENAME, [ $this, 'plugin_action_links' ] );
	}

	public function enqueue_assets() {
		wp_register_style( 'glotracol-quote', GLOTRACOL_QUOTE_URL . 'assets/css/quote.css', [], GLOTRACOL_QUOTE_VERSION );
		wp_register_script( 'glotracol-quote', GLOTRACOL_QUOTE_URL . 'assets/js/quote.js', [ 'jquery' ], GLOTRACOL_QUOTE_VERSION, true );

		// Capa 2 del rename: JS DOM rewrite. Carga en TODA página de WC para
		// cubrir mini-cart, breadcrumbs y bloques widgets, no solo /carrito.
		wp_register_script( 'glotracol-cart-rename', GLOTRACOL_QUOTE_URL . 'assets/js/cart-rename.js', [], GLOTRACOL_QUOTE_VERSION, true );

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
}
