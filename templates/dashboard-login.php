<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="gloq-fd gloq-fd-login">
	<p class="gloq-fd-eyebrow">Cotizador Glotracol</p>
	<h2 class="gloq-fd-title">Panel de cotizaciones</h2>
	<p class="gloq-fd-sub">Entra con tu usuario de WordPress para ver el resumen del cotizador.</p>
	<?php echo $login_form; // wp_login_form() ya escapa sus campos ?>
	<p class="gloq-fd-lost"><a href="<?php echo esc_url( $lost_url ); ?>">¿Olvidaste la contraseña?</a></p>
</div>
