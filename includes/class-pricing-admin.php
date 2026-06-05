<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Pantalla admin para gestionar precios publicos por producto (_glo_price).
 *
 * La fuente de datos es el meta privado _glo_price en cada producto de WooCommerce.
 * Se puede editar manualmente aqui o via importacion masiva CSV (Cotizaciones > Importar).
 */
class Glotracol_Quote_Pricing_Admin {

	const PAGE_SLUG   = 'glotracol-quote-pricing';
	const NONCE_ACTION = 'gloq_pricing_save';

	public function __construct() {
		add_action( 'admin_menu',              [ $this, 'add_menu' ] );
		add_action( 'admin_post_gloq_pricing_save',  [ $this, 'handle_save' ] );
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

	// -------------------------------------------------------------------------
	// Helpers internos
	// -------------------------------------------------------------------------

	/**
	 * Devuelve todos los productos publicados de WooCommerce con sus metadatos de precio.
	 * @return WC_Product[]
	 */
	private function get_all_products() {
		if ( ! function_exists( 'wc_get_products' ) ) return [];
		return wc_get_products( [
			'limit'  => -1,
			'status' => 'publish',
			'orderby' => 'title',
			'order'   => 'ASC',
		] );
	}

	/**
	 * Cuenta cuantos productos tienen _glo_price asignado.
	 */
	private function count_products_with_price() {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_glo_price' AND meta_value != '' AND meta_value > 0"
		);
	}

	// -------------------------------------------------------------------------
	// Renderizado
	// -------------------------------------------------------------------------

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$all_products  = $this->get_all_products();
		$total_products = count( $all_products );
		$with_price     = $this->count_products_with_price();

		// Filtro de busqueda por nombre o ID
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		if ( $search !== '' ) {
			$all_products = array_values( array_filter( $all_products, function ( $product ) use ( $search ) {
				return stripos( $product->get_name(), $search ) !== false
					|| (string) $product->get_id() === $search;
			} ) );
		}

		// Paginacion
		$per_page       = 50;
		$page           = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$total_filtered = count( $all_products );
		$pages          = max( 1, (int) ceil( $total_filtered / $per_page ) );
		$page           = min( $page, $pages );
		$page_products  = array_slice( $all_products, ( $page - 1 ) * $per_page, $per_page );

		$flash = isset( $_GET['gloq_pricing_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['gloq_pricing_msg'] ) ) : '';
		?>
		<div class="wrap">
			<h1>Lista publica de precios</h1>
			<p>Precios publicos (COP) asignados por producto. Se aplican cuando el cliente no tiene tarifa negociada para ese producto. Puedes editar manualmente o usar el <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=glo_quote&page=glotracol-quote-import' ) ); ?>">importador CSV</a>.</p>

			<?php if ( $flash === 'saved' ) : ?>
				<div class="notice notice-success is-dismissible"><p>Precios actualizados correctamente.</p></div>
			<?php elseif ( $flash === 'cleared' ) : ?>
				<div class="notice notice-warning is-dismissible"><p>Todos los precios publicos fueron eliminados.</p></div>
			<?php endif; ?>

			<div class="gloq-pricing-stats">
				<div class="gloq-pricing-stat"><strong><?php echo (int) $with_price; ?></strong> productos con precio publico de <strong><?php echo (int) $total_products; ?></strong></div>
				<?php if ( $search !== '' ) : ?>
					<div class="gloq-pricing-stat gloq-pricing-stat-filtered">
						Mostrando <strong><?php echo (int) $total_filtered; ?></strong> coincidencias para
						"<em><?php echo esc_html( $search ); ?></em>"
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG ) ); ?>">[limpiar]</a>
					</div>
				<?php endif; ?>
			</div>

			<form method="get" style="margin:14px 0">
				<input type="hidden" name="post_type" value="glo_quote">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Buscar por nombre o ID..." class="regular-text">
				<input type="submit" class="button" value="Buscar">
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gloq_pricing_save">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>

