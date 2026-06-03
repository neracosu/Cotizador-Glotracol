<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin UI del CPT glo_client: metaboxes con datos del cliente, tabla de
 * precios B2B negociados y bloque de historial de cotizaciones.
 */
class Glotracol_Quote_Client_Admin {

	const NONCE_FIELD = 'glo_client_nonce';
	const NONCE_ACTION = 'glo_client_save';

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
		add_action( 'save_post_' . Glotracol_Quote_Client_CPT::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
	}

	public function register_metaboxes() {
		$cpt = Glotracol_Quote_Client_CPT::POST_TYPE;
		add_meta_box( 'glo_client_basic', 'Datos del cliente', [ $this, 'render_basic' ], $cpt, 'normal', 'high' );
		add_meta_box( 'glo_client_pricing', 'Lista de precios negociados', [ $this, 'render_pricing' ], $cpt, 'normal', 'high' );
		add_meta_box( 'glo_client_history', 'Historial de cotizaciones', [ $this, 'render_history' ], $cpt, 'side', 'default' );
		add_meta_box( 'glo_client_status', 'Estado', [ $this, 'render_status' ], $cpt, 'side', 'high' );
	}

	public function render_basic( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$fields = [
			'_glo_client_nit'      => [ 'label' => 'NIT / Cédula', 'placeholder' => '900123456-7', 'required' => true ],
			'_glo_client_name'     => [ 'label' => 'Razón social', 'placeholder' => 'Ej. Distribuidora ABC S.A.S.', 'required' => true ],
			'_glo_client_email'    => [ 'label' => 'Email', 'placeholder' => 'compras@empresa.com', 'type' => 'email' ],
			'_glo_client_phone'    => [ 'label' => 'Teléfono', 'placeholder' => '+57 300 1234567' ],
			'_glo_client_contact'  => [ 'label' => 'Persona de contacto', 'placeholder' => 'Nombre del interlocutor' ],
			'_glo_client_city'     => [ 'label' => 'Ciudad / País', 'placeholder' => 'Bogotá, Colombia' ],
		];
		echo '<table class="form-table"><tbody>';
		foreach ( $fields as $key => $opts ) {
			$value = get_post_meta( $post->ID, $key, true );
			$type = $opts['type'] ?? 'text';
			$req = ! empty( $opts['required'] ) ? ' required' : '';
			echo '<tr><th style="width:180px"><label for="' . esc_attr( $key ) . '">' . esc_html( $opts['label'] ) . ( ! empty( $opts['required'] ) ? ' *' : '' ) . '</label></th>';
			echo '<td><input type="' . esc_attr( $type ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . esc_attr( $opts['placeholder'] ?? '' ) . '"' . $req . '></td></tr>';
		}
		// Notes
		$notes = get_post_meta( $post->ID, '_glo_client_notes', true );
		echo '<tr><th><label for="_glo_client_notes">Notas internas</label></th>';
		echo '<td><textarea id="_glo_client_notes" name="_glo_client_notes" rows="3" class="large-text" placeholder="Visible solo para el equipo. Condiciones de pago, observaciones, etc.">' . esc_textarea( $notes ) . '</textarea></td></tr>';
		echo '</tbody></table>';
	}

	public function render_status( $post ) {
		$active = get_post_meta( $post->ID, '_glo_client_active', true );
		$is_active = $active !== 'no'; // default activo si no hay valor
		echo '<p><label><input type="checkbox" name="_glo_client_active" value="yes"' . checked( $is_active, true, false ) . '> <strong>Cliente activo</strong></label></p>';
		echo '<p class="description">Si está inactivo, sus precios negociados se ignoran y los pedidos con su NIT recibirán precios públicos.</p>';
	}

	public function render_pricing( $post ) {
		$pricing = get_post_meta( $post->ID, '_glo_client_pricing', true );
		if ( ! is_array( $pricing ) ) $pricing = [];
		?>
		<p class="description">Define el precio negociado por SKU. Los SKUs que no aparezcan aquí usarán el precio público de la lista general. Las celdas vacías o en cero también se ignoran.</p>
		<table class="widefat striped" id="glo-pricing-table" style="max-width:720px">
			<thead>
				<tr>
					<th style="width:50%">SKU del producto</th>
					<th style="width:35%">Precio (COP)</th>
					<th style="width:15%"></th>
				</tr>
			</thead>
			<tbody id="glo-pricing-rows">
				<?php
				$row_idx = 0;
				if ( ! empty( $pricing ) ) :
					foreach ( $pricing as $sku => $price ) :
						$this->render_pricing_row( $row_idx, $sku, $price );
						$row_idx++;
					endforeach;
				endif;
				// Fila en blanco para añadir uno nuevo
				$this->render_pricing_row( $row_idx, '', '' );
				?>
			</tbody>
		</table>
		<template id="glo-pricing-tpl"><?php $this->render_pricing_row( '__IDX__', '', '' ); ?></template>
		<p style="margin-top:10px"><button type="button" class="button" id="glo-pricing-add" data-gloq-add-row data-target="#glo-pricing-rows" data-template="#glo-pricing-tpl" data-next="<?php echo (int) $row_idx + 1; ?>">+ Añadir fila</button> <span class="description" style="margin-left:10px">Para borrar una fila, deja el SKU en blanco al guardar.</span></p>
		<?php
	}

	private function render_pricing_row( $idx, $sku, $price ) {
		?>
		<tr>
			<td><input type="text" name="glo_pricing[<?php echo (int) $idx; ?>][sku]" value="<?php echo esc_attr( $sku ); ?>" class="regular-text" placeholder="SKU"></td>
			<td><input type="number" name="glo_pricing[<?php echo (int) $idx; ?>][price]" value="<?php echo esc_attr( $price ); ?>" min="0" step="1" placeholder="0"></td>
			<td><button type="button" class="button-link-delete glo-pricing-remove gloq-remove-row" aria-label="Quitar">×</button></td>
		</tr>
		<?php
	}

	public function render_history( $post ) {
		$client_id = $post->ID;
		$quotes = get_posts( [
			'post_type'   => 'glo_quote',
			'post_status' => 'any',
			'numberposts' => 10,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_key'    => '_glo_client_id',
			'meta_value'  => $client_id,
		] );
		if ( empty( $quotes ) ) {
			echo '<p>Aún no hay cotizaciones asociadas a este cliente.</p>';
			return;
		}
		echo '<ul style="margin:0">';
		foreach ( $quotes as $q ) {
			$status = get_post_status( $q->ID );
			$size = get_post_meta( $q->ID, '_glo_size_tag', true );
			echo '<li style="padding:8px 0;border-bottom:1px solid #eee">';
			echo '<a href="' . esc_url( get_edit_post_link( $q->ID ) ) . '"><strong>#' . (int) $q->ID . '</strong> — ' . esc_html( $q->post_title ) . '</a><br>';
			echo '<small>' . esc_html( get_the_date( 'd/m/Y H:i', $q ) ) . ' · ';
			echo '<span class="glo-status glo-status-' . esc_attr( $status ) . '">' . esc_html( glotracol_quote_status_label( $status ) ) . '</span>';
			if ( $size ) {
				echo ' · <span class="glo-size glo-size-' . esc_attr( $size ) . '">' . esc_html( glotracol_quote_size_tag_label( $size ) ) . '</span>';
			}
			echo '</small></li>';
		}
		echo '</ul>';
		$total = Glotracol_Quote_Client_CPT::count_quotes_for_client( $client_id );
		if ( $total > 10 ) {
			$url = admin_url( 'edit.php?post_type=glo_quote&meta_key=_glo_client_id&meta_value=' . $client_id );
			echo '<p style="margin-top:10px"><a href="' . esc_url( $url ) . '">Ver las ' . (int) $total . ' cotizaciones</a></p>';
		}
	}

	public function save_post( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) return;
		if ( ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_FIELD ] ), self::NONCE_ACTION ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( $post->post_type !== Glotracol_Quote_Client_CPT::POST_TYPE ) return;

