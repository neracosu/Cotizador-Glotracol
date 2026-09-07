<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Genera el PDF de una cotización al vuelo desde la meta _glo_items.
 * No persiste nada en uploads/: así un cambio de precio en el panel se refleja
 * al volver a descargar y no se acumulan archivos huérfanos.
 */
class Glotracol_Quote_PDF {

	/** FPDF trabaja en latin1; transliteramos para que tildes y ñ salgan bien. */
	private static function t( $s ) {
		$s = (string) $s;
		$out = @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $s );
		return $out === false ? preg_replace( '/[^\x20-\x7E]/', '', $s ) : $out;
	}

	public static function filename( $quote_id ) {
		$ref = get_post_meta( (int) $quote_id, '_glo_qid', true );
		$ref = $ref ? sanitize_file_name( $ref ) : (int) $quote_id;
		return 'cotizacion-' . $ref . '-' . (int) $quote_id . '.pdf';
	}

	/** Lineas que ocupa un texto en una celda de ancho $w con la fuente actual. */
	private static function nb_lines( FPDF $pdf, $w, $txt ) {
		$max = $w - 2; // margen interno de celda de FPDF (cMargin = 1mm)
		$lines = 1;
		$line  = '';
		foreach ( preg_split( '/\s+/', trim( (string) $txt ) ) as $word ) {
			$try = $line === '' ? $word : $line . ' ' . $word;
			if ( $pdf->GetStringWidth( $try ) <= $max ) {
				$line = $try;
			} else {
				$lines++;
				$line = $word;
			}
		}
		return $lines;
	}

	/**
	 * @param array $opts { compress?: bool } Sin compresion el flujo queda legible (pruebas).
	 * @return string PDF binario, o '' si la cotización no existe.
	 */
	public static function render( $quote_id, $opts = [] ) {
		$quote_id = (int) $quote_id;
		$post = get_post( $quote_id );
		if ( ! $post || $post->post_type !== 'glo_quote' ) return '';

		if ( ! class_exists( 'FPDF' ) ) {
			require_once GLOTRACOL_QUOTE_PATH . 'vendor/fpdf/fpdf.php';
		}

		$items = glotracol_quote_enrich_items( get_post_meta( $quote_id, '_glo_items', true ) ?: [] );
		$total = (int) get_post_meta( $quote_id, '_glo_total', true );
		$peso  = (float) get_post_meta( $quote_id, '_glo_weight_total_kg', true );
		$type  = get_post_meta( $quote_id, '_glo_type', true ) ?: 'quote';
		$cust  = [
			'name'    => get_post_meta( $quote_id, '_glo_customer_name', true ),
			'company' => get_post_meta( $quote_id, '_glo_customer_company', true ),
			'nit'     => get_post_meta( $quote_id, '_glo_customer_nit', true ),
			'email'   => get_post_meta( $quote_id, '_glo_customer_email', true ),
			'phone'   => get_post_meta( $quote_id, '_glo_customer_phone', true ),
			'city'    => get_post_meta( $quote_id, '_glo_customer_city', true ),
		];

		$brand = glotracol_quote_brand();
		$rgb   = $brand['rgb'];
		$txt   = glotracol_quote_brand_palette( $brand['color'] )['text'] === '#ffffff' ? [ 255, 255, 255 ] : [ 26, 26, 26 ];
		$dark  = array_map( 'hexdec', str_split( ltrim( $brand['dark'], '#' ), 2 ) );

		$pdf = new FPDF( 'P', 'mm', 'Letter' );
		if ( isset( $opts['compress'] ) && ! $opts['compress'] ) {
			$pdf->SetCompression( false );
		}
		$pdf->SetAutoPageBreak( true, 20 );
		$pdf->AddPage();

		// --- Cabecera de marca: franja de color, logo a la izquierda, titulo a la derecha ---
		$pdf->SetFillColor( $rgb[0], $rgb[1], $rgb[2] );
		$pdf->Rect( 0, 0, 216, 4, 'F' );
		$logo_h = 0;
		if ( ! empty( $brand['logo_path'] ) ) {
			try {
				$pdf->Image( $brand['logo_path'], 14, 10, 0, 14 );
				$logo_h = 14;
			} catch ( Throwable $e ) {
				$logo_h = 0;
			}
		}
		if ( ! $logo_h ) {
			$pdf->SetTextColor( 26, 26, 26 );
			$pdf->SetFont( 'Helvetica', 'B', 13 );
			$pdf->SetXY( 14, 12 );
			$pdf->Cell( 100, 8, self::t( get_bloginfo( 'name' ) ), 0, 0 );
		}
		$pdf->SetTextColor( $dark[0], $dark[1], $dark[2] );
		$pdf->SetFont( 'Helvetica', 'B', 8 );
		$pdf->SetXY( 120, 9 );
		$pdf->Cell( 82, 5, self::t( mb_strtoupper( $type === 'order' ? 'Pedido' : 'Cotización', 'UTF-8' ) ), 0, 1, 'R' );
		$pdf->SetTextColor( 26, 26, 26 );
		$pdf->SetFont( 'Helvetica', 'B', 18 );
		$pdf->SetXY( 120, 14 );
		$pdf->Cell( 82, 8, '#' . $quote_id, 0, 1, 'R' );
		$pdf->SetFont( 'Helvetica', '', 9 );
		$pdf->SetTextColor( 110, 110, 110 );
		$pdf->SetXY( 120, 22 );
		$pdf->Cell( 82, 5, self::t( current_time( 'd/m/Y' ) ), 0, 1, 'R' );
		$pdf->SetDrawColor( 230, 233, 236 );
		$pdf->Line( 14, 31, 202, 31 );

		// --- Datos del cliente ---
		$pdf->SetTextColor( 34, 34, 34 );
		$pdf->SetY( 38 );
		$pdf->SetFont( 'Helvetica', 'B', 11 );
		$pdf->Cell( 0, 7, self::t( 'Datos del cliente' ), 0, 1 );
		$pdf->SetDrawColor( $rgb[0], $rgb[1], $rgb[2] );
		$pdf->SetLineWidth( 0.5 );
		$pdf->Line( 14, $pdf->GetY(), 60, $pdf->GetY() );
		$pdf->SetLineWidth( 0.2 );
		$pdf->SetDrawColor( 200, 200, 200 );
		$pdf->Ln( 2 );
		$pdf->SetFont( 'Helvetica', '', 9 );
		foreach ( [ 'Nombre' => $cust['name'], 'Empresa' => $cust['company'], 'NIT' => $cust['nit'],
		            'Email' => $cust['email'], 'Teléfono' => $cust['phone'], 'Ciudad' => $cust['city'] ] as $k => $v ) {
			if ( trim( (string) $v ) === '' ) continue;
			$pdf->SetFont( 'Helvetica', 'B', 9 );
			$pdf->Cell( 28, 5, self::t( $k ), 0, 0 );
			$pdf->SetFont( 'Helvetica', '', 9 );
			$pdf->Cell( 0, 5, self::t( $v ), 0, 1 );
		}

		// --- Tabla de items ---
		$pdf->Ln( 4 );
		$w = [ 62, 24, 26, 14, 28, 34 ]; // suma 188, cabe en Letter con márgenes de 14
		$pdf->SetFont( 'Helvetica', 'B', 8 );
		$pdf->SetFillColor( 244, 246, 248 );
		$pdf->SetTextColor( 51, 51, 51 );
		foreach ( [ 'Producto', 'Empaque', 'Present./Peso', 'Cant.', 'Precio unit.', 'Subtotal' ] as $i => $h ) {
			$pdf->Cell( $w[ $i ], 7, self::t( $h ), 1, ( $i === 5 ? 1 : 0 ), ( $i >= 4 ? 'R' : 'L' ), true );
		}
		// Linea de marca bajo la cabecera de la tabla
		$pdf->SetDrawColor( $rgb[0], $rgb[1], $rgb[2] );
		$pdf->SetLineWidth( 0.6 );
		$pdf->Line( 14, $pdf->GetY(), 14 + array_sum( $w ), $pdf->GetY() );
		$pdf->SetLineWidth( 0.2 );
		$pdf->SetDrawColor( 200, 200, 200 );
		$pdf->SetTextColor( 34, 34, 34 );
		$pdf->SetFont( 'Helvetica', '', 8 );
		foreach ( $items as $it ) {
			$pend = ! empty( $it['es_pendiente'] );
			$pdf->SetFillColor( $pend ? 255 : 255, $pend ? 248 : 255, $pend ? 225 : 255 );
			// El nombre se envuelve en varias lineas en vez de recortarse.
			$name  = self::t( $it['name'] ?? '' );
			$lines = self::nb_lines( $pdf, $w[0], $name );
			$h     = max( 6, 4 * $lines + 2 );
			if ( $pdf->GetY() + $h > $pdf->GetPageHeight() - 20 ) {
				$pdf->AddPage();
			}
			$x = $pdf->GetX();
			$y = $pdf->GetY();
			$pdf->Rect( $x, $y, $w[0], $h, 'DF' );
			$pdf->SetXY( $x, $y + ( $h - 4 * $lines ) / 2 );
			$pdf->MultiCell( $w[0], 4, $name, 0, 'L' );
			$pdf->SetXY( $x + $w[0], $y );
			$pdf->Cell( $w[1], $h, self::t( $it['empaque'] ?? '—' ), 1, 0, 'L', true );
			$pdf->Cell( $w[2], $h, self::t( $it['presentacion'] ?? '—' ), 1, 0, 'L', true );
			$pdf->Cell( $w[3], $h, (int) ( $it['quantity'] ?? 0 ), 1, 0, 'C', true );
			$pdf->Cell( $w[4], $h, self::t( $it['precio_unit_fmt'] ?? '—' ), 1, 0, 'R', true );
			$pdf->Cell( $w[5], $h, self::t( $it['precio_sub_fmt'] ?? '—' ), 1, 1, 'R', true );
		}

		// --- Totales ---
		if ( $peso > 0 ) {
			$pdf->SetFont( 'Helvetica', '', 8 );
			$pdf->SetFillColor( 244, 246, 248 );
			$pdf->Cell( array_sum( $w ) - $w[5], 6, self::t( 'Peso total' ), 1, 0, 'R', true );
			$pdf->Cell( $w[5], 6, self::t( number_format( $peso, 2, ',', '.' ) . ' kg' ), 1, 1, 'R', true );
		}
		$pendientes = 0;
		foreach ( $items as $it ) { if ( ! empty( $it['es_pendiente'] ) ) $pendientes++; }
		$pdf->SetFont( 'Helvetica', 'B', 10 );
		$pdf->SetFillColor( $rgb[0], $rgb[1], $rgb[2] );
		$pdf->SetTextColor( $txt[0], $txt[1], $txt[2] );
		$pdf->Cell( array_sum( $w ) - $w[5], 8, self::t( $pendientes > 0 ? 'TOTAL PARCIAL' : 'TOTAL' ), 1, 0, 'R', true );
		$pdf->Cell( $w[5], 8, self::t( glotracol_quote_format_price( $total ) ), 1, 1, 'R', true );
		$pdf->SetTextColor( 34, 34, 34 );

		if ( $pendientes > 0 ) {
			$pdf->Ln( 3 );
			$pdf->SetFont( 'Helvetica', 'I', 8 );
			$pdf->MultiCell( 0, 4, self::t( $pendientes . ' producto(s) marcado(s) como "A cotizar": el equipo comercial enviará esos precios. El total mostrado es parcial.' ) );
		}

		// --- Pie ---
		$pdf->Ln( 6 );
		$pdf->SetFont( 'Helvetica', 'I', 7 );
		$pdf->SetTextColor( 130, 130, 130 );
		$pdf->MultiCell( 0, 4, self::t( 'Los precios mostrados son referenciales y están sujetos a confirmación de disponibilidad de inventario. Válido por 7 días desde la fecha de emisión. ' . get_bloginfo( 'name' ) ) );

		return $pdf->Output( 'S' );
	}

	/**
	 * @return string|false Ruta del archivo temporal, o false si falló.
	 */
	public static function save_temp( $quote_id ) {
		$data = self::render( $quote_id );
		if ( $data === '' ) return false;
		$dir  = get_temp_dir();
		$path = trailingslashit( $dir ) . self::filename( $quote_id );
		return file_put_contents( $path, $data ) === false ? false : $path;
	}
}
