<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$is_large   = ! empty( $size_tag ) && in_array( $size_tag, [ 'large', 'medium', 'tons' ], true );
$is_tons    = ( $size_tag ?? '' ) === 'tons';
$is_pending = in_array( $pricing_status ?? '', [ 'partial', 'none' ], true );
$is_order   = ( $type ?? 'quote' ) === 'order';
$accent     = $is_large ? '#dc3545' : '#0a4d3a';
$bg         = $is_large ? '#fef2f2' : '#f4f6f8';
$header_bg  = $is_large
	? 'linear-gradient(135deg,#dc3545 0%,#a02029 100%)'
	: 'linear-gradient(135deg,#13855e 0%,#0a4d3a 100%)';
$badge = $is_tons ? 'Atención máxima' : ( $is_large ? 'Atención prioritaria' : ( $is_order ? 'Pedido' : 'Nueva cotización' ) );
$pendientes = 0;
foreach ( (array) $items as $it ) { if ( ! empty( $it['es_pendiente'] ) ) $pendientes++; }
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title><?php echo $is_order ? 'Pedido' : 'Cotización'; ?> #<?php echo (int) $quote_id; ?></title></head>
<body style="margin:0;padding:0;background:<?php echo $bg; ?>;font-family:Arial,Helvetica,sans-serif;color:#222">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?php echo $bg; ?>;padding:24px 0">
	<tr><td align="center">
		<table role="presentation" width="700" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08);<?php echo $is_large ? 'border:2px solid #dc3545' : ''; ?>">

			<tr><td style="background:<?php echo $header_bg; ?>;color:#fff;padding:24px 28px">
				<div style="display:inline-block;background:rgba(255,255,255,0.18);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:0.6px;text-transform:uppercase;margin-bottom:10px"><?php echo esc_html( $badge ); ?></div>
				<h1 style="margin:0;font-size:22px;line-height:1.3"><?php echo $is_order ? 'Pedido' : 'Cotización'; ?> #<?php echo (int) $quote_id; ?><?php echo $is_tons ? ' — TONELADAS' : ''; ?></h1>
				<p style="margin:8px 0 0;font-size:14px;opacity:0.95"><strong><?php echo (int) $units_total; ?> unidades</strong> · <strong><?php echo (int) $skus_count; ?> productos distintos</strong><?php if ( $is_large ) : ?> · clasificado como <strong><?php echo $is_tons ? 'TONELADAS' : 'GRANDE'; ?></strong><?php endif; ?></p>
			</td></tr>

			<tr><td style="padding:24px 28px">
				<?php if ( ! empty( $intro ) ) : ?>
				<p style="margin:0 0 18px;font-size:14px;line-height:1.5;color:#5a5a5a"><?php echo esc_html( $intro ); ?></p>
				<?php endif; ?>

				<?php if ( $is_pending ) : ?>
				<div style="background:#fff8e1;border-left:4px solid #f7b500;padding:14px 18px;border-radius:4px;margin-bottom:20px;font-size:13px;color:#665100">
					<strong>Faltan precios:</strong> <?php echo (int) $pendientes; ?> producto<?php echo $pendientes === 1 ? '' : 's'; ?> sin precio cargado.
					<ul style="margin:6px 0 0;padding-left:20px">
						<li>Completa los precios en la pantalla <em>Precios</em> (Lista A) o en la ficha del cliente B2B.</li>
						<li>Luego abre la cotización y usa <strong>Reenviar con precios</strong> para auto-cotizar.</li>
					</ul>
				</div>
				<?php endif; ?>

				<?php if ( $is_large ) : ?>
				<div style="margin:0 0 20px;padding:14px 18px;background:#fff8e1;border-left:4px solid #f7b500;border-radius:4px">
					<strong style="color:#665100;font-size:13px">Sugerencia:</strong> <span style="font-size:13px;color:#665100">esta cotización supera tu umbral de pedido grande. Considera contactar al cliente por teléfono o WhatsApp para acelerar la conversión.</span>
				</div>
				<?php endif; ?>

				<h2 style="font-size:16px;margin:0 0 12px;color:<?php echo $accent; ?>;border-bottom:2px solid #e6e9ec;padding-bottom:6px">Datos del cliente</h2>
				<?php echo glotracol_quote_load_template( 'partials/customer-data.php', [
					'customer'       => array_merge( (array) $customer, [ 'message' => $message ?? '' ] ),
					'client_id'      => $client_id ?? 0,
					'accent'         => $accent,
					'show_crm_badge' => true,
				] ); ?>

				<?php if ( ! empty( $client_name ) ) : ?>
				<p style="margin:14px 0 0;font-size:13px;color:#0a4d3a;background:#f0fff4;padding:10px 14px;border-left:3px solid #0a4d3a;border-radius:4px"><strong>Cliente B2B:</strong> <?php echo esc_html( $client_name ); ?> · aplicaron precios negociados.</p>
				<?php endif; ?>

				<h2 style="font-size:16px;margin:22px 0 12px;color:<?php echo $accent; ?>;border-bottom:2px solid #e6e9ec;padding-bottom:6px">Productos solicitados (<?php echo (int) $skus_count; ?>)</h2>
				<?php echo glotracol_quote_load_template( 'partials/items-table.php', [
					'items'        => $items,
					'total'        => $total ?? 0,
					'weight_total' => $weight_total ?? 0,
					'accent'       => $accent,
					'show_sku'     => true,
				] ); ?>

				<p style="margin:24px 0 0;text-align:center">
					<a href="<?php echo esc_url( $edit_url ); ?>" style="display:inline-block;background:<?php echo $accent; ?>;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold;font-size:14px">Ver y responder en el panel</a>
				</p>
			</td></tr>

			<tr><td style="background:<?php echo $bg; ?>;padding:14px 28px;font-size:11px;color:#888;text-align:center">
				Enviado desde <?php echo esc_html( get_bloginfo( 'name' ) ); ?> · <?php echo esc_html( current_time( 'd/m/Y H:i' ) ); ?>
				<?php if ( ! empty( $meta['ip'] ) ) : ?> · IP <?php echo esc_html( $meta['ip'] ); ?><?php endif; ?>
			</td></tr>
		</table>
	</td></tr>
</table>
</body>
</html>
