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
