<?php
/**
 * Slice „layout" — globalna powłoka serwisu (header/topbar, sidebar+nav, scrim,
 * footer) i jej zasoby. Wyłącznie prezentacja powłoki, reużywana przez każdy
 * widok (single 3b, listy 3d). Partiale w partials/ ładowane przez szablony
 * w roocie motywu przez get_template_part(); ten plik jest właścicielem ENQUEUE
 * globalnych zasobów powłoki.
 *
 * Co GLOBALNE (ten slice): tokens.css + base.css (fundament), layout.css
 * (shell), layout.js (motyw/sidebar/scrollbary). Zasoby WIDOKU meczu
 * (match-single.css/js) enqueue'uje slice match-display, warunkowo na single.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'hajlajty_layout_enqueue' );

/**
 * Klucz localStorage motywu — JEDNO źródło prawdy. Używają go DWA miejsca:
 * skrypt anti-FOUC w <head> (partials/header.php, wypisuje go inline przed
 * pierwszym paintem) oraz toggle w assets/js/layout.js (dostaje go przez
 * wp_localize_script — patrz niżej). Wcześniej literał "hajlajty:theme" żył w
 * obu plikach; rename jednego cicho rozjechałby czytelnika (head) z zapisującym
 * (toggle) i przywrócił FOUC. Teraz oba wywodzą się stąd.
 */
function hajlajty_theme_store_key() {
	return 'hajlajty:theme';
}

/**
 * Globalne zasoby powłoki. Wersjonowanie po filemtime, żeby cache nie trzymał
 * starego CSS po edycji (dev-friendly; produkcyjnie i tak działa). Kolejność
 * ładowania CSS pilnowana zależnościami: base po tokens, layout po base.
 */
function hajlajty_layout_enqueue() {
	// Font Manrope (jak w designie) — preconnect + arkusz Google Fonts.
	wp_enqueue_style(
		'hajlajty-manrope',
		'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap',
		array(),
		null
	);

	$styles = array(
		'hajlajty-tokens' => array( 'assets/styles/tokens.css', array() ),
		'hajlajty-base'   => array( 'assets/styles/base.css', array( 'hajlajty-tokens' ) ),
		'hajlajty-layout' => array( 'assets/styles/layout.css', array( 'hajlajty-base' ) ),
		// Trim launchowy (MVP-a) — TYMCZASOWY: ukrycie afordancji konta + boks
		// „wkrótce". Po hajlajty-user usuwamy plik i ten wpis. Po layout (tokeny gotowe).
		'hajlajty-launch-trim' => array( 'assets/styles/launch-trim.css', array( 'hajlajty-layout' ) ),
	);
	foreach ( $styles as $handle => $def ) {
		$path = get_theme_file_path( $def[0] );
		wp_enqueue_style(
			$handle,
			get_theme_file_uri( $def[0] ),
			$def[1],
			is_readable( $path ) ? (string) filemtime( $path ) : '0.1.0'
		);
	}

	$layout_js = 'assets/js/layout.js';
	$js_path   = get_theme_file_path( $layout_js );
	wp_enqueue_script(
		'hajlajty-layout',
		get_theme_file_uri( $layout_js ),
		array(),
		is_readable( $js_path ) ? (string) filemtime( $js_path ) : '0.1.0',
		true // w stopce: DOM gotowy, uchwyty istnieją.
	);

	// Klucz motywu do toggle'a — z tego samego źródła co skrypt w <head>, żeby
	// zapisujący (toggle) i czytelnik (head) nie mogły się rozjechać.
	wp_localize_script(
		'hajlajty-layout',
		'hajlajtyLayout',
		array( 'themeKey' => hajlajty_theme_store_key() )
	);
}
