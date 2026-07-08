<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Importador CSV de datos del cotizador.
 *
 * 4 tipos de hojas soportadas:
 *  - clientes          : NIT/razón social/email/etc → CPT glo_client
 *  - precios_publicos  : SKU/precio → option public_pricing
 *  - precios_b2b       : NIT/SKU/precio → meta _glo_client_pricing del cliente
 *  - presentaciones    : SKU producto/presentaciones → meta _glo_presentaciones (preparación Fase C)
 *
 * Cada importer devuelve un reporte:
 *   { inserted: int, updated: int, skipped: int, errors: array<string> }
 */
class Glotracol_Quote_Importer {

	const TYPES = [
		'clientes'          => 'Clientes B2B',
		'precios_publicos'  => 'Lista de precios públicos',
		'precios_b2b'       => 'Precios negociados por cliente',
		'presentaciones'    => 'Presentaciones por producto',
		'precios_catalogo'  => 'Precios del catálogo (por ID)',
		'clientes_lista'    => 'Asignar clientes a Lista B',
	];

	/**
	 * Definición de columnas esperadas por tipo. La primera fila del CSV debe
	 * tener estos headers (orden flexible). Las columnas extra se ignoran.
	 *
	 * @return array<string, array{ required: array<string>, optional: array<string>, plantilla: array<string> }>
	 */
	public static function get_schemas() {
		return [
			'clientes' => [
				'required'  => [ 'nit', 'razon_social' ],
				'optional'  => [ 'email', 'telefono', 'contacto', 'ciudad', 'activo', 'notas' ],
				'plantilla' => [ 'nit', 'razon_social', 'email', 'telefono', 'contacto', 'ciudad', 'activo', 'notas' ],
			],
			'precios_publicos' => [
				'required'  => [ 'sku', 'precio' ],
				'optional'  => [],
				'plantilla' => [ 'sku', 'precio' ],
			],
			'precios_b2b' => [
				'required'  => [ 'nit', 'sku', 'precio' ],
				'optional'  => [],
				'plantilla' => [ 'nit', 'sku', 'precio' ],
			],
			'presentaciones' => [
				'required'  => [ 'sku_producto', 'label' ],
				'optional'  => [ 'sku_variante', 'peso_g', 'precio_publico' ],
				'plantilla' => [ 'sku_producto', 'label', 'sku_variante', 'peso_g', 'precio_publico' ],
			],
			'precios_catalogo' => [
				'required'  => [ 'id', 'precio normal' ],
				'optional'  => [ 'nombre', 'peso (kg)', 'disponibilidad', 'inventario', 'precio' ],
				'plantilla' => [ 'id', 'nombre', 'peso (kg)', 'precio normal', 'disponibilidad' ],
			],
			'clientes_lista' => [
				'required'  => [],
				'optional'  => [ 'nit', 'identificacion', 'nombre', 'lista', 'precio' ],
				'plantilla' => [ 'nit', 'nombre', 'lista' ],
			],
		];
	}

	/**
	 * Lee y parsea un CSV en arrays asociativos { header: value }.
	 *
	 * @param string $file_path  Ruta absoluta al archivo CSV.
	 * @param string $type       Tipo de hoja para validar headers.
	 * @return array{ headers: array<string>, rows: array<array<string,string>>, error: string|null }
	 */
	public static function parse_csv( $file_path, $type ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return [ 'headers' => [], 'rows' => [], 'error' => 'No se pudo leer el archivo.' ];
		}
		$schemas = self::get_schemas();
		if ( ! isset( $schemas[ $type ] ) ) {
			return [ 'headers' => [], 'rows' => [], 'error' => 'Tipo de hoja desconocido: ' . $type ];
		}
		$schema = $schemas[ $type ];

		$fh = fopen( $file_path, 'r' );
		if ( ! $fh ) {
			return [ 'headers' => [], 'rows' => [], 'error' => 'No se pudo abrir el archivo.' ];
		}

