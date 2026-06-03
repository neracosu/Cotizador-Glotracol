<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Pantalla admin para gestionar la lista pública de precios.
 *
 * Carga semanal vía importador CSV (F10) o edición manual aquí.
 */
class Glotracol_Quote_Pricing_Admin {

	const PAGE_SLUG = 'glotracol-quote-pricing';
	const NONCE_ACTION = 'gloq_pricing_save';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_post_gloq_pricing_save', [ $this, 'handle_save' ] );
		add_action( 'admin_post_gloq_pricing_clear', [ $this, 'handle_clear' ] );
	}

	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=glo_quote',
			'Lista de precios',
			'Precios',
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$pricing = Glotracol_Quote_Pricing::get_public_pricing();
		$count = count( $pricing );

		// Filtro por búsqueda
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		if ( $search !== '' ) {
			$pricing = array_filter( $pricing, function ( $price, $sku ) use ( $search ) {
				return stripos( $sku, $search ) !== false;
			}, ARRAY_FILTER_USE_BOTH );
		}

		// Paginación
		$per_page = 50;
		$page = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$total_filtered = count( $pricing );
		$pages = max( 1, ceil( $total_filtered / $per_page ) );
		$page = min( $page, $pages );
		ksort( $pricing );
		$pricing_page = array_slice( $pricing, ( $page - 1 ) * $per_page, $per_page, true );

		$flash = isset( $_GET['gloq_pricing_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['gloq_pricing_msg'] ) ) : '';
		?>
		<div class="wrap">
			<h1>Lista pública de precios</h1>
			<p>Estos precios se aplican cuando un cliente envía una cotización <strong>sin NIT identificado</strong>, o cuando su NIT está identificado pero no tiene precio negociado para ese SKU específico. <strong>Recomendado actualizar semanalmente</strong> vía el <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=glo_quote&page=glotracol-quote-import' ) ); ?>">importador CSV</a>.</p>

			<?php if ( $flash === 'saved' ) : ?>
				<div class="notice notice-success is-dismissible"><p>Precios actualizados correctamente.</p></div>
			<?php elseif ( $flash === 'cleared' ) : ?>
				<div class="notice notice-warning is-dismissible"><p>Lista pública vaciada.</p></div>
			<?php endif; ?>

			<div class="gloq-pricing-stats">
				<div class="gloq-pricing-stat"><strong><?php echo (int) $count; ?></strong> SKUs con precio público</div>
				<?php if ( $search !== '' ) : ?>
					<div class="gloq-pricing-stat gloq-pricing-stat-filtered">Mostrando <strong><?php echo (int) $total_filtered; ?></strong> coincidencias para "<em><?php echo esc_html( $search ); ?></em>" <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG ) ); ?>">[limpiar]</a></div>
				<?php endif; ?>
			</div>

			<form method="get" style="margin:14px 0">
				<input type="hidden" name="post_type" value="glo_quote">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Buscar SKU..." class="regular-text">
				<input type="submit" class="button" value="Buscar">
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gloq_pricing_save">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<table class="wp-list-table widefat striped" id="gloq-pricing-table">
					<thead>
						<tr>
							<th style="width:55%">SKU</th>
							<th style="width:30%">Precio público (COP)</th>
							<th style="width:15%">Acciones</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $pricing_page ) ) : ?>
						<tr><td colspan="3" style="text-align:center;padding:40px"><strong>No hay precios cargados</strong>. Usa el importador o agrega precios manualmente abajo.</td></tr>
					<?php else : ?>
						<?php $i = 0; foreach ( $pricing_page as $sku => $price ) : $i++; ?>
							<tr>
								<td><code><?php echo esc_html( $sku ); ?></code><input type="hidden" name="rows[<?php echo $i; ?>][sku]" value="<?php echo esc_attr( $sku ); ?>"></td>
								<td><input type="number" name="rows[<?php echo $i; ?>][price]" value="<?php echo esc_attr( $price ); ?>" min="0" step="1" style="width:160px"> <?php echo esc_html( glotracol_quote_format_price( $price ) ); ?></td>
								<td><label><input type="checkbox" name="rows[<?php echo $i; ?>][delete]" value="1"> Borrar</label></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
						<tr style="background:#f0fff4">
							<td><input type="text" name="new[sku]" placeholder="Nuevo SKU" class="regular-text"></td>
							<td><input type="number" name="new[price]" min="0" step="1" placeholder="0" style="width:160px"></td>
							<td><strong style="color:#0a4d3a">+ Añadir</strong></td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<input type="submit" class="button button-primary" value="Guardar cambios">
				</p>
			</form>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
				<?php
				$base_url = admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG );
				if ( $search ) $base_url = add_query_arg( 's', urlencode( $search ), $base_url );
				for ( $p = 1; $p <= $pages; $p++ ) {
					$url = add_query_arg( 'paged', $p, $base_url );
					$cls = $p === $page ? 'button button-primary' : 'button';
					echo '<a class="' . $cls . '" href="' . esc_url( $url ) . '">' . (int) $p . '</a> ';
				}
				?>
				</div></div>
			<?php endif; ?>

			<hr style="margin:30px 0">

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('¿Vaciar TODA la lista pública de precios? Esta acción no se puede deshacer.');">
				<input type="hidden" name="action" value="gloq_pricing_clear">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<details>
					<summary style="cursor:pointer;font-weight:600;color:#c0392b">Zona peligrosa</summary>
					<p style="margin:12px 0 8px;color:#5a5a5a">Borrar la lista pública entera. Las cotizaciones nuevas sin NIT identificado quedarán automáticamente en estado "pendiente de precios" hasta que cargues una lista nueva.</p>
					<input type="submit" class="button button-link-delete" value="Vaciar lista pública">
				</details>
			</form>
		</div>
		<style>
		.gloq-pricing-stats{display:flex;gap:14px;margin:16px 0;font-size:14px}
		.gloq-pricing-stat{padding:8px 16px;background:#f0fff4;border-left:3px solid #0a4d3a;border-radius:4px}
		.gloq-pricing-stat-filtered{background:#fff3cd;border-left-color:#f7b500}
		#gloq-pricing-table input[type=number]{font-family:monospace}
		</style>
		<?php
	}

	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos' );
		check_admin_referer( self::NONCE_ACTION );

		$rows = $_POST['rows'] ?? [];
		$new_row = $_POST['new'] ?? [];

		$current = Glotracol_Quote_Pricing::get_public_pricing();

		// Procesar filas existentes
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) continue;
				$sku = isset( $row['sku'] ) ? sanitize_text_field( wp_unslash( $row['sku'] ) ) : '';
				$price = isset( $row['price'] ) ? (int) $row['price'] : 0;
				$delete = ! empty( $row['delete'] );
				if ( $sku === '' ) continue;
				if ( $delete || $price <= 0 ) {
					unset( $current[ $sku ] );
				} else {
					$current[ $sku ] = $price;
				}
			}
		}

		// Nueva fila
		if ( is_array( $new_row ) && ! empty( $new_row['sku'] ) ) {
			$sku = sanitize_text_field( wp_unslash( $new_row['sku'] ) );
			$price = (int) ( $new_row['price'] ?? 0 );
			if ( $sku !== '' && $price > 0 ) {
				$current[ $sku ] = $price;
			}
		}

		update_option( Glotracol_Quote_Pricing::PUBLIC_OPTION, $current, false );

		wp_safe_redirect( add_query_arg( 'gloq_pricing_msg', 'saved', admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	public function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos' );
		check_admin_referer( self::NONCE_ACTION );
		update_option( Glotracol_Quote_Pricing::PUBLIC_OPTION, [], false );
		wp_safe_redirect( add_query_arg( 'gloq_pricing_msg', 'cleared', admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG ) ) );
		exit;
	}
}
