<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Modificaciones a las pestañas del producto (single product page).
 *
 * - Renombra la pestaña "Información adicional" a "Presentación".
 * - Inyecta un CTA "Añadir a mi cotización" al final de la pestaña Descripción.
 *
 * Diseñado defensivamente: si WC reorganiza tabs o callbacks, los hooks degradan
 * sin romper la página (la pestaña original sigue funcionando).
 */
class Glotracol_Quote_Product_Tabs {

	public function __construct() {
		add_filter( 'woocommerce_product_tabs', [ $this, 'modify_tabs' ], 99 );
	}

	/**
	 * Modifica las pestañas del producto:
	 *  - "additional_information" → renombra a "Presentación".
	 *  - "description" → wrappea la callback para añadir CTA al final.
	 */
	public function modify_tabs( $tabs ) {
		if ( ! is_array( $tabs ) ) return $tabs;

		// Renombrar Información adicional → Presentación
		if ( isset( $tabs['additional_information'] ) ) {
			$tabs['additional_information']['title'] = 'Presentación';
		}

		// Añadir CTA al final de la pestaña Descripción
		if ( isset( $tabs['description'] ) ) {
			$original_callback = isset( $tabs['description']['callback'] ) ? $tabs['description']['callback'] : 'woocommerce_product_description_tab';
			$tabs['description']['callback'] = function () use ( $original_callback ) {
				// Render original (descripción del producto)
				if ( is_callable( $original_callback ) ) {
					try {
						call_user_func( $original_callback );
					} catch ( \Throwable $e ) {
						// Si la callback original falla, mostramos solo el CTA — la página no rompe.
						if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
							Glotracol_Quote_Logger::warn( 'wc_compat', 'description tab callback falló', [
								'error' => $e->getMessage(),
								'file'  => $e->getFile() . ':' . $e->getLine(),
							] );
						}
					}
				}
				// CTA al final
				$this_obj = new self();
				$this_obj->render_description_cta();
			};
		}

		return $tabs;
	}

	/**
	 * Renderiza el CTA "Añadir a mi cotización" al final de la pestaña Descripción.
	 *
	 * Reusa el botón de loop de WC para mantener consistencia con el catálogo.
	 * Si por alguna razón el botón no se puede generar, se muestra un link
	 * directo al formulario de cotización.
	 */
	public function render_description_cta() {
		global $product;
		if ( ! $product || ! ( $product instanceof WC_Product ) ) return;

		$is_in_cart = $this->is_in_cart( $product->get_id() );
		$qty_in_cart = $this->get_cart_qty( $product->get_id() );
		$quote_url = function_exists( 'glotracol_quote_get_form_page_url' ) ? glotracol_quote_get_form_page_url() : home_url( '/solicitar-cotizacion/' );

		?>
		<div class="gloq-desc-cta" role="region" aria-label="Acciones rápidas de cotización">
			<div class="gloq-desc-cta-content">
				<div class="gloq-desc-cta-text">
					<strong><?php echo esc_html( $product->get_name() ); ?></strong>
					<?php if ( $is_in_cart ) : ?>
						<span class="gloq-desc-cta-sub">✓ Ya tienes <?php echo (int) $qty_in_cart; ?> en tu cotización. Puedes añadir más o ir al formulario.</span>
					<?php else : ?>
						<span class="gloq-desc-cta-sub">Añade este producto a tu cotización para que el equipo de Glotracol te envíe disponibilidad y precio.</span>
					<?php endif; ?>
				</div>
				<div class="gloq-desc-cta-actions">
					<?php
					// Botón add-to-cart estándar de WC para garantizar consistencia
					// y compatibilidad con el flujo AJAX del plugin (toast, etc).
					if ( $product->is_purchasable() && $product->is_in_stock() ) {
						echo apply_filters( 'woocommerce_loop_add_to_cart_link',
							sprintf(
								'<a href="%s" data-quantity="1" class="button gloq-add-button gloq-desc-cta-btn %s product_type_%s" data-product_id="%s" data-product_sku="%s" data-gloq-product-name="%s" aria-label="Añadir %s a la cotización" rel="nofollow">%s</a>',
								esc_url( $product->add_to_cart_url() ),
								$product->supports( 'ajax_add_to_cart' ) && $product->is_purchasable() && $product->is_in_stock() ? 'ajax_add_to_cart' : '',
								esc_attr( $product->get_type() ),
								esc_attr( $product->get_id() ),
								esc_attr( $product->get_sku() ),
								esc_attr( $product->get_name() ),
								esc_attr( $product->get_name() ),
								$is_in_cart ? '✓ Añadir más a la cotización' : 'Añadir a mi cotización'
							),
							$product,
							[]
						);
					}
					if ( $is_in_cart ) :
						?>
						<a href="<?php echo esc_url( $quote_url ); ?>" class="button gloq-desc-cta-secondary">Ir al formulario →</a>
						<?php
					endif;
					?>
				</div>
			</div>
		</div>
		<?php
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
