<?php
/**
 * Plugin Name: Glotracol Cotizador
 * Plugin URI: https://neracosu.com/
 * Description: Convierte WooCommerce en un sistema de solicitud de cotizaciones (RFQ): reemplaza el checkout por un formulario que envía la lista de productos al equipo de Glotracol y al cliente.
 * Version: 2.4.0
 * Author: Neracosu
 * Author URI: https://neracosu.com/
 * Text Domain: glotracol-quote
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 10.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GLOTRACOL_QUOTE_VERSION', '2.4.0' );
define( 'GLOTRACOL_QUOTE_FILE', __FILE__ );
define( 'GLOTRACOL_QUOTE_PATH', plugin_dir_path( __FILE__ ) );
define( 'GLOTRACOL_QUOTE_URL', plugin_dir_url( __FILE__ ) );
define( 'GLOTRACOL_QUOTE_BASENAME', plugin_basename( __FILE__ ) );

require_once GLOTRACOL_QUOTE_PATH . 'includes/helpers.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-logger.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-logger-admin.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-activator.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-rate-limit.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-product-buttons.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-product-tabs.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-cart-overrides.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-mini-cart.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-quote-cpt.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-client-cpt.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-client-admin.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-pricing.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-pricing-admin.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-importer.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-import-reader.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-importer-admin.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-presentations-admin.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-reports.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-quote-emails.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-smtp.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-webhook.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-quote-form.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-admin-meta-box.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-admin-settings.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-admin-dashboard.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-changelog-admin.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-updater.php';
require_once GLOTRACOL_QUOTE_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, [ 'Glotracol_Quote_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Glotracol_Quote_Activator', 'deactivate' ] );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

// Actualizador remoto desde GitHub. Independiente de WooCommerce: debe poder
// ofrecer actualizaciones aunque WC esté inactivo, y correr en cron (auto-updates).
new Glotracol_Quote_Updater();

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>Glotracol Cotizador</strong> requiere WooCommerce activo. Activa WooCommerce para usar este plugin.</p></div>';
		} );
		return;
	}
	load_plugin_textdomain( 'glotracol-quote', false, dirname( GLOTRACOL_QUOTE_BASENAME ) . '/languages' );
	Glotracol_Quote_Plugin::instance();
}, 20 );
