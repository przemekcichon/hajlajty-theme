<?php
/**
 * Slice „standings-view" — publiczny render tabeli grupowej (READ-ONLY). Punkt
 * wejścia: dociąga własną logikę (czyste funkcje stref + warstwa odczytu + pola
 * strony) i enqueue'uje CSS WIDOKU warunkowo, tylko na szablonie tabeli.
 *
 * Tor WIDOKU: czyta meta `standings_<sezon>` z termu „rozgrywki" (zapis MVP-d,
 * core) i renderuje 12 kart grup A–L. NIE pobiera z API, NIE zapisuje, NIE dotyka
 * core. Render samej tabeli jest STATYCZNY (serwerowy); wyszukiwarkę drużyn i
 * chipsbar dokłada współdzielony slice `filters` (jak na listach) — markup kart
 * niesie `data-teams`/`data-team-names`, a filtr kliencki zawęża karty grup.
 *
 * Powłoka „app-shell" (sidebar trwale odkryty, treść na całą szerokość) jak na
 * terminarzu/archiwach: slice dokłada klasę body `hajlajty-tabela-rozgrywek`,
 * którą KONSUMUJE layout.css (ten sam wzorzec co `hajlajty-terminarz`).
 *
 * Granica wobec layout: layout = globalna powłoka; standings-view = wszystko, co
 * znika razem z widokiem tabeli (standings.css, partial render). Flagę bierze z
 * `hajlajty_flag_url` (match-display/flags.php) — współdzielona infra display
 * motywu, NIE duplikujemy mapy FIFA→ISO (CLAUDE.md „Lokalizacja nazw").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Logika slice'a — czyste funkcje / odczyt, bez efektów ubocznych przy require.
require_once __DIR__ . '/zones.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/meta.php';

/**
 * Czy bieżący widok to Strona tabeli rozgrywek (Page Template MVP-e). Jedno
 * źródło prawdy „to tabela" — konsumują je enqueue, klasa body i (luźno) slice
 * filters. Wzór: hajlajty_match_lists_is_terminarz().
 */
function hajlajty_standings_view_is_table(): bool {
	return is_page_template( HAJLAJTY_STANDINGS_VIEW_TEMPLATE );
}

add_filter( 'body_class', 'hajlajty_standings_view_body_class' );
/**
 * Klasa body „app-shell" — włącza trwały sidebar + treść na całą szerokość przy
 * ≥1100px (layout.css KONSUMUJE `hajlajty-tabela-rozgrywek`, jak `hajlajty-terminarz`).
 * Slice ma wiedzę „to tabela"; layout.css tylko reaguje na klasę (luźne sprzężenie).
 *
 * @param string[] $classes Klasy body.
 * @return string[] Klasy body (z `hajlajty-tabela-rozgrywek` na tym szablonie).
 */
function hajlajty_standings_view_body_class( $classes ) {
	if ( hajlajty_standings_view_is_table() ) {
		$classes[] = 'hajlajty-tabela-rozgrywek';
	}
	return $classes;
}

add_action( 'wp_enqueue_scripts', 'hajlajty_standings_view_enqueue' );

/**
 * CSS tabeli grup — TYLKO na szablonie „Tabela rozgrywek" (nie obciąża reszty
 * serwisu). Zależny od bazowej powłoki ('hajlajty-layout'); wersja po filemtime
 * (dev-friendly). Wzór: match-lists.php / match-display.php. Filtr (chipsbar +
 * wyszukiwarka) ma własny enqueue w slice filters — tu tylko styl tabeli.
 */
function hajlajty_standings_view_enqueue() {
	if ( ! hajlajty_standings_view_is_table() ) {
		return;
	}

	$css  = 'assets/styles/standings.css';
	$path = get_theme_file_path( $css );
	if ( is_readable( $path ) ) {
		wp_enqueue_style( 'hajlajty-standings', get_theme_file_uri( $css ), array( 'hajlajty-layout' ), (string) filemtime( $path ) );
	}
}
