<?php if ( ! defined( 'ABSPATH' ) ) exit;
$is_order = ( $type ?? 'quote' ) === 'order';
$header_bg = $is_order ? 'linear-gradient(135deg,#0a4d3a 0%,#0d6149 100%)' : 'linear-gradient(135deg,#13855e 0%,#0a4d3a 100%)';
$header_label = $is_order ? 'Confirmación de pedido' : 'Cotización formal';
$cta_intro = $is_order
	? 'Hemos recibido tu pedido y aquí están los precios y totales calculados con base en tu lista de precios. Si está todo correcto, nuestro equipo procederá a la facturación.'
	: 'Aquí está la cotización formal con precios y totales según tu lista de precios. Si quieres confirmar el pedido, basta con responder este correo.';
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title><?php echo esc_html( $header_label ); ?> #<?php echo (int) $quote_id; ?></title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#222">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 0">
	<tr><td align="center">
		<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.06)">
			<tr><td style="background:<?php echo $header_bg; ?>;color:#fff;padding:24px 28px;text-align:center">
				<div style="display:inline-block;background:rgba(255,255,255,0.18);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:0.6px;text-transform:uppercase;margin-bottom:10px"><?php echo esc_html( $header_label ); ?></div>
				<h1 style="margin:0;font-size:24px;line-height:1.3"><?php echo $is_order ? '✓ Tu pedido' : '✓ Tu cotización'; ?> #<?php echo (int) $quote_id; ?></h1>
				<p style="margin:8px 0 0;font-size:14px;opacity:0.92">Hola, <?php echo esc_html( $customer['name'] ?? '' ); ?></p>
			</td></tr>

			<tr><td style="padding:28px 28px 8px;font-size:15px;line-height:1.65">
				<p style="margin:0"><?php echo esc_html( $cta_intro ); ?></p>
				<?php if ( $client_name ) : ?>
				<p style="margin:14px 0 0;font-size:13px;color:#0a4d3a;background:#f0fff4;padding:10px 14px;border-left:3px solid #0a4d3a;border-radius:4px">
					<strong>Cliente identificado:</strong> <?php echo esc_html( $client_name ); ?> · Aplicaron tus <strong>precios negociados</strong>.
				</p>
				<?php else : ?>
				<p style="margin:14px 0 0;font-size:13px;color:#856404;background:#fff8e1;padding:10px 14px;border-left:3px solid #f7b500;border-radius:4px">
					<strong>Lista de precios:</strong> aplicaron los <strong>precios públicos vigentes</strong>. Si tienes acuerdo comercial con Glotracol y no aparece reflejado, escríbenos respondiendo este correo y lo verificamos.
				</p>
				<?php endif; ?>
			</td></tr>

			<tr><td style="padding:18px 28px 8px">
				<h2 style="font-size:16px;margin:6px 0 12px;color:#0a4d3a;border-bottom:2px solid #e6e9ec;padding-bottom:6px">Detalle</h2>
				<table cellpadding="10" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px;border:1px solid #e6e9ec">
					<thead><tr style="background:#f4f6f8;text-align:left;color:#0a4d3a"><th>Producto</th><th>SKU</th><th style="text-align:center;width:70px">Cant.</th><th style="text-align:right;width:120px">Precio unit.</th><th style="text-align:right;width:140px">Subtotal</th></tr></thead>
					<tbody>
					<?php foreach ( (array) $items as $it ) :
						$price = (int) ( $it['precio_unitario'] ?? 0 );
						$qty = (int) ( $it['quantity'] ?? 0 );
						$sub = (int) ( $it['precio_subtotal'] ?? ( $price * $qty ) );
						$source = $it['precio_origen'] ?? '';
						$source_badge = $source === 'b2b' ? ' <span style="background:#cfe2ff;color:#0a3a6e;font-size:10px;padding:1px 6px;border-radius:6px;font-weight:700;margin-left:4px">B2B</span>' : '';
					?>
						<tr style="border-top:1px solid #e6e9ec">
							<td><?php echo esc_html( $it['name'] ?? '' ); ?><?php if ( ! empty( $it['presentacion_label'] ) ) echo ' <small style="color:#666">— ' . esc_html( $it['presentacion_label'] ) . '</small>'; ?></td>
							<td style="color:#666;font-family:monospace;font-size:13px"><?php echo esc_html( $it['sku'] ?? '—' ); ?></td>
							<td style="text-align:center"><strong><?php echo (int) $qty; ?></strong></td>
							<td style="text-align:right"><?php echo esc_html( glotracol_quote_format_price( $price ) ); ?><?php echo $source_badge; ?></td>
							<td style="text-align:right"><strong><?php echo esc_html( glotracol_quote_format_price( $sub ) ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr style="background:#0a4d3a;color:#fff">
							<td colspan="4" style="text-align:right;padding:14px 16px;font-weight:700;font-size:15px">TOTAL</td>
							<td style="text-align:right;padding:14px 16px;font-weight:700;font-size:18px"><?php echo esc_html( glotracol_quote_format_price( (int) $total ) ); ?></td>
						</tr>
					</tfoot>
				</table>
				<p style="margin:10px 0 0;font-size:11px;color:#888;font-style:italic">Los precios mostrados son referenciales y están sujetos a confirmación de disponibilidad de inventario por parte de Glotracol.</p>
			</td></tr>

			<tr><td style="padding:18px 28px;font-size:14px;line-height:1.6">
				<?php if ( $is_order ) : ?>
					<p>Para finalizar el pedido, responde este correo confirmando o escríbenos al WhatsApp comercial. Te enviaremos los datos para el pago y la fecha estimada de despacho.</p>
				<?php else : ?>
					<p>Si quieres confirmar el pedido con estos precios, simplemente responde este correo y nuestro equipo te enviará los datos para el pago y la fecha estimada de despacho.</p>
				<?php endif; ?>
				<p style="margin-top:18px">Saludos,<br><strong>Equipo Comercial Glotracol</strong><br><span style="color:#666">Global Trading de Colombia</span></p>
			</td></tr>

			<tr><td style="background:#f4f6f8;padding:14px 28px;font-size:11px;color:#888;text-align:center">
				<?php echo esc_html( get_bloginfo( 'name' ) ); ?> · Este precio es válido por 7 días desde el envío de este correo
			</td></tr>
		</table>
	</td></tr>
</table>
</body>
</html>
