<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_CPT {

	const POST_TYPE = 'glo_quote';

	public function __construct() {
		add_action( 'init', [ __CLASS__, 'register_post_type_static' ] );
		add_action( 'init', [ __CLASS__, 'register_statuses_static' ] );
		add_filter( 'manage_glo_quote_posts_columns', [ $this, 'columns' ] );
		add_action( 'manage_glo_quote_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_filter( 'manage_edit-glo_quote_sortable_columns', [ $this, 'sortable_columns' ] );
		add_action( 'admin_head-edit.php', [ $this, 'inject_status_filter' ] );
		add_action( 'admin_footer-post.php', [ $this, 'append_statuses_to_dropdown' ] );
		add_filter( 'display_post_states', [ $this, 'display_post_states' ], 10, 2 );
	}

	public static function register_post_type_static() {
		register_post_type( self::POST_TYPE, [
			'labels' => [
				'name'                  => 'Cotizaciones',
				'singular_name'         => 'Cotización',
				'menu_name'             => 'Cotizaciones',
				'add_new'               => 'Añadir nueva',
				'add_new_item'          => 'Nueva cotización',
				'edit_item'             => 'Editar cotización',
				'view_item'             => 'Ver cotización',
				'search_items'          => 'Buscar cotizaciones',
				'not_found'             => 'No se encontraron cotizaciones',
				'not_found_in_trash'    => 'No hay cotizaciones en la papelera',
				'all_items'             => 'Todas las cotizaciones',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'menu_icon'           => 'dashicons-clipboard',
			'menu_position'       => 56,
			'supports'            => [ 'title' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'exclude_from_search' => true,
		] );
	}

	public static function register_statuses_static() {
		$statuses = [
			'glo-new'             => 'Nueva',
			'glo-pending-prices'  => 'Pendiente de precios',
			'glo-auto-priced'     => 'Auto-cotizada',
			'glo-processing'      => 'En proceso',
			'glo-responded'       => 'Respondida',
			'glo-closed'          => 'Cerrada',
		];
		foreach ( $statuses as $slug => $label ) {
			register_post_status( $slug, [
				'label'                     => $label,
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>' ),
			] );
		}
	}

	public function columns( $columns ) {
		return [
			'cb'           => $columns['cb'] ?? '',
			'title'        => 'ID',
			'glo_type'     => 'Tipo',
			'glo_customer' => 'Cliente',
			'glo_email'    => 'Email',
			'glo_company'  => 'Empresa',
			'glo_items'    => 'Items',
			'glo_size'     => 'Tamaño',
			'glo_total'    => 'Total',
			'glo_status'   => 'Estado',
			'date'         => 'Fecha',
		];
	}

	public function render_column( $col, $post_id ) {
		switch ( $col ) {
			case 'glo_type':
				$type = get_post_meta( $post_id, '_glo_type', true ) ?: 'quote';
				$pricing = get_post_meta( $post_id, '_glo_pricing_status', true );
				$auto = '';
				echo '<span class="glo-type glo-type-' . esc_attr( $type ) . '">' . esc_html( glotracol_quote_type_label( $type ) ) . '</span>' . $auto;
				break;
			case 'glo_customer':
				echo esc_html( get_post_meta( $post_id, '_glo_customer_name', true ) );
				break;
			case 'glo_total':
				$total = (int) get_post_meta( $post_id, '_glo_total', true );
				echo $total > 0
					? '<strong>' . esc_html( glotracol_quote_format_price( $total ) ) . '</strong>'
					: '<span style="color:#999">—</span>';
				break;
			case 'glo_email':
				$email = get_post_meta( $post_id, '_glo_customer_email', true );
				echo $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '—';
				break;
			case 'glo_company':
				echo esc_html( get_post_meta( $post_id, '_glo_customer_company', true ) ?: '—' );
				break;
			case 'glo_phone':
				echo esc_html( get_post_meta( $post_id, '_glo_customer_phone', true ) ?: '—' );
				break;
			case 'glo_items':
				$items = get_post_meta( $post_id, '_glo_items', true );
				$count = is_array( $items ) ? count( $items ) : 0;
				$qty   = 0;
				if ( is_array( $items ) ) {
					foreach ( $items as $it ) {
						$qty += isset( $it['quantity'] ) ? (int) $it['quantity'] : 0;
					}
				}
				echo esc_html( $count . ' productos / ' . $qty . ' unidades' );
				break;
			case 'glo_size':
				$size = get_post_meta( $post_id, '_glo_size_tag', true );
				if ( ! $size ) {
					// Backfill on-the-fly para cotizaciones previas.
					$items = get_post_meta( $post_id, '_glo_items', true );
					if ( is_array( $items ) ) {
						$qty = 0;
						foreach ( $items as $it ) { $qty += (int) ( $it['quantity'] ?? 0 ); }
						$wk = glotracol_quote_weight_total( $items );
						$size = glotracol_quote_semaforo( $wk, $qty, count( $items ) );
					}
				}
				// Normaliza datos viejos (medium → large).
				if ( $size === 'medium' ) { $size = 'large'; }
				if ( $size ) {
					echo '<span class="glo-size glo-size-' . esc_attr( $size ) . '">' . esc_html( glotracol_quote_size_tag_label( $size ) ) . '</span>';
				} else {
					echo '—';
				}
				break;
			case 'glo_status':
				$status = get_post_status( $post_id );
				echo '<span class="glo-status glo-status-' . esc_attr( $status ) . '">' . esc_html( glotracol_quote_status_label( $status ) ) . '</span>';
				break;
		}
	}

	public function sortable_columns( $cols ) {
		$cols['glo_customer'] = '_glo_customer_name';
		$cols['glo_email']    = '_glo_customer_email';
		$cols['glo_company']  = '_glo_customer_company';
		return $cols;
	}

	public function inject_status_filter() {
		// Reservado para extensiones futuras (filtros por estado en list table).
	}

	public function append_statuses_to_dropdown() {
		global $post;
		if ( ! $post || $post->post_type !== self::POST_TYPE ) return;
		$current = $post->post_status;
		$statuses = [
			'glo-new'             => 'Nueva',
			'glo-pending-prices'  => 'Pendiente de precios',
			'glo-auto-priced'     => 'Auto-cotizada',
			'glo-processing'      => 'En proceso',
			'glo-responded'       => 'Respondida',
			'glo-closed'          => 'Cerrada',
		];
		$options = '';
		foreach ( $statuses as $slug => $label ) {
			$selected = selected( $slug, $current, false );
			$options .= '<option value="' . esc_attr( $slug ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		$current_label = isset( $statuses[ $current ] ) ? $statuses[ $current ] : ucfirst( $current );
		?>
		<script>
		jQuery(function($){
			var $sel = $('select#post_status');
			if (!$sel.length) return;
			$sel.html(<?php echo wp_json_encode( $options ); ?>);
			$('#post-status-display').text(<?php echo wp_json_encode( $current_label ); ?>);
		});
		</script>
		<?php
	}

	public function display_post_states( $states, $post ) {
		if ( $post->post_type !== self::POST_TYPE ) return $states;
		$status = $post->post_status;
		$label  = glotracol_quote_status_label( $status );
		if ( $label !== $status ) {
			$states[ $status ] = $label;
		}
		return $states;
	}
}
