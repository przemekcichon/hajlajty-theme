<?php
/**
 * Warstwa odczytu tabeli grupowej (READ-ONLY). Render czyta DOKŁADNIE to, co
 * zapisał slice core `features/standings-import/` (MVP-d): meta `standings_<sezon>`
 * na termie taksonomii `rozgrywki` (resolucja po term meta `league_id`).
 *
 * Granica artefakt↔artefakt (CLAUDE.md): motyw NIE woła resolverów core
 * (`hajlajty_import_find_term_id_by_meta` / `hajlajty_standings_find_rozgrywki_term`
 * żyją w pluginie). Slice trzyma WŁASNE, równoważne resolucje — ta sama konwencja
 * meta (`league_id`, `api_id`), własność motywu. Wzór: match-display/helpers.php.
 *
 * Tu mieszka JEDYNE `json_decode` standings w całym renderze (analog
 * `hajlajty_get_match_data`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dekoduje tabelę grup zapisaną przez MVP-d.
 *
 * @param int    $term_id Term taksonomii „rozgrywki".
 * @param string $season  Sezon (np. „2026"); normalizowany do samych cyfr, by
 *                        trafić w klucz `standings_<sezon>` zapisany przez core.
 * @return array Mapa litera→wiersze (`{A:[…],…,L:[…]}`) albo [] gdy term/sezon
 *               puste, meta nie istnieje, nie-string lub niepoprawny JSON.
 *               ZAWSZE tablica — render robi foreach/isset, więc [] jest bezpieczne.
 */
function hajlajty_get_standings( int $term_id, string $season ): array {
	if ( $term_id <= 0 ) {
		return array();
	}
	$season = (string) preg_replace( '/\D/', '', $season ); // mirror core: samé cyfry.
	if ( '' === $season ) {
		return array();
	}

	$raw = get_term_meta( $term_id, 'standings_' . $season, true );
	if ( ! is_string( $raw ) || '' === $raw ) {
		return array();
	}

	$decoded = json_decode( $raw, true );
	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Term_id taksonomii „rozgrywki" po term meta `league_id` (stabilne ID, NIGDY po
 * nazwie). WŁASNY resolver motywu (granica artefakt↔artefakt — NIE core).
 *
 * @param int $league_id api-football `league.id`.
 * @return int term_id albo 0.
 */
function hajlajty_standings_view_find_league_term( int $league_id ): int {
	if ( $league_id <= 0 ) {
		return 0;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'rozgrywki',
			'hide_empty' => false,
			'number'     => 1,
			'fields'     => 'ids',
			'meta_query' => array(
				array(
					'key'   => 'league_id',
					'value' => (string) $league_id,
				),
			),
		)
	);

	return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (int) $terms[0] : 0;
}

/**
 * Resolwuje listę `team_id` (= `api_id`) na termy „drużyna" — JEDNYM zapytaniem.
 *
 * BEZ N+1: wszystkie id idą jednym `get_terms` (`meta_query` `api_id` `IN`),
 * mapowanie po ODCZYCIE term meta `api_id` (NIE po kolejności zwrotu). Wzór:
 * match-display/helpers.php:69-96.
 *
 * @param int[] $team_ids Lista `team_id` z wierszy tabeli (mogą się powtarzać).
 * @return array<int,WP_Term> Mapa api_id → WP_Term (tylko znalezione; brak termu
 *                            = brak klucza → render robi fallback).
 */
function hajlajty_standings_resolve_teams( array $team_ids ): array {
	$wanted = array_values( array_unique( array_filter( array_map( 'intval', $team_ids ) ) ) );
	if ( empty( $wanted ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'druzyna',
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key'     => 'api_id',
					'value'   => array_map( 'strval', $wanted ),
					'compare' => 'IN',
				),
			),
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	$map = array();
	foreach ( $terms as $term ) {
		$api = (int) get_term_meta( $term->term_id, 'api_id', true );
		if ( $api > 0 ) {
			$map[ $api ] = $term;
		}
	}
	return $map;
}
