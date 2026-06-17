<?php
// Brak bezpośredniego dostępu.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cienki bootstrap motywu. Żadnej logiki biznesowej tutaj — tylko deklaracja
 * wsparcia motywu i ładowanie slice'ów z katalogu features/ (vertical slice).
 * Patrz hajlajty-meta/CLAUDE.md.
 */

/**
 * Wsparcie motywu. Minimalny, klasyczny zestaw (bez FSE/bloków):
 *  - title-tag: <title> generuje WP (slice layout woła wp_head()).
 *  - post-thumbnails: miniatury meczu (CPT mecz wspiera thumbnail — Faza 1).
 *  - html5: czysty markup formularzy/komentarzy/galerii.
 */
add_action( 'after_setup_theme', 'hajlajty_theme_setup' );
function hajlajty_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
}

/**
 * Autoloader slice'ów — IDENTYCZNY wzorzec co hajlajty-core: dla każdego
 * katalogu w features/ ładuje punkt wejścia o nazwie zgodnej z katalogiem
 * (features/layout/layout.php, features/match-display/match-display.php).
 *
 * Wybór: glob po features/* zamiast jawnej listy require — prostszy i spójny
 * z bootstrapem core (jedna konwencja w obu repo: nowy slice = nowy katalog,
 * zero edycji bootstrapu). Slice jest właścicielem tego, co ładuje: to JEGO
 * punkt wejścia dociąga własne pliki (helpers.php, partials itd.). Tu, w
 * bootstrapie, żadnej logiki — tylko require punktów wejścia.
 */
foreach ( glob( get_theme_file_path( 'features/*' ), GLOB_ONLYDIR ) as $slice_dir ) {
	$entry = $slice_dir . '/' . basename( $slice_dir ) . '.php';
	if ( is_readable( $entry ) ) {
		require_once $entry;
	}
}
