<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$is_large   = ! empty( $size_tag ) && in_array( $size_tag, [ 'large', 'medium', 'tons' ], true );
$is_tons    = ( $size_tag ?? '' ) === 'tons';
$is_pending = in_array( $pricing_status ?? '', [ 'partial', 'none' ], true );
$is_order   = ( $type ?? 'quote' ) === 'order';
$brand      = glotracol_quote_brand();
$accent     = $is_large ? '#dc3545' : $brand['color'];
$pal        = glotracol_quote_brand_palette( $accent );
$bg         = $is_large ? '#fef2f2' : '#f4f6f8';
$h2         = 'font-size:16px;margin:0 0 12px;color:#1a1a1a;border-bottom:2px solid ' . esc_attr( $accent ) . ';padding-bottom:6px';
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

			<?php echo glotracol_quote_load_template( 'partials/brand-header.php', [
				'brand'    => $brand,
				'accent'   => $accent,
				'label'    => $badge,
				'title'    => ( $is_order ? 'Pedido' : 'Cotización' ) . ' #' . (int) $quote_id . ( $is_tons ? ' — TONELADAS' : '' ),
				'subtitle' => (int) $units_total . ' unidades · ' . (int) $skus_count . ' productos distintos' . ( $is_large ? ' · ' . ( $is_tons ? 'TONELADAS' : 'GRANDE' ) : '' ),
			] ); ?>

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

				<h2 style="<?php echo $h2; ?>">Datos del cliente</h2>
				<?php echo glotracol_quote_load_template( 'partials/customer-data.php', [
					'customer'       => array_merge( (array) $customer, [ 'message' => $message ?? '' ] ),
					'client_id'      => $client_id ?? 0,
					'accent'         => $accent,
					'show_crm_badge' => true,
				] ); ?>

				<?php if ( ! empty( $client_name ) ) : ?>
				<p style="margin:14px 0 0;font-size:13px;color:<?php echo esc_attr( $brand['dark'] ); ?>;background:<?php echo esc_attr( $brand['tint'] ); ?>;padding:10px 14px;border-left:3px solid <?php echo esc_attr( $brand['color'] ); ?>;border-radius:4px"><strong>Cliente B2B:</strong> <?php echo esc_html( $client_name ); ?> · aplicaron precios negociados.</p>
				<?php endif; ?>

				<h2 style="<?php echo $h2; ?>;margin-top:22px">Productos solicitados (<?php echo (int) $skus_count; ?>)</h2>
				<?php echo glotracol_quote_load_template( 'partials/items-table.php', [
					'items'        => $items,
					'total'        => $total ?? 0,
					'weight_total' => $weight_total ?? 0,
					'accent'       => $accent,
					'show_sku'     => true,
				] ); ?>

				<p style="margin:24px 0 0;text-align:center">
					<a href="<?php echo esc_url( $edit_url ); ?>" style="display:inline-block;background:<?php echo esc_attr( $accent ); ?>;color:<?php echo esc_attr( $pal['text'] ); ?>;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold;font-size:14px">Ver y responder en el panel</a>
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
