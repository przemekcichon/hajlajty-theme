<?php
/**
 * Warstwa odczytu Profilu / Reprezentacji (READ-ONLY). Render czyta DOKŁADNIE to,
 * co zapisali producenci na main:
 *  - MVP-f: `team_stats_<liga>_<sezon>` (term meta „druzyna") — statystyki drużyny,
 *  - MVP-d: `standings_<sezon>` (term meta „rozgrywki") — tabela grup (→ grupa+ranga),
 *  - import meczów: CPT `mecz` + płaska meta `kickoff` + taksonomia `druzyna`.
 *
 * Granica artefakt↔artefakt (CLAUDE.md): motyw NIE woła resolverów/kluczy core
 * (`hajlajty_team_stats_*` / `hajlajty_standings_*` są gated WP-CLI i na froncie
 * NIE istnieją). Slice trzyma WŁASNE resolucje po tej samej konwencji meta
 * (`api_id`/`league_id`) — własność motywu. Wzór: standings-view/data.php,
 * match-display/helpers.php.
 *
 * KLUCZE jako LITERAŁY (przepisane z ground-truth, nie z pamięci):
 *  - prefiks statystyk: `team_stats_` → klucz `team_stats_<league_id>_<season>`,
 *  - prefiks tabeli:    `standings_`  → klucz `standings_<season>`.
 * Bez page-meta liga/sezon (Profil = archiwum termu, nie Strona): SKANUJEMY meta
 * po prefiksie i bierzemy pierwszy zapis. Dla Mundialu to dokładnie jeden blob;
 * uogólnia się naturalnie, bez magicznych stałych liga/sezon (#8).
 *
 * UWAGA term_id ≠ api_id: term „Belgia" ma term_id 5, ale api_id 1; w standings
 * `team_id` to api_id. Łączymy WYŁĄCZNIE po `api_id` (term meta), NIGDY po term_id
 * (ground-truth: po term_id „team_id 5" wskazałby Szwecję, nie Belgię).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Prefiks dynamicznego klucza meta statystyk drużyny (MVP-f, na termie „druzyna"). */
const HAJLAJTY_TEAMS_VIEW_STATS_PREFIX = 'team_stats_';
/** Prefiks dynamicznego klucza meta tabeli grup (MVP-d, na termie „rozgrywki"). */
const HAJLAJTY_TEAMS_VIEW_STANDINGS_PREFIX = 'standings_';

/* -------------------------------------------------------------------------
 * Drużyny (taksonomia „druzyna")
 * ---------------------------------------------------------------------- */

/**
 * `api_id` (team.id) z term meta drużyny. 0 gdy brak/niepoprawne.
 *
 * @param int $term_id Term taksonomii „druzyna".
 * @return int
 */
function hajlajty_teams_view_term_api_id( int $term_id ): int {
	return (int) get_term_meta( $term_id, 'api_id', true );
}

/**
 * Wszystkie termy „druzyna" (cały zaseedowany roster) — do listy Reprezentacje
 * w trybie „bez tabeli" (gdy standings jeszcze nie zaimportowano).
 *
 * @return WP_Term[] Posortowane nazwą (jak get_terms domyślnie), [] gdy brak.
 */
function hajlajty_teams_view_all_druzyna_terms(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => 'druzyna',
			'hide_empty' => false,
		)
	);
	return ( ! is_wp_error( $terms ) && is_array( $terms ) ) ? $terms : array();
}

/**
 * Mapa api_id → WP_Term dla listy team_id (JEDNYM get_terms, bez N+1). Mapowanie
 * po ODCZYTANYM `api_id`, NIE po kolejności zwrotu. Wzór: standings-view/data.php.
 *
 * @param int[] $api_ids Lista api_id (mogą się powtarzać).
 * @return array<int,WP_Term> Mapa api_id → term (tylko znalezione).
 */
function hajlajty_teams_view_resolve_by_api( array $api_ids ): array {
	$wanted = array_values( array_unique( array_filter( array_map( 'intval', $api_ids ) ) ) );
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
		$api = hajlajty_teams_view_term_api_id( $term->term_id );
		if ( $api > 0 ) {
			$map[ $api ] = $term;
		}
	}
	return $map;
}

/* -------------------------------------------------------------------------
 * Statystyki drużyny (MVP-f)
 * ---------------------------------------------------------------------- */

