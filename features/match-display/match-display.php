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
require_once __DIR__ . '/rest-live.php'; // REST fragment live (3e-iii) — rejestruje się na rest_api_init.

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

	// Poller auto-refreshu (3e-iii) — TYLKO gdy mecz jest na żywo (płaska meta
	// `status` ∈ kody live). Po FT/NS nie ładujemy go w ogóle. Zależy od
	// match-display.js (re-animacja statystyk po podmianie fragmentu).
	$short = (string) get_post_meta( get_queried_object_id(), 'status', true );
	if ( in_array( $short, hajlajty_status_live_codes(), true ) ) {
		// Efekty realnych zdarzeń (MVP-b) — tylko gdy live; klasy stanu nadaje poller.
		$live_css  = 'assets/styles/live-effects.css';
		$live_cssp = get_theme_file_path( $live_css );
		if ( is_readable( $live_cssp ) ) {
			wp_enqueue_style( 'hajlajty-live-effects', get_theme_file_uri( $live_css ), array( 'hajlajty-match-single' ), (string) filemtime( $live_cssp ) );
		}

		$live_js   = 'assets/js/live-refresh.js';
		$live_path = get_theme_file_path( $live_js );
		if ( is_readable( $live_path ) ) {
			wp_enqueue_script( 'hajlajty-live-refresh', get_theme_file_uri( $live_js ), array( 'hajlajty-match-display' ), (string) filemtime( $live_path ), true );
		}
	}
}
