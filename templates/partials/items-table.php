<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$accent       = $accent ?? '#0a4d3a';
$show_sku     = $show_sku ?? true;
$items        = (array) ( $items ?? [] );
$weight_total = (float) ( $weight_total ?? 0 );
$pendientes   = 0;
foreach ( $items as $it ) { if ( ! empty( $it['es_pendiente'] ) ) $pendientes++; }
$cols = $show_sku ? 6 : 5;
?>
<table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:13px;border:1px solid #e6e9ec">
	<thead>
		<tr style="background:#f4f6f8;text-align:left;color:<?php echo esc_attr( $accent ); ?>">
			<th>Producto</th>
			<?php if ( $show_sku ) : ?><th style="width:90px">SKU</th><?php endif; ?>
			<th style="width:90px">Empaque</th>
			<th style="width:100px">Presentación / Peso</th>
			<th style="width:55px;text-align:center">Cant.</th>
			<th style="width:110px;text-align:right">Precio unit.</th>
			<th style="width:120px;text-align:right">Subtotal</th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $items as $it ) :
		$pend = ! empty( $it['es_pendiente'] );
		$bg   = $pend ? '#fff8e1' : '#ffffff';
	?>
		<tr style="border-top:1px solid #e6e9ec;background:<?php echo $bg; ?>">
			<td><?php echo esc_html( $it['name'] ?? '' ); ?></td>
			<?php if ( $show_sku ) : ?>
			<td style="font-family:monospace;font-size:12px;color:#666"><?php echo esc_html( $it['sku'] ?? '—' ); ?></td>
			<?php endif; ?>
			<td style="color:#555"><?php echo esc_html( $it['empaque'] ?? '—' ); ?></td>
			<td style="color:#555"><?php echo esc_html( $it['presentacion'] ?? '—' ); ?></td>
			<td style="text-align:center"><strong><?php echo (int) ( $it['quantity'] ?? 0 ); ?></strong></td>
			<td style="text-align:right;<?php echo $pend ? 'color:#8a6d00;font-style:italic' : ''; ?>"><?php echo esc_html( $it['precio_unit_fmt'] ?? '—' ); ?></td>
			<td style="text-align:right"><strong><?php echo esc_html( $it['precio_sub_fmt'] ?? '—' ); ?></strong></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
	<tfoot>
		<?php if ( $weight_total > 0 ) : ?>
		<tr style="background:#f4f6f8;font-size:12px;color:#555">
			<td colspan="<?php echo (int) $cols; ?>" style="text-align:right;padding:8px 12px">Peso total</td>
			<td style="text-align:right;padding:8px 12px"><strong><?php echo esc_html( number_format( $weight_total, 2, ',', '.' ) ); ?> kg</strong></td>
		</tr>
		<?php endif; ?>
		<tr style="background:<?php echo esc_attr( $accent ); ?>;color:#fff">
			<td colspan="<?php echo (int) $cols; ?>" style="text-align:right;padding:12px 14px;font-weight:700;font-size:14px"><?php echo $pendientes > 0 ? 'TOTAL PARCIAL' : 'TOTAL'; ?></td>
			<td style="text-align:right;padding:12px 14px;font-weight:700;font-size:17px"><?php echo esc_html( glotracol_quote_format_price( (int) ( $total ?? 0 ) ) ); ?></td>
		</tr>
	</tfoot>
</table>
<?php if ( $pendientes > 0 ) : ?>
<p style="margin:10px 0 0;padding:10px 14px;background:#fff8e1;border-left:3px solid #f7b500;font-size:12px;color:#665100;border-radius:4px">
	<strong><?php echo (int) $pendientes; ?></strong> <?php echo $pendientes === 1 ? 'producto está pendiente' : 'productos están pendientes'; ?> de cotizar por el equipo comercial. El total mostrado es parcial y no incluye <?php echo $pendientes === 1 ? 'ese producto' : 'esos productos'; ?>.
</p>
<?php endif; ?>
