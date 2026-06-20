<?php
/**
 * Slice „filters" — PUBLICZNY filtr LIST meczów (READ-ONLY, KLIENCKI). Warstwa
 * FILTRA, świadomie oddzielona od warstwy RENDERU list (slice match-lists) —
 * granica vertical slice: match-lists produkuje karty + ich `data-*`, filters
 * dokłada nad nimi chipsbar, pole szukania i lekki JS, który ZAWĘŻA już
 * wyrenderowane karty.
 *
 * Wariant LEKKI (USTALENIA 4A, plan): chip = LEPKI filtr kliencki w
 * `sessionStorage` — NIE nawiguje, NIE robi tax_query, NIE tworzy archiwów
 * taksonomii ani rewrite. Serwer renderuje pełną listę stanu jak dziś; JS tylko
 * pokazuje/ukrywa karty. Headless-friendly: filtr żyje na `data-*`, które
 * produkują natywne taksonomie WP (te same dane pójdą przez WPGraphQL).
 *
 * Bootstrap CIENKI (CLAUDE.md): tylko dociąga pliki slice'a. Logika w:
 *  - normalize.php — normalizator nazw PL (kontrakt PHP↔JS wyszukiwania),
 *  - ui.php        — render chipsbara + pola szukania (dokładane przez szablony list),
 *  - assets/       — filters.js (lepki filtr) + filters.css (port z designu).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/normalize.php';
require_once __DIR__ . '/ui.php';

add_action( 'wp_enqueue_scripts', 'hajlajty_filters_enqueue' );
/**
 * Zasoby filtra — TYLKO na widokach LIST (archiwum „mecz" + strona główna),
 * dokładnie jak slice match-lists. Ten sam warunek z natury wyklucza single
 * (`is_singular('mecz')`), więc na meczu paska/JS nie ma. Wzorzec 1:1 z
 * match-lists.php: filemtime jako wersja, CSS zależny od „hajlajty-layout", JS
 * w stopce (DOM gotowy — karty i pasek istnieją).
 */
function hajlajty_filters_enqueue() {
	if ( ! is_post_type_archive( 'mecz' ) && ! is_front_page() ) {
		return;
	}

	$css  = 'features/filters/assets/filters.css';
	$path = get_theme_file_path( $css );
	if ( is_readable( $path ) ) {
		wp_enqueue_style( 'hajlajty-filters', get_theme_file_uri( $css ), array( 'hajlajty-layout' ), (string) filemtime( $path ) );
	}

	$js   = 'features/filters/assets/filters.js';
	$path = get_theme_file_path( $js );
	if ( is_readable( $path ) ) {
		wp_enqueue_script( 'hajlajty-filters', get_theme_file_uri( $js ), array(), (string) filemtime( $path ), true );
	}
}