		// Detectar BOM UTF-8 y saltarlo
		$bom = fread( $fh, 3 );
		if ( $bom !== "\xEF\xBB\xBF" ) {
			rewind( $fh );
		}

		// Detectar separador: probar coma, punto y coma, tab
		$first_line = fgets( $fh );
		if ( $first_line === false ) {
			fclose( $fh );
			return [ 'headers' => [], 'rows' => [], 'error' => 'Archivo vacío.' ];
		}
		$delimiter = ',';
		$counts = [ ',' => substr_count( $first_line, ',' ), ';' => substr_count( $first_line, ';' ), "\t" => substr_count( $first_line, "\t" ) ];
		arsort( $counts );
		$delimiter = (string) array_key_first( $counts );

		// Volver al inicio
		rewind( $fh );
		if ( $bom === "\xEF\xBB\xBF" ) fread( $fh, 3 );

		// Headers
		$headers_raw = fgetcsv( $fh, 0, $delimiter );
		if ( ! is_array( $headers_raw ) ) {
			fclose( $fh );
			return [ 'headers' => [], 'rows' => [], 'error' => 'Headers inválidos.' ];
		}
		$headers = array_map( function ( $h ) {
			return strtolower( trim( (string) $h ) );
		}, $headers_raw );

		// Validar headers requeridos
		$missing = array_diff( $schema['required'], $headers );
		if ( ! empty( $missing ) ) {
			fclose( $fh );
			return [ 'headers' => $headers, 'rows' => [], 'error' => 'Faltan columnas requeridas: ' . implode( ', ', $missing ) . '. Headers detectados: ' . implode( ', ', $headers ) ];
		}

		// Leer filas
		$rows = [];
		$line = 1;
		while ( ( $data = fgetcsv( $fh, 0, $delimiter ) ) !== false ) {
			$line++;
			if ( count( $data ) === 1 && $data[0] === null ) continue; // línea vacía
			$row = [];
			foreach ( $headers as $i => $h ) {
				$row[ $h ] = isset( $data[ $i ] ) ? trim( (string) $data[ $i ] ) : '';
			}
			// Saltar filas completamente vacías
			if ( empty( array_filter( $row, function ( $v ) { return $v !== ''; } ) ) ) continue;
			$row['__line'] = $line;
			$rows[] = $row;
		}
		fclose( $fh );

