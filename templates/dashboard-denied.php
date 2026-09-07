<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="gloq-fd gloq-fd-login">
	<p class="gloq-fd-eyebrow">Cotizador Glotracol</p>
	<h2 class="gloq-fd-title">Panel de cotizaciones</h2>
	<p class="gloq-fd-sub">Hola, <?php echo esc_html( $user->display_name ); ?>. Tu usuario no tiene permiso para ver este panel: hace falta ser editor o administrador del sitio.</p>
	<p><a class="gloq-fd-btn" href="<?php echo esc_url( $logout_url ); ?>">Salir y entrar con otro usuario</a></p>
</div>
