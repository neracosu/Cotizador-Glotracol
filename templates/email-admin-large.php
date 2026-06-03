<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Pedido grande recibido — #<?php echo (int) $quote_id; ?></title></head>
<body style="margin:0;padding:0;background:#fef2f2;font-family:Arial,Helvetica,sans-serif;color:#222;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;padding:24px 0">
	<tr><td align="center">
		<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 4px 16px rgba(220,38,38,0.15);border:2px solid #dc3545">
			<tr><td style="background:linear-gradient(135deg,#dc3545 0%,#a02029 100%);color:#fff;padding:24px 28px">
				<div style="display:inline-block;background:rgba(255,255,255,0.18);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:0.6px;text-transform:uppercase;margin-bottom:10px">Atención prioritaria</div>
				<h1 style="margin:0;font-size:22px;line-height:1.3">Pedido grande #<?php echo (int) $quote_id; ?></h1>
				<p style="margin:8px 0 0;font-size:14px;opacity:0.95"><strong><?php echo (int) $units_total; ?> unidades</strong> · <strong><?php echo (int) $skus_count; ?> productos distintos</strong> · clasificado como <strong>GRANDE</strong></p>
			</td></tr>

			<tr><td style="padding:24px 28px">
				<p style="margin:0 0 18px;font-size:14px;line-height:1.5;color:#5a5a5a"><?php echo esc_html( $intro ); ?></p>

				<h2 style="font-size:16px;margin:0 0 12px;color:#7a1d12;border-bottom:2px solid #fdecea;padding-bottom:6px">Datos del cliente</h2>
				<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px">
					<tr><td style="width:140px;color:#666"><strong>Nombre</strong></td><td><?php echo esc_html( $customer['name'] ?? '' ); ?></td></tr>
					<tr><td style="color:#666"><strong>Email</strong></td><td><a href="mailto:<?php echo esc_attr( $customer['email'] ?? '' ); ?>" style="color:#dc3545"><?php echo esc_html( $customer['email'] ?? '' ); ?></a></td></tr>
					<tr><td style="color:#666"><strong>Teléfono</strong></td><td><?php
						$phone = $customer['phone'] ?? '';
						$wa = $phone ? preg_replace( '/[^0-9]/', '', $phone ) : '';
						if ( $wa ) {
							echo '<a href="https://wa.me/' . esc_attr( $wa ) . '" style="color:#25D366;font-weight:600">' . esc_html( $phone ) . ' (WhatsApp)</a>';
						} else {
							echo esc_html( $phone );
						}
					?></td></tr>
					<tr><td style="color:#666"><strong>Empresa</strong></td><td><strong><?php echo esc_html( $customer['company'] ?? '' ); ?></strong></td></tr>
					<?php if ( ! empty( $customer['nit'] ) ) : ?>
					<tr><td style="color:#666"><strong>NIT</strong></td><td><?php echo esc_html( $customer['nit'] ); ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $customer['city'] ) ) : ?>
					<tr><td style="color:#666"><strong>Ciudad</strong></td><td><?php echo esc_html( $customer['city'] ); ?></td></tr>
					<?php endif; ?>
				</table>

				<?php if ( ! empty( $message ) ) : ?>
				<h3 style="font-size:14px;margin:18px 0 6px;color:#7a1d12">Mensaje del cliente</h3>
				<div style="background:#fef2f2;border-left:3px solid #dc3545;padding:10px 14px;font-size:14px;white-space:pre-wrap;color:#3d3d3d"><?php echo esc_html( $message ); ?></div>
				<?php endif; ?>

				<h2 style="font-size:16px;margin:22px 0 12px;color:#7a1d12;border-bottom:2px solid #fdecea;padding-bottom:6px">Productos solicitados (<?php echo (int) $skus_count; ?>)</h2>
				<table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px;border:1px solid #fdecea">
					<thead><tr style="background:#fdecea;text-align:left;color:#7a1d12"><th>Producto</th><th>SKU</th><th style="width:90px;text-align:center">Cantidad</th></tr></thead>
					<tbody>
					<?php foreach ( (array) $items as $it ) : ?>
						<tr style="border-top:1px solid #fdecea">
							<td><?php echo esc_html( $it['name'] ?? '' ); ?></td>
							<td style="color:#666"><?php echo esc_html( $it['sku'] ?? '—' ); ?></td>
							<td style="text-align:center"><strong style="background:#fef2f2;padding:2px 10px;border-radius:11px;color:#7a1d12"><?php echo (int) ( $it['quantity'] ?? 0 ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr style="background:#fef2f2;font-weight:700;color:#7a1d12"><td colspan="2" style="text-align:right">Total unidades:</td><td style="text-align:center"><?php echo (int) $units_total; ?></td></tr>
					</tfoot>
				</table>

				<div style="margin:22px 0 8px;padding:14px 18px;background:#fff8e1;border-left:4px solid #f7b500;border-radius:4px">
					<strong style="color:#665100;font-size:13px">Sugerencia:</strong> <span style="font-size:13px;color:#665100">esta cotización supera tu umbral de "pedido grande". Considera contactar al cliente directamente por teléfono o WhatsApp para acelerar la conversión.</span>
				</div>

				<p style="margin:22px 0 0;text-align:center">
					<a href="<?php echo esc_url( $edit_url ); ?>" style="display:inline-block;background:#dc3545;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold;font-size:14px;box-shadow:0 4px 12px rgba(220,53,69,0.3)">Ver y responder en el panel</a>
				</p>
			</td></tr>

			<tr><td style="background:#fef2f2;padding:14px 28px;font-size:11px;color:#888;text-align:center">
				Enviado desde <?php echo esc_html( get_bloginfo( 'name' ) ); ?> · <?php echo esc_html( current_time( 'd/m/Y H:i' ) ); ?>
				<?php if ( ! empty( $meta['ip'] ) ) : ?> · IP <?php echo esc_html( $meta['ip'] ); ?><?php endif; ?>
			</td></tr>
		</table>
	</td></tr>
</table>
</body>
</html>
