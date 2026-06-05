<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Pantalla "Novedades": historial de versiones del plugin en lenguaje de negocio,
 * para que el equipo comercial vea qué se ha mejorado sin pedir reportes técnicos.
 *
 * Fuente única de verdad: self::entries(). La versión actual se deriva de la
 * primera entrada (la más reciente), así nunca queda desincronizada del listado.
 */
class Glotracol_Quote_Changelog_Admin {

	const PAGE_SLUG = 'glotracol-quote-changelog';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
	}

	public function add_menu() {
		$menu_title = 'Novedades';
		if ( self::has_unseen() ) {
			// Globo estilo WordPress: marca que hay una versión que el usuario no ha visto.
			$menu_title .= ' <span class="awaiting-mod"><span class="pending-count">1</span></span>';
		}
		add_submenu_page(
			'edit.php?post_type=glo_quote',
			'Novedades',
			$menu_title,
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	/** True si la versión actual no coincide con la última que vio este usuario. */
	private static function has_unseen() {
		$uid = get_current_user_id();
		if ( ! $uid ) return false;
		return (string) get_user_meta( $uid, 'glo_changelog_seen', true ) !== (string) self::current_version();
	}

	/** Versión actual = primera entrada del listado (imposible desincronizar). */
	public static function current_version() {
		$entries = self::entries();
		return isset( $entries[0]['version'] ) ? $entries[0]['version'] : GLOTRACOL_QUOTE_VERSION;
	}

	/** Metadatos por tipo de cambio: etiqueta y colores (fondo;texto;borde / punto). */
	public static function type_meta() {
		return [
			'feature'     => [ 'label' => 'Nueva función', 'bg' => '#dcfce7', 'fg' => '#166534', 'bd' => '#bbf7d0', 'dot' => '#22c55e' ],
			'improvement' => [ 'label' => 'Mejora',        'bg' => '#cffafe', 'fg' => '#155e75', 'bd' => '#a5f3fc', 'dot' => '#06b6d4' ],
			'fix'         => [ 'label' => 'Corrección',    'bg' => '#fef3c7', 'fg' => '#92400e', 'bd' => '#fde68a', 'dot' => '#f59e0b' ],
			'security'    => [ 'label' => 'Seguridad',     'bg' => '#ffe4e6', 'fg' => '#9f1239', 'bd' => '#fecdd3', 'dot' => '#f43f5e' ],
		];
	}

	/**
	 * FUENTE ÚNICA DE VERDAD del changelog. Entradas más recientes primero.
	 *
	 * Lenguaje de negocio (sin tecnicismos): pensar en cómo se lo explicarías a
	 * alguien del equipo comercial. Al poner en producción algo que el equipo nota,
	 * agregar una entrada ARRIBA con su versión nueva y la fecha (YYYY-MM-DD).
	 *
	 * Numeración: el primer dígito sube en rediseños grandes; el segundo, cuando entra
	 * una función nueva; el tercero, en mejoras, correcciones o parches de seguridad.
	 *
	 * tipo: 'feature' | 'improvement' | 'fix' | 'security'.
	 */
	public static function entries() {
		return [
			[
				'date' => '2026-06-04', 'version' => '2.3.0', 'type' => 'feature',
				'title' => 'Sección de Novedades y actualizaciones automáticas',
				'summary' => 'Esta misma sección, y ahora el plugin se actualiza solo cuando publicamos una versión nueva, sin instalar archivos a mano.',
				'details' => [
					'Nueva sección "Novedades" con el historial de versiones en lenguaje claro.',
					'WordPress avisa cuando hay una versión nueva y la instala con un clic.',
					'El número de versión del tablero ahora es un botón que abre esta sección.',
				],
			],
			[
				'date' => '2026-06-04', 'version' => '2.2.2', 'type' => 'improvement',
				'title' => 'Más avisos para evitar errores al cargar o borrar datos',
				'summary' => 'El sistema ahora te explica, antes de confirmar, qué va a cambiar cada acción importante, para que no haya sorpresas ni cargas equivocadas.',
				'details' => [
					'Al cargar un archivo de precios, la previsualización indica con claridad qué se va a actualizar o sobrescribir según el tipo de archivo.',
					'Al borrar todos los precios o vaciar el historial, te dice el número exacto de registros que se verán afectados antes de continuar.',
					'Pide confirmación antes de convertir una cotización en pedido (que le envía un correo al cliente) y antes de mandar un correo de prueba.',
					'Al cargar el catálogo, ahora puede crear los productos nuevos que vengan sin código; si el nombre ya existe, lo actualiza en vez de duplicarlo.',
				],
			],
			[
				'date' => '2026-06-03', 'version' => '2.2.1', 'type' => 'fix',
				'title' => 'Corrección en los conteos del panel de inicio',
				'summary' => 'El tablero de inicio volvió a contar correctamente cuántos productos tienen su precio cargado.',
				'details' => [],
			],
			[
				'date' => '2026-06-03', 'version' => '2.2.0', 'type' => 'feature',
				'title' => 'Carga de precios por código de producto',
				'summary' => 'Ahora los precios se cargan desde el archivo del catálogo usando el código de cada producto. El precio es interno: nunca aparece en la tienda, solo se usa para armar la cotización del cliente.',
				'details' => [
					'Nuevo importador del catálogo que acepta el archivo exportado de la tienda.',
					'Tarifas negociadas por cliente, también por código de producto.',
					'Opción de actualizar la disponibilidad (disponible/agotado) desde el mismo archivo.',
				],
			],
			[
				'date' => '2026-05-20', 'version' => '2.1.0', 'type' => 'feature',
				'title' => 'Carrito flotante, alertas por peso y conexión con GoHighLevel',
				'summary' => 'Varias mejoras pedidas por el equipo comercial para agilizar el día a día.',
				'details' => [
					'Una burbuja de "Mi cotización" siempre visible mientras el cliente navega.',
					'Clasificación automática del pedido por peso: pequeño, grande o por toneladas, para priorizar la atención.',
					'El plugin adopta los colores y la tipografía del sitio para verse integrado.',
					'Envío automático de cada cotización a GoHighLevel, y de nuevo al convertirla en pedido.',
				],
			],
			[
				'date' => '2026-05-05', 'version' => '2.0.3', 'type' => 'improvement',
				'title' => 'Panel más pulido y fácil de usar',
				'summary' => 'Se unificó el diseño de todas las pantallas del panel y se agregaron indicadores de carga cuando una acción está en proceso.',
				'details' => [],
			],
			[
				'date' => '2026-05-02', 'version' => '2.0.2', 'type' => 'security',
				'title' => 'Refuerzos de seguridad',
				'summary' => 'Correcciones de seguridad y de robustez, especialmente en la carga de archivos y el envío a integraciones externas.',
				'details' => [],
			],
			[
				'date' => '2026-04-30', 'version' => '2.0.1', 'type' => 'improvement',
				'title' => 'Historial de eventos para diagnóstico',
				'summary' => 'Se agregó un registro central de lo que hace el plugin (envíos de correo, cargas, integraciones) para resolver cualquier problema más rápido.',
				'details' => [],
			],
			[
				'date' => '2026-04-15', 'version' => '2.0.0', 'type' => 'feature',
				'title' => 'Lanzamiento del cotizador',
				'summary' => 'El catálogo pasa a pedir cotización en lugar de compra directa, con precios privados según el cliente.',
				'details' => [
					'El cliente arma su lista de productos y envía la solicitud; el equipo responde con precios.',
					'Clientes mayoristas identificados por NIT reciben sus precios negociados automáticamente.',
					'Si todos los productos ya tienen precio, el cliente recibe la cotización formal al instante.',
					'Pantalla de reportes con filtros y exportación para análisis comercial.',
				],
			],
		];
	}

	/* ---------------------------------------------------------------------- */

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// Marcar como vista la versión actual: limpia el globo del menú para este usuario.
		if ( $uid = get_current_user_id() ) {
			update_user_meta( $uid, 'glo_changelog_seen', self::current_version() );
		}

		$entries = self::entries();
		$current = self::current_version();
		$type_meta = self::type_meta();

		// Conteo por tipo para los chips de resumen.
		$counts = [];
		foreach ( $entries as $e ) {
			$t = $e['type'];
			$counts[ $t ] = ( $counts[ $t ] ?? 0 ) + 1;
		}

		$grouped = self::group_by_month( $entries );
		?>
		<div class="wrap gloq-dashboard gloq-changelog">
			<?php echo self::inline_styles(); ?>

			<div class="gloq-cl-head">
				<div class="gloq-cl-head-text">
					<h1>Novedades de la plataforma</h1>
					<p>Historial de las funciones nuevas, mejoras y correcciones del cotizador. Lo más reciente aparece primero.</p>
				</div>
				<div class="gloq-cl-version">
					<div class="gloq-cl-version-label">Versión actual</div>
					<div class="gloq-cl-version-num">v<?php echo esc_html( $current ); ?></div>
				</div>
			</div>

			<div class="gloq-cl-chips">
				<span class="gloq-cl-chip gloq-cl-chip-total">Total: <strong><?php echo count( $entries ); ?></strong></span>
				<?php foreach ( $type_meta as $key => $meta ) :
					if ( empty( $counts[ $key ] ) ) continue; ?>
					<span class="gloq-cl-chip" style="background:<?php echo esc_attr( $meta['bg'] ); ?>;color:<?php echo esc_attr( $meta['fg'] ); ?>;border-color:<?php echo esc_attr( $meta['bd'] ); ?>">
						<?php echo esc_html( $meta['label'] ); ?>: <strong><?php echo (int) $counts[ $key ]; ?></strong>
					</span>
				<?php endforeach; ?>
			</div>

			<details class="gloq-cl-help">
				<summary>¿Qué significa el número de versión?</summary>
				<div>
					<p>Usamos el formato <span class="mono">X.Y.Z</span>:</p>
					<ul>
						<li><strong class="mono">X</strong> — Cambios grandes o rediseños del producto.</li>
						<li><strong class="mono">Y</strong> — Entró una función nueva.</li>
						<li><strong class="mono">Z</strong> — Una mejora pequeña, una corrección o un parche de seguridad.</li>
					</ul>
					<p>Hoy estamos en <span class="mono" style="font-weight:700">v<?php echo esc_html( $current ); ?></span> después de <?php echo count( $entries ); ?> versiones desde el lanzamiento.</p>
				</div>
			</details>

			<?php foreach ( $grouped as $group ) : ?>
				<section class="gloq-cl-month">
					<h2 class="gloq-cl-month-label"><?php echo esc_html( $group['label'] ); ?></h2>
					<div class="gloq-cl-timeline">
						<?php foreach ( $group['entries'] as $entry ) :
							$meta = $type_meta[ $entry['type'] ] ?? $type_meta['improvement']; ?>
							<article class="gloq-cl-item">
								<span class="gloq-cl-dot" style="background:<?php echo esc_attr( $meta['dot'] ); ?>"></span>
								<div class="gloq-cl-item-head">
									<span class="gloq-cl-badge" style="background:<?php echo esc_attr( $meta['bg'] ); ?>;color:<?php echo esc_attr( $meta['fg'] ); ?>;border-color:<?php echo esc_attr( $meta['bd'] ); ?>"><?php echo esc_html( $meta['label'] ); ?></span>
									<time class="gloq-cl-date"><?php echo esc_html( self::format_date( $entry['date'] ) ); ?></time>
									<span class="gloq-cl-ver">v<?php echo esc_html( $entry['version'] ); ?></span>
								</div>
								<h3><?php echo esc_html( $entry['title'] ); ?></h3>
								<p class="gloq-cl-summary"><?php echo esc_html( $entry['summary'] ); ?></p>
								<?php if ( ! empty( $entry['details'] ) ) : ?>
									<ul class="gloq-cl-details">
										<?php foreach ( $entry['details'] as $d ) : ?>
											<li><?php echo esc_html( $d ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/** Agrupa las entradas por mes-año conservando el orden (más reciente primero). */
	private static function group_by_month( $entries ) {
		$groups = [];
		foreach ( $entries as $e ) {
			$ts  = strtotime( $e['date'] );
			$key = gmdate( 'Y-m', $ts );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = [ 'label' => self::month_label( $ts ), 'entries' => [] ];
			}
			$groups[ $key ]['entries'][] = $e;
		}
		return array_values( $groups );
	}

	private static $months = [
		1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
		7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
	];

	private static function month_label( $ts ) {
		return self::$months[ (int) gmdate( 'n', $ts ) ] . ' ' . gmdate( 'Y', $ts );
	}

	private static function format_date( $iso ) {
		$ts = strtotime( $iso );
		return (int) gmdate( 'j', $ts ) . ' de ' . self::$months[ (int) gmdate( 'n', $ts ) ] . ' de ' . gmdate( 'Y', $ts );
	}

	/** Estilos propios de la pantalla (a juego con el panel verde del plugin). */
	private static function inline_styles() {
		return '<style>
		.gloq-changelog{max-width:880px}
		.gloq-cl-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;margin:10px 0 18px}
		.gloq-cl-head-text h1{margin:0;font-size:26px;color:#1a202c}
		.gloq-cl-head-text p{margin:8px 0 0;color:#64748b;max-width:560px}
		.gloq-cl-version{background:#0a4d3a;color:#fff;border-radius:14px;padding:12px 18px;box-shadow:0 4px 14px rgba(10,77,58,0.2);text-align:center}
		.gloq-cl-version-label{font-size:10px;letter-spacing:0.18em;text-transform:uppercase;color:#8fe3bf;font-weight:700}
		.gloq-cl-version-num{font-size:26px;font-weight:700;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;margin-top:2px}
		.gloq-cl-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
		.gloq-cl-chip{font-size:12px;font-weight:600;padding:5px 12px;border-radius:999px;border:1px solid #e2e8f0;background:#f8fafc;color:#475569}
		.gloq-cl-chip-total{background:#0a4d3a;color:#fff;border-color:#0a4d3a}
		.gloq-cl-help{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;font-size:13px;color:#475569;margin-bottom:26px}
		.gloq-cl-help summary{cursor:pointer;font-weight:600;color:#1a202c}
		.gloq-cl-help ul{margin:8px 0 0 18px;list-style:disc}
		.gloq-cl-help .mono,.gloq-cl-ver,.gloq-cl-version-num{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
		.gloq-cl-month{margin-bottom:30px}
		.gloq-cl-month-label{font-size:12px;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:#94a3b8;margin:0 0 14px}
		.gloq-cl-timeline{position:relative;padding-left:26px}
		.gloq-cl-timeline:before{content:"";position:absolute;left:5px;top:6px;bottom:6px;width:2px;background:#e2e8f0}
		.gloq-cl-item{position:relative;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;margin-bottom:14px;box-shadow:0 1px 2px rgba(0,0,0,0.04)}
		.gloq-cl-dot{position:absolute;left:-26px;top:22px;width:12px;height:12px;border-radius:50%;box-shadow:0 0 0 4px #fff}
		.gloq-cl-item-head{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:8px}
		.gloq-cl-badge{font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;border:1px solid}
		.gloq-cl-date{font-size:12px;color:#94a3b8}
		.gloq-cl-ver{font-size:11px;font-weight:600;color:#64748b;background:#f1f5f9;padding:2px 8px;border-radius:999px}
		.gloq-cl-item h3{margin:0 0 6px;font-size:16px;color:#1a202c}
		.gloq-cl-summary{margin:0;color:#475569;line-height:1.55}
		.gloq-cl-details{margin:10px 0 0 0;padding:0;list-style:none}
		.gloq-cl-details li{position:relative;padding-left:18px;margin:6px 0;color:#475569;line-height:1.5;font-size:13px}
		.gloq-cl-details li:before{content:"";position:absolute;left:2px;top:8px;width:5px;height:5px;border-radius:50%;background:#cbd5e1}
		</style>';
	}
}
