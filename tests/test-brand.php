<?php
/**
 * wp eval-file tests/test-brand.php
 *
 * Color de marca y logo configurables: helper de paleta, valores por defecto,
 * saneado de los ajustes y herencia de Elementor.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}
function gloq_lum( $hex ) {
	$hex = ltrim( $hex, '#' );
	return 0.299 * hexdec( substr( $hex, 0, 2 ) ) + 0.587 * hexdec( substr( $hex, 2, 2 ) ) + 0.114 * hexdec( substr( $hex, 4, 2 ) );
}

$OPT  = 'glotracol_quote_settings';
$ORIG = get_option( $OPT );
// Si una aserción revienta con fatal, los ajustes del sitio se restauran igual.
register_shutdown_function( function () use ( $OPT, $ORIG ) { if ( false !== $ORIG ) update_option( $OPT, $ORIG ); else delete_option( $OPT ); } );
$LOGO_ORIG = get_theme_mod( 'custom_logo' );

chk( 'existe glotracol_quote_brand_palette()', function_exists( 'glotracol_quote_brand_palette' ) );
chk( 'existe glotracol_quote_brand()', function_exists( 'glotracol_quote_brand' ) );

// ---------- 1. Paleta derivada ----------
if ( function_exists( 'glotracol_quote_brand_palette' ) ) {
	$p = glotracol_quote_brand_palette( '#F2A649' );
	chk( 'la paleta normaliza el color a minúsculas', ( $p['color'] ?? '' ) === '#f2a649' );
	chk( 'dark es más oscuro que el color', isset( $p['dark'] ) && gloq_lum( $p['dark'] ) < gloq_lum( '#f2a649' ) );
	chk( 'tint es más claro que el color', isset( $p['tint'] ) && gloq_lum( $p['tint'] ) > gloq_lum( '#f2a649' ) );
	chk( 'sobre naranja el texto va oscuro', ( $p['text'] ?? '' ) === '#1a1a1a' );
	chk( 'rgb devuelve tres enteros', ( $p['rgb'] ?? null ) === [ 242, 166, 73 ] );
	chk( 'line es un borde claro entre tint y color', isset( $p['line'] ) && gloq_lum( $p['line'] ) > gloq_lum( '#f2a649' ) && gloq_lum( $p['line'] ) < gloq_lum( $p['tint'] ) );

	$v = glotracol_quote_brand_palette( '#0a4d3a' );
	chk( 'sobre verde oscuro el texto va blanco', ( $v['text'] ?? '' ) === '#ffffff' );

	$bad = glotracol_quote_brand_palette( 'rojo' );
	chk( 'un color inválido cae al naranja por defecto', ( $bad['color'] ?? '' ) === '#f2a649' );
	$short = glotracol_quote_brand_palette( '#fa0' );
	chk( 'acepta hex corto', ( $short['color'] ?? '' ) === '#ffaa00' );
}

// ---------- 2. Ajuste por defecto y logo ----------
update_option( $OPT, is_array( $ORIG ) ? array_diff_key( $ORIG, [ 'brand_color' => 1, 'brand_logo_id' => 1, 'appearance_inherit_elementor' => 1 ] ) : [] );
if ( function_exists( 'glotracol_quote_brand' ) ) {
	$b = glotracol_quote_brand();
	chk( 'sin ajuste, el color de marca es #f2a649', ( $b['color'] ?? '' ) === '#f2a649' );

	$logo_id = (int) $LOGO_ORIG;
	if ( $logo_id ) {
		chk( 'sin logo propio usa el logo del sitio', ( $b['logo_url'] ?? '' ) === wp_get_attachment_url( $logo_id ) );
		chk( 'logo_path apunta a un archivo existente', ! empty( $b['logo_path'] ) && file_exists( $b['logo_path'] ) );
	} else {
		echo "[SKIP] el sitio no tiene custom_logo; no se prueba el fallback del logo\n";
	}

	set_theme_mod( 'custom_logo', 0 );
	$b0 = glotracol_quote_brand();
	chk( 'sin ningún logo, logo_url es vacío', ( $b0['logo_url'] ?? 'x' ) === '' );
	set_theme_mod( 'custom_logo', $LOGO_ORIG );

	// Ajuste explícito
	$s = glotracol_quote_get_settings();
	$s['brand_color'] = '#123456';
	update_option( $OPT, $s );
	chk( 'el ajuste brand_color manda', ( glotracol_quote_brand()['color'] ?? '' ) === '#123456' );

	// Herencia de Elementor gana sobre el ajuste fijo si el slot existe
	$globals = glotracol_quote_elementor_global_colors();
	$slot = null;
	foreach ( $globals as $g ) { if ( strtolower( $g['color'] ) === '#f2a649' ) { $slot = $g['id']; break; } }
	if ( $slot ) {
		$s['appearance_inherit_elementor'] = 'yes';
		$s['appearance_elementor_slot']    = $slot;
		update_option( $OPT, $s );
		chk( 'con herencia activa, el color viene del kit de Elementor', ( glotracol_quote_brand()['color'] ?? '' ) === '#f2a649' );
		$s['appearance_elementor_slot'] = 'no-existe-xyz';
		update_option( $OPT, $s );
		chk( 'si el slot no existe, cae al ajuste fijo', ( glotracol_quote_brand()['color'] ?? '' ) === '#123456' );
	} else {
		echo "[SKIP] el kit de Elementor no tiene el naranja; no se prueba la herencia\n";
	}
}

// ---------- 3. Saneado ----------
$S = new Glotracol_Quote_Admin_Settings();
$out = $S->sanitize( [ '__tab' => 'appearance', 'brand_color' => '#ABCDEF', 'brand_logo_id' => '12abc' ] );
chk( 'brand_color se guarda en minúsculas', ( $out['brand_color'] ?? '' ) === '#abcdef' );
chk( 'brand_logo_id se guarda como entero', ( $out['brand_logo_id'] ?? null ) === 12 );
$out = $S->sanitize( [ '__tab' => 'appearance', 'brand_color' => 'javascript:x', 'brand_logo_id' => '-3' ] );
chk( 'brand_color inválido cae al naranja', ( $out['brand_color'] ?? '' ) === '#f2a649' );
chk( 'brand_logo_id negativo queda en 0', ( $out['brand_logo_id'] ?? null ) === 0 );
chk( 'los dos campos están en la pestaña Apariencia del mapa', in_array( 'brand_color', Glotracol_Quote_Admin_Settings::TAB_FIELDS['appearance'] ?? [], true ) && in_array( 'brand_logo_id', Glotracol_Quote_Admin_Settings::TAB_FIELDS['appearance'] ?? [], true ) );

if ( false !== $ORIG ) update_option( $OPT, $ORIG ); else delete_option( $OPT );
chk( 'ajustes del sitio restaurados', get_option( $OPT ) === $ORIG );
echo ( $GLOBALS['gloq_fail'] === 0 ? "TODO OK\n" : "HAY {$GLOBALS['gloq_fail']} FALLOS\n" );