				<table class="wp-list-table widefat striped" id="gloq-pricing-table">
					<thead>
						<tr>
							<th style="width:8%">ID</th>
							<th style="width:52%">Nombre del producto</th>
							<th style="width:25%">Precio publico (COP)</th>
							<th style="width:15%">Borrar</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $page_products ) ) : ?>
						<tr>
							<td colspan="4" style="text-align:center;padding:40px">
								<?php echo $search !== '' ? 'No hay productos que coincidan con la busqueda.' : 'No se encontraron productos publicados.'; ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $page_products as $product ) :
							$pid   = $product->get_id();
							$name  = $product->get_name();
							$price = glotracol_quote_get_product_price( $pid );
						?>
							<tr>
								<td><code><?php echo (int) $pid; ?></code>
									<input type="hidden" name="rows[<?php echo (int) $pid; ?>][id]" value="<?php echo (int) $pid; ?>">
								</td>
								<td><?php echo esc_html( $name ); ?></td>
								<td>
									<input type="number"
										name="rows[<?php echo (int) $pid; ?>][price]"
										value="<?php echo $price !== null ? (int) $price : ''; ?>"
										min="0" step="1" style="width:160px"
										placeholder="Sin precio">
								</td>
								<td>
									<label>
										<input type="checkbox" name="rows[<?php echo (int) $pid; ?>][delete]" value="1">
										Borrar
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
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
				if ( $search !== '' ) {
					$base_url = add_query_arg( 's', urlencode( $search ), $base_url );
				}
				for ( $p = 1; $p <= $pages; $p++ ) {
					$url = add_query_arg( 'paged', $p, $base_url );
					$cls = $p === $page ? 'button button-primary' : 'button';
					echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '">' . (int) $p . '</a> ';
				}
				?>
				</div></div>
			<?php endif; ?>

			<hr style="margin:30px 0">

			<form method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				onsubmit="return confirm('Vas a borrar el precio interno de <?php echo (int) $with_price; ?> productos. Sus cotizaciones futuras quedaran en \'pendiente de precios\'. Es irreversible. Continuar?');">
				<input type="hidden" name="action" value="gloq_pricing_clear">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<details>
					<summary style="cursor:pointer;font-weight:600;color:#c0392b">Zona peligrosa</summary>
					<p style="margin:12px 0 8px;color:#5a5a5a">Elimina el precio publico (_glo_price) de todos los productos. Las cotizaciones sin tarifa negociada quedaran en estado "pendiente de precios" hasta que cargues nuevos precios.</p>
					<input type="submit" class="button button-link-delete" value="Borrar todos los precios publicos">
				</details>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Handlers POST
	// -------------------------------------------------------------------------

	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos' );
		check_admin_referer( self::NONCE_ACTION );

		$rows = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? $_POST['rows'] : [];

		foreach ( $rows as $pid_key => $row ) {
			if ( ! is_array( $row ) ) continue;
			$pid    = isset( $row['id'] ) ? (int) $row['id'] : (int) $pid_key;
			$price  = isset( $row['price'] ) && $row['price'] !== '' ? (int) $row['price'] : 0;
			$delete = ! empty( $row['delete'] );

			if ( $pid <= 0 || get_post_type( $pid ) !== 'product' ) continue;

			if ( $delete || $price <= 0 ) {
				glotracol_quote_set_product_price( $pid, 0 ); // 0 borra el meta
			} else {
				glotracol_quote_set_product_price( $pid, $price );
			}
		}

		wp_safe_redirect( add_query_arg( 'gloq_pricing_msg', 'saved', admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	public function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos' );
		check_admin_referer( self::NONCE_ACTION );

		// Eliminar _glo_price de todos los productos
		global $wpdb;
		$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_glo_price' ], [ '%s' ] );

		wp_safe_redirect( add_query_arg( 'gloq_pricing_msg', 'cleared', admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG ) ) );
		exit;
	}
}
