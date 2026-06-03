<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_Admin_Meta_Box {

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register' ] );
		add_action( 'edit_form_after_title', [ $this, 'inject_summary_top' ] );
		add_action( 'wp_ajax_gloq_convert_to_order', [ $this, 'ajax_convert_to_order' ] );
	}

	public function register() {
		add_meta_box( 'glotracol_quote_pricing', 'Tipo, precios y conversión', [ $this, 'render_pricing_box' ], 'glo_quote', 'side', 'high' );
		add_meta_box( 'glotracol_quote_customer', 'Datos del cliente', [ $this, 'render_customer' ], 'glo_quote', 'normal', 'high' );
		add_meta_box( 'glotracol_quote_items', 'Productos solicitados', [ $this, 'render_items' ], 'glo_quote', 'normal', 'high' );
		add_meta_box( 'glotracol_quote_meta', 'Información técnica', [ $this, 'render_meta' ], 'glo_quote', 'side', 'low' );
		add_meta_box( 'glotracol_quote_email_log', 'Log de envíos', [ $this, 'render_email_log' ], 'glo_quote', 'side', 'low' );
	}

	public function inject_summary_top( $post ) {
		if ( $post->post_type !== 'glo_quote' ) return;
		$email = get_post_meta( $post->ID, '_glo_customer_email', true );
		$phone = get_post_meta( $post->ID, '_glo_customer_phone', true );
		if ( ! $email && ! $phone ) return;
		echo '<div style="margin:10px 0;padding:10px 14px;background:#f0f6fc;border-left:4px solid #2271b1;">';
		echo '<strong>Acciones rápidas:</strong> ';
		if ( $email ) {
			echo '<a class="button" style="margin-right:6px" href="mailto:' . esc_attr( $email ) . '">Responder por email</a>';
		}
		if ( $phone ) {
			$wa = preg_replace( '/[^0-9]/', '', $phone );
			echo '<a class="button" target="_blank" rel="noopener" href="https://wa.me/' . esc_attr( $wa ) . '">Contactar por WhatsApp</a>';
		}
		echo '</div>';
	}

	public function render_customer( $post ) {
		$fields = [
			'_glo_customer_name'    => 'Nombre',
			'_glo_customer_email'   => 'Email',
			'_glo_customer_phone'   => 'Teléfono',
			'_glo_customer_company' => 'Empresa',
			'_glo_customer_nit'     => 'NIT / Documento',
			'_glo_customer_city'    => 'Ciudad',
			'_glo_customer_message' => 'Mensaje',
		];
		echo '<table class="form-table"><tbody>';
		foreach ( $fields as $key => $label ) {
			$value = get_post_meta( $post->ID, $key, true );
			echo '<tr><th style="width:180px">' . esc_html( $label ) . '</th><td>';
			if ( $key === '_glo_customer_message' ) {
				echo $value ? wp_kses_post( wpautop( $value ) ) : '—';
			} elseif ( $key === '_glo_customer_email' && $value ) {
				echo '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
			} else {
				echo esc_html( $value ?: '—' );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function render_items( $post ) {
		$items = get_post_meta( $post->ID, '_glo_items', true );
		if ( ! is_array( $items ) || empty( $items ) ) {
			echo '<p>No hay productos registrados.</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Producto</th><th>SKU</th><th style="width:90px">Cantidad</th></tr></thead><tbody>';
		foreach ( $items as $it ) {
			$pid  = isset( $it['product_id'] ) ? (int) $it['product_id'] : 0;
			$name = isset( $it['name'] ) ? $it['name'] : '';
			$sku  = isset( $it['sku'] ) ? $it['sku'] : '';
			$qty  = isset( $it['quantity'] ) ? (int) $it['quantity'] : 0;
			$link = $pid ? get_edit_post_link( $pid ) : '';
			echo '<tr><td>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . '</td>';
			echo '<td>' . esc_html( $sku ?: '—' ) . '</td>';
			echo '<td>' . esc_html( $qty ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function render_meta( $post ) {
		$meta = get_post_meta( $post->ID, '_glo_meta', true );
		if ( ! is_array( $meta ) ) $meta = [];
		$keys = [
			'ip'         => 'IP',
			'user_agent' => 'User Agent',
			'referer'    => 'Referer',
			'lang'       => 'Idioma',
			'timestamp'  => 'Timestamp',
		];
		echo '<ul style="margin:0">';
		foreach ( $keys as $k => $label ) {
			$v = isset( $meta[ $k ] ) ? $meta[ $k ] : '—';
			echo '<li><strong>' . esc_html( $label ) . ':</strong> <code style="word-break:break-all">' . esc_html( $v ) . '</code></li>';
		}
		echo '</ul>';
	}

	public function render_email_log( $post ) {
		$log = get_post_meta( $post->ID, '_glo_email_log', true );
		if ( ! is_array( $log ) || empty( $log ) ) {
			echo '<p>Sin envíos registrados.</p>';
			return;
		}
		echo '<ul style="margin:0">';
		foreach ( $log as $entry ) {
			$type = isset( $entry['type'] ) ? $entry['type'] : '';
			$to   = isset( $entry['to'] ) ? $entry['to'] : '';
			$ok   = ! empty( $entry['success'] );
			$at   = isset( $entry['sent_at'] ) ? $entry['sent_at'] : '';
			echo '<li style="margin-bottom:4px">' . ( $ok ? '✓' : '✗' ) . ' <strong>' . esc_html( $type ) . '</strong> → <code>' . esc_html( $to ) . '</code><br><small>' . esc_html( $at ) . '</small></li>';
		}
		echo '</ul>';
	}

	/**
	 * Metabox lateral: tipo (cotización/pedido), pricing status, total y botón
	 * "Convertir en pedido" (F7).
	 */
	public function render_pricing_box( $post ) {
		$type = get_post_meta( $post->ID, '_glo_type', true ) ?: 'quote';
		$pricing_status = get_post_meta( $post->ID, '_glo_pricing_status', true );
		$total = (int) get_post_meta( $post->ID, '_glo_total', true );
		$client_id = (int) get_post_meta( $post->ID, '_glo_client_id', true );
		$items = get_post_meta( $post->ID, '_glo_items', true );
		?>
		<div class="gloq-pricing-box">
			<p style="margin:0 0 10px"><strong>Tipo:</strong> <span class="glo-type glo-type-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( glotracol_quote_type_label( $type ) ); ?></span></p>
			<p style="margin:0 0 10px"><strong>Cliente B2B:</strong>
				<?php if ( $client_id ) :
					$cname = get_post_meta( $client_id, '_glo_client_name', true );
					$curl = get_edit_post_link( $client_id );
				?>
					<a href="<?php echo esc_url( $curl ); ?>"><?php echo esc_html( $cname ?: '#' . $client_id ); ?></a>
				<?php else : ?>
					<em style="color:#999">— No identificado por NIT</em>
				<?php endif; ?>
			</p>
			<p style="margin:0 0 10px"><strong>Pricing:</strong> <?php
				$badge_map = [
					'priced'  => [ 'background' => '#cfe2ff', 'color' => '#0a3a6e', 'label' => '✓ Todos con precio' ],
					'partial' => [ 'background' => '#fff8e1', 'color' => '#665100', 'label' => '⚠ Faltan precios' ],
					'none'    => [ 'background' => '#fdecea', 'color' => '#7a1d12', 'label' => '✗ Sin precios' ],
				];
				$b = $badge_map[ $pricing_status ] ?? null;
				if ( $b ) {
					echo '<span style="background:' . $b['background'] . ';color:' . $b['color'] . ';padding:2px 10px;border-radius:11px;font-size:11px;font-weight:600">' . esc_html( $b['label'] ) . '</span>';
				} else {
					echo '<em style="color:#999">— sin clasificar</em>';
				}
			?></p>
			<?php if ( $total > 0 ) : ?>
				<p style="margin:0 0 14px;font-size:18px"><strong>Total:</strong> <strong style="color:#0a4d3a"><?php echo esc_html( glotracol_quote_format_price( $total ) ); ?></strong></p>
			<?php endif; ?>

			<hr>

			<?php if ( $type === 'quote' ) : ?>
				<p style="margin:14px 0 8px"><strong>Convertir en pedido</strong></p>
				<p style="font-size:12px;color:#666;margin:0 0 10px">Cambia el tipo a "Pedido" (intención de compra firme), opcionalmente completa precios faltantes y reenvía el email formal al cliente.</p>
				<button type="button" class="button button-primary" id="gloq-convert-btn" data-post-id="<?php echo (int) $post->ID; ?>">→ Convertir en pedido</button>
				<span id="gloq-convert-status" style="display:block;margin-top:8px;font-size:12px"></span>
			<?php else : ?>
				<p style="margin:14px 0 0;font-size:12px;color:#666"><em>Esta solicitud ya está marcada como pedido en firme.</em></p>
			<?php endif; ?>
		</div>

		<!-- Modal -->
		<div class="gloq-convert-modal" id="gloq-convert-modal" style="display:none">
			<div class="gloq-convert-modal-inner">
				<button type="button" class="gloq-convert-close" aria-label="Cerrar">×</button>
				<h2>Convertir en pedido</h2>
				<p>Confirma o ajusta los precios. Si todos los SKUs quedan con precio, el cliente recibirá automáticamente el email de "Confirmación de pedido". El estado pasará a <strong>Auto-cotizada</strong>.</p>
				<table class="widefat striped" style="margin:14px 0">
					<thead><tr><th>Producto</th><th>SKU</th><th style="width:60px;text-align:center">Cant.</th><th style="width:160px">Precio unitario</th><th style="width:130px;text-align:right">Subtotal</th></tr></thead>
					<tbody>
					<?php foreach ( (array) $items as $i => $it ) :
						$price = (int) ( $it['precio_unitario'] ?? 0 );
						$qty = (int) ( $it['quantity'] ?? 0 );
					?>
						<tr>
							<td><?php echo esc_html( $it['name'] ?? '' ); ?><?php if ( ! empty( $it['presentacion_label'] ) ) echo ' <small style="color:#666">— ' . esc_html( $it['presentacion_label'] ) . '</small>'; ?></td>
							<td style="font-family:monospace;font-size:12px"><?php echo esc_html( $it['sku'] ?? '—' ); ?></td>
							<td style="text-align:center"><?php echo (int) $qty; ?></td>
							<td><input type="number" class="gloq-convert-price" data-idx="<?php echo (int) $i; ?>" data-qty="<?php echo (int) $qty; ?>" min="0" step="1" value="<?php echo (int) $price; ?>" style="width:140px"></td>
							<td style="text-align:right" class="gloq-convert-subtotal" data-idx="<?php echo (int) $i; ?>"><?php echo esc_html( glotracol_quote_format_price( $price * $qty ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr style="background:#f0fff4"><td colspan="4" style="text-align:right;padding:12px"><strong>Total:</strong></td><td id="gloq-convert-total" style="text-align:right;padding:12px;font-weight:700;color:#0a4d3a;font-size:16px"><?php echo esc_html( glotracol_quote_format_price( $total ) ); ?></td></tr>
					</tfoot>
				</table>
				<div style="display:flex;gap:10px;justify-content:flex-end">
					<button type="button" class="button" id="gloq-convert-cancel">Cancelar</button>
					<button type="button" class="button button-primary" id="gloq-convert-confirm">✓ Confirmar conversión</button>
				</div>
			</div>
		</div>

		<script>
		jQuery(function($){
			var $modal = $('#gloq-convert-modal');
			var $btn = $('#gloq-convert-btn');
			var $status = $('#gloq-convert-status');
			$btn.on('click', function(){ $modal.show(); recomputeTotal(); });
			$('#gloq-convert-cancel, .gloq-convert-close').on('click', function(){ $modal.hide(); });
			$modal.on('click', function(e){ if (e.target === this) $modal.hide(); });

			function fmt(amount){
				return '$ ' + (parseInt(amount,10)||0).toLocaleString('es-CO') + ' COP';
			}
			function recomputeTotal(){
				var total = 0;
				$('.gloq-convert-price').each(function(){
					var price = parseInt($(this).val(), 10) || 0;
					var qty = parseInt($(this).data('qty'), 10) || 0;
					var sub = price * qty;
					$('.gloq-convert-subtotal[data-idx="' + $(this).data('idx') + '"]').text(fmt(sub));
					total += sub;
				});
				$('#gloq-convert-total').text(fmt(total));
			}
			$(document).on('input change', '.gloq-convert-price', recomputeTotal);

			$('#gloq-convert-confirm').on('click', function(){
				var $cbtn = $(this);
				var prices = {};
				$('.gloq-convert-price').each(function(){
					prices[$(this).data('idx')] = parseInt($(this).val(), 10) || 0;
				});
				$cbtn.prop('disabled', true).text('Procesando…');
				$.post(ajaxurl, {
					action: 'gloq_convert_to_order',
					_wpnonce: '<?php echo esc_js( wp_create_nonce( 'gloq_convert_to_order' ) ); ?>',
					post_id: <?php echo (int) $post->ID; ?>,
					prices: prices
				}).done(function(resp){
					if (resp && resp.success) {
						$status.html('<span style="color:#155724;background:#d4edda;padding:6px 12px;border-radius:4px">✓ ' + resp.data.message + '</span>');
						setTimeout(function(){ location.reload(); }, 1200);
					} else {
						alert((resp && resp.data && resp.data.message) || 'Error desconocido');
						$cbtn.prop('disabled', false).text('✓ Confirmar conversión');
					}
				}).fail(function(){
					alert('Error de conexión');
					$cbtn.prop('disabled', false).text('✓ Confirmar conversión');
				});
			});
		});
		</script>

		<style>
		.gloq-convert-modal{position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px}
		.gloq-convert-modal-inner{background:#fff;border-radius:10px;max-width:880px;width:100%;padding:28px 30px;max-height:90vh;overflow:auto;position:relative;box-shadow:0 20px 60px rgba(0,0,0,0.32)}
		.gloq-convert-modal-inner h2{margin:0 0 8px;color:#0a4d3a}
		.gloq-convert-close{position:absolute;top:10px;right:14px;background:transparent;border:0;font-size:26px;cursor:pointer;color:#94a3b8;width:36px;height:36px;border-radius:6px}
		.gloq-convert-close:hover{background:#f1f5f9;color:#1a202c}
		</style>
		<?php
	}

	/**
	 * AJAX endpoint para F7 — convertir cotización en pedido.
	 *
	 * Recibe: post_id, prices[idx => precio]
	 * Acción: actualiza items con nuevos precios, cambia _glo_type → 'order',
	 *         recalcula pricing_status y total, cambia status a glo-auto-priced
	 *         si todos los precios > 0, dispara reenvío del email auto-cotizado.
	 */
	public function ajax_convert_to_order() {
		check_ajax_referer( 'gloq_convert_to_order', '_wpnonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( [ 'message' => 'Sin permisos' ] );

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$prices = isset( $_POST['prices'] ) && is_array( $_POST['prices'] ) ? $_POST['prices'] : [];
		if ( ! $post_id || get_post_type( $post_id ) !== 'glo_quote' ) {
			wp_send_json_error( [ 'message' => 'Cotización inválida' ] );
		}

		$items = get_post_meta( $post_id, '_glo_items', true );
		if ( ! is_array( $items ) ) wp_send_json_error( [ 'message' => 'Sin items' ] );

		$new_total = 0;
		$all_priced = true;
		foreach ( $items as $idx => &$it ) {
			$price = isset( $prices[ $idx ] ) ? (int) $prices[ $idx ] : (int) ( $it['precio_unitario'] ?? 0 );
			$qty = (int) ( $it['quantity'] ?? 0 );
			$it['precio_unitario'] = $price;
			$it['precio_subtotal'] = $price * $qty;
			if ( $price > 0 ) {
				$it['precio_origen'] = $it['precio_origen'] === 'pendiente' || empty( $it['precio_origen'] ) ? 'manual' : $it['precio_origen'];
				$new_total += $price * $qty;
			} else {
				$all_priced = false;
				$it['precio_origen'] = 'pendiente';
				$it['precio_subtotal'] = null;
			}
		}
		unset( $it );

		update_post_meta( $post_id, '_glo_items', $items );
		update_post_meta( $post_id, '_glo_total', $new_total );
		update_post_meta( $post_id, '_glo_type', 'order' );
		update_post_meta( $post_id, '_glo_pricing_status', $all_priced ? 'priced' : 'partial' );
		update_post_meta( $post_id, '_glo_converted_at', current_time( 'mysql' ) );

		// Cambiar status del post
		if ( $all_priced ) {
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'glo-auto-priced' ] );
		} else {
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'glo-pending-prices' ] );
		}

		// Renombrar título a "Pedido #..."
		$customer_name = get_post_meta( $post_id, '_glo_customer_name', true );
		$current_post = get_post( $post_id );
		if ( $current_post ) {
			$new_title = sprintf( 'Pedido #%d — %s', $post_id, $customer_name );
			wp_update_post( [ 'ID' => $post_id, 'post_title' => $new_title ] );
		}

		if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
			Glotracol_Quote_Logger::info( 'conversion', sprintf( 'Cotización #%d convertida a pedido (%s)', $post_id, $all_priced ? 'todos con precio' : 'parcial' ), [
				'quote_id' => $post_id, 'all_priced' => $all_priced, 'new_total' => $new_total,
			] );
		}

		// Si quedó priced, disparar email auto-cotizada al cliente
		if ( $all_priced ) {
			$payload = $this->reconstruct_payload( $post_id );
			// Solo enviar al cliente, no al admin (el admin ya está viendo esto)
			$this->send_customer_priced_email_only( $post_id, $payload );
			wp_send_json_success( [ 'message' => 'Convertido a pedido. Total: ' . glotracol_quote_format_price( $new_total ) . '. Email enviado al cliente.' ] );
		}

		wp_send_json_success( [ 'message' => 'Convertido a pedido. Faltan ' . count( array_filter( $items, function ( $it ) { return ($it['precio_origen'] ?? '') === 'pendiente'; } ) ) . ' precio(s) por completar.' ] );
	}

	/**
	 * Helper: dispara solo el email "auto-cotizada" al cliente. No reenvía al admin.
	 */
	private function send_customer_priced_email_only( $post_id, $payload ) {
		$customer = $payload['customer'] ?? [];
		if ( ! is_email( $customer['email'] ?? '' ) ) return false;

		$type = $payload['type'] ?? 'order';
		$total = (int) ( $payload['pricing']['total'] ?? get_post_meta( $post_id, '_glo_total', true ) );
		$client_id = (int) ( $payload['client_id'] ?? get_post_meta( $post_id, '_glo_client_id', true ) );
		$client_name = $client_id ? get_post_meta( $client_id, '_glo_client_name', true ) : '';

		$settings = glotracol_quote_get_settings();
		$from_name  = $settings['sender_name'] ?: get_bloginfo( 'name' );
		$from_email = is_email( $settings['sender_email'] ) ? $settings['sender_email'] : get_option( 'admin_email' );
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		];
		$subject = sprintf(
			'%s #%d — %s',
			$type === 'order' ? 'Confirmación de pedido' : 'Cotización formal',
			$post_id,
			glotracol_quote_format_price( $total )
		);
		$body = glotracol_quote_load_template( 'email-customer-priced.php', [
			'quote_id'    => $post_id,
			'customer'    => $customer,
			'items'       => $payload['items'] ?? [],
			'intro'       => glotracol_quote_get_setting( 'customer_intro' ),
			'type'        => $type,
			'total'       => $total,
			'client_name' => $client_name,
		] );
		$ok = wp_mail( $customer['email'], $subject, $body, $headers );

		// Log
		$log = get_post_meta( $post_id, '_glo_email_log', true );
		if ( ! is_array( $log ) ) $log = [];
		$log[] = [
			'type'    => 'customer-converted',
			'to'      => $customer['email'],
			'sent_at' => current_time( 'mysql' ),
			'success' => (bool) $ok,
		];
		update_post_meta( $post_id, '_glo_email_log', $log );
		return $ok;
	}

	/**
	 * Reconstruye un payload similar al del submit original a partir de las metas.
	 */
	private function reconstruct_payload( $post_id ) {
		return [
			'customer' => [
				'name'    => get_post_meta( $post_id, '_glo_customer_name', true ),
				'email'   => get_post_meta( $post_id, '_glo_customer_email', true ),
				'phone'   => get_post_meta( $post_id, '_glo_customer_phone', true ),
				'company' => get_post_meta( $post_id, '_glo_customer_company', true ),
				'nit'     => get_post_meta( $post_id, '_glo_customer_nit', true ),
				'city'    => get_post_meta( $post_id, '_glo_customer_city', true ),
				'message' => get_post_meta( $post_id, '_glo_customer_message', true ),
			],
			'type'      => get_post_meta( $post_id, '_glo_type', true ),
			'client_id' => (int) get_post_meta( $post_id, '_glo_client_id', true ),
			'items'     => get_post_meta( $post_id, '_glo_items', true ) ?: [],
			'pricing'   => [
				'status' => get_post_meta( $post_id, '_glo_pricing_status', true ),
				'total'  => (int) get_post_meta( $post_id, '_glo_total', true ),
			],
			'meta'      => get_post_meta( $post_id, '_glo_meta', true ) ?: [],
		];
	}
}
