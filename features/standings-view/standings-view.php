<?php
/**
 * Slice „standings-view" — publiczny render tabeli grupowej (READ-ONLY). Punkt
 * wejścia: dociąga własną logikę (czyste funkcje stref + warstwa odczytu + pola
 * strony) i enqueue'uje CSS WIDOKU warunkowo, tylko na szablonie tabeli.
 *
 * Tor WIDOKU: czyta meta `standings_<sezon>` z termu „rozgrywki" (zapis MVP-d,
 * core) i renderuje 12 kart grup A–L. NIE pobiera z API, NIE zapisuje, NIE dotyka
 * core. Markup statyczny (serwerowy) — bez warstwy JS wyszukiwarki/filtra grup.
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

add_action( 'wp_enqueue_scripts', 'hajlajty_standings_view_enqueue' );

/**
 * CSS tabeli grup — TYLKO na szablonie „Tabela rozgrywek" (nie obciąża reszty
 * serwisu). Zależny od bazowej powłoki ('hajlajty-layout'); wersja po filemtime
 * (dev-friendly). Wzór: match-lists.php / match-display.php. Bez JS — render statyczny.
 */
function hajlajty_standings_view_enqueue() {
	if ( ! is_page_template( HAJLAJTY_STANDINGS_VIEW_TEMPLATE ) ) {
		return;
	}

	$css  = 'assets/styles/standings.css';
	$path = get_theme_file_path( $css );
	if ( is_readable( $path ) ) {
		wp_enqueue_style( 'hajlajty-standings', get_theme_file_uri( $css ), array( 'hajlajty-layout' ), (string) filemtime( $path ) );
	}
}
