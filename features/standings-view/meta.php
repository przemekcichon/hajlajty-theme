<?php
/**
 * Pola STRONY tabeli: `league_id` (int) + `season` (cyfry) — parametryzacja per
 * Strona (redaktor tworzy Stronę per tabela i WPISUJE wartości, bez zmian w kodzie).
 *
 * DWA RÓWNOLEGŁE rejestracje tej samej pary meta, każda w swojej roli:
 *  - `register_post_meta` (hook init) = KONTRAKT DANYCH i ekspozycja headless.
 *    Pojedyncza wartość, typy, `show_in_rest` + `show_in_graphql` (CLAUDE.md #6:
 *    rejestrujemy z myślą o migracji do Next.js/WPGraphQL). To jest źródło prawdy
 *    rejestracji — działa nawet bez ACF.
 *  - `acf_add_local_field_group` (hook acf/init) = tylko ADMIN-UI edycji na tym
 *    szablonie. Te SAME klucze meta (`league_id`/`season`), więc ACF zapisuje do
 *    pól zarejestrowanych wyżej; bez ACF render i tak czyta przez `get_post_meta`.
 *
 * DLACZEGO meta strony, nie stała: serwis docelowo pokaże wiele tabel (turnieje,
 * sezony, w Fazie 5 ligi klubowe). Ten sam mechanizm obsłuży tabelę ligową (Faza 5);
 * render ligowy to JEDNAK Faza 5, nie teraz (#8).
 *
 * RENDER czyta przez `get_post_meta` (data.php / partial) — tor odczytu niezależny
 * od ACF (spójnie z match-display czytającym `match_data` przez get_post_meta).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HAJLAJTY_STANDINGS_VIEW_TEMPLATE = 'template-tabela-rozgrywek.php';

add_action( 'init', 'hajlajty_standings_view_register_meta' );

/**
 * Natywna rejestracja meta strony = kontrakt danych + headless (REST/GraphQL).
 * Źródło prawdy rejestracji; ACF (niżej) to tylko nakładka edycyjna na te klucze.
 */
function hajlajty_standings_view_register_meta() {
	register_post_meta(
		'page',
		'league_id',
		array(
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => true,
			'show_in_graphql'   => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => function () {
				return current_user_can( 'edit_pages' );
			},
		)
	);

	register_post_meta(
		'page',
		'season',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'show_in_graphql'   => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function () {
				return current_user_can( 'edit_pages' );
			},
		)
	);
}

add_action( 'acf/init', 'hajlajty_standings_view_register_fields' );

/**
 * Pole admina (ACF) na szablonie „Tabela rozgrywek". Te same klucze meta co
 * `register_post_meta` — ACF tylko dostarcza UI edycji (wzór: core features/match/acf.php).
 */
function hajlajty_standings_view_register_fields() {
	// ACF PRO jest zależnością projektu, ale nie zakładamy jej na ślepo — brak
	// funkcji = nie rejestrujemy grupy (zero fatal; render i tak czyta meta, a
	// natywna rejestracja meta + REST/GraphQL żyje niezależnie wyżej).
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'    => 'group_tabela_rozgrywek',
			'title'  => 'Tabela rozgrywek — źródło danych',
			'fields' => array(
				array(
					'key'          => 'field_tabela_league_id',
					'label'        => 'ID rozgrywek (league_id)',
					'name'         => 'league_id',
					'type'         => 'number',
					'instructions' => 'Liczbowe ID rozgrywek z api-football (np. 1 = Mistrzostwa Świata). Musi pasować do term meta „league_id" istniejącego termu taksonomii „rozgrywki".',
					'required'     => 0,
				),
				array(
					'key'          => 'field_tabela_season',
					'label'        => 'Sezon',
					'name'         => 'season',
					'type'         => 'text',
					'instructions' => 'Rok sezonu, np. 2026. Wskazuje, którą zaimportowaną tabelę pokazać (meta „standings_<sezon>").',
					'placeholder'  => '2026',
					'required'     => 0,
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'page_template',
						'operator' => '==',
						'value'    => HAJLAJTY_STANDINGS_VIEW_TEMPLATE,
					),
				),
			),
			// REST/GraphQL ekspozycja idzie przez register_post_meta (źródło prawdy);
			// ACF tu wyłącznie edytuje — bez własnego show_in_rest, by nie dublować
			// reprezentacji tego samego klucza.
		)
	);
}
