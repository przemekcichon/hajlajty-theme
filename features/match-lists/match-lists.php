<?php
/**
 * Slice „match-lists" — publiczne LISTY meczów (READ-ONLY): archiwum kategorii
 * (/na-zywo/, /zapowiedzi/, /skroty/) i sekcje strony głównej. Punkt wejścia
 * slice'a: dociąga własny batch-resolver drużyn i:
 *  - routuje trzy ładne URL-e na archiwum CPT „mecz" z query var `hajlajty_lista`,
 *  - kształtuje GŁÓWNE zapytanie archiwum (meta_query + orderby) wg stanu listy,
 *  - enqueue'uje CSS kart i mały JS odliczania — tylko na archiwum i stronie głównej.
 *
 * Granica wobec slice'a match-display: tamten renderuje POJEDYNCZY mecz; ten
 * renderuje LISTY (karty). Warstwa danych (helpers/lookups/derive z match-display)
 * jest REUŻYWANA bez modyfikacji — markup kart jest tu zduplikowany świadomie (VSA).
 *
 * Stan listy LIVE to PLACEHOLDER 3e: brak płaskiego pola statusu, więc okno czasowe
 * po `kickoff` to przybliżenie — do zastąpienia realnym statusem w 3e.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/terms.php';

/**
 * Okno LIVE (minuty wstecz od kickoffu): mecz traktujemy jako „trwający", gdy
 * kickoff mieści się między (teraz − OKNO) a teraz. ~2,5h pokrywa grę + przerwę
 * + doliczony + zapas. Świadome przybliżenie do czasu 3e (realny status).
 */
const HAJLAJTY_LISTS_LIVE_WINDOW_MIN = 150;

/* ============================================================
   1a. ROUTING — ładne URL-e → archiwum CPT „mecz" + query var.
   Core nietknięty: rewrite żyje w motywie. Flush robi człowiek
   (`wp rewrite flush`) — patrz instrukcje weryfikacji.
============================================================ */

add_filter( 'query_vars', 'hajlajty_match_lists_query_vars' );
/**
 * Rejestruje `hajlajty_lista` jako rozpoznawany query var (live|zapowiedzi|skroty).
 *
 * @param string[] $vars Lista publicznych query vars.
 * @return string[] Lista z dorzuconym `hajlajty_lista`.
 */
function hajlajty_match_lists_query_vars( $vars ) {
	$vars[] = 'hajlajty_lista';
	return $vars;
}

add_action( 'init', 'hajlajty_match_lists_rewrite_rules' );
/**
 * Trzy ładne URL-e listy → archiwum CPT „mecz" z wariantem w `hajlajty_lista`.
 * `post_type=mecz` wymusza is_post_type_archive('mecz') (CPT ma has_archive),
 * więc szablon archive-mecz.php obsłuży wszystkie trzy. Domyślne /mecz/ (bez var)
 * potraktujemy w pre_get_posts jako „skroty". Dla KAŻDEGO slug-a rejestrujemy też
 * regułę stronicowania (/slug/page/N/ → paged=N), bo bazowa reguła ma kotwicę `$`
 * i nie złapałaby 2. strony listy.
 */
function hajlajty_match_lists_rewrite_rules() {
	$map = array(
		'na-zywo'    => 'live',
		'zapowiedzi' => 'zapowiedzi',
		'skroty'     => 'skroty',
	);
	foreach ( $map as $slug => $lista ) {
		// Reguła STRONICOWANIA pierwsza (bardziej szczegółowa): /slug/page/N/ →
		// paged=N. Bez niej WP nie zna tego URL-a → 404 na 2. stronie listy
		// (the_posts_pagination buduje linki w formacie .../page/N/).
		add_rewrite_rule(
			'^' . $slug . '/page/([0-9]+)/?$',
			'index.php?post_type=mecz&hajlajty_lista=' . $lista . '&paged=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^' . $slug . '/?$',
			'index.php?post_type=mecz&hajlajty_lista=' . $lista,
			'top'
		);
	}
}

/* ============================================================
   1b. KSZTAŁTOWANIE GŁÓWNEGO ZAPYTANIA ARCHIWUM.
============================================================ */

