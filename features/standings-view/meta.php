<?php
/**
 * Pola STRONY tabeli: `league_id` (int) + `season` (cyfry) — parametryzacja per
 * Strona, rejestrowana KODEM (acf_add_local_field_group), nie klikana w adminie
 * (wzór: hajlajty-core features/match/acf.php; wersjonowalne, migracja-safe).
 *
 * DLACZEGO meta strony, nie stała: serwis docelowo pokaże wiele tabel (turnieje,
 * sezony, w Fazie 5 ligi klubowe). Redaktor tworzy Stronę per tabela i WPISUJE
 * `league_id`+`season` — bez zmian w kodzie. Ten sam mechanizm obsłuży tabelę
 * ligową (Faza 5); render ligowy to JEDNAK Faza 5, nie teraz (#8).
 *
 * ACF = tylko admin-UI edycji. RENDER czyta przez `get_post_meta` (data.php /
 * partial), więc tor odczytu NIE zależy od ACF (headless-friendly, spójnie z
 * match-display czytającym `match_data` przez get_post_meta).
 *
 * Pola wiszą na szablonie „Tabela rozgrywek" (neutralna nazwa — przyjmie też
 * wariant ligowy w Fazie 5), po nazwie pliku szablonu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HAJLAJTY_STANDINGS_VIEW_TEMPLATE = 'template-tabela-rozgrywek.php';

add_action( 'acf/init', 'hajlajty_standings_view_register_fields' );

function hajlajty_standings_view_register_fields() {
	// ACF PRO jest zależnością projektu, ale nie zakładamy jej na ślepo — brak
	// funkcji = nie rejestrujemy grupy (zero fatal; render i tak czyta meta).
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
			'show_in_rest' => 1,
		)
	);
}
