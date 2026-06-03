<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_Product_Buttons {

	public function __construct() {
		add_filter( 'woocommerce_product_single_add_to_cart_text', [ $this, 'single_text' ] );
		add_filter( 'woocommerce_product_add_to_cart_text', [ $this, 'loop_text' ], 10, 2 );
		add_filter( 'woocommerce_get_price_html', [ $this, 'hide_price' ], 100, 2 );
		add_filter( 'woocommerce_show_admin_instock_email_notice', '__return_false' );

		// Productos sin precio → forzar purchasable para que el carrito acepte items
		add_filter( 'woocommerce_is_purchasable', [ $this, 'force_purchasable' ], 100, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', [ $this, 'force_purchasable' ], 100, 2 );

		// Modificar el link del botón en loop para añadir clases dinámicas y badge
		add_filter( 'woocommerce_loop_add_to_cart_link', [ $this, 'loop_button_html' ], 10, 3 );
		add_filter( 'woocommerce_loop_add_to_cart_args', [ $this, 'loop_button_args' ], 10, 2 );

		// Selector de presentación en single-product
		add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_single_presentation_selector' ], 9 );
	}

	public function single_text( $text ) {
		$pid = get_the_ID();
		if ( $pid && $this->is_in_cart( $pid ) ) {
			$qty = $this->get_cart_qty( $pid );
			return sprintf( '✓ Añadir más (ya tienes %d en tu cotización)', $qty );
		}
		return 'Añadir a mi cotización';
	}

	public function loop_text( $text, $product = null ) {
		if ( $product && glotracol_quote_get_presentaciones( $product ) ) {
			return 'Ver presentaciones →';
		}
		if ( $product && $this->is_in_cart( $product->get_id() ) ) {
			return '✓ Añadir más a la cotización';
		}
		return 'Añadir a la cotización';
	}

	public function loop_button_args( $args, $product ) {
		if ( ! is_array( $args ) ) $args = [];
		if ( ! isset( $args['class'] ) ) $args['class'] = '';
		if ( $this->is_in_cart( $product->get_id() ) ) {
			$args['class'] .= ' gloq-in-cart';
		}
		$args['class'] .= ' gloq-add-button';
		$args['attributes']['data-gloq-product-name'] = $product->get_name();
		return $args;
	}

	public function loop_button_html( $html, $product, $args ) {
		// Si el producto tiene presentaciones, fuerza link al producto (sin AJAX)
		// para que el cliente elija la presentación allí.
		if ( glotracol_quote_get_presentaciones( $product ) ) {
			$url = $product->get_permalink();
			$qty_in_cart = $this->get_cart_qty( $product->get_id() );
			$class = 'button gloq-add-button gloq-has-presentations' . ( $qty_in_cart > 0 ? ' gloq-in-cart' : '' );
			$label = $qty_in_cart > 0 ? '✓ Ver presentaciones →' : 'Ver presentaciones →';
			$html = sprintf(
				'<a href="%s" class="%s" data-product_id="%d" data-gloq-product-name="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				(int) $product->get_id(),
				esc_attr( $product->get_name() ),
				esc_html( $label )
			);
			if ( $qty_in_cart > 0 ) {
				$html .= sprintf( '<span class="gloq-cart-badge">✓ %d en tu cotización</span>', (int) $qty_in_cart );
			}
			return $html;
		}
		// Caso simple: el html ya viene generado por WC; añadimos badge si está en cart.
		if ( $this->is_in_cart( $product->get_id() ) ) {
			$qty = $this->get_cart_qty( $product->get_id() );
			$badge = sprintf( '<span class="gloq-cart-badge">✓ %d en tu cotización</span>', (int) $qty );
			return $html . $badge;
		}
		return $html;
	}

	/**
	 * Selector de presentaciones en single-product. Se renderiza ANTES del
	 * botón add-to-cart estándar de WC. El name del field se inyecta en el
	 * form que rodea al botón, por lo que llega al $_POST en el add_to_cart.
	 */
	public function render_single_presentation_selector() {
		global $product;
		if ( ! $product || ! ( $product instanceof WC_Product ) ) return;
		$presentaciones = glotracol_quote_get_presentaciones( $product );
		if ( empty( $presentaciones ) ) return;
		// Default: la primera presentación. Si viene `?presentacion=` lo respetamos.
		$default_idx = isset( $_GET['presentacion'] ) ? (int) $_GET['presentacion'] : (int) ( $presentaciones[0]['idx'] ?? 0 );
		?>
		<div class="gloq-presentation-selector">
			<label for="gloq_presentacion_idx"><strong>Presentación</strong></label>
			<select name="gloq_presentacion_idx" id="gloq_presentacion_idx" required>
				<?php foreach ( $presentaciones as $p ) :
					$idx = (int) ( $p['idx'] ?? 0 );
					$label = $p['label'] ?? '';
					$sku = $p['sku'] ?? '';
				?>
					<option value="<?php echo $idx; ?>" data-sku="<?php echo esc_attr( $sku ); ?>" <?php selected( $idx, $default_idx ); ?>>
						<?php echo esc_html( $label ); ?><?php if ( $sku ) echo ' (' . esc_html( $sku ) . ')'; ?>
					</option>
				<?php endforeach; ?>
			</select>
			<small class="gloq-presentation-hint">Elige la presentación que quieres añadir a tu cotización.</small>
		</div>
		<?php
	}

	public function hide_price( $html, $product ) {
		return '';
	}

	public function force_purchasable( $purchasable, $product ) {
		if ( $purchasable ) return $purchasable;
		if ( $product && $product->exists() && $product->is_in_stock() ) {
			return true;
		}
		return $purchasable;
	}

	private function is_in_cart( $product_id ) {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) return false;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( (int) ( $item['product_id'] ?? 0 ) === (int) $product_id ) return true;
		}
		return false;
	}

	private function get_cart_qty( $product_id ) {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) return 0;
		$qty = 0;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( (int) ( $item['product_id'] ?? 0 ) === (int) $product_id ) {
				$qty += (int) ( $item['quantity'] ?? 0 );
			}
		}
		return $qty;
	}
}
