<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="glotracol-quote-wrapper">
	<?php if ( ! empty( $error ) ) : ?>
		<div class="glotracol-quote-alert glotracol-quote-alert-error"><?php echo esc_html( $error ); ?></div>
	<?php endif; ?>

	<?php if ( ! empty( $form_intro ) ) : ?>
		<p class="glotracol-quote-intro"><?php echo esc_html( $form_intro ); ?></p>
	<?php endif; ?>

	<div class="glotracol-quote-items-header">
		<h3>Productos en tu cotización <span class="gloq-items-count">(<?php echo count( $cart_items ); ?>)</span></h3>
		<a href="<?php echo esc_url( $shop_url ); ?>" class="glotracol-quote-add-more">+ Añadir más productos</a>
	</div>

	<p class="gloq-cotiza-hint"><span class="dashicons-info"></span> Al enviar podrás elegir si es una <strong>cotización</strong> o un <strong>pedido en firme</strong>.</p>

	<table class="glotracol-quote-items" id="gloq-items-table" data-reprice-nonce="<?php echo esc_attr( $reprice_nonce ?? '' ); ?>">
		<thead>
			<tr><th>Producto</th><th>Presentación</th><th>Cantidad</th><th class="gloq-col-valor">Valor</th><th></th></tr>
		</thead>
		<tbody>
		<?php foreach ( $cart_items as $item ) : ?>
			<tr data-cart-key="<?php echo esc_attr( $item['key'] ); ?>">
				<td>
					<?php echo $item['image']; // image markup from WC ?>
					<a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
				</td>
				<td class="gloq-col-presentacion"><?php echo esc_html( $item['presentacion'] ?: '—' ); ?></td>
				<td>
					<div class="gloq-qty-cell">
						<input type="number"
							class="gloq-qty-input"
							data-cart-key="<?php echo esc_attr( $item['key'] ); ?>"
							value="<?php echo (int) $item['quantity']; ?>"
							min="1"
							step="1"
							aria-label="Cantidad de <?php echo esc_attr( $item['name'] ); ?>">
					</div>
				</td>
				<td class="gloq-col-valor" data-cart-key="<?php echo esc_attr( $item['key'] ); ?>">
					<span class="gloq-valor-sub"><?php echo esc_html( $item['valor_sub_fmt'] ); ?></span>
					<span class="gloq-valor-unit"><?php echo esc_html( $item['valor_unit_fmt'] ); ?> c/u</span>
				</td>
				<td>
					<button type="button" class="gloq-remove-item" data-cart-key="<?php echo esc_attr( $item['key'] ); ?>" title="Quitar de la cotización" aria-label="Quitar producto">×</button>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr class="gloq-total-row"<?php echo empty( $cart_total_fmt ) ? ' hidden' : ''; ?>>
				<td colspan="3"></td>
				<td class="gloq-col-valor"><strong>Total:</strong> <span class="gloq-total-value"><?php echo esc_html( $cart_total_fmt ); ?></span></td>
				<td></td>
			</tr>
		</tfoot>
	</table>
	<p class="gloq-valor-nota" id="gloq-valor-nota">Los precios mostrados son de lista pública. Si tu empresa tiene precios negociados, escribe tu <strong>NIT</strong> abajo y se actualizarán automáticamente.</p>
	<p class="gloq-helper-text"><span class="dashicons-info"></span> Las cantidades se guardan automáticamente al cambiarlas. Puedes seguir agregando productos desde el catálogo.</p>

	<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="glotracol-quote-form" id="gloq-form">
		<input type="hidden" name="action" value="<?php echo esc_attr( $submit_action ); ?>">
		<input type="hidden" name="gloq_type" id="gloq-type-input" value="">
		<?php wp_nonce_field( $nonce_action, $nonce_field ); ?>

		<h3>Tus datos</h3>

		<div class="glotracol-quote-row">
			<label>
				<span>Nombre completo <span class="req">*</span></span>
				<input type="text" name="gloq_name" required value="<?php echo esc_attr( $old['name'] ?? '' ); ?>">
			</label>
			<label>
				<span>Email <span class="req">*</span></span>
				<input type="email" name="gloq_email" required value="<?php echo esc_attr( $old['email'] ?? '' ); ?>">
			</label>
		</div>

		<div class="glotracol-quote-row">
			<label>
				<span>Teléfono / WhatsApp <span class="req">*</span></span>
				<input type="tel" name="gloq_phone" required value="<?php echo esc_attr( $old['phone'] ?? '' ); ?>">
			</label>
			<label>
				<span>Empresa <span class="req">*</span></span>
				<input type="text" name="gloq_company" required value="<?php echo esc_attr( $old['company'] ?? '' ); ?>">
			</label>
		</div>

		<div class="glotracol-quote-row">
			<label>
				<span>NIT / Documento</span>
				<input type="text" name="gloq_nit" value="<?php echo esc_attr( $old['nit'] ?? '' ); ?>">
			</label>
			<label>
				<span>Ciudad / País</span>
				<input type="text" name="gloq_city" value="<?php echo esc_attr( $old['city'] ?? '' ); ?>">
			</label>
		</div>

		<label class="glotracol-quote-block">
			<span>Mensaje / Observaciones</span>
			<textarea name="gloq_message" rows="4" placeholder="Cuéntanos requerimientos especiales, frecuencia de compra, presentaciones de interés…"><?php echo esc_textarea( $old['message'] ?? '' ); ?></textarea>
		</label>

		<div class="glotracol-quote-honeypot" aria-hidden="true">
			<label>Si eres humano deja este campo vacío:
				<input type="text" name="gloq_website" tabindex="-1" autocomplete="off">
			</label>
		</div>

		<label class="glotracol-quote-terms">
			<input type="checkbox" name="gloq_terms" value="1" required>
			<span><?php echo esc_html( $terms_text ); ?></span>
		</label>

		<button type="submit" class="glotracol-quote-submit button alt" id="gloq-submit-btn">Enviar solicitud →</button>
	</form>
</div>

<div class="gloq-modal-overlay" id="gloq-type-modal" role="dialog" aria-modal="true" aria-labelledby="gloq-type-modal-title" hidden>
	<div class="gloq-modal">
		<button type="button" class="gloq-modal-close" id="gloq-modal-close" aria-label="Cerrar">×</button>
		<h2 id="gloq-type-modal-title">¿Qué deseas enviar?</h2>
		<p class="gloq-modal-intro">Antes de enviar, confírmanos si esta solicitud es una <strong>cotización exploratoria</strong> (necesitas saber precios y disponibilidad antes de decidir) o un <strong>pedido en firme</strong> (ya estás listo para comprar y solo esperas la factura).</p>
		<div class="gloq-modal-options">
			<button type="button" class="gloq-modal-option" data-gloq-type="quote">
				<div class="gloq-modal-option-body">
					<strong>Es una cotización</strong>
					<span>Quiero saber precios y disponibilidad antes de decidir.</span>
				</div>
			</button>
			<button type="button" class="gloq-modal-option" data-gloq-type="order">
				<div class="gloq-modal-option-body">
					<strong>Es un pedido</strong>
					<span>Ya decidí, quiero comprar estos productos. Esperamos la factura.</span>
				</div>
			</button>
		</div>
		<p class="gloq-modal-hint">Puedes cambiar de tipo después si lo necesitas — esta selección es solo para que el equipo Glotracol priorice tu solicitud correctamente.</p>
	</div>
</div>
