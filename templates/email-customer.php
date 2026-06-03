<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Hemos recibido tu cotización</title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#222">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 0">
	<tr><td align="center">
		<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.06)">
			<tr><td style="background:#0a4d3a;color:#fff;padding:24px 28px;text-align:center">
				<h1 style="margin:0;font-size:22px">¡Recibimos tu solicitud!</h1>
				<p style="margin:8px 0 0;font-size:14px;opacity:0.9">Cotización #<?php echo (int) $quote_id; ?></p>
			</td></tr>

			<tr><td style="padding:28px 28px 8px;font-size:15px;line-height:1.6">
				<p><?php echo esc_html( $intro ); ?></p>
				<p style="background:#fff8e1;border-left:3px solid #f7b500;padding:12px 14px;font-size:13px;color:#665100;margin:18px 0"><strong>Nota:</strong> este correo es la <strong>confirmación de recepción</strong> de tu solicitud. La cotización formal con precios y disponibilidad la recibirás directamente del equipo comercial de Glotracol.</p>
			</td></tr>

			<tr><td style="padding:0 28px 8px">
				<h2 style="font-size:16px;margin:6px 0 10px;color:#0a4d3a;border-bottom:2px solid #e6e9ec;padding-bottom:6px">Productos solicitados</h2>
				<table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px;border:1px solid #e6e9ec">
					<thead><tr style="background:#f4f6f8;text-align:left"><th>Producto</th><th style="width:90px;text-align:center">Cantidad</th></tr></thead>
					<tbody>
					<?php foreach ( (array) $items as $it ) : ?>
						<tr style="border-top:1px solid #e6e9ec">
							<td><?php echo esc_html( $it['name'] ?? '' ); ?></td>
							<td style="text-align:center"><strong><?php echo (int) ( $it['quantity'] ?? 0 ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</td></tr>

			<tr><td style="padding:18px 28px 28px;font-size:14px;line-height:1.6">
				<p>Si necesitas modificar tu solicitud, escríbenos respondiendo este correo.</p>
				<p style="margin-top:18px">Saludos,<br><strong>Equipo Glotracol</strong><br>Global Trading de Colombia</p>
			</td></tr>

			<tr><td style="background:#f4f6f8;padding:14px 28px;font-size:11px;color:#888;text-align:center">
				<?php echo esc_html( get_bloginfo( 'name' ) ); ?> · <?php echo esc_html( home_url( '/' ) ); ?>
			</td></tr>
		</table>
	</td></tr>
</table>
</body>
</html>
