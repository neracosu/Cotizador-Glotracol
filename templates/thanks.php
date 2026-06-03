<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="glotracol-quote-thanks">
	<div class="glotracol-quote-thanks-icon">✓</div>
	<h2>¡Cotización enviada!</h2>
	<?php if ( $quote_id ) : ?>
		<p class="glotracol-quote-thanks-id">Referencia: <strong>#<?php echo esc_html( $quote_id ); ?></strong></p>
	<?php endif; ?>
	<p><?php echo esc_html( $message ); ?></p>
	<p class="glotracol-quote-thanks-cta">
		<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>">Volver al inicio</a>
		<?php if ( function_exists( 'wc_get_page_id' ) ) :
			$shop_id = wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) : ?>
				<a class="button" href="<?php echo esc_url( get_permalink( $shop_id ) ); ?>" style="margin-left:6px">Ver catálogo</a>
			<?php endif;
		endif; ?>
	</p>
</div>
