<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="gloq-fd">
	<header class="gloq-fd-head">
		<div>
			<p class="gloq-fd-eyebrow">Cotizador Glotracol</p>
			<h2 class="gloq-fd-title">Panel de cotizaciones</h2>
			<p class="gloq-fd-sub">Hola, <?php echo esc_html( $user->display_name ); ?>. Datos en vivo al <?php echo esc_html( date_i18n( 'd/m/Y H:i' ) ); ?>.</p>
		</div>
		<nav class="gloq-fd-actions">
			<a class="gloq-fd-btn gloq-fd-btn-primary" href="<?php echo esc_url( $list_url ); ?>">Ver todas en el panel</a>
			<a class="gloq-fd-btn" href="<?php echo esc_url( $reports_url ); ?>">Reportes</a>
			<a class="gloq-fd-btn gloq-fd-btn-quiet" href="<?php echo esc_url( $logout_url ); ?>">Salir</a>
		</nav>
	</header>

	<h3 class="gloq-fd-section">Este mes</h3>
	<div class="gloq-fd-stats gloq-fd-stats-month">
		<div class="gloq-fd-stat gloq-fd-stat-brand">
			<span class="gloq-fd-stat-value"><?php echo (int) $stats['month_count']; ?></span>
			<span class="gloq-fd-stat-label">Cotizaciones y pedidos</span>
		</div>
		<div class="gloq-fd-stat gloq-fd-stat-brand">
			<span class="gloq-fd-stat-value gloq-fd-stat-money"><?php echo esc_html( glotracol_quote_format_price( $stats['month_total'] ) ); ?></span>
			<span class="gloq-fd-stat-label">Monto cotizado</span>
		</div>
		<div class="gloq-fd-stat gloq-fd-stat-brand">
			<span class="gloq-fd-stat-value"><?php echo (int) $stats['month_orders']; ?></span>
			<span class="gloq-fd-stat-label">Pedidos en firme</span>
		</div>
	</div>

	<h3 class="gloq-fd-section">Por estado</h3>
	<div class="gloq-fd-stats gloq-fd-stats-status">
		<?php foreach ( $status_tiles as $key => $tile ) : ?>
		<a class="gloq-fd-stat gloq-fd-stat-<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( add_query_arg( 'post_status', $tile['status'], $list_url ) ); ?>">
			<span class="gloq-fd-stat-value"><?php echo (int) $stats[ $key ]; ?></span>
			<span class="gloq-fd-stat-label"><?php echo esc_html( $tile['label'] ); ?></span>
		</a>
		<?php endforeach; ?>
	</div>

	<h3 class="gloq-fd-section">Últimas <?php echo (int) $recent_limit; ?> cotizaciones</h3>
	<?php if ( empty( $stats['recent'] ) ) : ?>
		<p class="gloq-fd-empty">Todavía no hay cotizaciones. Cuando un cliente envíe el formulario aparecerá aquí.</p>
	<?php else : ?>
	<div class="gloq-fd-tablewrap">
		<table class="gloq-fd-table">
			<thead><tr><th>N.º</th><th>Tipo</th><th>Cliente</th><th>Productos</th><th class="gloq-fd-num">Total</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $stats['recent'] as $q ) :
				$status = get_post_status( $q->ID );
				$items  = get_post_meta( $q->ID, '_glo_items', true );
				$type   = get_post_meta( $q->ID, '_glo_type', true ) ?: 'quote';
				$total  = (int) get_post_meta( $q->ID, '_glo_total', true );
				$pdf    = wp_nonce_url( admin_url( 'admin-post.php?action=gloq_download_pdf&quote_id=' . (int) $q->ID ), 'gloq_pdf_' . (int) $q->ID );
			?>
				<tr class="gloq-fd-row">
					<td class="gloq-fd-id">#<?php echo (int) $q->ID; ?></td>
					<td><span class="gloq-fd-type gloq-fd-type-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( glotracol_quote_type_label( $type ) ); ?></span></td>
					<td><?php echo esc_html( get_post_meta( $q->ID, '_glo_customer_name', true ) ); ?><small><?php echo esc_html( get_post_meta( $q->ID, '_glo_customer_company', true ) ?: '—' ); ?></small></td>
					<td><?php echo is_array( $items ) ? count( $items ) : 0; ?></td>
					<td class="gloq-fd-num"><?php echo $total > 0 ? esc_html( glotracol_quote_format_price( $total ) ) : '<span class="gloq-fd-muted">—</span>'; ?></td>
					<td><span class="gloq-fd-status gloq-fd-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( glotracol_quote_status_label( $status ) ); ?></span></td>
					<td><?php echo esc_html( get_the_date( 'd/m/Y H:i', $q ) ); ?></td>
					<td class="gloq-fd-rowactions"><a href="<?php echo esc_url( get_edit_post_link( $q->ID ) ); ?>">Ver</a> <a href="<?php echo esc_url( $pdf ); ?>">PDF</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>
</div>
