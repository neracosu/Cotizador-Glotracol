<?php
/**
 * wp eval-file tests/test-activacion-paginas.php
 *
 * Crear las paginas del plugin no pisa paginas existentes con el mismo slug: las adopta
 * solo si ya llevan el shortcode (tambien dentro de Elementor); si no, crea otra.
 * Y el panel no se recrea solo si el administrador lo borro.
 */
$GLOBALS['gloq_fail'] = 0;
if ( ! function_exists( 'chk' ) ) {
	function chk( $label, $cond ) {
		echo ( $cond ? "[OK] " : "[FAIL] " ) . $label . "\n";
		if ( ! $cond ) $GLOBALS['gloq_fail']++;
	}
}
$tag  = strtolower( wp_generate_password( 6, false, false ) );
$opt  = 'gloq_test_page_' . $tag;
$slug = 'test-pagina-' . $tag;
$sc   = '[gloq_test_' . $tag . ']';

// 1. Slug ocupado por una pagina ajena: no se toca, se crea otra.
$ajena = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Ajena', 'post_name' => $slug, 'post_content' => 'Contenido del cliente' ] );
$id = Glotracol_Quote_Activator::ensure_page( $opt, 'Prueba', $slug, $sc );
chk( 'no reutiliza la pagina ajena', $id && $id !== $ajena );
chk( 'la pagina ajena conserva su contenido', get_post_field( 'post_content', $ajena ) === 'Contenido del cliente' );
chk( 'la nueva lleva el shortcode', strpos( get_post_field( 'post_content', $id ), $sc ) !== false );
wp_delete_post( $id, true );
delete_option( $opt );

// 2. Pagina de Elementor con el shortcode solo en _elementor_data: se adopta.
wp_update_post( [ 'ID' => $ajena, 'post_content' => '' ] );
update_post_meta( $ajena, '_elementor_data', wp_slash( wp_json_encode( [ [ 'elType' => 'widget', 'settings' => [ 'shortcode' => $sc ] ] ] ) ) );
$id = Glotracol_Quote_Activator::ensure_page( $opt, 'Prueba', $slug, $sc );
chk( 'adopta la pagina de Elementor que ya tiene el shortcode', $id === $ajena );
chk( 'y no le cambia el contenido', get_post_field( 'post_content', $ajena ) === '' );

// 3. Pagina borrada por el admin: sin $recreate no vuelve a aparecer.
wp_delete_post( $ajena, true );
$id = Glotracol_Quote_Activator::ensure_page( $opt, 'Prueba', $slug, $sc, false );
chk( 'no recrea una pagina que el administrador borro', $id === 0 && ! get_page_by_path( $slug ) );
$id = Glotracol_Quote_Activator::ensure_page( $opt, 'Prueba', $slug, $sc, true );
chk( 'al activar si la recrea', $id > 0 );
wp_delete_post( $id, true );
delete_option( $opt );
exit( $GLOBALS['gloq_fail'] ? 1 : 0 );