/**
 * CURATED JSON statystyk drużyny — PIERWSZY zapis `team_stats_*` na termie.
 * Dekodowane raz (jedyne json_decode statystyk w renderze, analog
 * hajlajty_get_match_data / hajlajty_get_standings).
 *
 * Bierze PIERWSZY pasujący blob `team_stats_*`. Dla Mundialu (jeden turniej =
 * jeden blob) jest to deterministyczne i wystarczające (#8: bez wyboru na zapas).
 * TODO (Faza 5, piłka klubowa): gdy term „druzyna" dostanie >1 blob (różne ligi/
 * sezony), „pierwszy" jest arbitralny — dodać deterministyczny wybór (np. bieżący
 * sezon / kontekst widoku), zamiast polegać na kolejności get_term_meta.
 *
 * @param int $term_id Term „druzyna".
 * @return array Zdekodowany curated (albo [] gdy brak meta / nie-string / zły JSON).
 */
function hajlajty_teams_view_get_team_stats( int $term_id ): array {
	if ( $term_id <= 0 ) {
		return array();
	}

	$all = get_term_meta( $term_id );
	if ( ! is_array( $all ) ) {
		return array();
	}

	foreach ( $all as $key => $values ) {
		if ( 0 !== strpos( (string) $key, HAJLAJTY_TEAMS_VIEW_STATS_PREFIX ) ) {
			continue;
		}
		$raw = is_array( $values ) ? ( $values[0] ?? '' ) : $values;
		if ( ! is_string( $raw ) || '' === $raw ) {
			continue;
		}
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) && ! empty( $decoded ) ) {
			return $decoded;
		}
	}
	return array();
}

/* -------------------------------------------------------------------------
 * Tabela grup (MVP-d) — grupa + ranga drużyny
 * ---------------------------------------------------------------------- */

/**
 * Pierwsza zaimportowana tabela grup w serwisie: skan termów „rozgrywki" po
 * meta `standings_*`. Dla Mundialu to jedna tabela (term „Mistrzostwa Świata").
 *
 * Bierze PIERWSZY pasujący blob (pierwszy term × pierwszy `standings_*`). Dla
 * jednego turnieju deterministyczne i wystarczające (#8). TODO (Faza 5, piłka
 * klubowa): przy wielu rozgrywkach/sezonach „pierwszy" jest arbitralny — dodać
 * deterministyczny wybór (bieżący sezon / kontekst), nie kolejność get_terms.
 *
 * @return array{table:array<string,array>,rozgrywki:WP_Term,season:string}|null
 *   null gdy żadna tabela nie istnieje (render degraduje do trybu „bez tabeli").
 */
function hajlajty_teams_view_find_standings() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'rozgrywki',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return null;
	}

	foreach ( $terms as $term ) {
		$all = get_term_meta( $term->term_id );
		if ( ! is_array( $all ) ) {
			continue;
		}
		foreach ( $all as $key => $values ) {
			if ( 0 !== strpos( (string) $key, HAJLAJTY_TEAMS_VIEW_STANDINGS_PREFIX ) ) {
				continue;
			}
			$raw = is_array( $values ) ? ( $values[0] ?? '' ) : $values;
			if ( ! is_string( $raw ) || '' === $raw ) {
				continue;
			}
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) && ! empty( $decoded ) ) {
				return array(
					'table'     => $decoded,
					'rozgrywki' => $term,
					'season'    => (string) substr( (string) $key, strlen( HAJLAJTY_TEAMS_VIEW_STANDINGS_PREFIX ) ),
				);
			}
		}
	}
	return null;
}

/**
 * Grupa + ranga + wiersze grupy drużyny (po `api_id`) z pierwszej tabeli grup.
 * Źródło „seeda" (litera+ranga) i widżetu tabeli na Profilu.
 *
 * @param int        $api_id   api_id drużyny (team.id).
 * @param array|null $standings Wynik hajlajty_teams_view_find_standings() (opcj.;
 *                              przekaż, jeśli masz — oszczędza ponowny skan).
 * @return array{letter:string,rank:mixed,rows:array<int,array>,rozgrywki:WP_Term,season:string}|null
 *   null gdy drużyny nie ma w żadnej tabeli.
 */
