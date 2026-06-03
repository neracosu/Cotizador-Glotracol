<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin del metabox "Presentaciones" en la pantalla de edición de producto.
 *
 * Modela las presentaciones (250g, 500g, 1kg, etc.) como meta `_glo_presentaciones`
 * del producto WC. Cada item es:
 *
 *   { idx, label, sku, peso_g, precio_publico }
 *
 * NO usa productos variables WC — es nuestra propia capa de modelo. Permite
 * importar masivamente vía CSV (F10) y mantener control total del flujo de
 * cotización sin depender de cómo WC trate variations.
 *
 * El SKU efectivo del item del carrito es el de la presentación elegida, lo
 * que permite que `Glotracol_Quote_Pricing::resolve()` use el SKU correcto
 * para mirar precios públicos / B2B.
 */
class Glotracol_Quote_Presentations_Admin {

	const META_KEY = '_glo_presentaciones';
	const NONCE_FIELD = 'glo_presentaciones_nonce';
	const NONCE_ACTION = 'glo_presentaciones_save';

	public function __construct() {
		// Pestaña en el panel de datos del producto WC
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_tab' ] );
		add_action( 'woocommerce_product_data_panels', [ $this, 'render_panel' ] );
		// Save
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'save_panel' ], 10, 1 );
	}

	public function add_tab( $tabs ) {
		$tabs['glotracol_presentaciones'] = [
			'label'    => 'Presentaciones',
			'target'   => 'glotracol_presentaciones_data',
			'class'    => [], // visible para todos los tipos de producto
			'priority' => 65,
		];
		return $tabs;
	}

	public function render_panel() {
		global $post;
		$presentaciones = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! is_array( $presentaciones ) ) $presentaciones = [];
		?>
		<div id="glotracol_presentaciones_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<p class="form-field" style="padding:0 12px">
					<strong>Presentaciones del producto</strong> · <span style="color:#666">Define las variantes de empaque (250g, 500g, 1kg, etc.). Si no hay ninguna, el producto se trata como simple.</span>
				</p>

				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>

				<div id="glo-presentaciones-wrap" style="padding:0 12px 12px">
					<table class="widefat striped" id="glo-presentaciones-table" style="margin-top:8px">
						<thead>
							<tr>
								<th style="width:24%">Etiqueta visible</th>
								<th style="width:20%">SKU variante</th>
								<th style="width:14%">Peso (g)</th>
								<th style="width:24%">Precio público (COP)</th>
								<th style="width:12%"></th>
							</tr>
						</thead>
						<tbody id="glo-pres-rows">
						<?php
						$row_idx = 0;
						foreach ( $presentaciones as $p ) {
							$this->render_row( $row_idx, $p );
							$row_idx++;
						}
						// Una fila vacía si no hay nada
						if ( empty( $presentaciones ) ) {
							$this->render_row( $row_idx, [] );
							$row_idx++;
						}
						?>
						</tbody>
					</table>
					<template id="glo-pres-tpl"><?php $this->render_row( '__IDX__', [] ); ?></template>
					<p style="margin-top:10px">
						<button type="button" class="button" id="glo-pres-add" data-gloq-add-row data-target="#glo-pres-rows" data-template="#glo-pres-tpl" data-next="<?php echo (int) $row_idx; ?>">+ Añadir presentación</button>
						<span class="description" style="margin-left:10px">El precio público se usa cuando no hay match de NIT B2B. La lista global de precios (Cotizaciones → Precios) tiene prioridad si lo que se cargó allí coincide con el SKU de la variante.</span>
					</p>
				</div>
			</div>

		</div>
		<?php
	}

	private function render_row( $idx, $p ) {
		$label = $p['label'] ?? '';
		$sku   = $p['sku']   ?? '';
		$peso  = $p['peso_g'] ?? '';
		$price = $p['precio_publico'] ?? '';
		?>
		<tr>
			<td><input type="text" name="glo_pres[<?php echo (int) $idx; ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="250 g"></td>
			<td><input type="text" name="glo_pres[<?php echo (int) $idx; ?>][sku]" value="<?php echo esc_attr( $sku ); ?>" placeholder="SKU-250"></td>
			<td><input type="number" name="glo_pres[<?php echo (int) $idx; ?>][peso_g]" min="0" step="1" value="<?php echo esc_attr( $peso ); ?>" placeholder="0"></td>
			<td><input type="number" name="glo_pres[<?php echo (int) $idx; ?>][precio_publico]" min="0" step="1" value="<?php echo esc_attr( $price ); ?>" placeholder="0"></td>
			<td><button type="button" class="glo-pres-remove gloq-remove-row" aria-label="Quitar">×</button></td>
		</tr>
		<?php
	}

	public function save_panel( $product ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) return;
		if ( ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_FIELD ] ), self::NONCE_ACTION ) ) return;

		$rows = $_POST['glo_pres'] ?? [];
		$presentaciones = [];
		if ( is_array( $rows ) ) {
			$idx = 0;
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) continue;
				$label = sanitize_text_field( wp_unslash( $row['label'] ?? '' ) );
				$sku   = sanitize_text_field( wp_unslash( $row['sku'] ?? '' ) );
				if ( $label === '' && $sku === '' ) continue; // fila vacía
				$presentaciones[] = [
					'idx'             => $idx,
					'label'           => $label,
					'sku'             => $sku !== '' ? $sku : ( $product->get_sku() . '-' . sanitize_title( $label ) ),
					'peso_g'          => (int) ( $row['peso_g'] ?? 0 ),
					'precio_publico'  => (int) ( $row['precio_publico'] ?? 0 ),
				];
				$idx++;
			}
		}

		$product->update_meta_data( self::META_KEY, $presentaciones );
	}
}
