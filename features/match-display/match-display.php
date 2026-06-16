<?php
/**
 * Slice „match-display" — publiczny render meczu (READ-ONLY). Punkt wejścia:
 * dociąga własną logikę (helpers/lookups/derive — czyste funkcje z Fazy 3a/3b)
 * i enqueue'uje zasoby WIDOKU meczu warunkowo, tylko na single CPT „mecz".
 *
 * Granica wobec slice'a layout: layout = globalna powłoka (tokens/base/shell);
 * match-display = wszystko, co znika razem z widokiem meczu (match-single.css/js,
 * karty card-highlight w prawym aside). Zgodnie z CLAUDE.md (vertical slice).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Logika slice'a — czyste funkcje, bez efektów ubocznych przy require.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/lookups.php';

// derive.php dochodzi w E4/E5 (oś czasu + indeks zdarzeń zawodnika). Require
// warunkowy, żeby slice ładował się spójnie zanim plik powstanie.
$hajlajty_derive = __DIR__ . '/derive.php';
if ( is_readable( $hajlajty_derive ) ) {
	require_once $hajlajty_derive;
}

add_action( 'wp_enqueue_scripts', 'hajlajty_match_display_enqueue' );

/**
 * Zasoby widoku meczu — TYLKO na single „mecz" (nie obciążają reszty serwisu).
 * Każdy plik ładowany warunkowo (is_readable): rdzeń CSS i JS dochodzą w E3,
 * karty card-highlight w E6 — do tego czasu enqueue po prostu ich nie dotyka.
 * Zależność CSS: match-single po layout (handle 'hajlajty-layout' z layout slice).
 */
function hajlajty_match_display_enqueue() {
	if ( ! is_singular( 'mecz' ) ) {
		return;
	}

	$styles = array(
		'hajlajty-card-highlight' => array( 'assets/styles/card-highlight.css', array( 'hajlajty-base' ) ),
		'hajlajty-match-single'   => array( 'assets/styles/match-single.css', array( 'hajlajty-layout' ) ),
	);
	foreach ( $styles as $handle => $def ) {
		$path = get_theme_file_path( $def[0] );
		if ( ! is_readable( $path ) ) {
			continue;
		}
		wp_enqueue_style( $handle, get_theme_file_uri( $def[0] ), $def[1], (string) filemtime( $path ) );
	}

	$js   = 'assets/js/match-display.js';
	$path = get_theme_file_path( $js );
	if ( is_readable( $path ) ) {
		wp_enqueue_script( 'hajlajty-match-display', get_theme_file_uri( $js ), array(), (string) filemtime( $path ), true );
	}
}
