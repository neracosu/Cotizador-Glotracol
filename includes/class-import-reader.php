<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Lector tolerante de archivos de importación (.xlsx / .csv).
 *
 * Convierte un archivo crudo en filas con las CLAVES EXACTAS del schema elegido,
 * normalizando valores y detectando el tipo. NO escribe nada en la base de datos.
 * Barreras de seguridad: nada se auto-aplica; el xlsx es acotado (ver read_xlsx).
 */
class Glotracol_Quote_Import_Reader {

	/** Precio → entero COP. Quita moneda/miles/espacios (punto = miles en CO). '' → 0. */
	public static function norm_price( $raw ) {
		return (int) preg_replace( '/[^0-9]/', '', (string) $raw );
	}

	/** Peso con coma decimal ("22,68") → "22.68". Deja dígitos y un punto. '' si no numérico. */
	public static function norm_weight( $raw ) {
		$s = str_replace( ',', '.', trim( (string) $raw ) );
		$s = preg_replace( '/[^0-9.]/', '', $s );
		// colapsar múltiples puntos: dejar el primero
		if ( substr_count( $s, '.' ) > 1 ) {
			$first = strpos( $s, '.' );
			$s = substr( $s, 0, $first + 1 ) . str_replace( '.', '', substr( $s, $first + 1 ) );
		}
		return ( $s === '' || ! is_numeric( $s ) ) ? '' : $s;
	}

	/** Texto: trim + colapsa espacios internos. */
	public static function norm_text( $raw ) {
		return trim( preg_replace( '/\s+/u', ' ', (string) $raw ) );
	}

	/**
	 * Diccionario de sinónimos por columna canónica (todo en minúscula).
	 * La clave es EXACTAMENTE la que espera el schema (respeta 'precio normal' con espacio).
	 */
	const SYNONYMS = [
		'id'             => [ 'id', 'codigo', 'código', 'sku wc', 'id producto', 'id wc' ],
		'nit'            => [ 'nit', 'identificacion', 'identificación', 'cedula', 'cédula', 'documento', 'rif' ],
		'razon_social'   => [ 'razon_social', 'razón social', 'razon social', 'nombre de la compañia', 'nombre de la compañía', 'empresa', 'compañia', 'compañía' ],
		'nombre'         => [ 'nombre', 'producto', 'descripcion', 'descripción', 'nombre del producto' ],
		'precio normal'  => [ 'precio normal', 'precio', 'valor', 'valor unitario', 'precio unitario', 'precio a', 'precio lista' ],
		'precio'         => [ 'precio', 'valor', 'precio normal', 'precio unitario', 'valor unitario' ],
		'sku'            => [ 'sku', 'referencia', 'ref' ],
		'peso (kg)'      => [ 'peso (kg)', 'peso', 'peso kg', 'kg', 'peso neto' ],
		'disponibilidad' => [ 'disponibilidad', 'inventario', 'stock', 'estado', 'existencia' ],
		'lista'          => [ 'lista', 'nivel', 'tipo de precio' ],
		'email'          => [ 'email', 'correo', 'correo electronico', 'correo electrónico', 'e-mail' ],
		'telefono'       => [ 'telefono', 'teléfono', 'celular', 'movil', 'móvil', 'tel' ],
		'contacto'       => [ 'contacto', 'persona de contacto', 'responsable' ],
		'ciudad'         => [ 'ciudad', 'ciudad / país', 'ciudad/pais', 'ubicacion', 'ubicación' ],
		'activo'         => [ 'activo', 'habilitado' ],
		'notas'          => [ 'notas', 'observaciones', 'comentarios' ],
		'sku_producto'   => [ 'sku_producto', 'sku producto' ],
		'label'          => [ 'label', 'presentacion', 'presentación', 'etiqueta' ],
		'sku_variante'   => [ 'sku_variante', 'sku variante', 'variante' ],
		'peso_g'         => [ 'peso_g', 'peso (g)', 'gramos', 'peso g' ],
		'precio_publico' => [ 'precio_publico', 'precio público', 'precio publico' ],
	];

