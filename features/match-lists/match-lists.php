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
 * Stan listy LIVE (3e-i): filtr po REALNYM statusie meczu (płaska meta `status`
 * z importu) — `status IN (kody live)`. Zastąpił dawne okno czasowe wokół
 * `kickoff` (placeholder 3d). Świeżość statusu zależy od importu; pętla live to 3e-ii.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/terms.php';
require_once __DIR__ . '/knockout.php';

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
 * Ustawia meta_query + orderby głównego zapytania archiwum „mecz" wg stanu listy
 * ORAZ wymusza render CAŁEJ listy stanu na jednej stronie (bez stronicowania).
 *
 * DLACZEGO bez stronicowania (4A): publiczny filtr (slice filters) jest KLIENCKI —
 * zawęża tylko karty obecne w DOM. Przy stronicowaniu widziałby wyłącznie bieżącą
 * stronę, więc trafienia ze strony 2+ byłyby nieosiągalne. `posts_per_page = -1`
 * sprawia, że serwer renderuje KOMPLET stanu, a JS filtruje całość. Świadomie NIE
 * dajemy capa: cap po przekroczeniu po cichu przywróciłby ten sam problem.
 * REWIZJA NA PRZYSZŁOŚĆ: gdy publiczne listy bardzo urosną (piłka klubowa po
 * Mundialu), wrócić do stronicowania + filtra serwerowego/Algolii (4B). Dla
 * Mundialu (≲104 mecze/lista) komplet w DOM jest tani.
 *
 * Skoro nie paginujemy — `no_found_rows` (zero zbędnego SQL COUNT).
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

	// Cała lista stanu na jednej stronie — patrz docblock (filtr kliencki 4A).
	$q->set( 'posts_per_page', -1 );
	$q->set( 'no_found_rows', true );

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
			// REALNY status (3e-i): mecz „na żywo" = status IN (kody live). Kody
			// wywiedzione z jedynej mapy statusu (lookups.php). Wymagamy też kickoffa
			// — sortujemy po nim (najwcześniej rozpoczęte u góry).
			$q->set(
				'meta_query',
				array(
					'relation' => 'AND',
					'stat'     => array(
						'key'     => 'status',
						'value'   => hajlajty_status_live_codes(),
						'compare' => 'IN',
					),
					'kick'     => array(
						'key'     => 'kickoff',
						'compare' => 'EXISTS',
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
   1c. TERMINARZ — predykat widoku (MVP-c).
============================================================ */

/**
 * Czy bieżący widok to strona „Terminarz turnieju" (Page Template MVP-c)?
 *
 * Terminarz to KOLEJNA lista meczów tego slice'a (reużywa kart + resolvera +
 * atrybutów filtra), ale żyje jako Strona z szablonem `template-terminarz.php`
 * — nie jako archiwum CPT. Predykat jest WSPÓLNYM źródłem prawdy „to terminarz"
 * dla gate'ów enqueue (tu) i widoku list (slice filters konsumuje przez
 * function_exists — luźne sprzężenie, bez twardej zależności na ten slice).
 *
 * Szablon MUSI leżeć w roocie motywu: WP skanuje szablony stron z głębokością 1
 * (`WP_Theme::get_post_templates()` → `get_files('php',1)`), więc plik w
 * podkatalogu slice'a (features/match-lists/) nie zostałby wykryty. Stąd
 * `template-terminarz.php` w roocie, a logika listy w partialu tego slice'a
 * (vertical slice zachowany).
 *
 * @return bool
 */
function hajlajty_match_lists_is_terminarz(): bool {
	return is_page_template( 'template-terminarz.php' );
}

add_filter( 'body_class', 'hajlajty_match_lists_terminarz_body_class' );
/**
 * Znacznik body „to terminarz" — włącza pełnoekranową powłokę z TRWAŁYM sidebarem
 * (desktop ≥1100px) tak jak home/archiwa. Slice match-lists JEST właścicielem
 * wiedzy „to terminarz"; layout.css tylko KONSUMUJE klasę (`body.hajlajty-terminarz`),
 * spójnie z `body.home`/`body.post-type-archive`. Bez tego Page Template dostałby
 * domyślny drawer (sidebar schowany) — patrz layout.css „WIDOKI Z TRWAŁYM MENU".
 *
 * @param string[] $classes Klasy body.
 * @return string[] Klasy body (z `hajlajty-terminarz` na stronie terminarza).
 */
function hajlajty_match_lists_terminarz_body_class( $classes ) {
	if ( hajlajty_match_lists_is_terminarz() ) {
		$classes[] = 'hajlajty-terminarz';
	}
	return $classes;
}

/* ============================================================
   1d. ENQUEUE — CSS kart + JS odliczania, tylko gdzie potrzeba.
   Wzorzec 1:1 z match-display.php (filemtime, dep 'hajlajty-layout').
============================================================ */

add_action( 'wp_enqueue_scripts', 'hajlajty_match_lists_enqueue' );
/**
 * Zasoby LIST — archiwum „mecz", strona główna ORAZ terminarz (MVP-c reużywa te
 * same karty, więc potrzebuje tych samych stylów). CSS sportowany z designu do
 * assets/styles/ motywu (NIE ładujemy z design/).
 */
function hajlajty_match_lists_enqueue() {
	if ( ! is_post_type_archive( 'mecz' ) && ! is_front_page() && ! hajlajty_match_lists_is_terminarz() ) {
		return;
	}

	// CSS współdzielony przez archiwum i stronę główną. Stylu paginacji NIE
	// ładujemy — archiwum nie stronicuje (cała lista stanu na jednej stronie,
	// pod kliencki filtr 4A; patrz hajlajty_match_lists_pre_get_posts).
	$styles = array(
		'hajlajty-card-preview' => array( 'assets/styles/card-preview.css', array( 'hajlajty-layout' ) ),
		'hajlajty-match-lists'  => array( 'assets/styles/match-lists.css', array( 'hajlajty-layout' ) ),
	);

	foreach ( $styles as $handle => $def ) {
		$path = get_theme_file_path( $def[0] );
		if ( ! is_readable( $path ) ) {
			continue;
		}
		wp_enqueue_style( $handle, get_theme_file_uri( $def[0] ), $def[1], (string) filemtime( $path ) );
	}

	// Layout terminarza (sekcje dni + siatka + karta wyniku) — TYLKO na tej stronie.
	// Zależny od match-lists.css (dziedziczy chrome kart) i layoutu (tokeny).
	if ( hajlajty_match_lists_is_terminarz() ) {
		$ter  = 'assets/styles/terminarz.css';
		$path = get_theme_file_path( $ter );
		if ( is_readable( $path ) ) {
			wp_enqueue_style( 'hajlajty-terminarz', get_theme_file_uri( $ter ), array( 'hajlajty-match-lists', 'hajlajty-layout' ), (string) filemtime( $path ) );
		}
	}

	$js   = 'assets/js/match-lists.js';
	$path = get_theme_file_path( $js );
	if ( is_readable( $path ) ) {
		wp_enqueue_script( 'hajlajty-match-lists', get_theme_file_uri( $js ), array(), (string) filemtime( $path ), true );
	}
}
