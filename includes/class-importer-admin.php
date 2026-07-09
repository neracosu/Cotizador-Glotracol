<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * UI del importador CSV.
 *
 * Flujo:
 *  1. GET → muestra formulario con selector de tipo y botón "descargar plantilla"
 *  2. POST con `action=preview` → parsea y muestra preview (primeras 20 filas)
 *  3. POST con `action=import` → ejecuta la importación y muestra reporte
 *
 * El archivo subido se guarda temporalmente en uploads/glotracol-import/<token>.csv
 * y se elimina al final del flujo (o tras 1 hora vía wp-cron).
 */
class Glotracol_Quote_Importer_Admin {

	const PAGE_SLUG = 'glotracol-quote-import';
	const NONCE_ACTION = 'gloq_import';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_post_gloq_import_template', [ $this, 'download_template' ] );
		add_action( 'admin_post_gloq_import_preview', [ $this, 'handle_preview' ] );
		add_action( 'admin_post_gloq_import_run', [ $this, 'handle_run' ] );
	}

	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=glo_quote',
			'Importar datos',
			'Importar',
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'upload';
		?>
		<div class="wrap">
			<h1>Importar datos</h1>
			<p>Sube los datos del cotizador en formato CSV. Cada hoja tiene su propia plantilla descargable. La importación es <strong>aditiva</strong>: actualiza filas existentes (por NIT o SKU) y crea las nuevas. Las filas con error se reportan al final pero no detienen el resto.</p>

			<?php
			switch ( $step ) {
				case 'preview':  $this->render_preview(); break;
				case 'report':   $this->render_report(); break;
				default:         $this->render_upload(); break;
			}
			?>
		</div>
		<?php
	}

	private function render_upload() {
		$types = Glotracol_Quote_Importer::TYPES;
		$selected_type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'clientes';
		?>
		<div class="gloq-importer-card">
			<h2>1. Elige el tipo de hoja</h2>
			<div class="gloq-importer-types">
				<?php foreach ( $types as $key => $label ) :
					$desc = $this->describe_type( $key );
					$is_selected = $key === $selected_type;
				?>
					<label class="gloq-importer-type <?php echo $is_selected ? 'gloq-importer-type-selected' : ''; ?>">
						<input type="radio" name="gloq_type" value="<?php echo esc_attr( $key ); ?>" <?php checked( $is_selected ); ?> onchange="window.location='<?php echo esc_url( admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG . '&type=' ) ); ?>' + this.value">
						<div class="gloq-importer-type-body">
							<strong><?php echo esc_html( $label ); ?></strong>
							<p><?php echo wp_kses_post( $desc ); ?></p>
						</div>
					</label>
				<?php endforeach; ?>
			</div>

			<h2 style="margin-top:24px">2. Descarga la plantilla</h2>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gloq_import_template&type=' . $selected_type ), self::NONCE_ACTION ) ); ?>">Descargar plantilla <code><?php echo esc_html( $selected_type ); ?>.csv</code></a>
			</p>

			<h2 style="margin-top:24px">3. Sube el archivo CSV</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="gloq_import_preview">
				<input type="hidden" name="gloq_type" value="<?php echo esc_attr( $selected_type ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<table class="form-table">
					<tr><th><label>Archivo CSV o Excel (.xlsx)</label></th>
						<td><input type="file" name="gloq_csv" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
						<p class="description">Tamaño máximo: <?php echo size_format( wp_max_upload_size() ); ?>. Puedes subir el Excel (.xlsx) directamente o un CSV (UTF-8). El sistema reconoce las columnas aunque tengan nombres distintos.</p></td></tr>
				</table>
				<?php if ( $selected_type === 'precios_catalogo' ) :
					$clients = get_posts( [ 'post_type' => Glotracol_Quote_Client_CPT::POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
				?>
				<table class="form-table">
					<tr><th>Tipo de lista</th><td>
						<label><input type="radio" name="gloq_mode" value="publico" checked> Lista pública (precio general)</label><br>
						<label><input type="radio" name="gloq_mode" value="b2b"> Tarifa de un cliente B2B</label>
					</td></tr>
					<tr><th>Cliente B2B</th><td>
						<select name="gloq_client_id">
							<option value="0">— (solo para tarifa B2B) —</option>
							<?php foreach ( $clients as $c ) {
								printf( '<option value="%d">%s</option>', (int) $c->ID, esc_html( get_post_meta( $c->ID, '_glo_client_nit', true ) . ' — ' . $c->post_title ) );
							} ?>
						</select>
						<p class="description">Requerido si elegiste "Tarifa de un cliente B2B".</p>
					</td></tr>
					<tr><th>Sincronizar stock</th><td>
						<label><input type="checkbox" name="gloq_sync_stock" value="1"> Actualizar disponibilidad en WooCommerce desde la columna Disponibilidad / Inventario (solo lista pública)</label>
					</td></tr>
					<tr><th>Crear faltantes</th><td>
						<label><input type="checkbox" name="gloq_create_missing" value="1"> Crear el producto cuando la fila venga sin ID (solo lista pública). Si el nombre ya existe, actualiza el producto existente en vez de duplicar.</label>
					</td></tr>
				</table>
				<?php endif; ?>
				<p class="submit"><input type="submit" class="button button-primary" value="Subir y previsualizar →"></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Aviso de impacto mostrado en el preview, antes de confirmar la importación.
	 * Describe en lenguaje claro qué se va a escribir/sobrescribir según el tipo de hoja,
	 * para que el operador no confirme una carga equivocada. Devuelve HTML.
	 */
	private function preview_impact_notice( $type, $opts = [] ) {
		$mode           = $opts['mode'] ?? 'publico';
		$client_label   = $opts['client_label'] ?? '';
		$create_missing = ! empty( $opts['create_missing'] );
		$no_id_count    = (int) ( $opts['no_id_count'] ?? 0 );

		$lines = [];
		switch ( $type ) {
			case 'precios_catalogo':
				if ( $mode === 'b2b' ) {
					$lines[] = sprintf(
						'Vas a escribir la <strong>tarifa negociada</strong> de <strong>%s</strong> sobre los productos de este archivo, identificados por <strong>ID</strong>.',
						esc_html( $client_label ?: 'el cliente seleccionado' )
					);
				} else {
					$lines[] = 'Vas a escribir el <strong>precio de Lista pública (interno)</strong> sobre los productos de este archivo, identificados por <strong>ID</strong>. <em>Este precio no se muestra en la tienda</em>: solo lo usa el sistema para armar la cotización.';
					$lines[] = '<strong>Confirma que este archivo es la Lista pública.</strong> Si subes otra lista con las mismas columnas (p. ej. una lista con descuento) sobrescribirás los precios públicos: el sistema no puede distinguirla por su contenido.';
					if ( $create_missing ) {
						$lines[] = sprintf(
							'Las filas <strong>sin ID</strong> (%d en este archivo) <strong>crearán productos nuevos publicados</strong> en el catálogo (sin foto/categoría/descripción). Si el nombre ya existe, se actualiza el producto existente <strong>sin duplicar</strong>.',
							$no_id_count
						);
					} elseif ( $no_id_count > 0 ) {
						$lines[] = sprintf(
							'"Crear faltantes" está <strong>desactivado</strong>: las <strong>%d filas sin ID se omitirán</strong> (no se crea ningún producto). Si este archivo trae productos nuevos, vuelve atrás y actívalo.',
							$no_id_count
						);
					}
				}
				break;
			case 'precios_b2b':
				$lines[] = 'Escribe la <strong>tarifa negociada</strong> de cada cliente del archivo (por NIT y SKU). Un NIT/SKU repetido <strong>pisa</strong> el precio anterior; los SKU que no vengan en el CSV se mantienen.';
				$lines[] = 'Los NITs deben existir ya en el CRM; las filas con NIT desconocido se omiten.';
				break;
			case 'precios_publicos':
				$lines[] = 'Actualiza o inserta precios de la <strong>Lista pública</strong> por <strong>SKU</strong>. Un SKU repetido pisa el valor anterior.';
				break;
			case 'presentaciones':
				$lines[] = 'Para cada producto del archivo <strong>reemplaza toda su lista de presentaciones</strong>: las presentaciones que no estén en el CSV <strong>dejarán de aparecer</strong> en la tienda.';
				break;
			case 'clientes':
				$lines[] = 'Crea o actualiza <strong>clientes B2B</strong> por NIT. Un NIT existente actualiza sus datos con los del archivo.';
				break;
			default:
				return '';
		}

		// Salvedad común a todas las hojas.
		$lines[] = 'Reimportar el mismo archivo es seguro: <strong>actualiza, no duplica</strong>. Las filas inválidas o sin precio se omiten y se reportan al final.';

		$html  = '<div class="notice notice-warning inline" style="margin:16px 0;padding:12px 16px">';
		$html .= '<p style="margin:0 0 8px"><strong>Antes de confirmar, verifica qué hará esta importación:</strong></p>';
		$html .= '<ul style="margin:0 0 4px 18px;list-style:disc">';
		foreach ( $lines as $l ) {
			$html .= '<li>' . wp_kses_post( $l ) . '</li>';
		}
		$html .= '</ul>';
		$html .= '<p style="margin:8px 0 0">Si algo de esto no es lo que quieres, usa <strong>Cancelar</strong> y vuelve a empezar.</p>';
		$html .= '</div>';
		return $html;
	}

	private function describe_type( $type ) {
		switch ( $type ) {
			case 'clientes':
				return 'Crea o actualiza clientes B2B en el CRM por NIT/Cédula. Columnas: <code>nit, razon_social, email, telefono, contacto, ciudad, activo, notas</code>.';
			case 'precios_publicos':
				return 'Carga la lista pública de precios (semanal). Se aplica a clientes sin NIT identificado. Columnas: <code>sku, precio</code>.';
			case 'precios_b2b':
				return 'Precios negociados por cliente. Requiere que los NITs ya existan en el CRM. Columnas: <code>nit, sku, precio</code>.';
			case 'presentaciones':
				return 'Define las presentaciones (250g, 500g, etc.) por producto. Columnas: <code>sku_producto, label, sku_variante, peso_g, precio_publico</code>.';
			case 'precios_catalogo':
				return 'Carga precios por <strong>ID de producto</strong> desde el export del catálogo. Columnas: <code>ID, Nombre, Peso (kg), Precio normal, Disponibilidad</code> (acepta <code>Inventario</code> como alias de Disponibilidad). Elige abajo si es lista pública o tarifa de un cliente B2B; en lista pública puedes <strong>crear los productos cuyas filas vengan sin ID</strong>.';
			case 'clientes_lista':
				return 'Marca qué clientes reciben la <strong>Lista B</strong> (mayoreo). Acepta la plantilla (<code>nit, nombre, lista</code>) o el Excel de clientes con precio diferente (<code>identificacion, nombre de la compañia, precio</code>): los rótulos <code>LISTA B</code> y <code>PRECIO DIFERENTE</code> se toman como Lista B. Si el NIT no existe, crea una ficha mínima etiquetada B.';
			case 'precios_lista_b':
				return 'Carga los precios de <strong>Lista B</strong> (mayoreo) por <strong>ID de producto</strong>, usando el mismo archivo del catálogo (columna <code>Precio normal</code>). Escribe un precio B aparte, sin tocar la Lista A. Las filas sin ID se resuelven por <strong>nombre</strong> a un producto ya existente (primero carga la Lista A para crear los productos nuevos).';
		}
		return '';
	}

	private function render_preview() {
		$token = isset( $_GET['token'] ) ? sanitize_key( $_GET['token'] ) : '';
		$mode           = isset( $_GET['mode'] ) && $_GET['mode'] === 'b2b' ? 'b2b' : 'publico';
		$client_id      = isset( $_GET['client_id'] ) ? (int) $_GET['client_id'] : 0;
		$sync_stock     = ! empty( $_GET['sync_stock'] ) ? 1 : 0;
		$create_missing = ! empty( $_GET['create_missing'] ) ? 1 : 0;
		$ext            = ( isset( $_GET['ext'] ) && $_GET['ext'] === 'xlsx' ) ? 'xlsx' : 'csv';
		if ( ! $token ) {
			if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
				Glotracol_Quote_Logger::warn( 'import', 'render_preview: token vacío en query', [
					'token' => $token, 'query' => $_GET,
				] );
			}
			echo '<div class="notice notice-error"><p>Sesión inválida.</p></div>';
			return;
		}
		$file = $this->get_temp_file_path( $token, $ext );
		if ( ! file_exists( $file ) ) {
			if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
				$dir = $this->get_temp_dir();
				$existing_files = $dir && is_dir( $dir ) ? array_diff( scandir( $dir ), [ '.', '..', '.htaccess', 'index.php' ] ) : [];
				Glotracol_Quote_Logger::error( 'import', 'render_preview: archivo no encontrado', [
					'token'          => $token,
					'ext'            => $ext,
					'expected_path'  => $file,
					'temp_dir'       => $dir,
					'existing_files' => array_values( $existing_files ),
				] );
			}
			echo '<div class="notice notice-error"><p>Archivo no encontrado o expirado.</p></div>';
			return;
		}
		$forced = isset( $_GET['type'] ) && $_GET['type'] !== '' ? sanitize_key( $_GET['type'] ) : null;
		$read = Glotracol_Quote_Import_Reader::read( $file, $forced );
		if ( $read['error'] && empty( $read['rows'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $read['error'] ) . '</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG ) ) . '" class="button">← Volver</a></p>';
			return;
		}
		$type  = $read['chosen_type'];
		$total = count( $read['rows'] );
		echo '<div class="gloq-importer-card">';
		echo '<h2>Previsualización</h2>';
		// Tipo detectado (barrera 2)
		echo '<p><strong>Tipo detectado:</strong> ' . esc_html( Glotracol_Quote_Importer::TYPES[ $type ] ?? $type );
		echo ' <span class="description">(confianza ' . esc_html( (int) round( $read['type_confidence'] * 100 ) ) . '%)</span></p>';
		if ( $read['type_ambiguous'] && ! $forced ) {
			echo '<div class="notice notice-warning inline"><p><strong>El tipo no es claro</strong> (dos formatos coinciden). Elige el tipo correcto antes de continuar:</p><p>';
			foreach ( Glotracol_Quote_Importer::TYPES as $k => $label ) {
				$url = add_query_arg( 'type', $k );
				echo '<a class="button" style="margin:2px" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
			}
			echo '</p></div>';
		}
		// Columnas entendidas
		echo '<p><strong>Columnas que entendí:</strong> ';
		$pairs = [];
		foreach ( $read['mapping'] as $canonical => $raw_h ) {
			$pairs[] = esc_html( $raw_h ) . ' → <code>' . esc_html( $canonical ) . '</code>';
		}
		echo $pairs ? implode( ', ', $pairs ) : '—';
		echo '</p>';
		if ( ! empty( $read['unmapped'] ) ) {
			echo '<p class="description">Columnas ignoradas (no reconocidas): ' . esc_html( implode( ', ', $read['unmapped'] ) ) . '</p>';
		}
		if ( ! empty( $read['corrections'] ) ) {
			echo '<details style="margin:8px 0"><summary style="cursor:pointer"><strong>Valores que corregí (' . count( $read['corrections'] ) . ')</strong></summary><ul style="margin:6px 0 0 18px;list-style:disc">';
			foreach ( $read['corrections'] as $c ) {
				echo '<li>' . esc_html( $c ) . '</li>';
			}
			echo '</ul></details>';
		}
		echo '<p>Filas válidas: <strong>' . (int) $total . '</strong>. Muestra de las primeras ' . min( 20, $total ) . ':</p>';
		// Tabla de muestra
		$cols = $read['headers'];
		echo '<table class="wp-list-table widefat striped"><thead><tr>';
		foreach ( $cols as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( array_slice( $read['rows'], 0, 20 ) as $r ) {
			echo '<tr>';
			foreach ( $cols as $h ) {
				echo '<td>' . esc_html( $r[ $h ] ?? '' ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';

		// Info extra para precios_catalogo
		$client_label = '';
		if ( $type === 'precios_catalogo' && $mode === 'b2b' && $client_id > 0 ) {
			$cp = get_post( $client_id );
			$client_label = $cp ? $cp->post_title : '';
		}
		// Filas sin ID (relevante solo para "crear faltantes").
		$no_id_count = 0;
		foreach ( $read['rows'] as $r ) {
			if ( (int) ( $r['id'] ?? 0 ) <= 0 ) $no_id_count++;
		}
		if ( $type === 'precios_catalogo' ) {
			echo '<p><strong>Modo:</strong> ' . ( $mode === 'b2b' ? 'Tarifa B2B' . ( $client_label ? ' — ' . esc_html( $client_label ) : '' ) : 'Lista pública' ) . ' · <strong>Sincronizar stock:</strong> ' . ( $sync_stock ? 'sí' : 'no' );
			if ( $mode === 'publico' ) {
				echo ' · <strong>Crear faltantes:</strong> ' . ( $create_missing ? 'sí' : 'no' );
			}
			echo '</p>';
			if ( $mode === 'b2b' && $client_id <= 0 ) {
				echo '<div class="notice notice-error inline"><p>Elegiste tarifa B2B pero no seleccionaste un cliente. Vuelve atrás y elige el cliente.</p></div>';
			}
		}

		// Aviso de impacto: qué va a escribir/sobrescribir esta importación, antes de confirmar.
		echo $this->preview_impact_notice( $type, [
			'mode'           => $mode,
			'client_label'   => $client_label,
			'create_missing' => $create_missing,
			'no_id_count'    => $no_id_count,
		] );

		// Calcular filas sin match (solo tipos por-ID de producto).
		$resolvable = in_array( $type, [ 'precios_catalogo', 'precios_lista_b' ], true );
		$unmatched = [];
		if ( $resolvable ) {
			foreach ( $read['rows'] as $r ) {
				$pid = (int) ( $r['id'] ?? 0 );
				$name = (string) ( $r['nombre'] ?? '' );
				if ( $pid > 0 ) continue;
				if ( $name === '' ) continue;
				// ¿ya casa por nombre normalizado? entonces el importador lo resolverá solo → no lo mostramos.
				if ( Glotracol_Quote_Import_Reader::find_by_name( $name, 'product' ) > 0 ) continue;
				$cands = Glotracol_Quote_Import_Reader::suggest_candidates( $name, 'product', 3 );
				$unmatched[] = [ 'line' => $r['__line'] ?? '?', 'name' => $name, 'cands' => $cands ];
			}
		}

		// Barrera de ambigüedad: bloquear confirmar si el tipo no está claro y no se forzó.
		if ( $read['type_ambiguous'] && ! $forced ) {
			echo '<p><em>Elige el tipo arriba para habilitar la importación.</em></p>';
		} else {
			$back_url = esc_url( admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG . '&type=' . $type ) );
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:20px">';
			echo '<input type="hidden" name="action" value="gloq_import_run">';
			echo '<input type="hidden" name="token" value="' . esc_attr( $token ) . '">';
			echo '<input type="hidden" name="ext" value="' . esc_attr( $ext ) . '">';
			echo '<input type="hidden" name="gloq_type" value="' . esc_attr( $type ) . '">';
			echo '<input type="hidden" name="gloq_mode" value="' . esc_attr( $mode ) . '">';
			echo '<input type="hidden" name="gloq_client_id" value="' . (int) $client_id . '">';
			echo '<input type="hidden" name="gloq_sync_stock" value="' . (int) $sync_stock . '">';
			echo '<input type="hidden" name="gloq_create_missing" value="' . (int) $create_missing . '">';
			wp_nonce_field( self::NONCE_ACTION );
			// Bloque de filas sin coincidencia (dentro del form para que los <select> viajen en el POST).
			if ( ! empty( $unmatched ) ) {
				echo '<div class="notice notice-warning inline" style="margin-top:16px"><p><strong>' . count( $unmatched ) . ' fila(s) sin coincidencia por ID/nombre.</strong> Elige a qué producto corresponde cada una (o déjalas para ignorar). Esta elección es solo para esta carga; no se guarda.</p></div>';
				echo '<table class="wp-list-table widefat striped"><thead><tr><th>Fila</th><th>Nombre en el archivo</th><th>¿A qué producto corresponde?</th></tr></thead><tbody>';
				foreach ( $unmatched as $u ) {
					echo '<tr><td>' . esc_html( $u['line'] ) . '</td><td>' . esc_html( $u['name'] ) . '</td><td>';
					$field = 'gloq_resolve[' . esc_attr( $u['line'] ) . ']';
					echo '<select name="' . $field . '">';
					echo '<option value="">— Ignorar esta fila —</option>';
					foreach ( $u['cands'] as $c ) {
						echo '<option value="' . (int) $c['id'] . '">' . esc_html( $c['label'] ) . ' (' . (int) $c['score'] . '%)</option>';
					}
					echo '</select>';
					echo '</td></tr>';
				}
				echo '</tbody></table>';
			}
			if ( ! ( $type === 'precios_catalogo' && $mode === 'b2b' && $client_id <= 0 ) ) {
				echo '<p class="submit">';
				echo '<input type="submit" class="button button-primary button-large" value="Confirmar e importar ' . (int) $total . ' filas">';
				echo ' <a href="' . $back_url . '" class="button">Cancelar</a>';
				echo '</p>';
			} else {
				echo '<p><a href="' . $back_url . '" class="button">← Volver y elegir cliente</a></p>';
			}
			echo '</form>';
		}
		echo '</div>';
	}

	private function render_report() {
		$report = get_transient( 'gloq_import_last_report' );
		if ( ! is_array( $report ) ) {
			echo '<div class="notice notice-info"><p>No hay reporte reciente.</p></div>';
			return;
		}
		?>
		<div class="gloq-importer-card">
			<h2>Reporte de importación — <?php echo esc_html( Glotracol_Quote_Importer::TYPES[ $report['type'] ] ?? $report['type'] ); ?></h2>
			<div class="gloq-import-summary">
				<div class="gloq-import-stat gloq-import-stat-inserted">
					<div class="gloq-import-num"><?php echo (int) ( $report['inserted'] ?? 0 ); ?></div>
					<div class="gloq-import-label">Insertados</div>
				</div>
				<div class="gloq-import-stat gloq-import-stat-updated">
					<div class="gloq-import-num"><?php echo (int) ( $report['updated'] ?? 0 ); ?></div>
					<div class="gloq-import-label">Actualizados</div>
				</div>
				<div class="gloq-import-stat gloq-import-stat-skipped">
					<div class="gloq-import-num"><?php echo (int) ( $report['skipped'] ?? 0 ); ?></div>
					<div class="gloq-import-label">Omitidos</div>
				</div>
				<div class="gloq-import-stat gloq-import-stat-errors">
					<div class="gloq-import-num"><?php echo count( $report['errors'] ?? [] ); ?></div>
					<div class="gloq-import-label">Errores</div>
				</div>
			</div>

			<?php if ( ! empty( $report['errors'] ) ) : ?>
				<h3 style="margin-top:24px;color:#7a1d12">Detalle de errores</h3>
				<div class="gloq-import-errors">
					<?php foreach ( $report['errors'] as $err ) : ?>
						<div class="gloq-import-error"><?php echo esc_html( $err ); ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<p style="margin-top:24px"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=glo_quote&page=' . self::PAGE_SLUG ) ); ?>" class="button button-primary">Importar otro archivo</a></p>
		</div>
		<?php
	}

	/* -------------------------------------------------------------------------
	 * HANDLERS
	 * ---------------------------------------------------------------------- */

	public function download_template() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos' );
		check_admin_referer( self::NONCE_ACTION );
		$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
		$path = GLOTRACOL_QUOTE_PATH . 'templates/csv/' . $type . '.csv';
		if ( ! file_exists( $path ) ) wp_die( 'Plantilla no encontrada.' );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="glotracol-' . $type . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		readfile( $path );
		exit;
	}

	public function handle_preview() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos' );
		check_admin_referer( self::NONCE_ACTION );
		$type = isset( $_POST['gloq_type'] ) ? sanitize_key( $_POST['gloq_type'] ) : '';
		if ( ! isset( Glotracol_Quote_Importer::TYPES[ $type ] ) ) {
			$this->redirect_back( 'Tipo de hoja inválido.' );
		}
		if ( empty( $_FILES['gloq_csv']['tmp_name'] ) ) {
			$this->redirect_back( 'No se subió archivo.' );
		}
		$tmp = $_FILES['gloq_csv']['tmp_name'];
		// Validar mime (lenient, fgetcsv tolera más que mime)
		$ok_mimes = [ 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip' ];
		$mime = $_FILES['gloq_csv']['type'] ?? '';
		if ( $mime && ! in_array( $mime, $ok_mimes, true ) ) {
			// permitir igual, solo log
			if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
				Glotracol_Quote_Logger::warn( 'import', 'MIME no esperado pero permitido', [ 'mime' => $mime, 'type' => $type ] );
			}
		}

		// Mover a directorio temporal del plugin.
		// Token solo lowercase hex para que sanitize_key() (que lowercasea)
		// no genere mismatch entre el path guardado y el lookup posterior.
		// La extensión viaja como parámetro aparte (no dentro del token).
		$ext = ( strtolower( pathinfo( (string) ( $_FILES['gloq_csv']['name'] ?? '' ), PATHINFO_EXTENSION ) ) === 'xlsx' ) ? 'xlsx' : 'csv';
		$token = bin2hex( random_bytes( 8 ) ); // hex puro, siempre lowercase
		$dest_dir = $this->get_temp_dir();
		if ( ! $dest_dir ) {
			$this->redirect_back( 'No se pudo crear directorio temporal.' );
		}
		$dest = $dest_dir . '/' . $token . '.' . $ext;
		if ( ! move_uploaded_file( $tmp, $dest ) ) {
			if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
				Glotracol_Quote_Logger::error( 'import', 'move_uploaded_file falló', [
					'type' => $type, 'token' => $token, 'tmp' => $tmp, 'dest' => $dest,
				] );
			}
			$this->redirect_back( 'No se pudo guardar el archivo.' );
		}
		// Asegurar que solo el dueño puede leer
		@chmod( $dest, 0640 );

		if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
			Glotracol_Quote_Logger::info( 'import', 'Archivo subido para preview', [
				'type' => $type, 'token' => $token, 'size' => filesize( $dest ),
			] );
		}

		// Programar limpieza en 1h
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'gloq_importer_cleanup', [ $token, $ext ] );

		$extra = [ 'ext' => $ext ];
		if ( $type === 'precios_catalogo' ) {
			$extra['mode']       = ( ( $_POST['gloq_mode'] ?? 'publico' ) === 'b2b' ) ? 'b2b' : 'publico';
			$extra['client_id']  = (int) ( $_POST['gloq_client_id'] ?? 0 );
			$extra['sync_stock'] = ! empty( $_POST['gloq_sync_stock'] ) ? 1 : 0;
			$extra['create_missing'] = ! empty( $_POST['gloq_create_missing'] ) ? 1 : 0;
		}

		wp_safe_redirect( add_query_arg( array_merge( [
			'post_type' => 'glo_quote',
			'page'      => self::PAGE_SLUG,
			'step'      => 'preview',
			'type'      => $type,
			'token'     => $token,
		], $extra ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public function handle_run() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos' );
		check_admin_referer( self::NONCE_ACTION );
		$token = isset( $_POST['token'] ) ? sanitize_key( $_POST['token'] ) : '';
		$type  = isset( $_POST['gloq_type'] ) ? sanitize_key( $_POST['gloq_type'] ) : '';
		$ext   = ( isset( $_POST['ext'] ) && $_POST['ext'] === 'xlsx' ) ? 'xlsx' : 'csv';
		if ( ! $token || ! $type ) {
			$this->redirect_back( 'Sesión inválida.' );
		}
		$file = $this->get_temp_file_path( $token, $ext );
		if ( ! file_exists( $file ) ) {
			$this->redirect_back( 'Archivo no encontrado o expirado.' );
		}
		// Releer con el reader (respeta tipo forzado del POST).
		$forced = ( isset( $_POST['gloq_type'] ) && $_POST['gloq_type'] !== '' ) ? sanitize_key( $_POST['gloq_type'] ) : null;
		$read = Glotracol_Quote_Import_Reader::read( $file, $forced );
		if ( $read['error'] && empty( $read['rows'] ) ) { $this->redirect_back( $read['error'] ); }
		$type = $read['chosen_type'];
		$rows = $read['rows'];
		// Resolutor en-sesión: mapear fila(__line) → product_id elegido.
		$resolve = ( isset( $_POST['gloq_resolve'] ) && is_array( $_POST['gloq_resolve'] ) ) ? wp_unslash( $_POST['gloq_resolve'] ) : [];
		if ( ! empty( $resolve ) ) {
			foreach ( $rows as &$r ) {
				$line = (string) ( $r['__line'] ?? '' );
				if ( isset( $resolve[ $line ] ) && (int) $resolve[ $line ] > 0 ) {
					$r['id'] = (int) $resolve[ $line ]; // el importador escribirá por este ID
				}
			}
			unset( $r );
		}
		$opts = [];
		if ( $type === 'precios_catalogo' ) {
			$opts = [
				'mode'       => ( ( $_POST['gloq_mode'] ?? 'publico' ) === 'b2b' ) ? 'b2b' : 'publico',
				'client_id'  => (int) ( $_POST['gloq_client_id'] ?? 0 ),
				'sync_stock' => ! empty( $_POST['gloq_sync_stock'] ),
				'create_missing' => ! empty( $_POST['gloq_create_missing'] ),
			];
		}
		$report = Glotracol_Quote_Importer::import( $type, $rows, $opts );
		$report['type'] = $type;
		$report['imported_at'] = current_time( 'mysql' );
		set_transient( 'gloq_import_last_report', $report, HOUR_IN_SECONDS );

		if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
			$has_errors = ! empty( $report['errors'] );
			$level = $has_errors ? 'warn' : 'info';
			Glotracol_Quote_Logger::log( $level, 'import', sprintf(
				'Import %s: %d insertados, %d actualizados, %d omitidos, %d errores',
				$type, $report['inserted'] ?? 0, $report['updated'] ?? 0, $report['skipped'] ?? 0, count( $report['errors'] ?? [] )
			), [
				'type'      => $type,
				'inserted'  => $report['inserted'] ?? 0,
				'updated'   => $report['updated'] ?? 0,
				'skipped'   => $report['skipped'] ?? 0,
				'error_count' => count( $report['errors'] ?? [] ),
				'errors_sample' => array_slice( $report['errors'] ?? [], 0, 5 ),
			] );
		}

		// Limpiar archivo temporal
		@unlink( $file );

		wp_safe_redirect( add_query_arg( [
			'post_type' => 'glo_quote',
			'page'      => self::PAGE_SLUG,
			'step'      => 'report',
		], admin_url( 'edit.php' ) ) );
		exit;
	}

	private function redirect_back( $message ) {
		if ( class_exists( 'Glotracol_Quote_Logger' ) ) {
			Glotracol_Quote_Logger::warn( 'import', 'redirect_back: ' . $message );
		}
		wp_safe_redirect( add_query_arg( [
			'post_type'        => 'glo_quote',
			'page'             => self::PAGE_SLUG,
			'gloq_import_error' => rawurlencode( $message ),
		], admin_url( 'edit.php' ) ) );
		exit;
	}

	private function get_temp_dir() {
		$uploads = wp_upload_dir();
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) ) return null;
		$dir = $uploads['basedir'] . '/glotracol-import';
		if ( ! file_exists( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) return null;
			// Bloquear acceso directo
			file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
			file_put_contents( $dir . '/index.php', "<?php // Silence is golden\n" );
		}
		return $dir;
	}

	private function get_temp_file_path( $token, $ext = 'csv' ) {
		$dir = $this->get_temp_dir();
		if ( ! $dir ) return '';
		$safe = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $token ); // token sigue siendo hex puro
		$ext  = ( $ext === 'xlsx' ) ? 'xlsx' : 'csv';
		return $dir . '/' . $safe . '.' . $ext;
	}
}

// Cleanup hook
add_action( 'gloq_importer_cleanup', function ( $token, $ext = 'csv' ) {
	$uploads = wp_upload_dir();
	if ( empty( $uploads['basedir'] ) ) return;
	$safe = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $token );
	$ext  = ( $ext === 'xlsx' ) ? 'xlsx' : 'csv';
	$file = $uploads['basedir'] . '/glotracol-import/' . $safe . '.' . $ext;
	if ( file_exists( $file ) ) @unlink( $file );
}, 10, 2 );
