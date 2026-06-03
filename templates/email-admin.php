<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Nueva cotización #<?php echo (int) $quote_id; ?></title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#222;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 0">
	<tr><td align="center">
		<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.06)">
			<tr><td style="background:#0a4d3a;color:#fff;padding:20px 28px">
				<h1 style="margin:0;font-size:20px">Nueva cotización #<?php echo (int) $quote_id; ?></h1>
				<p style="margin:6px 0 0;font-size:13px;opacity:0.85"><?php echo esc_html( $intro ); ?></p>
			</td></tr>

			<tr><td style="padding:24px 28px">
				<h2 style="font-size:16px;margin:0 0 12px;color:#0a4d3a;border-bottom:2px solid #e6e9ec;padding-bottom:6px">Datos del cliente</h2>
				<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px">
					<tr><td style="width:140px;color:#666"><strong>Nombre</strong></td><td><?php echo esc_html( $customer['name'] ?? '' ); ?></td></tr>
					<tr><td style="color:#666"><strong>Email</strong></td><td><a href="mailto:<?php echo esc_attr( $customer['email'] ?? '' ); ?>" style="color:#0a4d3a"><?php echo esc_html( $customer['email'] ?? '' ); ?></a></td></tr>
					<tr><td style="color:#666"><strong>Teléfono</strong></td><td><?php echo esc_html( $customer['phone'] ?? '' ); ?></td></tr>
					<tr><td style="color:#666"><strong>Empresa</strong></td><td><?php echo esc_html( $customer['company'] ?? '' ); ?></td></tr>
					<?php if ( ! empty( $customer['nit'] ) ) : ?>
					<tr><td style="color:#666"><strong>NIT</strong></td><td><?php echo esc_html( $customer['nit'] ); ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $customer['city'] ) ) : ?>
					<tr><td style="color:#666"><strong>Ciudad</strong></td><td><?php echo esc_html( $customer['city'] ); ?></td></tr>
					<?php endif; ?>
				</table>

				<?php if ( ! empty( $message ) ) : ?>
				<h3 style="font-size:14px;margin:18px 0 6px;color:#0a4d3a">Mensaje del cliente</h3>
				<div style="background:#f4f6f8;border-left:3px solid #0a4d3a;padding:10px 14px;font-size:14px;white-space:pre-wrap"><?php echo esc_html( $message ); ?></div>
				<?php endif; ?>

				<h2 style="font-size:16px;margin:22px 0 12px;color:#0a4d3a;border-bottom:2px solid #e6e9ec;padding-bottom:6px">Productos solicitados</h2>
				<table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px;border:1px solid #e6e9ec">
					<thead><tr style="background:#f4f6f8;text-align:left"><th>Producto</th><th>SKU</th><th style="width:90px;text-align:center">Cantidad</th></tr></thead>
					<tbody>
					<?php foreach ( (array) $items as $it ) : ?>
						<tr style="border-top:1px solid #e6e9ec">
							<td><?php echo esc_html( $it['name'] ?? '' ); ?></td>
							<td><?php echo esc_html( $it['sku'] ?? '—' ); ?></td>
							<td style="text-align:center"><strong><?php echo (int) ( $it['quantity'] ?? 0 ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin:22px 0 0;text-align:center">
					<a href="<?php echo esc_url( $edit_url ); ?>" style="display:inline-block;background:#0a4d3a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-weight:bold">Ver y responder en el panel</a>
				</p>
			</td></tr>

			<tr><td style="background:#f4f6f8;padding:14px 28px;font-size:11px;color:#888;text-align:center">
				Enviado desde <?php echo esc_html( get_bloginfo( 'name' ) ); ?> · <?php echo esc_html( current_time( 'd/m/Y H:i' ) ); ?>
				<?php if ( ! empty( $meta['ip'] ) ) : ?> · IP <?php echo esc_html( $meta['ip'] ); ?><?php endif; ?>
			</td></tr>
		</table>
	</td></tr>
</table>
</body>
</html>
