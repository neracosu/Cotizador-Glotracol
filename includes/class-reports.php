<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Pantalla de reportes con filtros, stats y export CSV.
 *
 * Submenu "Reportes" bajo Cotizaciones.
 *
 * Cobertura:
 *  - Filtros: rango de fechas, tipo (quote/order), estado, pricing_status, cliente B2B, size_tag.
 *  - Tabla paginada con resumen de cada cotización.
 *  - Stats panel: total cotizaciones, monto total cotizado, conversion rate, top clientes, top SKUs.
 *  - Export CSV con todas las cotizaciones del filtro actual (incluyendo items expandidos).
 */
class Glotracol_Quote_Reports {

	const PAGE_SLUG = 'glotracol-quote-reports';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_post_gloq_export_csv', [ $this, 'export_csv' ] );
	}

	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=glo_quote',
			'Reportes',
			'Reportes',
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	/* -------------------------------------------------------------------------
	 * QUERY
	 * ---------------------------------------------------------------------- */

	private function parse_filters() {
		// Defaults: últimos 30 días
		$today = current_time( 'Y-m-d' );
		$thirty_ago = date( 'Y-m-d', strtotime( $today . ' -30 days' ) );

		return [
			'date_from'      => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : $thirty_ago,
			'date_to'        => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : $today,
			'type'           => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
			'status'         => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'pricing_status' => isset( $_GET['pricing_status'] ) ? sanitize_key( wp_unslash( $_GET['pricing_status'] ) ) : '',
			'size_tag'       => isset( $_GET['size_tag'] ) ? sanitize_key( wp_unslash( $_GET['size_tag'] ) ) : '',
			'client_id'      => isset( $_GET['client_id'] ) ? (int) $_GET['client_id'] : 0,
			'paged'          => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
		];
	}

	private function build_query_args( $filters, $extra = [] ) {
		$meta_query = [ 'relation' => 'AND' ];
		if ( $filters['type'] ) {
			$meta_query[] = [ 'key' => '_glo_type', 'value' => $filters['type'], 'compare' => '=' ];
		}
		if ( $filters['pricing_status'] ) {
			$meta_query[] = [ 'key' => '_glo_pricing_status', 'value' => $filters['pricing_status'], 'compare' => '=' ];
		}
		if ( $filters['size_tag'] ) {
			$meta_query[] = [ 'key' => '_glo_size_tag', 'value' => $filters['size_tag'], 'compare' => '=' ];
		}
		if ( $filters['client_id'] ) {
			$meta_query[] = [ 'key' => '_glo_client_id', 'value' => $filters['client_id'], 'compare' => '=' ];
		}

		$status = $filters['status'] ?: [ 'glo-new', 'glo-pending-prices', 'glo-auto-priced', 'glo-processing', 'glo-responded', 'glo-closed' ];

		$args = [
			'post_type'      => 'glo_quote',
			'post_status'    => $status,
			'posts_per_page' => 25,
			'paged'          => $filters['paged'],
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => count( $meta_query ) > 1 ? $meta_query : [],
			'date_query'     => [
				[
					'after'     => $filters['date_from'] . ' 00:00:00',
					'before'    => $filters['date_to'] . ' 23:59:59',
					'inclusive' => true,
				],
			],
		];
		return array_merge( $args, $extra );
	}

	/* -------------------------------------------------------------------------
	 * STATS
	 * ---------------------------------------------------------------------- */

	private function compute_stats( $filters ) {
		// Traer TODOS los IDs (sin paginar) para los stats
		$args = $this->build_query_args( $filters, [ 'posts_per_page' => -1, 'fields' => 'ids', 'paged' => 1 ] );
		$ids = get_posts( $args );
		// Primear post + meta cache en una sola consulta para evitar N+1 en el bucle.
		if ( ! empty( $ids ) ) _prime_post_caches( $ids, false, true );

		$stats = [
			'total_count'    => 0,
			'total_amount'   => 0,
			'count_quote'    => 0,
			'count_order'    => 0,
			'count_priced'   => 0,
			'count_partial'  => 0,
			'count_large'    => 0,
			'top_clients'    => [],
			'top_skus'       => [],
			'by_status'      => [],
		];

		$client_amounts = []; // client_id => total
		$client_counts  = []; // client_id => count
		$sku_counts     = [];

		foreach ( (array) $ids as $id ) {
			$stats['total_count']++;
			$total = (int) get_post_meta( $id, '_glo_total', true );
			$stats['total_amount'] += $total;
			$type = get_post_meta( $id, '_glo_type', true ) ?: 'quote';
			if ( $type === 'order' ) $stats['count_order']++;
			else $stats['count_quote']++;
			$pricing = get_post_meta( $id, '_glo_pricing_status', true );
			if ( $pricing === 'priced' ) $stats['count_priced']++;
			elseif ( $pricing === 'partial' ) $stats['count_partial']++;
			$is_large = (int) get_post_meta( $id, '_glo_is_large_alert', true ) === 1;
			if ( $is_large ) $stats['count_large']++;

			$client_id = (int) get_post_meta( $id, '_glo_client_id', true );
			if ( $client_id ) {
				$client_amounts[ $client_id ] = ( $client_amounts[ $client_id ] ?? 0 ) + $total;
				$client_counts[ $client_id ]  = ( $client_counts[ $client_id ] ?? 0 ) + 1;
			}

			$items = get_post_meta( $id, '_glo_items', true );
			if ( is_array( $items ) ) {
				foreach ( $items as $it ) {
					$sku = $it['sku'] ?? '';
					if ( $sku === '' ) continue;
					$qty = (int) ( $it['quantity'] ?? 0 );
					if ( ! isset( $sku_counts[ $sku ] ) ) $sku_counts[ $sku ] = [ 'name' => $it['name'] ?? '', 'qty' => 0, 'requests' => 0 ];
					$sku_counts[ $sku ]['qty'] += $qty;
					$sku_counts[ $sku ]['requests']++;
				}
			}

			$post_status = get_post_status( $id );
			$stats['by_status'][ $post_status ] = ( $stats['by_status'][ $post_status ] ?? 0 ) + 1;
		}

		// Top clientes por monto total
		arsort( $client_amounts );
		$top = array_slice( $client_amounts, 0, 5, true );
		foreach ( $top as $cid => $amount ) {
			$stats['top_clients'][] = [
				'id'     => $cid,
				'name'   => get_post_meta( $cid, '_glo_client_name', true ) ?: '#' . $cid,
				'amount' => $amount,
				'count'  => $client_counts[ $cid ] ?? 0,
			];
		}

		// Top SKUs por unidades pedidas
		uasort( $sku_counts, function ( $a, $b ) { return $b['qty'] <=> $a['qty']; } );
		$top_skus = array_slice( $sku_counts, 0, 5, true );
		foreach ( $top_skus as $sku => $data ) {
			$stats['top_skus'][] = [
				'sku'      => $sku,
				'name'     => $data['name'],
				'qty'      => $data['qty'],
				'requests' => $data['requests'],
			];
		}

		// Conversion rate (pedidos / (cotizaciones + pedidos))
		$denom = $stats['count_quote'] + $stats['count_order'];
		$stats['conversion_rate'] = $denom > 0 ? round( ( $stats['count_order'] / $denom ) * 100, 1 ) : 0;

		return $stats;
	}

	/* -------------------------------------------------------------------------
	 * RENDER
	 * ---------------------------------------------------------------------- */

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$filters = $this->parse_filters();
		$stats = $this->compute_stats( $filters );
		$args = $this->build_query_args( $filters );
		$query = new WP_Query( $args );

		?>
		<div class="wrap gloq-reports">
			<h1>Reportes</h1>
			<p>Análisis y exportación de cotizaciones y pedidos.</p>

			<form method="get" class="gloq-reports-filters">
				<input type="hidden" name="post_type" value="glo_quote">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<div class="gloq-reports-filter-grid">
					<label>Desde
						<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
					</label>
					<label>Hasta
						<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
					</label>
					<label>Tipo
						<select name="type">
							<option value="">Todos</option>
							<option value="quote" <?php selected( $filters['type'], 'quote' ); ?>>Cotizaciones</option>
							<option value="order" <?php selected( $filters['type'], 'order' ); ?>>Pedidos</option>
						</select>
					</label>
					<label>Estado
						<select name="status">
							<option value="">Todos</option>
							<?php foreach ( [ 'glo-new', 'glo-pending-prices', 'glo-auto-priced', 'glo-processing', 'glo-responded', 'glo-closed' ] as $s ) : ?>
								<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $filters['status'], $s ); ?>><?php echo esc_html( glotracol_quote_status_label( $s ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>Pricing
						<select name="pricing_status">
							<option value="">Todos</option>
							<option value="priced" <?php selected( $filters['pricing_status'], 'priced' ); ?>>Con precios</option>
							<option value="partial" <?php selected( $filters['pricing_status'], 'partial' ); ?>>Pendiente</option>
							<option value="none" <?php selected( $filters['pricing_status'], 'none' ); ?>>Sin precios</option>
						</select>
					</label>
					<label>Tamaño
						<select name="size_tag">
							<option value="">Todos</option>
							<option value="small" <?php selected( $filters['size_tag'], 'small' ); ?>>Pequeña</option>
							<option value="medium" <?php selected( $filters['size_tag'], 'medium' ); ?>>Mediana</option>
							<option value="large" <?php selected( $filters['size_tag'], 'large' ); ?>>Grande</option>
						</select>
					</label>
				</div>
				<div class="gloq-reports-filter-actions">
					<input type="submit" class="button button-primary" value="Aplicar filtros">
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG ) ); ?>" class="button">Limpiar</a>
					<a href="<?php echo esc_url( $this->build_export_url( $filters ) ); ?>" class="button button-secondary">Exportar CSV</a>
				</div>
			</form>

			<!-- Stats panel -->
			<div class="gloq-reports-stats">
				<div class="gloq-rstat">
					<div class="gloq-rstat-label">Total cotizaciones + pedidos</div>
					<div class="gloq-rstat-value"><?php echo (int) $stats['total_count']; ?></div>
					<div class="gloq-rstat-detail"><?php echo (int) $stats['count_quote']; ?> cotizaciones · <?php echo (int) $stats['count_order']; ?> pedidos</div>
				</div>
				<div class="gloq-rstat gloq-rstat-money">
					<div class="gloq-rstat-label">Monto total cotizado</div>
					<div class="gloq-rstat-value"><?php echo esc_html( glotracol_quote_format_price( $stats['total_amount'] ) ); ?></div>
					<div class="gloq-rstat-detail"><?php echo (int) $stats['count_priced']; ?> con precios · <?php echo (int) $stats['count_partial']; ?> pendientes</div>
				</div>
				<div class="gloq-rstat gloq-rstat-conversion">
					<div class="gloq-rstat-label">Tasa de conversión</div>
					<div class="gloq-rstat-value"><?php echo (float) $stats['conversion_rate']; ?>%</div>
					<div class="gloq-rstat-detail">Cotizaciones que se marcaron como pedido</div>
				</div>
				<div class="gloq-rstat gloq-rstat-large">
					<div class="gloq-rstat-label">Pedidos grandes</div>
					<div class="gloq-rstat-value"><?php echo (int) $stats['count_large']; ?></div>
					<div class="gloq-rstat-detail">Disparon alerta destacada al equipo</div>
				</div>
			</div>

			<div class="gloq-reports-grid">
				<section class="gloq-rcard">
					<h2>Top 5 clientes</h2>
					<?php if ( empty( $stats['top_clients'] ) ) : ?>
						<p style="color:#999">Sin clientes B2B identificados en este rango.</p>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr><th>Cliente</th><th style="width:80px;text-align:center"># Cotiz.</th><th style="text-align:right">Monto</th></tr></thead>
							<tbody>
							<?php foreach ( $stats['top_clients'] as $tc ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( get_edit_post_link( $tc['id'] ) ); ?>"><?php echo esc_html( $tc['name'] ); ?></a></td>
									<td style="text-align:center"><strong><?php echo (int) $tc['count']; ?></strong></td>
									<td style="text-align:right"><strong><?php echo esc_html( glotracol_quote_format_price( $tc['amount'] ) ); ?></strong></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</section>

				<section class="gloq-rcard">
					<h2>Top 5 SKUs solicitados</h2>
					<?php if ( empty( $stats['top_skus'] ) ) : ?>
						<p style="color:#999">Sin items en este rango.</p>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr><th>SKU</th><th>Producto</th><th style="width:80px;text-align:center">Veces</th><th style="width:90px;text-align:right">Unidades</th></tr></thead>
							<tbody>
							<?php foreach ( $stats['top_skus'] as $ts ) : ?>
								<tr>
									<td><code><?php echo esc_html( $ts['sku'] ); ?></code></td>
									<td><?php echo esc_html( $ts['name'] ); ?></td>
									<td style="text-align:center"><?php echo (int) $ts['requests']; ?></td>
									<td style="text-align:right"><strong><?php echo (int) $ts['qty']; ?></strong></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</section>
			</div>

			<h2 style="margin-top:24px">Detalle</h2>
			<?php if ( ! $query->have_posts() ) : ?>
				<div class="gloq-empty">
					<span class="dashicons dashicons-chart-bar"></span>
					<h3>Sin cotizaciones en este rango</h3>
					<p>Ajusta los filtros o espera a que lleguen nuevas solicitudes.</p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat striped">
					<thead>
						<tr>
							<th>ID</th><th>Tipo</th><th>Cliente</th><th>Items</th><th>Tamaño</th><th style="text-align:right">Total</th><th>Estado</th><th>Fecha</th>
						</tr>
					</thead>
					<tbody>
						<?php while ( $query->have_posts() ) : $query->the_post(); $pid = get_the_ID();
							$type = get_post_meta( $pid, '_glo_type', true ) ?: 'quote';
							$total = (int) get_post_meta( $pid, '_glo_total', true );
							$status = get_post_status( $pid );
							$size = get_post_meta( $pid, '_glo_size_tag', true );
							$name = get_post_meta( $pid, '_glo_customer_name', true );
							$items = get_post_meta( $pid, '_glo_items', true );
							$item_count = is_array( $items ) ? count( $items ) : 0;
						?>
							<tr>
								<td><a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><strong>#<?php echo (int) $pid; ?></strong></a></td>
								<td><span class="glo-type glo-type-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( glotracol_quote_type_label( $type ) ); ?></span></td>
								<td><?php echo esc_html( $name ); ?></td>
								<td><?php echo (int) $item_count; ?> productos</td>
								<td><?php if ( $size ) echo '<span class="glo-size glo-size-' . esc_attr( $size ) . '">' . esc_html( glotracol_quote_size_tag_label( $size ) ) . '</span>'; ?></td>
								<td style="text-align:right"><?php echo $total > 0 ? '<strong>' . esc_html( glotracol_quote_format_price( $total ) ) . '</strong>' : '<span style="color:#999">—</span>'; ?></td>
								<td><span class="glo-status glo-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( glotracol_quote_status_label( $status ) ); ?></span></td>
								<td><?php echo esc_html( get_the_date( 'd/m/Y' ) ); ?></td>
							</tr>
						<?php endwhile; wp_reset_postdata(); ?>
					</tbody>
				</table>

				<?php if ( $query->max_num_pages > 1 ) : ?>
					<div class="tablenav"><div class="tablenav-pages">
						<?php
						$base_url = remove_query_arg( 'paged' );
						for ( $p = 1; $p <= $query->max_num_pages; $p++ ) {
							$url = add_query_arg( 'paged', $p, $base_url );
							$cls = $p === $filters['paged'] ? 'button button-primary' : 'button';
							echo '<a class="' . $cls . '" href="' . esc_url( $url ) . '">' . (int) $p . '</a> ';
						}
						?>
					</div></div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function build_export_url( $filters ) {
		$args = [ 'action' => 'gloq_export_csv', '_wpnonce' => wp_create_nonce( 'gloq_export_csv' ) ];
		foreach ( [ 'date_from', 'date_to', 'type', 'status', 'pricing_status', 'size_tag', 'client_id' ] as $k ) {
			if ( ! empty( $filters[ $k ] ) ) $args[ $k ] = $filters[ $k ];
		}
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/* -------------------------------------------------------------------------
	 * EXPORT CSV
	 * ---------------------------------------------------------------------- */

	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos' );
		check_admin_referer( 'gloq_export_csv' );

		$filters = $this->parse_filters();
		$filters['paged'] = 1;
		$args = $this->build_query_args( $filters, [ 'posts_per_page' => -1, 'fields' => 'ids' ] );
		$ids = get_posts( $args );
		// Primear post + meta cache en una sola consulta para evitar N+1 en la exportación.
		if ( ! empty( $ids ) ) _prime_post_caches( $ids, false, true );

		// Configurar headers del archivo
		$filename = 'glotracol-cotizaciones-' . current_time( 'Y-m-d-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );

		$out = fopen( 'php://output', 'w' );
		// BOM UTF-8 para Excel
		fwrite( $out, "\xEF\xBB\xBF" );

		fputcsv( $out, [
			'ID', 'Fecha', 'Tipo', 'Estado', 'Pricing',
			'NIT', 'Cliente', 'Razón social',
			'Email', 'Teléfono', 'Empresa',
			'Tamaño', 'Items', 'Unidades totales', 'Total',
			'SKU', 'Producto', 'Presentación', 'Cantidad', 'Precio unitario', 'Subtotal', 'Origen precio',
		] );

		foreach ( (array) $ids as $id ) {
			$customer_name  = get_post_meta( $id, '_glo_customer_name', true );
			$customer_email = get_post_meta( $id, '_glo_customer_email', true );
			$customer_phone = get_post_meta( $id, '_glo_customer_phone', true );
			$customer_company = get_post_meta( $id, '_glo_customer_company', true );
			$customer_nit   = get_post_meta( $id, '_glo_customer_nit', true );
			$client_id      = (int) get_post_meta( $id, '_glo_client_id', true );
			$client_name    = $client_id ? get_post_meta( $client_id, '_glo_client_name', true ) : '';
			$type           = get_post_meta( $id, '_glo_type', true ) ?: 'quote';
			$pricing_status = get_post_meta( $id, '_glo_pricing_status', true );
			$total          = (int) get_post_meta( $id, '_glo_total', true );
			$size           = get_post_meta( $id, '_glo_size_tag', true );
			$units          = (int) get_post_meta( $id, '_glo_units_total', true );
			$items          = get_post_meta( $id, '_glo_items', true );
			$status         = get_post_status( $id );
			$date           = get_the_date( 'Y-m-d H:i', $id );

			$item_count = is_array( $items ) ? count( $items ) : 0;
			if ( ! is_array( $items ) || empty( $items ) ) {
				// fila base sin items
				self::fputcsv_safe( $out, [
					$id, $date, glotracol_quote_type_label( $type ), glotracol_quote_status_label( $status ), $pricing_status,
					$customer_nit, $customer_name, $client_name,
					$customer_email, $customer_phone, $customer_company,
					glotracol_quote_size_tag_label( $size ), $item_count, $units, $total,
					'', '', '', '', '', '', '',
				] );
				continue;
			}
			// Una fila por item (formato "expandido" útil para análisis)
			foreach ( $items as $it ) {
				self::fputcsv_safe( $out, [
					$id, $date, glotracol_quote_type_label( $type ), glotracol_quote_status_label( $status ), $pricing_status,
					$customer_nit, $customer_name, $client_name,
					$customer_email, $customer_phone, $customer_company,
					glotracol_quote_size_tag_label( $size ), $item_count, $units, $total,
					$it['sku'] ?? '',
					$it['name'] ?? '',
					$it['presentacion_label'] ?? '',
					(int) ( $it['quantity'] ?? 0 ),
					$it['precio_unitario'] ?? '',
					$it['precio_subtotal'] ?? '',
					$it['precio_origen'] ?? '',
				] );
			}
		}

		fclose( $out );
		exit;
	}

	/**
	 * Escribe una fila al CSV neutralizando inyección de fórmulas: si una celda
	 * empieza por = + - @ (o tab/CR), se le antepone un apóstrofo para que Excel
	 * y Google Sheets la traten como texto y no la ejecuten como fórmula.
	 *
	 * @param resource $handle
	 * @param array    $row
	 */
	private static function fputcsv_safe( $handle, $row ) {
		$safe = array_map( function ( $cell ) {
			$cell = (string) $cell;
			if ( $cell !== '' && in_array( $cell[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
				return "'" . $cell;
			}
			return $cell;
		}, $row );
		fputcsv( $handle, $safe );
	}
}
