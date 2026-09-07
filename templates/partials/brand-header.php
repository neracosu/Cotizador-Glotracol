<?php
/**
 * Cabecera de marca para los correos: franja de color, logo a la izquierda y
 * titulo/numero a la derecha. Variables: $brand (glotracol_quote_brand),
 * $accent (color de la franja), $label, $title, $subtitle.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$brand    = $brand ?? glotracol_quote_brand();
$accent   = $accent ?? $brand['color'];
$pal      = glotracol_quote_brand_palette( $accent );
$label    = $label ?? '';
$title    = $title ?? '';
$subtitle = $subtitle ?? '';
?>
<tr><td style="height:6px;background:<?php echo esc_attr( $pal['color'] ); ?>;font-size:0;line-height:0">&nbsp;</td></tr>
<tr><td style="padding:22px 28px 18px;border-bottom:1px solid #e6e9ec">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
		<tr>
			<td valign="middle" style="width:55%">
				<?php if ( ! empty( $brand['logo_url'] ) ) : ?>
				<img src="<?php echo esc_url( $brand['logo_url'] ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" style="display:block;max-height:56px;max-width:260px;height:auto;width:auto;border:0">
				<?php else : ?>
				<span style="font-size:20px;font-weight:700;color:#1a1a1a"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
				<?php endif; ?>
			</td>
			<td valign="middle" align="right" style="text-align:right">
				<?php if ( $label !== '' ) : ?>
				<div style="font-size:11px;font-weight:700;letter-spacing:0.6px;text-transform:uppercase;color:<?php echo esc_attr( $pal['dark'] ); ?>"><?php echo esc_html( $label ); ?></div>
				<?php endif; ?>
				<div style="font-size:24px;font-weight:700;line-height:1.2;color:#1a1a1a"><?php echo esc_html( $title ); ?></div>
				<?php if ( $subtitle !== '' ) : ?>
				<div style="font-size:12px;color:#666;margin-top:4px"><?php echo esc_html( $subtitle ); ?></div>
				<?php endif; ?>
			</td>
		</tr>
	</table>
</td></tr>
