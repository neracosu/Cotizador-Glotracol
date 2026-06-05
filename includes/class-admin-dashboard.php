<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Glotracol_Quote_Admin_Dashboard {

	const PAGE_SLUG = 'glotracol-quote-dashboard';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ], 9 );
	}

	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=glo_quote',
			'Inicio — Cotizador',
			'Inicio',
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);

		// Reordenar: poner "Inicio" como primer ítem del submenú
		global $submenu;
		$parent = 'edit.php?post_type=glo_quote';
		if ( isset( $submenu[ $parent ] ) ) {
			$dashboard_idx = null;
			foreach ( $submenu[ $parent ] as $idx => $item ) {
				if ( isset( $item[2] ) && $item[2] === self::PAGE_SLUG ) {
					$dashboard_idx = $idx;
					break;
				}
			}
			if ( $dashboard_idx !== null && $dashboard_idx !== 0 ) {
				$dashboard_item = $submenu[ $parent ][ $dashboard_idx ];
				unset( $submenu[ $parent ][ $dashboard_idx ] );
				array_unshift( $submenu[ $parent ], $dashboard_item );
				$submenu[ $parent ] = array_values( $submenu[ $parent ] );
			}
		}
	}

	public function render() {
		$stats = $this->get_stats();
		$config_checks = $this->config_checks();
		$compat_checks = $this->compatibility_checks();
		$compat_summary = $this->summarize_compat( $compat_checks );
		$pages = $this->get_page_status();
		$settings_url = admin_url( 'edit.php?post_type=glo_quote&page=' . Glotracol_Quote_Admin_Settings::PAGE_SLUG );
		$list_url     = admin_url( 'edit.php?post_type=glo_quote' );
		$form_url     = glotracol_quote_get_form_page_url();
		?>
		<?php
		$clients_url = admin_url( 'edit.php?post_type=glo_client' );
		$pricing_url = admin_url( 'edit.php?post_type=glo_quote&page=glotracol-quote-pricing' );
		$import_url  = admin_url( 'edit.php?post_type=glo_quote&page=glotracol-quote-import' );
		$reports_url = admin_url( 'edit.php?post_type=glo_quote&page=glotracol-quote-reports' );
		$logs_url    = admin_url( 'edit.php?post_type=glo_quote&page=glotracol-quote-logs' );
		$log_counts  = class_exists( 'Glotracol_Quote_Logger' ) ? Glotracol_Quote_Logger::counts() : [ 'total' => 0, 'error' => 0, 'warn' => 0 ];
		?>
		<div class="wrap gloq-dashboard">

			<div class="gloq-hero">
				<div class="gloq-hero-content">
					<a class="gloq-hero-badge gloq-hero-badge-link" href="<?php echo esc_url( admin_url( 'edit.php?post_type=glo_quote&page=' . Glotracol_Quote_Changelog_Admin::PAGE_SLUG ) ); ?>" title="Ver el historial de novedades y versiones">v<?php echo esc_html( GLOTRACOL_QUOTE_VERSION ); ?> · Novedades <span aria-hidden="true">&rarr;</span></a>
					<h1>Cotizador Glotracol</h1>
					<p>Sistema completo de cotizaciones y pedidos B2B con precios diferenciados por cliente, auto-respuesta cuando los precios están cargados, y conversión cotización a pedido.</p>
					<div class="gloq-hero-actions">
						<a href="<?php echo esc_url( $list_url ); ?>" class="button button-primary button-hero">Ver cotizaciones</a>
						<a href="<?php echo esc_url( $reports_url ); ?>" class="button button-hero">Reportes</a>
						<a href="<?php echo esc_url( $form_url ); ?>" target="_blank" class="button button-hero">Ver formulario público</a>
					</div>
				</div>
				<div class="gloq-hero-icon"><span class="dashicons dashicons-clipboard"></span></div>
			</div>

			<?php if ( $log_counts['error'] > 0 ) : ?>
				<div class="notice notice-error inline gloq-log-alert">
					<p><span class="dashicons dashicons-warning"></span> <strong>Hay <?php echo (int) $log_counts['error']; ?> errores recientes en el log</strong>. <a href="<?php echo esc_url( $logs_url ); ?>">Revisar log</a></p>
				</div>
			<?php endif; ?>

			<!-- Accesos rápidos a las pantallas v2.0 -->
			<div class="gloq-quick-grid">
				<a href="<?php echo esc_url( $clients_url ); ?>" class="gloq-quick-card">
					<div class="gloq-quick-icon"><span class="dashicons dashicons-groups"></span></div>
					<div class="gloq-quick-body">
						<strong>Clientes B2B</strong>
						<span><?php echo (int) $stats['client_count']; ?> en CRM</span>
					</div>
				</a>
				<a href="<?php echo esc_url( $pricing_url ); ?>" class="gloq-quick-card">
					<div class="gloq-quick-icon"><span class="dashicons dashicons-money-alt"></span></div>
					<div class="gloq-quick-body">
						<strong>Lista de precios</strong>
						<span><?php echo (int) $stats['public_skus']; ?> productos con precio</span>
					</div>
				</a>
				<a href="<?php echo esc_url( $import_url ); ?>" class="gloq-quick-card">
					<div class="gloq-quick-icon"><span class="dashicons dashicons-download"></span></div>
					<div class="gloq-quick-body">
						<strong>Importar CSV</strong>
						<span><?php echo (int) count( Glotracol_Quote_Importer::TYPES ); ?> tipos disponibles</span>
					</div>
				</a>
				<a href="<?php echo esc_url( $logs_url ); ?>" class="gloq-quick-card<?php echo $log_counts['error'] > 0 ? ' gloq-quick-alert' : ''; ?>">
					<div class="gloq-quick-icon"><span class="dashicons <?php echo $log_counts['error'] > 0 ? 'dashicons-warning' : 'dashicons-list-view'; ?>"></span></div>
					<div class="gloq-quick-body">
						<strong>Logs</strong>
						<span><?php echo (int) $log_counts['total']; ?> entradas<?php if ( $log_counts['error'] > 0 ) echo ' · ' . (int) $log_counts['error'] . ' errores'; ?></span>
					</div>
				</a>
			</div>

			<h2 class="gloq-section-title">Resumen del mes</h2>
			<div class="gloq-stats gloq-stats-month">
				<div class="gloq-stat gloq-stat-month-total">
					<div class="gloq-stat-value"><?php echo (int) $stats['month_count']; ?></div>
					<div class="gloq-stat-label">Cotizaciones + pedidos del mes</div>
				</div>
				<div class="gloq-stat gloq-stat-month-money">
					<div class="gloq-stat-value"><?php echo esc_html( glotracol_quote_format_price( $stats['month_total'] ) ); ?></div>
					<div class="gloq-stat-label">Monto cotizado del mes</div>
				</div>
				<div class="gloq-stat gloq-stat-month-orders">
					<div class="gloq-stat-value"><?php echo (int) $stats['month_orders']; ?></div>
					<div class="gloq-stat-label">Pedidos confirmados</div>
					<a class="gloq-stat-link" href="<?php echo esc_url( $reports_url ); ?>">Ver reportes</a>
				</div>
			</div>

			<h2 class="gloq-section-title">Por estado</h2>
			<div class="gloq-stats">
				<div class="gloq-stat gloq-stat-new">
					<div class="gloq-stat-value"><?php echo (int) $stats['new']; ?></div>
					<div class="gloq-stat-label">Nuevas <?php if ( $stats['new'] > 0 ) echo '<span class="gloq-pulse"></span>'; ?></div>
					<a class="gloq-stat-link" href="<?php echo esc_url( add_query_arg( 'post_status', 'glo-new', $list_url ) ); ?>">Ver</a>
				</div>
				<div class="gloq-stat gloq-stat-pending">
					<div class="gloq-stat-value"><?php echo (int) $stats['pending_prices']; ?></div>
					<div class="gloq-stat-label">Pendiente precios</div>
					<a class="gloq-stat-link" href="<?php echo esc_url( add_query_arg( 'post_status', 'glo-pending-prices', $list_url ) ); ?>">Ver</a>
				</div>
				<div class="gloq-stat gloq-stat-auto">
					<div class="gloq-stat-value"><?php echo (int) $stats['auto_priced']; ?></div>
					<div class="gloq-stat-label">Auto-cotizadas</div>
					<a class="gloq-stat-link" href="<?php echo esc_url( add_query_arg( 'post_status', 'glo-auto-priced', $list_url ) ); ?>">Ver</a>
				</div>
				<div class="gloq-stat gloq-stat-processing">
					<div class="gloq-stat-value"><?php echo (int) $stats['processing']; ?></div>
					<div class="gloq-stat-label">En proceso</div>
				</div>
				<div class="gloq-stat gloq-stat-responded">
					<div class="gloq-stat-value"><?php echo (int) $stats['responded']; ?></div>
					<div class="gloq-stat-label">Respondidas</div>
				</div>
				<div class="gloq-stat gloq-stat-closed">
					<div class="gloq-stat-value"><?php echo (int) $stats['closed']; ?></div>
					<div class="gloq-stat-label">Cerradas</div>
				</div>
			</div>

			<?php if ( $stats['total'] === 0 ) : ?>
				<div class="gloq-empty-state">
					<div class="gloq-empty-icon"><span class="dashicons dashicons-clipboard"></span></div>
					<p><strong>Aún no has recibido cotizaciones.</strong> Cuando un cliente envíe el formulario, aparecerá aquí en tiempo real. Mientras tanto, puedes ir cargando clientes B2B y precios para que las primeras cotizaciones lleguen ya con auto-respuesta lista.</p>
				</div>
			<?php else : ?>
				<h3 class="gloq-section-title">Últimas cotizaciones</h3>
				<table class="wp-list-table widefat striped gloq-recent">
					<thead><tr><th>ID</th><th>Tipo</th><th>Cliente</th><th>Items</th><th style="text-align:right">Total</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $stats['recent'] as $q ) :
						$status = get_post_status( $q->ID );
						$items = get_post_meta( $q->ID, '_glo_items', true );
						$icount = is_array( $items ) ? count( $items ) : 0;
						$type = get_post_meta( $q->ID, '_glo_type', true ) ?: 'quote';
						$total = (int) get_post_meta( $q->ID, '_glo_total', true );
					?>
						<tr>
							<td>#<?php echo (int) $q->ID; ?></td>
							<td><span class="glo-type glo-type-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( glotracol_quote_type_label( $type ) ); ?></span></td>
							<td><?php echo esc_html( get_post_meta( $q->ID, '_glo_customer_name', true ) ); ?><br><small style="color:#666"><?php echo esc_html( get_post_meta( $q->ID, '_glo_customer_company', true ) ?: '—' ); ?></small></td>
							<td><?php echo (int) $icount; ?> productos</td>
							<td style="text-align:right"><?php echo $total > 0 ? '<strong>' . esc_html( glotracol_quote_format_price( $total ) ) . '</strong>' : '<span style="color:#999">—</span>'; ?></td>
							<td><span class="glo-status glo-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( glotracol_quote_status_label( $status ) ); ?></span></td>
							<td><?php echo esc_html( get_the_date( 'd/m/Y H:i', $q ) ); ?></td>
							<td><a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $q->ID ) ); ?>">Ver</a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<section class="gloq-card gloq-compat-card gloq-compat-<?php echo esc_attr( $compat_summary['level'] ); ?>">
				<div class="gloq-compat-header">
					<h2><span class="dashicons dashicons-shield"></span> Estado de compatibilidad
						<span class="gloq-compat-badge gloq-compat-badge-<?php echo esc_attr( $compat_summary['level'] ); ?>">
							<?php echo esc_html( $compat_summary['headline'] ); ?>
						</span>
					</h2>
					<p class="gloq-compat-intro">Verificación automática de que el plugin sigue blindado contra actualizaciones de WooCommerce, el tema y otros plugins. Si algo aparece en amarillo o rojo, revisa el detalle — el plugin sigue funcionando pero conviene actuar.</p>
				</div>
				<ul class="gloq-checklist gloq-compat-list">
					<?php foreach ( $compat_checks as $check ) :
						$icon_class = 'gloq-check-' . $check['level']; // ok|warn|fail
						$icon = $check['level'] === 'ok' ? 'dashicons-yes-alt' : ( $check['level'] === 'warn' ? 'dashicons-warning' : 'dashicons-dismiss' );
					?>
						<li class="gloq-check <?php echo esc_attr( $icon_class ); ?>">
							<span class="gloq-check-icon"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span></span>
							<div class="gloq-check-body">
								<strong><?php echo esc_html( $check['title'] ); ?></strong>
								<p><?php echo wp_kses_post( $check['desc'] ); ?></p>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<div class="gloq-grid-2col">
				<section class="gloq-card">
					<h2><span class="dashicons dashicons-admin-generic"></span> Estado de configuración</h2>
					<ul class="gloq-checklist">
						<?php foreach ( $config_checks as $check ) : ?>
							<li class="gloq-check <?php echo $check['ok'] ? 'gloq-check-ok' : 'gloq-check-todo'; ?>">
								<span class="gloq-check-icon"><span class="dashicons <?php echo $check['ok'] ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span></span>
								<div class="gloq-check-body">
									<strong><?php echo esc_html( $check['title'] ); ?></strong>
									<p><?php echo wp_kses_post( $check['desc'] ); ?></p>
									<?php if ( ! empty( $check['action_url'] ) && ! $check['ok'] ) : ?>
										<a class="button button-small" href="<?php echo esc_url( $check['action_url'] ); ?>"><?php echo esc_html( $check['action_label'] ); ?></a>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>

				<section class="gloq-card">
					<h2><span class="dashicons dashicons-yes-alt"></span> Setup recomendado (en orden)</h2>
					<ol class="gloq-steps">
						<li>
							<strong>Configura emails y remitente</strong>
							<p>Ve a <a href="<?php echo esc_url( $settings_url ); ?>"><strong>Configuración › General</strong></a> y define a qué correo llegan las cotizaciones, el remitente y los textos de los emails. Si tienes Site Mailer activo, los emails ya salen autenticados.</p>
						</li>
						<li>
							<strong>Carga clientes B2B en el CRM</strong>
							<p>Vía <a href="<?php echo esc_url( $import_url ); ?>"><strong>Importar › "Clientes B2B"</strong></a> sube un CSV con NIT/Razón social/email/etc. de tus clientes. O créalos manualmente en <a href="<?php echo esc_url( $clients_url ); ?>"><strong>Clientes B2B › Añadir cliente</strong></a>.</p>
						</li>
						<li>
							<strong>Carga precios públicos (semanal)</strong>
							<p>Vía <a href="<?php echo esc_url( $import_url ); ?>"><strong>Importar › "Lista de precios públicos"</strong></a>. Estos precios se aplican a clientes <em>sin NIT identificado</em>. La actualización semanal es lo que mantiene la auto-respuesta funcionando.</p>
						</li>
						<li>
							<strong>Carga precios negociados B2B (opcional)</strong>
							<p>Si tienes acuerdos comerciales con clientes específicos, carga sus precios via <a href="<?php echo esc_url( $import_url ); ?>"><strong>Importar › "Precios negociados por cliente"</strong></a> con columnas <code>nit, sku, precio</code>. Se aplican automáticamente cuando ese NIT envíe una cotización.</p>
						</li>
						<li>
							<strong>Define presentaciones por producto (si aplica)</strong>
							<p>Si un producto se vende en varias presentaciones (250g, 500g, 1kg…), edítalo en <em>Productos › editar</em>, abre la pestaña <strong>"Presentaciones"</strong> y agrega cada variante con su SKU. El cliente final podrá elegir presentación al añadirlo y cambiarla en el carrito.</p>
						</li>
						<li>
							<strong>Configura reglas y auto-respuesta</strong>
							<p>En <a href="<?php echo esc_url( $settings_url . '&tab=rules' ); ?>"><strong>Configuración › Reglas</strong></a> ajusta los umbrales de tamaño (Mediana/Grande), el email destacado para pedidos grandes y activa la auto-respuesta con precios.</p>
						</li>
						<li>
							<strong>(Opcional) Conecta automatización por WhatsApp</strong>
							<p>Configura un webhook en <a href="<?php echo esc_url( $settings_url . '&tab=integrations' ); ?>"><strong>Configuración › Integraciones</strong></a> para que cada cotización dispare un mensaje automático vía Goja/Make/Zapier/n8n. El plugin firma cada POST con HMAC-SHA256.</p>
						</li>
						<li>
							<strong>Prueba end-to-end y revisa reportes</strong>
							<p>Desde una ventana de incógnito haz una cotización con un NIT registrado y otra con uno desconocido. Verifica que llegue el email correcto y que aparezcan en <a href="<?php echo esc_url( $reports_url ); ?>"><strong>Reportes</strong></a>. Filtra por mes y exporta a CSV cuando quieras analizar.</p>
						</li>
					</ol>
				</section>
			</div>

			<div class="gloq-grid-2col">
				<section class="gloq-card">
					<h2><span class="dashicons dashicons-randomize"></span> Cómo funciona para tus clientes</h2>
					<div class="gloq-flow">
						<div class="gloq-flow-step">
							<div class="gloq-flow-num">1</div>
							<div><strong>Explora el catálogo</strong><p>No ve precios. Cada producto muestra <em>"Añadir a la cotización"</em>. Si tiene presentaciones, ve <em>"Ver presentaciones"</em> y elige al entrar al producto.</p></div>
						</div>
						<div class="gloq-flow-step">
							<div class="gloq-flow-num">2</div>
							<div><strong>Arma su lista</strong><p>El carrito acepta múltiples presentaciones del mismo producto como items separados. Puede cambiar la presentación en el carrito sin tener que borrar y agregar.</p></div>
						</div>
						<div class="gloq-flow-step">
							<div class="gloq-flow-num">3</div>
							<div><strong>Llena sus datos en <code>/solicitar-cotizacion</code></strong><p>El <strong>NIT/Cédula</strong> es la clave: el sistema lo busca en el CRM B2B. Si coincide, aplica precios negociados; si no, usa precios públicos.</p></div>
						</div>
						<div class="gloq-flow-step">
							<div class="gloq-flow-num">4</div>
							<div><strong>Modal "¿Cotización o Pedido?"</strong><p>Antes de enviar, el cliente elige entre <strong>Cotización</strong> (necesita conocer precios para decidir) o <strong>Pedido</strong> (ya decidió, espera factura).</p></div>
						</div>
						<div class="gloq-flow-step">
							<div class="gloq-flow-num">5</div>
							<div><strong>Recibe respuesta automática (si hay precios)</strong><p>Si todos sus SKUs tienen precio cargado, recibe email con la <strong>cotización formal</strong> (precios + total + validez 7 días). Si falta uno, confirmación simple y tu equipo recibe alerta de "Pendiente de precios".</p></div>
						</div>
						<div class="gloq-flow-step">
							<div class="gloq-flow-num">6</div>
							<div><strong>Tu equipo da seguimiento</strong><p>Para cotizaciones pendientes, completas precios desde el panel y conviertes en pedido con un clic. Para auto-cotizadas, monitoreas y das soporte si el cliente responde.</p></div>
						</div>
					</div>
				</section>

				<section class="gloq-card">
					<h2><span class="dashicons dashicons-list-view"></span> Cómo gestionar las cotizaciones</h2>
					<ul class="gloq-bullets">
						<li><strong>Vista general:</strong> menú <em>Cotizaciones › Todas las cotizaciones</em>. Cada fila muestra <strong>Tipo</strong> (Cotización/Pedido), <strong>Tamaño</strong> (Pequeña/Mediana/Grande), <strong>Total</strong> y <strong>Estado</strong>.</li>
						<li><strong>Estados:</strong> <span class="glo-status glo-status-glo-new">Nueva</span> · <span class="glo-status glo-status-glo-pending-prices">Pendiente</span> · <span class="glo-status glo-status-glo-auto-priced">Auto-cotizada</span> · <span class="glo-status glo-status-glo-processing">En proceso</span> · <span class="glo-status glo-status-glo-responded">Respondida</span> · <span class="glo-status glo-status-glo-closed">Cerrada</span>.</li>
						<li><strong>Pendiente de precios:</strong> faltan precios. Carga el SKU faltante en <em>Precios</em> o usa "Convertir en pedido" para completar manualmente y notificar al cliente.</li>
						<li><strong>Convertir en pedido:</strong> abre la cotización › metabox lateral <em>"Tipo, precios y conversión"</em> › botón <strong>"Convertir en pedido"</strong>. Se abre modal con tabla de precios editable; al confirmar, el cliente recibe email "Confirmación de pedido".</li>
						<li><strong>Acciones rápidas:</strong> botones "Responder por email" y "Contactar por WhatsApp" en la parte superior de cada cotización.</li>
						<li><strong>Reportes:</strong> en <em><a href="<?php echo esc_url( $reports_url ); ?>">Reportes</a></em> puedes filtrar por mes/cliente/tipo y exportar todo a CSV (formato expandido para Excel).</li>
					</ul>
				</section>
			</div>

			<section class="gloq-card">
				<h2><span class="dashicons dashicons-editor-help"></span> Preguntas frecuentes</h2>
				<details class="gloq-faq">
					<summary>¿Cuándo recibe el cliente cotización con precios y cuándo no?</summary>
					<p>El cliente recibe cotización formal automáticamente <strong>solo si todos los SKUs de su solicitud tienen precio cargado</strong>. Si su NIT está en el CRM B2B, se usan precios negociados. Si no, se usan precios públicos. Si falta precio para algún SKU, el cliente recibe solo confirmación de recepción y la cotización entra como <em>"Pendiente de precios"</em> para que tu equipo la complete a mano.</p>
				</details>
				<details class="gloq-faq">
					<summary>¿Cómo cargo precios? ¿Manual o por CSV?</summary>
					<p>Lo recomendado es <strong>CSV semanal</strong>: ve a <a href="<?php echo esc_url( $import_url ); ?>">Importar › "Lista de precios públicos"</a>, descarga la plantilla, edítala en Excel y súbela. La importación es aditiva (actualiza/inserta sin borrar). Para edición puntual de un SKU, usa <a href="<?php echo esc_url( $pricing_url ); ?>">Precios</a>: tabla con búsqueda y edición inline.</p>
				</details>
				<details class="gloq-faq">
					<summary>¿Cómo funciona la diferencia "Cotización" vs "Pedido"?</summary>
					<p>Antes de enviar el formulario, el cliente ve un modal con dos opciones: <strong>Cotización</strong> (exploratorio, quiere precios) o <strong>Pedido</strong> (firme, quiere comprar). En el panel admin las cotizaciones aparecen con badge azul ("Cotización") o naranja ("Pedido"). Tu equipo puede convertir una cotización en pedido en cualquier momento con el botón <em>"Convertir en pedido"</em>.</p>
				</details>
				<details class="gloq-faq">
					<summary>¿Qué pasa con la pestaña "Información adicional" del producto?</summary>
					<p>El plugin la renombró a <strong>"Presentación"</strong> automáticamente. Ahí puedes definir las variantes de empaque (250g, 500g, 1kg) editando el producto en WordPress y abriendo la pestaña <em>Presentaciones</em> en la zona de datos del producto.</p>
				</details>
				<details class="gloq-faq">
					<summary>¿Cómo importo datos masivamente?</summary>
					<p>Vía <a href="<?php echo esc_url( $import_url ); ?>">Importar</a> hay 4 tipos de hojas CSV con plantilla descargable: <strong>Clientes B2B</strong>, <strong>Lista de precios públicos</strong>, <strong>Precios negociados por cliente</strong>, <strong>Presentaciones por producto</strong>. Cada importación tiene preview de 20 filas antes de confirmar y reporte detallado al final con #insertados, #actualizados, #omitidos, #errores.</p>
				</details>
				<details class="gloq-faq">
					<summary>¿Cómo evito que me llenen de spam el formulario?</summary>
					<p>El plugin trae 3 capas: <strong>nonce</strong> (token único por sesión), <strong>honeypot</strong> (campo oculto que solo bots llenan) y <strong>rate-limit por IP</strong> (3 envíos por hora por defecto, configurable en <em>Avanzado</em>).</p>
				</details>
				<details class="gloq-faq">
					<summary>¿Qué pasa si un cliente intenta ir directo al checkout (/finalizar-compra)?</summary>
					<p>El plugin lo detecta y redirige automáticamente a <code>/solicitar-cotizacion</code> con código 302. No hay forma de pagar, así que no hay riesgo de cobrar a nadie por error.</p>
				</details>
				<details class="gloq-faq">
					<summary>¿Puedo conectar WhatsApp automático?</summary>
					<p>Sí, vía webhook. Configura la URL de tu automatización (Make, Zapier, n8n, Goja) en <em>Configuración › Integraciones</em> y cada cotización dispara un POST con todos los datos en JSON (incluyendo tipo, total y client_id). El header <code>X-Glotracol-Signature</code> permite verificar autenticidad.</p>
				</details>
				<details class="gloq-faq">
					<summary>¿Cómo veo reportes y exporto a Excel?</summary>
					<p>En <a href="<?php echo esc_url( $reports_url ); ?>">Reportes</a> filtras por mes/tipo/estado/cliente y ves stats panel con monto cotizado, conversión y top 5 clientes/SKUs. Botón <strong>"Exportar CSV"</strong> descarga formato expandido (1 fila por producto solicitado) listo para abrir en Excel — incluye BOM UTF-8 para tildes y la ñ.</p>
				</details>
				<details class="gloq-faq">
					<summary>¿Puedo cambiar las páginas de formulario y gracias?</summary>
					<p>Sí. Las páginas <code>/solicitar-cotizacion</code> y <code>/cotizacion-enviada</code> se crearon automáticamente al activar el plugin. Puedes editarlas con Elementor como cualquier página: solo conserva los shortcodes <code>[glotracol_quote_form]</code> y <code>[glotracol_quote_thanks]</code> en su contenido.</p>
				</details>
				<details class="gloq-faq">
					<summary>¿El plugin almacena datos sensibles?</summary>
					<p>Guarda los datos del cliente (nombre, email, teléfono, NIT, etc.) y la lista de productos en la base de datos como Custom Post Types (<code>glo_quote</code> y <code>glo_client</code>). Los precios públicos viven en <code>wp_options</code>; los precios B2B en meta de cada cliente. En <em>Configuración › Avanzado</em> puedes activar <strong>"Borrar datos al desinstalar"</strong> para eliminar todo si retiras el plugin.</p>
				</details>
				<details class="gloq-faq">
					<summary>¿Qué pasa si WooCommerce o el tema se actualizan?</summary>
					<p>El plugin tiene blindaje en triple capa para los renombres del carrito y verificación automática de compatibilidad arriba. Si una actualización rompe algo crítico, el panel "Estado de compatibilidad" lo detectará y mostrará alerta amarilla o roja, pero el plugin no se romperá completamente — degrada graceful.</p>
				</details>
			</section>

			<div class="gloq-footer">
				<p>Plugin desarrollado por <a href="https://neracosu.com/" target="_blank" rel="noopener">Neracosu</a> para <a href="https://www.eagencia.co/" target="_blank" rel="noopener">eagencia</a> · v<?php echo esc_html( GLOTRACOL_QUOTE_VERSION ); ?></p>
			</div>
		</div>
		<?php
	}

	private function get_stats() {
		$counts = wp_count_posts( 'glo_quote' );
		$total = 0;
		foreach ( (array) $counts as $k => $v ) {
			if ( strpos( $k, 'glo-' ) === 0 ) $total += (int) $v;
		}
		$recent = get_posts( [
			'post_type'   => 'glo_quote',
			'post_status' => [ 'glo-new', 'glo-pending-prices', 'glo-auto-priced', 'glo-processing', 'glo-responded', 'glo-closed' ],
			'numberposts' => 5,
			'orderby'     => 'date',
			'order'       => 'DESC',
		] );

		// Monto cotizado del mes en curso
		$month_start = date( 'Y-m-01 00:00:00' );
		$month_quotes = get_posts( [
			'post_type'   => 'glo_quote',
			'post_status' => [ 'glo-new', 'glo-pending-prices', 'glo-auto-priced', 'glo-processing', 'glo-responded', 'glo-closed' ],
			'numberposts' => -1,
			'fields'      => 'ids',
			'date_query'  => [ [ 'after' => $month_start, 'inclusive' => true ] ],
		] );
		if ( ! empty( $month_quotes ) ) _prime_post_caches( $month_quotes, false, true );
		$month_total = 0;
		$month_orders = 0;
		foreach ( (array) $month_quotes as $id ) {
			$month_total += (int) get_post_meta( $id, '_glo_total', true );
			if ( get_post_meta( $id, '_glo_type', true ) === 'order' ) $month_orders++;
		}

		// Conteos de B2B / pricing público
		$client_count = (int) wp_count_posts( 'glo_client' )->publish;
		$public_pricing_count = glotracol_quote_count_products_with_price();

		return [
			'total'           => $total,
			'new'             => (int) ( $counts->{'glo-new'} ?? 0 ),
			'pending_prices'  => (int) ( $counts->{'glo-pending-prices'} ?? 0 ),
			'auto_priced'     => (int) ( $counts->{'glo-auto-priced'} ?? 0 ),
			'processing'      => (int) ( $counts->{'glo-processing'} ?? 0 ),
			'responded'       => (int) ( $counts->{'glo-responded'} ?? 0 ),
			'closed'          => (int) ( $counts->{'glo-closed'} ?? 0 ),
			'recent'          => $recent,
			'month_count'     => count( $month_quotes ),
			'month_total'     => $month_total,
			'month_orders'    => $month_orders,
			'client_count'    => $client_count,
			'public_skus'     => $public_pricing_count,
		];
	}

	private function get_page_status() {
		return [
			'form'   => (int) get_option( 'glotracol_quote_form_page_id' ),
			'thanks' => (int) get_option( 'glotracol_quote_thanks_page_id' ),
		];
	}

	private function config_checks() {
		$s = glotracol_quote_get_settings();
		$settings_url = admin_url( 'edit.php?post_type=glo_quote&page=' . Glotracol_Quote_Admin_Settings::PAGE_SLUG );
		$pages = $this->get_page_status();

		$emails = glotracol_quote_emails_to_array( $s['destination_emails'] );
		$has_real_email = ! empty( $emails ) && $emails[0] !== get_option( 'admin_email' );

		$checks = [];

		$checks[] = [
			'ok'    => ! empty( $emails ),
			'title' => 'Email destino interno configurado',
			'desc'  => empty( $emails )
				? 'Aún no hay correo destino. Sin esto, las cotizaciones se irán al admin del sitio.'
				: 'Cotizaciones llegando a: <code>' . esc_html( implode( ', ', $emails ) ) . '</code>' . ( ! $has_real_email ? ' <em>(es el admin del sitio — considera cambiarlo a un correo del equipo comercial)</em>' : '' ),
			'action_url'   => $settings_url,
			'action_label' => 'Configurar email',
		];

		$checks[] = [
			'ok'    => $pages['form'] > 0 && get_post( $pages['form'] ),
			'title' => 'Página de formulario creada',
			'desc'  => $pages['form'] > 0
				? '<a href="' . esc_url( get_permalink( $pages['form'] ) ) . '" target="_blank">' . esc_html( get_permalink( $pages['form'] ) ) . '</a>'
				: 'No se encontró la página. Reactiva el plugin para recrearla.',
			'action_url'   => '',
			'action_label' => '',
		];

		$checks[] = [
			'ok'    => $pages['thanks'] > 0 && get_post( $pages['thanks'] ),
			'title' => 'Página de confirmación creada',
			'desc'  => $pages['thanks'] > 0
				? '<a href="' . esc_url( get_permalink( $pages['thanks'] ) ) . '" target="_blank">' . esc_html( get_permalink( $pages['thanks'] ) ) . '</a>'
				: 'No se encontró la página. Reactiva el plugin para recrearla.',
			'action_url'   => '',
			'action_label' => '',
		];

		$checks[] = [
			'ok'    => is_email( $s['sender_email'] ),
			'title' => 'Remitente configurado',
			'desc'  => is_email( $s['sender_email'] )
				? '<code>' . esc_html( $s['sender_name'] ) . ' &lt;' . esc_html( $s['sender_email'] ) . '&gt;</code>' . ( strpos( $s['sender_email'], '@glotracol.com' ) === false ? ' <em>(considera usar un email del dominio glotracol.com para mejor entregabilidad)</em>' : '' )
				: 'Email de remitente inválido.',
			'action_url'   => $settings_url,
			'action_label' => 'Configurar remitente',
		];

		$checks[] = [
			'ok'    => ! empty( trim( $s['webhook_url'] ) ),
			'title' => 'Webhook (opcional)',
			'desc'  => ! empty( trim( $s['webhook_url'] ) )
				? 'Conectado a una automatización externa. Cada cotización dispara un POST.'
				: 'Sin webhook configurado. Es opcional — actívalo cuando quieras integrar con Goja/Make/Zapier para mensajes automáticos de WhatsApp.',
			'action_url'   => $settings_url . '&tab=integrations',
			'action_label' => 'Configurar webhook',
		];

		$external_smtp = Glotracol_Quote_SMTP::detect_external_smtp();
		$internal_smtp = ( $s['smtp_enabled'] ?? '' ) === 'yes' && ! empty( $s['smtp_host'] );
		$checks[] = [
			'ok'    => $external_smtp || $internal_smtp,
			'title' => 'SMTP configurado',
			'desc'  => $internal_smtp
				? 'Usando <strong>SMTP propio</strong> del plugin (host: <code>' . esc_html( $s['smtp_host'] ) . '</code>).'
				: ( $external_smtp
					? 'Usando <strong>' . esc_html( $external_smtp ) . '</strong> (plugin externo).'
					: 'Ningún plugin SMTP detectado — los emails se envían vía <code>mail()</code> y podrían ir a SPAM. Configura SMTP en la pestaña correspondiente.' ),
			'action_url'   => $settings_url . '&tab=smtp',
			'action_label' => 'Configurar SMTP',
		];

		// v2.0: Clientes B2B
		$client_count = wp_count_posts( 'glo_client' )->publish ?? 0;
		$checks[] = [
			'ok'    => (int) $client_count > 0,
			'title' => 'Clientes B2B en CRM',
			'desc'  => (int) $client_count > 0
				? '<strong>' . (int) $client_count . '</strong> clientes B2B cargados. Sus NITs serán reconocidos en el formulario de cotización para aplicar precios negociados.'
				: 'Aún no hay clientes B2B cargados. Importa la lista vía CSV o agrégalos manualmente para que el sistema los identifique por NIT.',
			'action_url'   => admin_url( 'edit.php?post_type=glo_client' ),
			'action_label' => 'Gestionar clientes',
		];

		// v2.0: Precios públicos
		$public_skus = glotracol_quote_count_products_with_price();
		$checks[] = [
			'ok'    => $public_skus > 0,
			'title' => 'Lista de precios públicos cargada',
			'desc'  => $public_skus > 0
				? '<strong>' . (int) $public_skus . '</strong> productos con precio público. Recuerda actualizarla vía el importador CSV (Precios del catálogo por ID).'
				: 'Sin precios públicos cargados. Sin estos precios, los clientes <em>sin NIT B2B</em> no recibirán cotización automática y todas sus solicitudes quedarán como "Pendiente de precios".',
			'action_url'   => admin_url( 'edit.php?post_type=glo_quote&page=glotracol-quote-pricing' ),
			'action_label' => 'Cargar precios',
		];

		// v2.0: Auto-respuesta
		$auto_respond = ( $s['auto_respond_enabled'] ?? 'yes' ) === 'yes';
		$checks[] = [
			'ok'    => $auto_respond,
			'title' => 'Auto-respuesta con precios activada',
			'desc'  => $auto_respond
				? 'Cuando una cotización tiene precios para todos los SKUs, el cliente recibe automáticamente el email con la cotización formal.'
				: 'La auto-respuesta está <strong>desactivada</strong>. Los clientes nunca reciben precios automáticos; toda respuesta requiere acción manual del equipo.',
			'action_url'   => $settings_url . '&tab=rules',
			'action_label' => 'Activar auto-respuesta',
		];

		return $checks;
	}

	/**
	 * Verificación de blindaje (resilience) ante updates de WC, tema, plugins.
	 *
	 * Cada entrada tiene `level` entre {ok, warn, fail}. El plugin no aborta si
	 * alguno falla — solo informa al admin.
	 */
	private function compatibility_checks() {
		$checks = [];

		// 1) WooCommerce versión
		$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : '';
		$wc_min     = '8.0.0';
		$checks[] = [
			'level' => $wc_version && version_compare( $wc_version, $wc_min, '>=' ) ? 'ok' : ( $wc_version ? 'warn' : 'fail' ),
			'title' => 'WooCommerce activo y soportado',
			'desc'  => $wc_version
				? sprintf( 'Detectado WooCommerce <code>%s</code>. Mínimo recomendado: <code>%s</code>.', esc_html( $wc_version ), esc_html( $wc_min ) )
				: 'No se detectó la constante <code>WC_VERSION</code>. Verifica que WooCommerce esté activo.',
		];

		// 2) Hooks críticos disponibles
		$critical_hooks = [
			'woocommerce_proceed_to_checkout',
			'woocommerce_get_price_html',
			'wc_add_to_cart_message_html',
			'woocommerce_add_to_cart',
		];
		$missing = [];
		foreach ( $critical_hooks as $h ) {
			// has_filter devuelve falso solo si NADIE ha enganchado nunca; útil indicador
			// pero no definitivo. Como red de seguridad, validamos que el hook al menos
			// haya sido invocado o tenga alguna acción.
			if ( ! has_action( $h ) && ! has_filter( $h ) ) {
				$missing[] = $h;
			}
		}
		$checks[] = [
			'level' => empty( $missing ) ? 'ok' : 'warn',
			'title' => 'Hooks críticos de WooCommerce',
			'desc'  => empty( $missing )
				? 'Todos los hooks que usa el plugin (' . count( $critical_hooks ) . ') están disponibles.'
				: 'Estos hooks no parecen estar registrados: <code>' . esc_html( implode( ', ', $missing ) ) . '</code>. Si alguien los removió, parte del plugin podría no funcionar.',
		];

		// 3) WC()->cart accesible
		$cart_ok = function_exists( 'WC' ) && WC() && WC()->cart instanceof WC_Cart;
		$checks[] = [
			'level' => $cart_ok ? 'ok' : 'warn',
			'title' => 'Cart de WooCommerce accesible',
			'desc'  => $cart_ok
				? 'La instancia <code>WC()->cart</code> está disponible en este request.'
				: '<code>WC()->cart</code> no está inicializado en admin (esperable). Se valida en frontend al usar el cotizador.',
		];

		// 4) Path interno wc-cart-functions.php
		$wc_path = defined( 'WC_ABSPATH' ) ? WC_ABSPATH : '';
		$cart_funcs = $wc_path ? $wc_path . 'includes/wc-cart-functions.php' : '';
		$cart_funcs_ok = $cart_funcs && file_exists( $cart_funcs );
		$checks[] = [
			'level' => $cart_funcs_ok ? 'ok' : ( $wc_path ? 'fail' : 'warn' ),
			'title' => 'Paths internos de WooCommerce',
			'desc'  => $cart_funcs_ok
				? 'El archivo <code>wc-cart-functions.php</code> está donde lo esperamos. La carga manual del cart en admin-post/AJAX funcionará.'
				: ( $wc_path
					? 'No encontramos <code>wc-cart-functions.php</code> en <code>' . esc_html( $wc_path ) . '</code>. Si WC reorganizó archivos, el submit del formulario podría fallar — revisa logs.'
					: 'No se detectó <code>WC_ABSPATH</code>. Usaremos fallback a <code>wp-content/plugins/woocommerce/</code> pero conviene verificar.' ),
		];

		// 5) Filtro gettext interceptando "Cart" › "Mi cotización" (capa 1)
		$gettext_ok = method_exists( 'Glotracol_Quote_Cart_Overrides', 'self_test_gettext' )
			? Glotracol_Quote_Cart_Overrides::self_test_gettext()
			: false;
		$checks[] = [
			'level' => $gettext_ok ? 'ok' : 'warn',
			'title' => 'Renombrado de carrito (capa 1: gettext)',
			'desc'  => $gettext_ok
				? 'Confirmado: la string "Cart" se traduce a "Mi cotización" vía filtro <code>gettext</code>.'
				: 'El filtro <code>gettext</code> no intercepta "Cart" en este momento. Las capas 2 (JS) y 3 (CSS) siguen activas, pero conviene revisar si otro plugin está pisando el filtro.',
		];

		// 6) JS de rename (capa 2): registered/enqueueable
		$js_registered = wp_script_is( 'glotracol-cart-rename', 'registered' );
		$checks[] = [
			'level' => $js_registered ? 'ok' : 'warn',
			'title' => 'Renombrado de carrito (capa 2: JS DOM)',
			'desc'  => $js_registered
				? 'El script <code>cart-rename.js</code> está registrado y se enqueua en frontend.'
				: 'No detectamos <code>cart-rename.js</code> registrado. Verifica que <code>class-plugin.php</code> haya cargado correctamente.',
		];

		// 7) Carga manual de WC: ¿hubo fallos recientes?
		$wc_load_failure = get_transient( 'glotracol_quote_wc_load_failure' );
		if ( $wc_load_failure && is_array( $wc_load_failure ) ) {
			$checks[] = [
				'level' => 'fail',
				'title' => 'Carga manual de WooCommerce falló recientemente',
				'desc'  => 'Última falla: <code>' . esc_html( $wc_load_failure['reason'] ?? '' ) . '</code> (WC ' . esc_html( $wc_load_failure['wc_version'] ?? '' ) . ' · ' . esc_html( $wc_load_failure['timestamp'] ?? '' ) . '). Esto suele indicar que un update de WC reorganizó archivos. Revisa los logs y avisa al desarrollador.',
			];
		} else {
			$checks[] = [
				'level' => 'ok',
				'title' => 'Submit del formulario operativo',
				'desc'  => 'No se han registrado fallos recientes de carga de WooCommerce en admin-post/AJAX (últimas 24h).',
			];
		}

		// 8) Plugins en conflicto / redundantes
		$conflicts = [];
		if ( function_exists( 'is_plugin_active' ) ) {
			// YITH Catalog Mode redundante con nuestro plugin
			if ( is_plugin_active( 'yith-woocommerce-catalog-mode/init.php' ) || class_exists( 'YITH_WC_Catalog_Mode' ) ) {
				$conflicts[] = '<strong>YITH Catalog Mode</strong>: oculta precios y deshabilita add-to-cart. Nuestro plugin ya hace eso de forma controlada — tener ambos puede causar comportamientos inesperados. Considera desactivarlo.';
			}
		}
		$checks[] = [
			'level' => empty( $conflicts ) ? 'ok' : 'warn',
			'title' => 'Plugins potencialmente redundantes',
			'desc'  => empty( $conflicts )
				? 'No detectamos plugins redundantes con el cotizador.'
				: implode( '<br>', $conflicts ),
		];

		return $checks;
	}

	/**
	 * Resume el array de compatibility_checks en un nivel global y un titular.
	 *
	 * @param array $checks
	 * @return array{ level: string, headline: string }
	 */
	private function summarize_compat( $checks ) {
		$has_fail = false;
		$has_warn = false;
		foreach ( $checks as $c ) {
			if ( $c['level'] === 'fail' ) $has_fail = true;
			elseif ( $c['level'] === 'warn' ) $has_warn = true;
		}
		if ( $has_fail ) return [ 'level' => 'fail', 'headline' => 'Atención requerida' ];
		if ( $has_warn ) return [ 'level' => 'warn', 'headline' => 'Revisa los avisos' ];
		return [ 'level' => 'ok', 'headline' => 'Todo en orden' ];
	}
}