		return [ 'headers' => $headers, 'rows' => $rows, 'error' => null ];
	}

	/**
	 * Importa según el tipo. Cada importer devuelve el reporte estandarizado.
	 *
	 * @param string $type
	 * @param array  $rows  Array de filas asociativas (output de parse_csv).
	 * @return array{ inserted: int, updated: int, skipped: int, errors: array<string> }
	 */
	public static function import( $type, $rows, $opts = [] ) {
		switch ( $type ) {
			case 'clientes':
				return self::import_clients( $rows );
			case 'precios_publicos':
				return self::import_public_pricing( $rows );
			case 'precios_b2b':
				return self::import_b2b_pricing( $rows );
			case 'presentaciones':
				return self::import_presentations( $rows );
			case 'precios_catalogo':
				return self::import_catalog_prices( $rows, $opts );
			case 'clientes_lista':
				return self::import_clients_lista( $rows );
			default:
				return [ 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [ 'Tipo desconocido: ' . $type ] ];
		}
	}

	/* -------------------------------------------------------------------------
	 * IMPORTERS
	 * ---------------------------------------------------------------------- */

	public static function import_clients( $rows ) {
		$report = [ 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [] ];
		foreach ( (array) $rows as $row ) {
			$line = $row['__line'] ?? '?';
			$nit = sanitize_text_field( (string) ( $row['nit'] ?? '' ) );
			$name = sanitize_text_field( (string) ( $row['razon_social'] ?? '' ) );
			if ( $nit === '' || $name === '' ) {
				$report['skipped']++;
				$report['errors'][] = "Línea $line: NIT o razón social vacíos.";
				continue;
			}
			// Lookup por NIT existente
			$existing_id = glotracol_quote_find_client_by_nit( $nit );
			$post_data = [
				'post_type'   => Glotracol_Quote_Client_CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $name,
			];
			if ( $existing_id ) {
				$post_data['ID'] = $existing_id;
				$result = wp_update_post( $post_data, true );
				$is_update = true;
			} else {
				$result = wp_insert_post( $post_data, true );
				$is_update = false;
			}
			if ( is_wp_error( $result ) || ! $result ) {
				$report['skipped']++;
				$report['errors'][] = "Línea $line: error al guardar cliente — " . ( is_wp_error( $result ) ? $result->get_error_message() : 'desconocido' );
				continue;
			}
			$client_id = (int) $result;
			update_post_meta( $client_id, '_glo_client_nit', $nit );
			update_post_meta( $client_id, '_glo_client_name', $name );
			if ( isset( $row['email'] ) && is_email( $row['email'] ) ) {
				update_post_meta( $client_id, '_glo_client_email', sanitize_email( $row['email'] ) );
			}
			if ( isset( $row['telefono'] ) && $row['telefono'] !== '' ) {
				update_post_meta( $client_id, '_glo_client_phone', sanitize_text_field( $row['telefono'] ) );
			}
			if ( isset( $row['contacto'] ) && $row['contacto'] !== '' ) {
				update_post_meta( $client_id, '_glo_client_contact', sanitize_text_field( $row['contacto'] ) );
			}
			if ( isset( $row['ciudad'] ) && $row['ciudad'] !== '' ) {
				update_post_meta( $client_id, '_glo_client_city', sanitize_text_field( $row['ciudad'] ) );
			}
			if ( isset( $row['notas'] ) && $row['notas'] !== '' ) {
				update_post_meta( $client_id, '_glo_client_notes', sanitize_textarea_field( $row['notas'] ) );
			}
			$active_raw = strtolower( trim( (string) ( $row['activo'] ?? 'yes' ) ) );
			$active = in_array( $active_raw, [ 'no', '0', 'false', 'inactivo' ], true ) ? 'no' : 'yes';
			update_post_meta( $client_id, '_glo_client_active', $active );

			$is_update ? $report['updated']++ : $report['inserted']++;
		}
		// Rebuild explícito del index de NIT — no depende de hooks save_post,
		// que pueden no dispararse en contextos no estándar (wp-cli, wp eval).
		if ( $report['inserted'] > 0 || $report['updated'] > 0 ) {
			Glotracol_Quote_Client_CPT::rebuild_full_index();
		}
		return $report;
	}

	/**
	 * Asigna clientes a Lista B (o A) por NIT/identificación.
	 * - Columna `lista` explícita (B/A), o se deriva de `precio` (LISTA B / PRECIO DIFERENTE → B).
	 * - NIT existente → actualiza `_glo_price_list`. NIT nuevo con lista B → crea ficha mínima etiquetada B.
	 * - Un NIT nuevo con lista A se omite (A es el valor por defecto; no hay nada que crear).
	 */
	public static function import_clients_lista( $rows ) {
		$report = [ 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [] ];
		$touched = false;
		// Índice NIT → post_id (incluye inactivos), leído una vez. Se complementa con
		// $seen para NITs creados/tocados en esta misma corrida (el índice-opción no se
		// refresca hasta el rebuild final).
		$nit_index = Glotracol_Quote_Client_CPT::get_nit_index();
		$seen = [];
		foreach ( (array) $rows as $row ) {
			$line = $row['__line'] ?? '?';
			$nit_raw = (string) ( $row['nit'] ?? ( $row['identificacion'] ?? '' ) );
			// Nombre: 'nombre' o cualquier header que empiece por 'nombre de la' (evita el ñ de "compañia").
			$name = sanitize_text_field( (string) ( $row['nombre'] ?? '' ) );
			if ( $name === '' ) {
				foreach ( $row as $k => $v ) {
					if ( strpos( (string) $k, 'nombre de la' ) === 0 ) { $name = sanitize_text_field( (string) $v ); break; }
				}
			}
			// Lista: columna explícita, o derivada del rótulo de 'precio'.
			$lista_raw = strtoupper( trim( (string) ( $row['lista'] ?? '' ) ) );
			if ( $lista_raw === '' ) {
				$precio_lbl = strtoupper( trim( (string) ( $row['precio'] ?? '' ) ) );
				if ( strpos( $precio_lbl, 'LISTA B' ) !== false || strpos( $precio_lbl, 'DIFERENTE' ) !== false ) {
					$lista_raw = 'B';
				}
			}
			$lista = ( $lista_raw === 'B' ) ? 'B' : 'A';

			$nit_clean = Glotracol_Quote_Client_CPT::normalize_nit( $nit_raw );
			if ( ! $nit_clean ) {
				$report['skipped']++;
				$report['errors'][] = "Línea $line: identificación/NIT vacío o inválido.";
				continue;
			}
			// Buscar ficha existente por NIT, INCLUYENDO inactivos, para no duplicar.
			$existing_id = $seen[ $nit_clean ] ?? ( isset( $nit_index[ $nit_clean ] ) ? (int) $nit_index[ $nit_clean ] : 0 );
			if ( $existing_id && get_post_type( $existing_id ) !== Glotracol_Quote_Client_CPT::POST_TYPE ) {
				$existing_id = 0; // entrada de índice obsoleta
			}
			if ( $existing_id ) {
				update_post_meta( $existing_id, '_glo_price_list', $lista );
				$seen[ $nit_clean ] = $existing_id;
				$report['updated']++;
				$touched = true;
			} elseif ( $lista === 'B' ) {
				$title = $name !== '' ? $name : $nit_clean;
				$new_id = wp_insert_post( [
					'post_type'   => Glotracol_Quote_Client_CPT::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $title,
				], true );
				if ( is_wp_error( $new_id ) || ! $new_id ) {
					$report['skipped']++;
					$report['errors'][] = "Línea $line: no se pudo crear el cliente $nit_clean.";
					continue;
				}
				update_post_meta( $new_id, '_glo_client_nit', $nit_clean );
				if ( $name !== '' ) update_post_meta( $new_id, '_glo_client_name', $name );
				update_post_meta( $new_id, '_glo_client_active', 'yes' );
				update_post_meta( $new_id, '_glo_price_list', 'B' );
				$seen[ $nit_clean ] = (int) $new_id;
				$report['inserted']++;
				$touched = true;
			} else {
				$report['skipped']++;
				$report['errors'][] = "Línea $line: NIT $nit_clean no existe y la lista es A (A es el valor por defecto; no se crea ficha).";
			}
		}
		if ( $touched ) {
			Glotracol_Quote_Client_CPT::rebuild_full_index();
		}
		return $report;
	}

	public static function import_public_pricing( $rows ) {
		$report = [ 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [] ];
		$batch = [];
		foreach ( (array) $rows as $row ) {
			$line = $row['__line'] ?? '?';
			$sku = trim( (string) ( $row['sku'] ?? '' ) );
			$price = (int) ( $row['precio'] ?? 0 );
			if ( $sku === '' ) {
				$report['skipped']++;
				$report['errors'][] = "Línea $line: SKU vacío.";
				continue;
			}
			if ( $price <= 0 ) {
				$report['skipped']++;
				$report['errors'][] = "Línea $line: precio inválido (debe ser > 0).";
				continue;
			}
			$batch[ $sku ] = $price;
		}
		if ( ! empty( $batch ) ) {
			$merge = Glotracol_Quote_Pricing::merge_public_pricing( $batch );
			$report['inserted'] = $merge['inserted'];
			$report['updated']  = $merge['updated'];
		}
		return $report;
	}

	public static function import_b2b_pricing( $rows ) {
		$report = [ 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [] ];
		// Agrupar filas por NIT para hacer un solo update_post_meta por cliente
		$by_client = [];
		foreach ( (array) $rows as $row ) {
			$line = $row['__line'] ?? '?';
			$nit = trim( (string) ( $row['nit'] ?? '' ) );
			$sku = trim( (string) ( $row['sku'] ?? '' ) );
			$price = (int) ( $row['precio'] ?? 0 );
			if ( $nit === '' || $sku === '' || $price <= 0 ) {
				$report['skipped']++;
				$report['errors'][] = "Línea $line: NIT/SKU/precio inválido.";
				continue;
			}
			$client_id = glotracol_quote_find_client_by_nit( $nit );
			if ( ! $client_id ) {
				$report['skipped']++;
				$report['errors'][] = "Línea $line: NIT $nit no existe en clientes B2B (importa primero la hoja de clientes).";
				continue;
			}
			$by_client[ $client_id ][ $sku ] = $price;
		}
		// Aplicar
		foreach ( $by_client as $client_id => $prices ) {
			$existing = get_post_meta( $client_id, '_glo_client_pricing', true );
			if ( ! is_array( $existing ) ) $existing = [];
			$inserted = 0; $updated = 0;
			foreach ( $prices as $sku => $price ) {
				if ( isset( $existing[ $sku ] ) ) $updated++;
				else $inserted++;
				$existing[ $sku ] = $price;
			}
			update_post_meta( $client_id, '_glo_client_pricing', $existing );
			$report['inserted'] += $inserted;
			$report['updated']  += $updated;
		}
		return $report;
	}

	public static function import_presentations( $rows ) {
		$report = [ 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [] ];
		// Agrupar por sku_producto para hacer un solo update_post_meta por producto
		$by_product = [];
		foreach ( (array) $rows as $row ) {
			$line = $row['__line'] ?? '?';
			$sku_prod = trim( (string) ( $row['sku_producto'] ?? '' ) );
			$label = trim( (string) ( $row['label'] ?? '' ) );
			if ( $sku_prod === '' || $label === '' ) {
				$report['skipped']++;
				$report['errors'][] = "Línea $line: sku_producto o label vacíos.";
				continue;
			}
			$presentation = [
				'label'           => $label,
				'sku'             => trim( (string) ( $row['sku_variante'] ?? '' ) ) ?: $sku_prod . '-' . sanitize_title( $label ),
				'peso_g'          => (int) ( $row['peso_g'] ?? 0 ),
				'precio_publico'  => (int) ( $row['precio_publico'] ?? 0 ),
			];
			$by_product[ $sku_prod ][] = $presentation;
		}
		// Resolver SKUs de productos a post_ids y guardar
		foreach ( $by_product as $sku_prod => $presentations ) {
			$product_id = wc_get_product_id_by_sku( $sku_prod );
			if ( ! $product_id ) {
				$report['skipped'] += count( $presentations );
				$report['errors'][] = "SKU producto '$sku_prod' no encontrado en el catálogo (" . count( $presentations ) . " presentaciones omitidas).";
				continue;
			}
			$existing = get_post_meta( $product_id, '_glo_presentaciones', true );
			$is_update = is_array( $existing ) && ! empty( $existing );
			// Indexar con idx
			$indexed = [];
			foreach ( $presentations as $i => $p ) {
				$p['idx'] = $i;
				$indexed[] = $p;
			}
			update_post_meta( $product_id, '_glo_presentaciones', $indexed );
			if ( $is_update ) {
				$report['updated'] += count( $presentations );
			} else {
				$report['inserted'] += count( $presentations );
			}
		}
		return $report;
	}

	/**
	 * Importa precios del catálogo por ID de producto.
	 *
	 * @param array $rows
	 * @param array $opts  { mode: 'publico'|'b2b', client_id: int, sync_stock: bool }
	 */
	public static function import_catalog_prices( $rows, $opts = [] ) {
		$report = [ 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [] ];
		$mode       = ( ( $opts['mode'] ?? 'publico' ) === 'b2b' ) ? 'b2b' : 'publico';
		$client_id  = (int) ( $opts['client_id'] ?? 0 );
		$sync_stock = ! empty( $opts['sync_stock'] );
		// Crear productos faltantes solo aplica en lista pública.
		$create_missing = ! empty( $opts['create_missing'] ) && $mode === 'publico';

		$existing = [];
		if ( $mode === 'b2b' ) {
			if ( $client_id <= 0 || get_post_type( $client_id ) !== Glotracol_Quote_Client_CPT::POST_TYPE ) {
				return [ 'inserted' => 0, 'updated' => 0, 'skipped' => count( (array) $rows ), 'errors' => [ 'Modo B2B sin cliente válido seleccionado.' ] ];
			}
			$existing = get_post_meta( $client_id, '_glo_client_pricing', true );
			if ( ! is_array( $existing ) ) $existing = [];
		}

		// Índice nombre normalizado → product_id, construido perezosamente solo si se crean faltantes.
		$name_index = null;

		foreach ( (array) $rows as $row ) {
			$line = $row['__line'] ?? '?';
			$pid  = (int) ( $row['id'] ?? 0 );
			$price_raw = $row['precio normal'] ?? ( $row['precio'] ?? '' );
			$price = (int) preg_replace( '/[^0-9]/', '', (string) $price_raw );
			$stock_text = self::row_stock( $row );

			if ( $pid <= 0 ) {
				// Fila sin ID: si está habilitado, resolver por nombre (crear o actualizar el existente).
				if ( $create_missing ) {
					if ( $name_index === null ) $name_index = self::build_name_index();
					$name = trim( (string) ( $row['nombre'] ?? '' ) );
					if ( $name === '' ) {
						$report['skipped']++;
						$report['errors'][] = "Línea $line: fila sin ID y sin nombre; no se puede crear.";
						continue;
					}
					if ( $price <= 0 ) {
						$report['skipped']++;
						$report['errors'][] = "Línea $line: producto nuevo \"$name\" sin precio (no se crea).";
						continue;
					}
					$weight = self::parse_weight( $row['peso (kg)'] ?? '' );
					$key = self::normalize_name( $name );
					if ( isset( $name_index[ $key ] ) ) {
						// Ya existe por nombre → actualizar ese producto, sin duplicar.
						$existing_id = (int) $name_index[ $key ];
						$product = function_exists( 'wc_get_product' ) ? wc_get_product( $existing_id ) : null;
						if ( ! $product ) {
							$report['skipped']++;
							$report['errors'][] = "Línea $line: \"$name\" mapeó a ID $existing_id pero no es un producto válido.";
							continue;
						}
						update_post_meta( $existing_id, '_glo_price', $price );
						if ( $weight !== null ) $product->set_weight( $weight );
						if ( $stock_text !== '' ) $product->set_stock_status( strpos( strtolower( $stock_text ), 'agot' ) !== false ? 'outofstock' : 'instock' );
						$product->save();
						$report['updated']++;
					} else {
						$new_id = self::create_product( $name, $price, $weight, $stock_text );
						if ( ! $new_id ) {
							$report['skipped']++;
							$report['errors'][] = "Línea $line: no se pudo crear el producto \"$name\".";
							continue;
						}
						$name_index[ $key ] = $new_id; // evita duplicar si el nombre se repite en el archivo
						$report['inserted']++;
					}
				} else {
					$report['skipped']++;
					$report['errors'][] = "Línea $line: ID vacío o inválido.";
				}
				continue;
			}
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
			if ( ! $product ) {
				$report['skipped']++;
				$report['errors'][] = "Línea $line: el producto ID $pid no existe en el catálogo.";
				continue;
			}
			if ( $price <= 0 ) {
				$report['skipped']++;
				$report['errors'][] = "Línea $line: producto ID $pid sin precio (queda pendiente).";
				if ( $mode === 'publico' && $sync_stock ) {
					self::apply_stock( $product, $stock_text );
				}
				continue;
			}

			if ( $mode === 'b2b' ) {
				if ( isset( $existing[ $pid ] ) ) $report['updated']++; else $report['inserted']++;
				$existing[ $pid ] = $price;
			} else {
				$had = (int) get_post_meta( $pid, '_glo_price', true );
				update_post_meta( $pid, '_glo_price', $price );
				if ( $had > 0 ) $report['updated']++; else $report['inserted']++;
				if ( $sync_stock ) {
					self::apply_stock( $product, $stock_text );
				}
			}
		}

		if ( $mode === 'b2b' ) {
			update_post_meta( $client_id, '_glo_client_pricing', $existing );
		}
		return $report;
	}

	/** Disponibilidad de la fila: acepta la columna `disponibilidad` o su alias `inventario`. */
	private static function row_stock( $row ) {
		$d = trim( (string) ( $row['disponibilidad'] ?? '' ) );
		if ( $d === '' ) $d = trim( (string) ( $row['inventario'] ?? '' ) );
		return $d;
	}

	/** Sincroniza el stock WC desde el texto de disponibilidad (AGOTADO/DISPONIBLE). */
	private static function apply_stock( $product, $disp ) {
		$d = strtolower( trim( (string) $disp ) );
		if ( $d === '' ) return;
		$product->set_stock_status( strpos( $d, 'agot' ) !== false ? 'outofstock' : 'instock' );
		$product->save();
	}

	/** Normaliza un nombre de producto para comparar: sin acentos, espacios colapsados, mayúsculas. */
	private static function normalize_name( $s ) {
		$s = (string) $s;
		if ( function_exists( 'remove_accents' ) ) $s = remove_accents( $s );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return strtoupper( trim( $s ) );
	}

	/** Convierte un peso con coma decimal ("22,68") a float. '' o ≤0 → null (no setear). */
	private static function parse_weight( $raw ) {
		$raw = str_replace( ',', '.', trim( (string) $raw ) );
		$raw = preg_replace( '/[^0-9.]/', '', $raw );
		if ( $raw === '' || ! is_numeric( $raw ) ) return null;
		$w = (float) $raw;
		return $w > 0 ? $w : null;
	}

	/** Mapa nombre_normalizado → product_id de todo el catálogo (incluye borradores). */
	private static function build_name_index() {
		$index = [];
		$q = new WP_Query( [
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		] );
		foreach ( $q->posts as $id ) {
			$title = get_the_title( $id );
			$key = self::normalize_name( $title );
			// El primero gana (ID menor): conserva determinismo si hay nombres repetidos.
			if ( $key !== '' && ! isset( $index[ $key ] ) ) $index[ $key ] = (int) $id;
		}
		return $index;
	}

	/** Crea un producto simple publicado con su precio interno, peso y stock. Devuelve el ID o 0. */
	private static function create_product( $name, $price, $weight, $stock_text ) {
		if ( ! class_exists( 'WC_Product_Simple' ) ) return 0;
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		if ( $weight !== null ) $product->set_weight( $weight );
		if ( $stock_text !== '' ) {
			$product->set_stock_status( strpos( strtolower( $stock_text ), 'agot' ) !== false ? 'outofstock' : 'instock' );
		}
		$id = $product->save();
		if ( ! $id ) return 0;
		update_post_meta( $id, '_glo_price', (int) $price );
		return (int) $id;
	}
}
