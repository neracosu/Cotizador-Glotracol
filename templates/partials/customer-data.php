<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$accent          = $accent ?? '#0a4d3a';
$customer        = (array) ( $customer ?? [] );
$client_id       = (int) ( $client_id ?? 0 );
$show_crm_badge  = $show_crm_badge ?? false;
$phone           = $customer['phone'] ?? '';
$wa              = $phone ? preg_replace( '/[^0-9]/', '', $phone ) : '';
$rows = [
	'Nombre'   => $customer['name'] ?? '',
	'Empresa'  => $customer['company'] ?? '',
	'NIT'      => $customer['nit'] ?? '',
	'Ciudad'   => $customer['city'] ?? '',
];
?>
<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px">
	<?php foreach ( $rows as $label => $value ) : if ( $value === '' ) continue; ?>
	<tr>
		<td style="width:140px;color:#666"><strong><?php echo esc_html( $label ); ?></strong></td>
		<td><?php echo esc_html( $value ); ?><?php
			if ( $label === 'NIT' && $show_crm_badge ) {
				echo $client_id
					? ' <span style="font-size:11px;background:#cfe2ff;color:#0a3a6e;padding:1px 6px;border-radius:6px;font-weight:700;margin-left:4px">EN CRM</span>'
					: ' <span style="font-size:11px;background:#fff8e1;color:#665100;padding:1px 6px;border-radius:6px;font-weight:700;margin-left:4px">NO EN CRM</span>';
			}
		?></td>
	</tr>
	<?php endforeach; ?>
	<?php if ( ! empty( $customer['email'] ) ) : ?>
	<tr><td style="color:#666"><strong>Email</strong></td>
		<td><a href="mailto:<?php echo esc_attr( $customer['email'] ); ?>" style="color:<?php echo esc_attr( $accent ); ?>"><?php echo esc_html( $customer['email'] ); ?></a></td></tr>
	<?php endif; ?>
	<?php if ( $phone !== '' ) : ?>
	<tr><td style="color:#666"><strong>Teléfono</strong></td>
		<td><?php if ( $wa ) : ?><a href="https://wa.me/<?php echo esc_attr( $wa ); ?>" style="color:#25D366;font-weight:600"><?php echo esc_html( $phone ); ?> (WhatsApp)</a><?php else : echo esc_html( $phone ); endif; ?></td></tr>
	<?php endif; ?>
</table>
<?php if ( ! empty( $customer['message'] ) ) : ?>
<h3 style="font-size:14px;margin:18px 0 6px;color:<?php echo esc_attr( $accent ); ?>">Mensaje del cliente</h3>
<div style="background:#f4f6f8;border-left:3px solid <?php echo esc_attr( $accent ); ?>;padding:10px 14px;font-size:14px;white-space:pre-wrap"><?php echo esc_html( $customer['message'] ); ?></div>
<?php endif; ?>
