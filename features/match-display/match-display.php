<?php
/**
 * Slice „match-display" — publiczny render meczu (READ-ONLY). Punkt wejścia:
 * dociąga własną logikę (helpers/lookups/derive — czyste funkcje z Fazy 3a/3b)
 * i enqueue'uje zasoby WIDOKU meczu warunkowo, tylko na single CPT „mecz".
 *
 * Granica wobec slice'a layout: layout = globalna powłoka (tokens/base/shell);
 * match-display = wszystko, co znika razem z widokiem meczu (match-single.css/js,
 * w tym kompaktowe wiersze .rvideo prawego aside). Zgodnie z CLAUDE.md (vertical slice).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Logika slice'a — czyste funkcje, bez efektów ubocznych przy require.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/lookups.php';
require_once __DIR__ . '/derive.php';
require_once __DIR__ . '/flags.php';

add_action( 'wp_enqueue_scripts', 'hajlajty_match_display_enqueue' );

/**
 * Zasoby widoku meczu — TYLKO na single „mecz" (nie obciążają reszty serwisu).
 * Rdzeń single (z kompaktowym aside .rvideo) i JS w match-single.css/js. NIE
 * ładujemy tu card-highlight.css — duża karta należy do home/list (3d), nie do
 * pojedynczego meczu. Zależność CSS: match-single po layout ('hajlajty-layout').
 */
function hajlajty_match_display_enqueue() {
	if ( ! is_singular( 'mecz' ) ) {
		return;
	}

	$styles = array(
		'hajlajty-match-single' => array( 'assets/styles/match-single.css', array( 'hajlajty-layout' ) ),
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
