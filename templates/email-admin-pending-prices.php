<?php if ( ! defined( 'ABSPATH' ) ) exit;
$missing_count = 0;
foreach ( (array) $items as $it ) {
	if ( ( $it['precio_origen'] ?? '' ) === 'pendiente' ) $missing_count++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Cotización pendiente de precios — #<?php echo (int) $quote_id; ?></title></head>
<body style="margin:0;padding:0;background:#fffbf2;font-family:Arial,Helvetica,sans-serif;color:#222">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fffbf2;padding:24px 0">
	<tr><td align="center">
		<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 4px 16px rgba(247,181,0,0.15);border:2px solid #f7b500">
			<tr><td style="background:linear-gradient(135deg,#f7b500 0%,#cc8c00 100%);color:#fff;padding:24px 28px">
				<div style="display:inline-block;background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:0.6px;text-transform:uppercase;margin-bottom:10px">Acción requerida</div>
				<h1 style="margin:0;font-size:22px;line-height:1.3">Cotización pendiente — completar precios</h1>
				<p style="margin:8px 0 0;font-size:14px;opacity:0.95">#<?php echo (int) $quote_id; ?> · <strong><?php echo (int) $missing_count; ?></strong> SKUs sin precio · Cliente espera respuesta manual</p>
			</td></tr>

			<tr><td style="padding:24px 28px">
				<p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#5a5a5a">El cliente envió una solicitud que <strong>no se pudo auto-cotizar</strong> porque faltan precios para uno o más SKUs. La cotización está guardada con estado <strong>"Pendiente de precios"</strong>; el cliente solo recibió email de confirmación de recepción.</p>

				<div style="background:#fff8e1;border-left:4px solid #f7b500;padding:14px 18px;border-radius:4px;margin-bottom:20px;font-size:13px;color:#665100">
					<strong>Acciones recomendadas:</strong>
					<ul style="margin:6px 0 0;padding-left:20px">
						<li>Completar precios faltantes en la pantalla <em>"Precios"</em> (lista pública) o en el cliente B2B (si aplica).</li>
						<li>Una vez cargados, abre la cotización y haz clic en <strong>"Reenviar con precios"</strong> para auto-cotizar.</li>
						<li>O responde manualmente desde la cotización.</li>
					</ul>
				</div>

				<h2 style="font-size:16px;margin:0 0 12px;color:#0a4d3a;border-bottom:2px solid #e6e9ec;padding-bottom:6px">Datos del cliente</h2>
				<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px">
					<tr><td style="width:140px;color:#666"><strong>Nombre</strong></td><td><?php echo esc_html( $customer['name'] ?? '' ); ?></td></tr>
					<tr><td style="color:#666"><strong>Email</strong></td><td><a href="mailto:<?php echo esc_attr( $customer['email'] ?? '' ); ?>" style="color:#0a4d3a"><?php echo esc_html( $customer['email'] ?? '' ); ?></a></td></tr>
					<tr><td style="color:#666"><strong>Teléfono</strong></td><td><?php
						$phone = $customer['phone'] ?? '';
						$wa = $phone ? preg_replace( '/[^0-9]/', '', $phone ) : '';
						if ( $wa ) {
							echo '<a href="https://wa.me/' . esc_attr( $wa ) . '" style="color:#25D366;font-weight:600">' . esc_html( $phone ) . '</a>';
						} else {
							echo esc_html( $phone );
						}
					?></td></tr>
					<tr><td style="color:#666"><strong>Empresa</strong></td><td><strong><?php echo esc_html( $customer['company'] ?? '' ); ?></strong></td></tr>
					<?php if ( ! empty( $customer['nit'] ) ) : ?>
					<tr><td style="color:#666"><strong>NIT</strong></td><td><?php echo esc_html( $customer['nit'] ); ?><?php if ( $client_id ) echo ' <span style="font-size:11px;background:#cfe2ff;color:#0a3a6e;padding:1px 6px;border-radius:6px;font-weight:700;margin-left:4px">EN CRM</span>'; else echo ' <span style="font-size:11px;background:#fff8e1;color:#665100;padding:1px 6px;border-radius:6px;font-weight:700;margin-left:4px">NO EN CRM</span>'; ?></td></tr>
					<?php endif; ?>
				</table>

				<h2 style="font-size:16px;margin:22px 0 12px;color:#0a4d3a;border-bottom:2px solid #e6e9ec;padding-bottom:6px">Productos solicitados</h2>
				<table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:13px;border:1px solid #e6e9ec">
					<thead><tr style="background:#f4f6f8;text-align:left;color:#0a4d3a"><th>Producto</th><th>SKU efectivo</th><th style="text-align:center;width:60px">Cant.</th><th style="width:130px;text-align:right">Precio</th></tr></thead>
					<tbody>
					<?php foreach ( (array) $items as $it ) :
						$price = (int) ( $it['precio_unitario'] ?? 0 );
						$source = $it['precio_origen'] ?? '';
						$row_bg = $source === 'pendiente' ? '#fff8e1' : '#fff';
					?>
						<tr style="border-top:1px solid #e6e9ec;background:<?php echo $row_bg; ?>">
							<td><?php echo esc_html( $it['name'] ?? '' ); ?><?php if ( ! empty( $it['presentacion_label'] ) ) echo ' <small style="color:#666">— ' . esc_html( $it['presentacion_label'] ) . '</small>'; ?></td>
							<td style="font-family:monospace;font-size:12px;color:#666"><?php echo esc_html( $it['sku'] ?? '—' ); ?></td>
							<td style="text-align:center"><strong><?php echo (int) ( $it['quantity'] ?? 0 ); ?></strong></td>
							<td style="text-align:right">
								<?php if ( $source === 'pendiente' ) : ?>
									<span style="background:#f7b500;color:#fff;padding:3px 10px;border-radius:11px;font-size:11px;font-weight:700">FALTA PRECIO</span>
								<?php else : ?>
									<?php echo esc_html( glotracol_quote_format_price( $price ) ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin:24px 0 0;text-align:center">
					<a href="<?php echo esc_url( $edit_url ); ?>" style="display:inline-block;background:#f7b500;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold;font-size:14px">Completar precios y responder</a>
				</p>
			</td></tr>

			<tr><td style="background:#fffbf2;padding:14px 28px;font-size:11px;color:#888;text-align:center">
				Enviado desde <?php echo esc_html( get_bloginfo( 'name' ) ); ?> · <?php echo esc_html( current_time( 'd/m/Y H:i' ) ); ?>
			</td></tr>
		</table>
	</td></tr>
</table>
</body>
</html>