	/**
	 * Lee la PRIMERA hoja de un .xlsx (ZipArchive+XML). Acotado (barrera 6):
	 * celdas por referencia (A2/B2) para no desalinear; strings compartidos/inline/
	 * fórmula cacheada; números directos. Sin fechas/fórmulas complejas/multi-hoja.
	 */
	public static function read_xlsx( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return [ 'headers' => [], 'rows' => [], 'error' => 'El servidor no puede leer Excel (falta ZipArchive). Exporta el archivo como CSV.' ];
		}
		$zip = new ZipArchive();
		if ( $zip->open( $file_path ) !== true ) {
			return [ 'headers' => [], 'rows' => [], 'error' => 'No se pudo abrir el archivo Excel. Exporta como CSV.' ];
		}
		// Strings compartidos (opcional).
		$shared = [];
		$ss = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( $ss !== false ) {
			$sx = @simplexml_load_string( $ss );
			if ( $sx !== false ) {
				foreach ( $sx->si as $si ) {
					// Concatenar todos los <t> (maneja runs de texto enriquecido).
					$text = '';
					foreach ( $si->xpath( './/*[local-name()="t"]' ) as $t ) $text .= (string) $t;
					$shared[] = $text;
				}
			}
		}
		// Primera hoja según el ZIP (sheet1.xml es el nombre estándar).
		$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		if ( $sheet_xml === false ) {
			// Buscar la primera worksheet disponible.
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$name = $zip->getNameIndex( $i );
				if ( strpos( $name, 'xl/worksheets/sheet' ) === 0 && substr( $name, -4 ) === '.xml' ) {
					$sheet_xml = $zip->getFromName( $name ); break;
				}
			}
		}
		$zip->close();
		if ( $sheet_xml === false || $sheet_xml === null ) {
			return [ 'headers' => [], 'rows' => [], 'error' => 'No encontré datos en el Excel. Exporta como CSV.' ];
		}
		$sx = @simplexml_load_string( $sheet_xml );
		if ( $sx === false || ! isset( $sx->sheetData ) ) {
			return [ 'headers' => [], 'rows' => [], 'error' => 'No pude leer la hoja del Excel. Exporta como CSV.' ];
		}
		// Construir matriz por referencia de celda.
		$matrix = []; // [rowIndex1based][colIndex0based] = value
		foreach ( $sx->sheetData->row as $row ) {
			foreach ( $row->c as $c ) {
				$ref = (string) $c['r'];              // p.ej. "B2"
				if ( ! preg_match( '/^([A-Z]+)(\d+)$/', $ref, $mm ) ) continue;
				$col = self::col_to_index( $mm[1] );  // 0-based
				$rowi = (int) $mm[2];
				$type = (string) $c['t'];
				$val = '';
				if ( $type === 's' ) {
					$idx = (int) ( (string) $c->v );
					$val = $shared[ $idx ] ?? '';
				} elseif ( $type === 'inlineStr' ) {
					foreach ( $c->xpath( './/*[local-name()="t"]' ) as $t ) $val .= (string) $t;
				} else { // número o fórmula cacheada (t="str")
					$val = isset( $c->v ) ? (string) $c->v : '';
				}
				$matrix[ $rowi ][ $col ] = trim( $val );
			}
		}
		if ( empty( $matrix ) ) return [ 'headers' => [], 'rows' => [], 'error' => 'El Excel está vacío.' ];
		ksort( $matrix );
		$row_indices = array_keys( $matrix );
		$header_row = array_shift( $row_indices );
		$max_col = 0;
		foreach ( $matrix as $cells ) { if ( ! empty( $cells ) ) $max_col = max( $max_col, max( array_keys( $cells ) ) ); }
		$headers = [];
		for ( $i = 0; $i <= $max_col; $i++ ) {
			$headers[ $i ] = mb_strtolower( trim( (string) ( $matrix[ $header_row ][ $i ] ?? '' ) ), 'UTF-8' );
		}
		$rows = [];
		foreach ( $row_indices as $ri ) {
			$assoc = [];
			$has = false;
			for ( $i = 0; $i <= $max_col; $i++ ) {
				$h = $headers[ $i ];
				if ( $h === '' ) continue;
				$v = trim( (string) ( $matrix[ $ri ][ $i ] ?? '' ) );
				$assoc[ $h ] = $v;
				if ( $v !== '' ) $has = true;
			}
			if ( ! $has ) continue;
			$assoc['__line'] = $ri;
			$rows[] = $assoc;
		}
		// Filtrar headers vacíos de la lista pública.
		$clean_headers = array_values( array_filter( $headers, function ( $h ) { return $h !== ''; } ) );
		return [ 'headers' => $clean_headers, 'rows' => $rows, 'error' => null ];
	}

	/** Columnas que se normalizan como precio / peso, por nombre canónico. */
	const PRICE_COLS  = [ 'precio', 'precio normal', 'precio_publico' ];
	const WEIGHT_COLS = [ 'peso (kg)', 'peso_g' ];

	/**
	 * Lee un archivo y devuelve filas normalizadas con claves de schema + metadatos.
	 * Barreras: no escribe nada; xlsx acotado; auto-detección solo sugiere (chosen_type
	 * respeta $forced_type si viene).
	 */
	public static function read( $file_path, $forced_type = null ) {
		$is_xlsx = self::looks_xlsx( $file_path );
		$raw = $is_xlsx ? self::read_xlsx( $file_path ) : Glotracol_Quote_Importer::read_delimited( $file_path );
		if ( $raw['error'] !== null ) {
			return array_merge( $raw, [ 'raw_headers' => $raw['headers'], 'detected_type' => null, 'type_confidence' => 0.0, 'type_ambiguous' => false, 'chosen_type' => null, 'mapping' => [], 'unmapped' => [], 'corrections' => [] ] );
		}
		$raw_headers = $raw['headers'];
		$det = self::detect_type( $raw_headers );
		$chosen = $forced_type ?: $det['type'];
		$schemas = Glotracol_Quote_Importer::get_schemas();
		if ( ! $chosen || ! isset( $schemas[ $chosen ] ) ) {
			return [ 'rows' => [], 'headers' => [], 'raw_headers' => $raw_headers, 'detected_type' => $det['type'], 'type_confidence' => $det['confidence'], 'type_ambiguous' => $det['ambiguous'], 'chosen_type' => null, 'mapping' => [], 'unmapped' => $raw_headers, 'corrections' => [], 'error' => 'No pude reconocer el tipo de archivo. Elige el tipo manualmente.' ];
		}
		$schema = $schemas[ $chosen ];
		$mm = self::map_headers_to_schema( $raw_headers, $schema );
		$map = $mm['map']; // canonical => raw
		$rows = [];
		$corr = [];
		foreach ( $raw['rows'] as $rrow ) {
			$out = [ '__line' => $rrow['__line'] ?? '?' ];
			foreach ( $map as $canonical => $raw_h ) {
				$val = (string) ( $rrow[ $raw_h ] ?? '' );
				if ( in_array( $canonical, self::PRICE_COLS, true ) ) {
					$n_int = self::norm_price( $val );
					$n = (string) $n_int;
					if ( $n !== '' && $n !== '0' && $n !== $val ) $corr[ "precio \"$val\" → $n" ] = true;
					$out[ $canonical ] = ( $n_int > 0 ) ? $n : '';
				} elseif ( in_array( $canonical, self::WEIGHT_COLS, true ) ) {
					$n = self::norm_weight( $val );
					if ( $n !== '' && $n !== $val ) $corr[ "peso \"$val\" → $n" ] = true;
					$out[ $canonical ] = $n;
				} else {
					$out[ $canonical ] = self::norm_text( $val );
				}
			}
			$rows[] = $out;
		}
		return [
			'rows'            => $rows,
			'headers'         => array_keys( $map ),
			'raw_headers'     => $raw_headers,
			'detected_type'   => $det['type'],
			'type_confidence' => $det['confidence'],
			'type_ambiguous'  => $det['ambiguous'],
			'chosen_type'     => $chosen,
			'mapping'         => $map,
			'unmapped'        => $mm['unmapped'],
			'corrections'     => array_slice( array_keys( $corr ), 0, 30 ),
			'error'           => null,
		];
	}

	/** Detecta xlsx por firma ZIP (PK\x03\x04) o extensión. */
	private static function looks_xlsx( $file_path ) {
		if ( preg_match( '/\.xlsx$/i', $file_path ) ) return true;
		$fh = @fopen( $file_path, 'rb' );
		if ( ! $fh ) return false;
		$sig = fread( $fh, 4 );
		fclose( $fh );
		return $sig === "PK\x03\x04";
	}

	/** "A"->0, "B"->1, ... "AA"->26. */
	private static function col_to_index( $letters ) {
		$n = 0;
		$len = strlen( $letters );
		for ( $i = 0; $i < $len; $i++ ) $n = $n * 26 + ( ord( $letters[ $i ] ) - 64 );
		return $n - 1;
	}

	/** Columnas conocidas de un schema (required + optional + plantilla), únicas, minúscula. */
	public static function schema_columns( $schema ) {
		$cols = array_merge( $schema['required'] ?? [], $schema['optional'] ?? [], $schema['plantilla'] ?? [] );
		$cols = array_map( function ( $c ) { return strtolower( trim( (string) $c ) ); }, $cols );
		return array_values( array_unique( $cols ) );
	}

	/** Sinónimos de una columna canónica (incluye la propia clave). */
	private static function synonyms_of( $canonical ) {
		$syn = self::SYNONYMS[ $canonical ] ?? [];
		if ( ! in_array( $canonical, $syn, true ) ) $syn[] = $canonical;
		return $syn;
	}

	/** Puntúa un schema contra headers crudos. required faltante => -1. */
	private static function score_schema( $raw_headers_lower, $schema ) {
		$req = array_map( 'strtolower', $schema['required'] ?? [] );
		$opt = array_map( 'strtolower', $schema['optional'] ?? [] );
		$matched_req = 0;
		foreach ( $req as $c ) {
			foreach ( $raw_headers_lower as $h ) { if ( in_array( $h, self::synonyms_of( $c ), true ) ) { $matched_req++; break; } }
		}
		if ( $matched_req < count( $req ) ) return -1.0;
		$matched_opt = 0;
		foreach ( $opt as $c ) {
			foreach ( $raw_headers_lower as $h ) { if ( in_array( $h, self::synonyms_of( $c ), true ) ) { $matched_opt++; break; } }
		}
		return (float) ( $matched_req * 2 + $matched_opt );
	}

	/**
	 * Detecta el tipo de hoja por sus headers. Barrera 2: la UI debe confirmar/elegir.
	 * @return array{ type: string|null, confidence: float, ambiguous: bool, scores: array<string,float> }
	 */
	public static function detect_type( $raw_headers_lower ) {
		$schemas = Glotracol_Quote_Importer::get_schemas();
		$scores = [];
		foreach ( $schemas as $type => $schema ) {
			$s = self::score_schema( $raw_headers_lower, $schema );
			if ( $s > 0 ) $scores[ $type ] = $s;
		}
		if ( empty( $scores ) ) return [ 'type' => null, 'confidence' => 0.0, 'ambiguous' => false, 'scores' => [] ];
		arsort( $scores );
		$types = array_keys( $scores );
		$best = $types[0];
		$best_score = $scores[ $best ];
		// confianza = score / máximo posible del schema ganador
		$sw = $schemas[ $best ];
		$max = ( count( $sw['required'] ?? [] ) * 2 ) + count( $sw['optional'] ?? [] );
		$confidence = $max > 0 ? min( 1.0, $best_score / $max ) : 0.0;
		// ambigüedad: comparar confianzas normalizadas de los dos mejores candidatos
		$ambiguous = false;
		if ( count( $types ) > 1 ) {
			$s2_type = $types[1];
			$s2 = $schemas[ $s2_type ];
			$max2 = ( count( $s2['required'] ?? [] ) * 2 ) + count( $s2['optional'] ?? [] );
			$conf2 = $max2 > 0 ? min( 1.0, $scores[ $s2_type ] / $max2 ) : 0.0;
			if ( $confidence > 0 && $conf2 >= 0.9 * $confidence ) $ambiguous = true;
		}
		return [ 'type' => $best, 'confidence' => round( $confidence, 2 ), 'ambiguous' => $ambiguous, 'scores' => $scores ];
	}

	/** Normaliza un nombre para comparar: sin acentos, espacios colapsados, mayúsculas. */
	public static function normalize_name( $s ) {
		$s = (string) $s;
		if ( function_exists( 'remove_accents' ) ) $s = remove_accents( $s );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return strtoupper( trim( $s ) );
	}

	/**
	 * ¿Existe una entidad con ese nombre normalizado exacto? Devuelve su id o 0.
	 * Misma normalización que usa el importador (import_prices_lista_b) para que
	 * preview e importación coincidan (evita marcar "sin match" algo que el
	 * importador sí resolvería por nombre). No usa get_page_by_title (deprecado).
	 */
	public static function find_by_name( $name, $kind ) {
		$key = self::normalize_name( $name );
		if ( $key === '' ) return 0;
		foreach ( self::candidate_index( $kind ) as $id => $title ) {
			if ( self::normalize_name( $title ) === $key ) return (int) $id;
		}
		return 0;
	}

	/** Tokens ≥3 chars del nombre normalizado. */
	private static function tokens( $s ) {
		return array_values( array_filter( explode( ' ', self::normalize_name( $s ) ), function ( $t ) { return strlen( $t ) >= 3; } ) );
	}

	/**
	 * Sugiere entidades existentes parecidas a $name.
	 * @param string $kind 'product'|'client'
	 * @return array<array{id:int,label:string,score:int}>
	 */
	public static function suggest_candidates( $name, $kind, $limit = 3 ) {
		$name = (string) $name;
		if ( self::normalize_name( $name ) === '' ) return [];
		$targets = self::candidate_index( $kind ); // [id => title]
		$nt = self::tokens( $name );
		$nn = self::normalize_name( $name );
		$scored = [];
		foreach ( $targets as $id => $title ) {
			$ct = self::tokens( $title );
			$inter = count( array_intersect( $nt, $ct ) );
			if ( $inter < 1 ) continue;
			$pct = 0.0; similar_text( $nn, self::normalize_name( $title ), $pct );
			// score compuesto: prioriza tokens compartidos, desempata por similitud textual.
			$score = (int) round( min( 100, $inter * 25 + $pct * 0.5 ) );
			$scored[] = [ 'id' => (int) $id, 'label' => $title, 'score' => $score, '_i' => $inter ];
		}
		usort( $scored, function ( $a, $b ) { return $b['score'] <=> $a['score']; } );
		return array_map( function ( $c ) { return [ 'id' => $c['id'], 'label' => $c['label'], 'score' => $c['score'] ]; }, array_slice( $scored, 0, $limit ) );
	}

	/** Índice id => título para productos o clientes. Construido perezosamente. */
	/** Caché por-request del índice (el preview llama suggest_candidates/find_by_name una vez por fila). */
	private static $index_cache = [];

	private static function candidate_index( $kind ) {
		$kind = ( $kind === 'client' ) ? 'client' : 'product';
		if ( isset( self::$index_cache[ $kind ] ) ) return self::$index_cache[ $kind ];
		$post_type = ( $kind === 'client' ) ? Glotracol_Quote_Client_CPT::POST_TYPE : 'product';
		$ids = get_posts( [ 'post_type' => $post_type, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] );
		$out = [];
		foreach ( (array) $ids as $id ) $out[ (int) $id ] = get_the_title( $id );
		return self::$index_cache[ $kind ] = $out;
	}

	/**
	 * Mapea headers crudos a las columnas del schema por sinónimos.
	 * @return array{ map: array<string,string>, unmapped: string[] }
	 *   map: clave = columna del schema, valor = header crudo que la satisface.
	 */
	public static function map_headers_to_schema( $raw_headers_lower, $schema ) {
		$cols = self::schema_columns( $schema );
		$map = [];
		$used = [];
		foreach ( $cols as $canonical ) {
			$syn = self::synonyms_of( $canonical );
			foreach ( $raw_headers_lower as $h ) {
				if ( in_array( $h, $used, true ) ) continue;
				if ( in_array( $h, $syn, true ) ) { $map[ $canonical ] = $h; $used[] = $h; break; }
			}
		}
		$unmapped = array_values( array_diff( $raw_headers_lower, $used ) );
		return [ 'map' => $map, 'unmapped' => $unmapped ];
	}
}