		// Datos básicos
		$fields = [
			'_glo_client_nit'      => [ 'sanitizer' => 'sanitize_text_field' ],
			'_glo_client_name'     => [ 'sanitizer' => 'sanitize_text_field' ],
			'_glo_client_email'    => [ 'sanitizer' => 'sanitize_email' ],
			'_glo_client_phone'    => [ 'sanitizer' => 'sanitize_text_field' ],
			'_glo_client_contact'  => [ 'sanitizer' => 'sanitize_text_field' ],
			'_glo_client_city'     => [ 'sanitizer' => 'sanitize_text_field' ],
			'_glo_client_notes'    => [ 'sanitizer' => 'sanitize_textarea_field' ],
		];
		foreach ( $fields as $key => $opts ) {
			$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$clean = call_user_func( $opts['sanitizer'], $raw );
			update_post_meta( $post_id, $key, $clean );
		}

		// Estado activo
		$active = ! empty( $_POST['_glo_client_active'] ) && $_POST['_glo_client_active'] === 'yes' ? 'yes' : 'no';
		update_post_meta( $post_id, '_glo_client_active', $active );

		// Pricing rows
		$pricing = [];
		if ( isset( $_POST['glo_pricing'] ) && is_array( $_POST['glo_pricing'] ) ) {
			foreach ( $_POST['glo_pricing'] as $row ) {
				if ( ! is_array( $row ) ) continue;
				$sku = isset( $row['sku'] ) ? sanitize_text_field( wp_unslash( $row['sku'] ) ) : '';
				$price = isset( $row['price'] ) ? (int) $row['price'] : 0;
				if ( $sku === '' || $price <= 0 ) continue;
				$pricing[ $sku ] = $price;
			}
		}
		update_post_meta( $post_id, '_glo_client_pricing', $pricing );

		// Si el title vino vacío, usar la razón social como title del CPT
		$company = get_post_meta( $post_id, '_glo_client_name', true );
		$current_title = get_the_title( $post_id );
		if ( $company && ( ! $current_title || $current_title === 'Auto Draft' ) ) {
			remove_action( 'save_post_' . Glotracol_Quote_Client_CPT::POST_TYPE, [ $this, 'save_post' ], 10 );
			wp_update_post( [ 'ID' => $post_id, 'post_title' => $company ] );
			add_action( 'save_post_' . Glotracol_Quote_Client_CPT::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
		}
	}
}
