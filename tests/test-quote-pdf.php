<?php
/** wp eval-file tests/test-quote-pdf.php — generación del PDF de cotización. */
$GLOBALS['gloq_fail'] = 0;
function chk( $label, $cond ) {
	echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
	if ( ! $cond ) $GLOBALS['gloq_fail']++;
}

chk( 'la clase existe', class_exists( 'Glotracol_Quote_PDF' ) );

$qid = (int) $GLOBALS['wpdb']->get_var(
	"SELECT ID FROM {$GLOBALS['wpdb']->posts} WHERE post_type='glo_quote' ORDER BY ID DESC LIMIT 1"
);
chk( 'hay una cotización para probar', $qid > 0 );

if ( $qid > 0 && class_exists( 'Glotracol_Quote_PDF' ) ) {
	$pdf = Glotracol_Quote_PDF::render( $qid );
	chk( 'devuelve un string', is_string( $pdf ) );
	chk( 'empieza por %PDF', substr( $pdf, 0, 4 ) === '%PDF' );
	chk( 'pesa más de 800 bytes', strlen( $pdf ) > 800 );

	$path = Glotracol_Quote_PDF::save_temp( $qid );
	chk( 'save_temp devuelve una ruta existente', $path && file_exists( $path ) );
	if ( $path && file_exists( $path ) ) {
		chk( 'el archivo temporal no está vacío', filesize( $path ) > 800 );
		unlink( $path );
	}

	$name = Glotracol_Quote_PDF::filename( $qid );
	chk( 'el nombre termina en .pdf', substr( $name, -4 ) === '.pdf' );
	chk( 'el nombre lleva el número', strpos( $name, (string) $qid ) !== false );

	// Un ID inexistente no debe reventar.
	$vacio = Glotracol_Quote_PDF::render( 999999999 );
	chk( 'ID inexistente devuelve string vacío', $vacio === '' );
}

echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
