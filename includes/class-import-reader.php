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
}
