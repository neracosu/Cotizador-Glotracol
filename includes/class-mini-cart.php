<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Carrito flotante persistente ("mini-cotización").
 *
 * Burbuja fija + panel deslizante visible en todo el frontend. Muestra los items
 * del carrito (sin precios, es RFQ) y enlaza al formulario. Se actualiza vía WC
 * fragments y reutiliza el endpoint AJAX gloq_update_qty para editar cantidades.
 */
class Glotracol_Quote_Mini_Cart {

	public function __construct() {
		add_action( 'wp_footer', [ $this, 'render_fab' ], 20 );
		add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'cart_fragments' ] );
	}

	/** Páginas donde NO mostramos el FAB (form, gracias, admin). */
	private function is_suppressed() {
		if ( is_admin() ) return true;
		if ( glotracol_quote_get_setting( 'mini_cart_enabled', 'yes' ) !== 'yes' ) return true;
		$form_id   = (int) get_option( 'glotracol_quote_form_page_id' );
		$thanks_id = (int) get_option( 'glotracol_quote_thanks_page_id' );
		if ( $form_id && is_page( $form_id ) ) return true;
		if ( $thanks_id && is_page( $thanks_id ) ) return true;
		return false;
	}

	/** Cuenta total de items (sumatoria de cantidades). */
	private function count() {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) return 0;
		return (int) WC()->cart->get_cart_contents_count();
	}

	/** HTML del cuerpo del panel (lista de items o estado vacío). */
	private function panel_body_html() {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
			return '<p class="gloq-fab-empty">Aún no has añadido productos.</p>';
		}
		$cart = WC()->cart->get_cart();
		if ( empty( $cart ) ) {
			$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
			return '<p class="gloq-fab-empty">Aún no has añadido productos.</p>'
				. '<a class="gloq-fab-shoplink" href="' . esc_url( $shop ) . '">Ver catálogo</a>';
		}
		$out = '<ul class="gloq-fab-items">';
		foreach ( $cart as $key => $item ) {
			$product = $item['data'] ?? null;
			if ( ! $product ) continue;
			$name = $product->get_name();
			$qty  = (int) $item['quantity'];
			$img  = $product->get_image( [ 48, 48 ] );
			$pres = $item['gloq_presentacion']['label'] ?? '';
			$pres_html = $pres ? '<span class="gloq-fab-pres">' . esc_html( $pres ) . '</span>' : '';
			$out .= '<li class="gloq-fab-item" data-key="' . esc_attr( $key ) . '">'
				. '<span class="gloq-fab-thumb">' . $img . '</span>'
				. '<span class="gloq-fab-info"><span class="gloq-fab-name">' . esc_html( $name ) . '</span>' . $pres_html . '</span>'
				. '<input type="number" class="gloq-fab-qty" min="0" step="1" value="' . $qty . '" data-key="' . esc_attr( $key ) . '" aria-label="Cantidad">'
				. '<button type="button" class="gloq-fab-remove" data-key="' . esc_attr( $key ) . '" aria-label="Quitar">&times;</button>'
				. '</li>';
		}
		$out .= '</ul>';
		return $out;
	}

	/** Markup del FAB + panel en el footer. */
	public function render_fab() {
		if ( $this->is_suppressed() ) return;
		$pos      = glotracol_quote_get_setting( 'mini_cart_position', 'bottom-left' );
		$pos      = in_array( $pos, [ 'bottom-left', 'bottom-right', 'top-left', 'top-right' ], true ) ? $pos : 'bottom-left';
		$count    = $this->count();
		$form_url = glotracol_quote_get_form_page_url();
		$hidden   = $count > 0 ? '' : ' gloq-fab-hidden';
		?>
		<div class="gloq-fab-wrap gloq-fab-<?php echo esc_attr( $pos ); ?><?php echo $hidden; ?>" id="gloq-fab">
			<button type="button" class="gloq-fab-btn" aria-label="Ver mi cotización" aria-expanded="false">
				<span class="gloq-fab-icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
				</span>
				<span class="gloq-fab-count"><?php echo (int) $count; ?></span>
			</button>
			<div class="gloq-fab-panel" role="dialog" aria-label="Mi cotización" aria-hidden="true">
				<div class="gloq-fab-head">
					<strong>Mi cotización</strong>
					<button type="button" class="gloq-fab-close" aria-label="Cerrar">&times;</button>
				</div>
				<div class="gloq-fab-panel-body"><?php echo $this->panel_body_html(); ?></div>
				<div class="gloq-fab-foot">
					<a class="gloq-fab-cta" href="<?php echo esc_url( $form_url ); ?>">Ir a la cotización &rarr;</a>
				</div>
			</div>
			<div class="gloq-fab-overlay" hidden></div>
		</div>
		<?php
	}

	/** Fragments WC: refrescan contador y cuerpo del panel tras add/remove. */
	public function cart_fragments( $fragments ) {
		$fragments['.gloq-fab-count']      = '<span class="gloq-fab-count">' . (int) $this->count() . '</span>';
		$fragments['.gloq-fab-panel-body'] = '<div class="gloq-fab-panel-body">' . $this->panel_body_html() . '</div>';
		return $fragments;
	}
}