function hajlajty_teams_view_find_team_group( int $api_id, $standings = null ) {
	if ( $api_id <= 0 ) {
		return null;
	}
	if ( null === $standings ) {
		$standings = hajlajty_teams_view_find_standings();
	}
	if ( null === $standings || empty( $standings['table'] ) ) {
		return null;
	}

	foreach ( $standings['table'] as $letter => $rows ) {
		if ( ! is_array( $rows ) ) {
			continue;
		}
		foreach ( $rows as $row ) {
			if ( (int) ( $row['team_id'] ?? 0 ) === $api_id ) {
				return array(
					'letter'    => (string) $letter,
					'rank'      => $row['rank'] ?? null,
					'rows'      => $rows,
					'rozgrywki' => $standings['rozgrywki'],
					'season'    => $standings['season'],
				);
			}
		}
	}
	return null;
}

/* -------------------------------------------------------------------------
 * Mecze drużyny (CPT „mecz")
 * ---------------------------------------------------------------------- */

/**
 * ID meczów drużyny dla danego stanu, sortowane po płaskiej meta `kickoff`.
 *
 * KONWENCJA CZASU = match-lists: `kickoff` to `Y-m-d H:i:s` UTC, zero-padded →
 * porównania/sortowanie LEKSYKALNE (type CHAR) są chronologiczne. NIGDY _num.
 * Sortowanie po NAZWANEJ klauzuli `kick` (wzór match-lists.php), bez meta_key.
 *
 * Stany:
 *  - 'live'     — REALNY status `status IN (kody live)` (jak listy 3e-i), NIE okno
 *                 czasowe; sort kickoff ASC. UWAGA: mecz live ma kickoff w przeszłości,
 *                 więc też pasuje do 'recent' — konsument MUSI odjąć live od recent
 *                 (patrz profile.php), żeby nie pokazać go dwa razy.
 *  - 'upcoming' — kickoff >= teraz, ASC (najbliższe u góry).
 *  - 'recent'   — kickoff < teraz, DESC (najnowsze u góry).
 *
 * @param int    $term_id Term „druzyna" (tax_query).
 * @param string $when    'live' | 'upcoming' | 'recent'.
 * @param int    $limit   Maks. liczba meczów.
 * @return int[] ID meczów (może być puste).
 */
function hajlajty_teams_view_match_ids( int $term_id, string $when, int $limit ): array {
	if ( $term_id <= 0 || $limit <= 0 ) {
		return array();
	}

	$args = array(
		'post_type'      => 'mecz',
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'tax_query'      => array(
			array(
				'taxonomy' => 'druzyna',
				'field'    => 'term_id',
				'terms'    => $term_id,
			),
		),
	);

	if ( 'live' === $when ) {
		// Kody live wywodzone z jedynej mapy statusu (match-display/lookups.php) — to
		// samo źródło co filtr list „Na żywo". Wymagamy kickoffa (sort).
		$args['meta_query'] = array(
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
		);
		$args['orderby'] = array( 'kick' => 'ASC' );
	} else {
		$now                = gmdate( 'Y-m-d H:i:s' );
		$args['meta_query'] = array(
			'kick' => array(
				'key'     => 'kickoff',
				'value'   => $now,
				'compare' => ( 'upcoming' === $when ) ? '>=' : '<',
				'type'    => 'CHAR',
			),
		);
		$args['orderby'] = array( 'kick' => ( 'upcoming' === $when ) ? 'ASC' : 'DESC' );
	}

	$query = new WP_Query( $args );

	return array_map( 'intval', $query->posts );
}

/**
 * Selekcjoner drużyny — best-effort z `match_data.lineups.<side>.coach.name`
 * najnowszego meczu, w którym drużyna ma skład. Stronę (home/away) ustala api_id
 * (NIE kolejność). Brak danych → '' (render ukrywa; NIE blokuje widoku).
 *
 * @param int[] $match_ids ID meczów (najlepiej posortowane malejąco po kickoff).
 * @param int   $api_id    api_id drużyny.
 * @return string Nazwa selekcjonera albo ''.
 */
function hajlajty_teams_view_coach_name( array $match_ids, int $api_id ): string {
	if ( $api_id <= 0 ) {
		return '';
	}
	foreach ( $match_ids as $pid ) {
		$data = hajlajty_get_match_data( (int) $pid );
		if ( empty( $data['lineups'] ) || ! is_array( $data['lineups'] ) ) {
			continue;
		}
		$home_api = (int) ( $data['teams']['home']['api_id'] ?? 0 );
		$side     = ( $home_api === $api_id ) ? 'home' : 'away';
		$coach    = $data['lineups'][ $side ]['coach']['name'] ?? '';
		if ( is_string( $coach ) && '' !== $coach ) {
			return $coach;
		}
	}
	return '';
}
