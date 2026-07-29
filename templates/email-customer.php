<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$is_order = ( $type ?? 'quote' ) === 'order';
$pendientes = 0;
foreach ( (array) $items as $it ) { if ( ! empty( $it['es_pendiente'] ) ) $pendientes++; }
$header_label = $is_order ? 'Confirmación de pedido' : 'Tu cotización';
$intro_txt = $is_order
	? 'Hemos recibido tu pedido. Abajo está el detalle con presentaciones y precios.'
	: 'Hemos recibido tu solicitud. Abajo está el detalle de tu cotización con presentaciones y precios.';
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title><?php echo esc_html( $header_label ); ?> #<?php echo (int) $quote_id; ?></title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#222">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 0">
	<tr><td align="center">
		<table role="presentation" width="700" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.06)">

			<tr><td style="background:linear-gradient(135deg,#13855e 0%,#0a4d3a 100%);color:#fff;padding:24px 28px;text-align:center">
				<div style="display:inline-block;background:rgba(255,255,255,0.18);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:0.6px;text-transform:uppercase;margin-bottom:10px"><?php echo esc_html( $header_label ); ?></div>
				<h1 style="margin:0;font-size:24px;line-height:1.3"><?php echo $is_order ? 'Tu pedido' : 'Tu cotización'; ?> #<?php echo (int) $quote_id; ?></h1>
				<p style="margin:8px 0 0;font-size:14px;opacity:0.92">Hola, <?php echo esc_html( $customer['name'] ?? '' ); ?></p>
			</td></tr>

			<tr><td style="padding:28px 28px 8px;font-size:15px;line-height:1.65">
				<p style="margin:0"><?php echo esc_html( ! empty( $intro ) ? $intro : $intro_txt ); ?></p>
				<?php if ( ! empty( $client_name ) ) : ?>
				<p style="margin:14px 0 0;font-size:13px;color:#0a4d3a;background:#f0fff4;padding:10px 14px;border-left:3px solid #0a4d3a;border-radius:4px"><strong>Cliente identificado:</strong> <?php echo esc_html( $client_name ); ?> · aplicaron tus <strong>precios negociados</strong>.</p>
				<?php else : ?>
				<p style="margin:14px 0 0;font-size:13px;color:#856404;background:#fff8e1;padding:10px 14px;border-left:3px solid #f7b500;border-radius:4px"><strong>Lista de precios:</strong> aplicaron los <strong>precios públicos vigentes</strong>. Si tienes acuerdo comercial con Glotracol y no aparece reflejado, escríbenos respondiendo este correo.</p>
				<?php endif; ?>
			</td></tr>

			<tr><td style="padding:18px 28px 8px">
				<h2 style="font-size:16px;margin:6px 0 12px;color:#0a4d3a;border-bottom:2px solid #e6e9ec;padding-bottom:6px">Detalle</h2>
				<?php echo glotracol_quote_load_template( 'partials/items-table.php', [
					'items'        => $items,
					'total'        => $total ?? 0,
					'weight_total' => $weight_total ?? 0,
					'accent'       => '#0a4d3a',
					'show_sku'     => true,
				] ); ?>
				<p style="margin:10px 0 0;font-size:11px;color:#888;font-style:italic">Los precios mostrados son referenciales y están sujetos a confirmación de disponibilidad de inventario por parte de Glotracol.</p>
			</td></tr>

			<tr><td style="padding:18px 28px 8px">
				<h2 style="font-size:16px;margin:6px 0 12px;color:#0a4d3a;border-bottom:2px solid #e6e9ec;padding-bottom:6px">Datos que nos enviaste</h2>
				<?php echo glotracol_quote_load_template( 'partials/customer-data.php', [
					'customer'       => $customer,
					'client_id'      => 0,
					'accent'         => '#0a4d3a',
					'show_crm_badge' => false,
				] ); ?>
			</td></tr>

			<tr><td style="padding:18px 28px;font-size:14px;line-height:1.6">
				<?php if ( $pendientes > 0 ) : ?>
				<p>Algunos productos quedaron marcados como <strong>A cotizar</strong>: nuestro equipo comercial te enviará esos precios a la brevedad.</p>
				<?php endif; ?>
				<p><?php echo $is_order
					? 'Para finalizar el pedido, responde este correo confirmando. Te enviaremos los datos de pago y la fecha estimada de despacho.'
					: 'Si quieres confirmar el pedido con estos precios, responde este correo y nuestro equipo te enviará los datos de pago y la fecha estimada de despacho.'; ?></p>
				<p style="margin-top:18px">Saludos,<br><strong>Equipo Comercial Glotracol</strong><br><span style="color:#666">Global Trading de Colombia</span></p>
			</td></tr>

			<tr><td style="background:#f4f6f8;padding:14px 28px;font-size:11px;color:#888;text-align:center">
				<?php echo esc_html( get_bloginfo( 'name' ) ); ?> · <?php echo esc_html( home_url( '/' ) ); ?> · Este precio es válido por 7 días desde el envío de este correo
			</td></tr>
		</table>
	</td></tr>
</table>
</body>
</html>
