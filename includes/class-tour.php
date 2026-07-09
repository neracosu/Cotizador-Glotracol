<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tour guiado del panel. Define los pasos como datos, los encola y los pasa a JS.
 * El tour solo resalta/describe; nunca dispara acciones.
 */
class Glotracol_Quote_Tour {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/** Pantalla actual → clave de tour, o '' si no aplica. */
	private function current_tour() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( $page === 'glotracol-quote-dashboard' ) return 'inicio';
		if ( $page === 'glotracol-quote-pricing' )   return 'precios';
		if ( $page === 'glotracol-quote-import' )     return 'importar';
		return '';
	}

	public function enqueue() {
		$tour = $this->current_tour();
		if ( $tour === '' ) return;
		// Librería driver.js (bundleada local, MIT) + init propio que la alimenta con los pasos.
		wp_enqueue_style( 'glotracol-driver', GLOTRACOL_QUOTE_URL . 'assets/css/driver.css', [], '1.3.6' );
		wp_enqueue_script( 'glotracol-driver', GLOTRACOL_QUOTE_URL . 'assets/js/driver.js.iife.js', [], '1.3.6', true );
		wp_enqueue_script( 'glotracol-quote-tour', GLOTRACOL_QUOTE_URL . 'assets/js/tour.js', [ 'glotracol-driver' ], GLOTRACOL_QUOTE_VERSION, true );
		wp_localize_script( 'glotracol-quote-tour', 'GloqTour', [
			'label' => $tour === 'inicio' ? 'Primeros pasos' : 'Guía',
			'steps' => self::steps_for( $tour ),
		] );
	}

	/** Definición de los 3 tours (editable). */
	public static function tours() {
		return [
			'inicio' => [
				[ 'target' => '', 'title' => 'Bienvenido al cotizador', 'text' => 'Este panel gestiona precios, clientes e importaciones. Te muestro lo esencial en unos pasos.', 'pos' => 'auto' ],
				[ 'target' => '[data-tour="inicio-precios"]', 'title' => 'Precios', 'text' => 'Aquí ves y editas los precios por producto (Lista A regular y Lista B mayoreo).', 'pos' => 'bottom' ],
				[ 'target' => '[data-tour="inicio-importar"]', 'title' => 'Importar', 'text' => 'Cargá listas de precios y clientes desde Excel o CSV; el sistema reconoce las columnas.', 'pos' => 'bottom' ],
				[ 'target' => '[data-tour="inicio-clientes"]', 'title' => 'Clientes', 'text' => 'Gestioná los clientes y cuáles reciben la Lista B.', 'pos' => 'bottom' ],
			],
			'importar' => [
				[ 'target' => '[data-tour="import-tipo"]', 'title' => 'Elegí el tipo', 'text' => 'Seleccioná qué estás cargando. Al subir el archivo, el sistema también detecta el tipo solo.', 'pos' => 'bottom' ],
				[ 'target' => '[data-tour="import-plantilla"]', 'title' => 'Plantilla', 'text' => 'Si dudás del formato, descargá la plantilla en Excel: trae una hoja de instrucciones.', 'pos' => 'bottom' ],
				[ 'target' => '[data-tour="import-archivo"]', 'title' => 'Subí tu archivo', 'text' => 'Aceptamos Excel (.xlsx) y CSV aunque las columnas tengan otros nombres. Verás una vista previa antes de guardar.', 'pos' => 'top' ],
			],
			'precios' => [
				[ 'target' => '[data-tour="precios-a"]', 'title' => 'Precio A (regular)', 'text' => 'La lista de precios general que reciben los clientes sin tarifa especial.', 'pos' => 'bottom' ],
				[ 'target' => '[data-tour="precios-b"]', 'title' => 'Precio B (mayoreo)', 'text' => 'El precio para clientes de Lista B. Si un producto no tiene precio B, ese cliente usa el Precio A.', 'pos' => 'bottom' ],
				[ 'target' => '[data-tour="precios-guardar"]', 'title' => 'Guardá', 'text' => 'No olvides guardar los cambios al terminar de editar.', 'pos' => 'top' ],
			],
		];
	}

	public static function steps_for( $tour ) {
		$t = self::tours();
		return $t[ $tour ] ?? [];
	}
}