add_action( 'pre_get_posts', 'hajlajty_match_lists_pre_get_posts' );
/**
 * Ustawia meta_query + orderby głównego zapytania archiwum „mecz" wg stanu listy.
 * NIE ustawia no_found_rows — archiwum paginuje (the_posts_pagination potrzebuje
 * found_rows).
 *
 * KONWENCJA CZASU: `kickoff` to płaska meta `Y-m-d H:i:s` w UTC, zero-padded —
 * porównania i sortowanie LEKSYKALNE (type CHAR) są chronologiczne. NIGDY _num.
 *
 * @param WP_Query $q Zapytanie.
 */
function hajlajty_match_lists_pre_get_posts( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_post_type_archive( 'mecz' ) ) {
		return;
	}

	$lista = (string) $q->get( 'hajlajty_lista' );
	if ( '' === $lista ) {
		$lista = 'skroty'; // Domyślnie (gołe /mecz/) — najczęstszy widok.
	}

	$now = gmdate( 'Y-m-d H:i:s' );

	switch ( $lista ) {
		case 'zapowiedzi':
			// Mecze jeszcze nierozegrane: kickoff >= teraz. Najbliższe u góry.
			$q->set(
				'meta_query',
				array(
					'kick' => array(
						'key'     => 'kickoff',
						'value'   => $now,
						'compare' => '>=',
						'type'    => 'CHAR',
					),
				)
			);
			$q->set( 'orderby', array( 'kick' => 'ASC' ) );
			break;

		case 'live':
			// PLACEHOLDER 3e: brak płaskiego statusu → okno czasowe wokół kickoffu.
			$start = gmdate( 'Y-m-d H:i:s', time() - HAJLAJTY_LISTS_LIVE_WINDOW_MIN * 60 );
			$q->set(
				'meta_query',
				array(
					'kick' => array(
						'key'     => 'kickoff',
						'value'   => array( $start, $now ),
						'compare' => 'BETWEEN',
						'type'    => 'CHAR',
					),
				)
			);
			$q->set( 'orderby', array( 'kick' => 'ASC' ) );
			break;

		case 'skroty':
		default:
			// „Ma wideo" = niepuste skrot_url (decyzja #9). Wymagamy też kickoffa,
			// bo po nim sortujemy (najnowsze skróty u góry).
			$q->set(
				'meta_query',
				array(
					'relation' => 'AND',
					'skrot'    => array(
						'key'     => 'skrot_url',
						'value'   => '',
						'compare' => '!=',
					),
					'kick'     => array(
						'key'     => 'kickoff',
						'compare' => 'EXISTS',
					),
				)
			);
			$q->set( 'orderby', array( 'kick' => 'DESC' ) );
			break;
	}
}

/* ============================================================
   1d. ENQUEUE — CSS kart + JS odliczania, tylko gdzie potrzeba.
   Wzorzec 1:1 z match-display.php (filemtime, dep 'hajlajty-layout').
============================================================ */

add_action( 'wp_enqueue_scripts', 'hajlajty_match_lists_enqueue' );
/**
 * Zasoby LIST — tylko na archiwum „mecz" i stronie głównej (nie obciążają reszty).
 * CSS sportowany z designu do assets/styles/ motywu (NIE ładujemy z design/).
 */
function hajlajty_match_lists_enqueue() {
	$is_archive = is_post_type_archive( 'mecz' );
	if ( ! $is_archive && ! is_front_page() ) {
		return;
	}

	// CSS współdzielony przez archiwum i stronę główną.
	$styles = array(
		'hajlajty-card-preview' => array( 'assets/styles/card-preview.css', array( 'hajlajty-layout' ) ),
		'hajlajty-match-lists'  => array( 'assets/styles/match-lists.css', array( 'hajlajty-layout' ) ),
	);
	// Paginacja tylko na archiwum (strona główna nie paginuje sekcji).
	if ( $is_archive ) {
		$styles['hajlajty-pagination'] = array( 'assets/styles/pagination.css', array( 'hajlajty-layout' ) );
	}

	foreach ( $styles as $handle => $def ) {
		$path = get_theme_file_path( $def[0] );
		if ( ! is_readable( $path ) ) {
			continue;
		}
		wp_enqueue_style( $handle, get_theme_file_uri( $def[0] ), $def[1], (string) filemtime( $path ) );
	}

	$js   = 'assets/js/match-lists.js';
	$path = get_theme_file_path( $js );
	if ( is_readable( $path ) ) {
		wp_enqueue_script( 'hajlajty-match-lists', get_theme_file_uri( $js ), array(), (string) filemtime( $path ), true );
	}
}
