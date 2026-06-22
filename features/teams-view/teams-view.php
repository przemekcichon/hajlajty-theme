<?php
/**
 * Slice „teams-view" — publiczny render Reprezentacji i Profilu kraju (READ-ONLY,
 * MVP-g). KONSUMENT danych zapisanych przez producentów na main: statystyki
 * drużyny (MVP-f), tabela grup (MVP-d), mecze drużyny (import). NIE pobiera z API,
 * NIE zapisuje, NIE dotyka core.
 *
 * Dwa widoki (oba app-shell, jak terminarz/tabele — trwały sidebar ≥1100px):
 *  - PROFIL KRAJU = archiwum termu taksonomii `druzyna` → szablon root
 *    `taxonomy-druzyna.php` deleguje do partials/profile.php (BEZ nowego CPT; #7
 *    stabilny URL = archiwum termu),
 *  - REPREZENTACJE = Page Template root `template-reprezentacje.php` → partials/
 *    reprezentacje.php (redaktor tworzy Stronę pod slug `reprezentacje`).
 *
 * Granica wobec layout: layout = globalna powłoka; teams-view = wszystko, co znika
 * razem z tymi widokami (teams.css, partiale, readery). Flagi/nazwy/kody/lookupy
 * REUŻYWAMY z match-display + match-lists (współdzielona infra display) — zero
 * duplikacji mapy FIFA (CLAUDE.md „Lokalizacja nazw").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Logika slice'a — czyste funkcje / odczyt, bez efektów ubocznych przy require.
require_once __DIR__ . '/stats.php';
require_once __DIR__ . '/data.php';

/** Nazwa pliku Page Template listy Reprezentacje (root motywu — skan głębokości 1). */
const HAJLAJTY_TEAMS_VIEW_TEMPLATE = 'template-reprezentacje.php';

/**
 * Czy bieżący widok to Profil kraju (archiwum termu „druzyna").
 */
function hajlajty_teams_view_is_profile(): bool {
	return is_tax( 'druzyna' );
}

/**
 * Czy bieżący widok to lista Reprezentacje (Page Template).
 */
function hajlajty_teams_view_is_list(): bool {
	return is_page_template( HAJLAJTY_TEAMS_VIEW_TEMPLATE );
}

add_filter( 'body_class', 'hajlajty_teams_view_body_class' );
/**
 * Klasy body „app-shell" — włączają trwały sidebar + treść na całą szerokość przy
 * ≥1100px (layout.css KONSUMUJE te klasy, jak `hajlajty-tabela-rozgrywek`). Slice
 * ma wiedzę „to Profil/Reprezentacje"; layout.css tylko reaguje (luźne sprzężenie).
 *
 * @param string[] $classes Klasy body.
 * @return string[]
 */
function hajlajty_teams_view_body_class( $classes ) {
	if ( hajlajty_teams_view_is_profile() ) {
		$classes[] = 'hajlajty-profil-druzyny';
	} elseif ( hajlajty_teams_view_is_list() ) {
		$classes[] = 'hajlajty-reprezentacje';
	}
	return $classes;
}

add_action( 'wp_enqueue_scripts', 'hajlajty_teams_view_enqueue' );
/**
 * Zasoby widoków teams-view — TYLKO na Profilu/Reprezentacjach (nie obciążają
 * reszty serwisu). teams.css wszędzie; karty meczów na Profilu REUŻYWAJĄ stylów i
 * JS list (card-preview/match-lists/terminarz + odliczanie), więc dociągamy je
 * warunkowo tu (Profil renderuje te same partiale kart co archiwum/terminarz).
 * Wzór enqueue: standings-view.php / match-lists.php (filemtime, dep hajlajty-layout).
 */
function hajlajty_teams_view_enqueue() {
	$is_profile = hajlajty_teams_view_is_profile();
	$is_list    = hajlajty_teams_view_is_list();
	if ( ! $is_profile && ! $is_list ) {
		return;
	}

	$styles = array(
		'hajlajty-teams' => array( 'assets/styles/teams.css', array( 'hajlajty-layout' ) ),
	);

	// Profil renderuje karty meczów (zapowiedź/skrót/wynik) — te same partiale co
	// listy, więc te same style + JS odliczania. Reprezentacje kart nie pokazuje.
	if ( $is_profile ) {
		$styles['hajlajty-card-preview'] = array( 'assets/styles/card-preview.css', array( 'hajlajty-layout' ) );
		$styles['hajlajty-match-lists']  = array( 'assets/styles/match-lists.css', array( 'hajlajty-layout' ) );
		$styles['hajlajty-terminarz']    = array( 'assets/styles/terminarz.css', array( 'hajlajty-match-lists', 'hajlajty-layout' ) );
	}

	foreach ( $styles as $handle => $def ) {
		$path = get_theme_file_path( $def[0] );
		if ( ! is_readable( $path ) ) {
			continue;
		}
		wp_enqueue_style( $handle, get_theme_file_uri( $def[0] ), $def[1], (string) filemtime( $path ) );
	}

	if ( $is_profile ) {
		$js   = 'assets/js/match-lists.js';
		$path = get_theme_file_path( $js );
		if ( is_readable( $path ) ) {
			wp_enqueue_script( 'hajlajty-match-lists', get_theme_file_uri( $js ), array(), (string) filemtime( $path ), true );
		}
	}
}
