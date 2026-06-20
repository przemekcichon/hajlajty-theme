<?php
/**
 * Batch-resolver drużyn dla LIST meczów (archiwum + strona główna). Mieszka w
 * slice „match-lists", NIE w match-display/helpers.php (3b): tam żyje resolucja
 * 1 meczu (≤2 termy) na single; tu resolwujemy CAŁĄ listę JEDNYM get_terms, żeby
 * uniknąć N+1 przy renderze wielu kart. Wzorzec 1:1 z asideem „Inne skróty"
 * single-ft.php — różni się tylko tym, że zwraca mapę post_id → strony.
 *
 * Render kart jest READ-ONLY: czyta to, co zapisał import (match_data + term meta
 * `api_id`/`fifa_code`). Czyste funkcje pomocnicze (kod flagi/skrót) trzymamy tu,
 * bo znikają razem z widokiem listy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rozwiązuje drużyny (gospodarz/gość) dla LISTY postów meczu — JEDNYM get_terms.
 *
 * Strona (home/away) ustalana po `api_id` z match_data, NIGDY po kolejności
 * get_the_terms. Brak api_id dla strony albo niewysiana drużyna → null (stan
 * oczekiwany, render degraduje do „—").
 *
 * Dla filtra 4A (slice filters) KAŻDY wpis niesie też slugi taksonomii, po których
 * front zawęża karty klient-side: `rozgrywki`/`sezon`/`kanal` (drużyny filtruje po
 * kodzie FIFA z term meta, budowanym osobno w atrybutach karty). Slugi dociągamy
 * JEDNYM `wp_get_object_terms` na całą listę — spójnie z zasadą „zero N+1".
 *
 * @param int[] $post_ids ID postów meczu (z jednego WP_Query).
 * @return array<int,array{home:?WP_Term,away:?WP_Term,rozgrywki:string[],sezon:string[],kanal:string[]}>
 *   Mapa post_id → strony + slugi taksonomii. Każdy podany post_id ma wpis
 *   (z null-ami / pustymi tablicami, gdy brak danych).
 */
function hajlajty_match_lists_resolve_terms( array $post_ids ): array {
	$result = array();
	$sides  = array();   // post_id => [home_api, away_api]
	$api_ids = array();
	$ids     = array();  // znormalizowane int-y do batcha taksonomii

	foreach ( $post_ids as $pid ) {
		$pid   = (int) $pid;
		$ids[] = $pid;
		$result[ $pid ] = array(
			'home'      => null,
			'away'      => null,
			'rozgrywki' => array(),
			'sezon'     => array(),
			'kanal'     => array(),
		);

		$data = hajlajty_get_match_data( $pid );
		$h    = isset( $data['teams']['home']['api_id'] ) ? (int) $data['teams']['home']['api_id'] : 0;
		$a    = isset( $data['teams']['away']['api_id'] ) ? (int) $data['teams']['away']['api_id'] : 0;

		$sides[ $pid ] = array( $h, $a );
		if ( $h ) {
			$api_ids[] = $h;
		}
		if ( $a ) {
			$api_ids[] = $a;
		}
	}

	// Slugi taksonomii (rozgrywki/sezon/kanal) dla CAŁEJ listy — JEDEN batch,
	// niezależny od resolucji drużyn (działa też, gdy żadna drużyna się nie wysiała,
	// np. skróty bez zaseedowanej drużyny). Zero N+1: jedno zapytanie na listę.
	$obj_terms = wp_get_object_terms(
		$ids,
		array( 'rozgrywki', 'sezon', 'kanal' ),
		array( 'fields' => 'all_with_object_id' )
	);
	if ( ! is_wp_error( $obj_terms ) ) {
		foreach ( $obj_terms as $obj_term ) {
			$opid = (int) $obj_term->object_id;
			if ( isset( $result[ $opid ][ $obj_term->taxonomy ] ) ) {
				$result[ $opid ][ $obj_term->taxonomy ][] = $obj_term->slug;
			}
		}
	}

	$api_ids = array_values( array_unique( array_filter( $api_ids ) ) );
	if ( empty( $api_ids ) ) {
		return $result;
	}

	// JEDEN get_terms na całą listę — sedno braku N+1.
	$terms = get_terms(
		array(
			'taxonomy'   => 'druzyna',
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key'     => 'api_id',
					'value'   => array_map( 'strval', $api_ids ),
					'compare' => 'IN',
				),
			),
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return $result;
	}

	$term_by_api = array();
	foreach ( $terms as $term ) {
		$term_by_api[ (int) get_term_meta( $term->term_id, 'api_id', true ) ] = $term;
	}

	foreach ( $sides as $pid => $pair ) {
		list( $h, $a ) = $pair;
		if ( $h && isset( $term_by_api[ $h ] ) ) {
			$result[ $pid ]['home'] = $term_by_api[ $h ];
		}
		if ( $a && isset( $term_by_api[ $a ] ) ) {
			$result[ $pid ]['away'] = $term_by_api[ $a ];
		}
	}

	return $result;
}

/**
 * Krótki kod drużyny na kartę (POL/BRA…) z term meta `fifa_code`; fallback nazwa.
 *
 * @param ?WP_Term $term Term drużyny albo null.
 * @return string Kod FIFA wielkimi literami, nazwa termu, albo „—" gdy brak termu.
 */
function hajlajty_match_lists_team_code( $term ): string {
	if ( ! ( $term instanceof WP_Term ) ) {
		return '—';
	}
	$code = strtoupper( (string) get_term_meta( $term->term_id, 'fifa_code', true ) );
	return '' !== $code ? $code : $term->name;
}

/**
 * Pełna serwisowa nazwa drużyny na kartę; fallback „—" gdy brak termu.
 *
 * @param ?WP_Term $term Term drużyny albo null.
 * @return string Nazwa termu albo „—".
 */
function hajlajty_match_lists_team_name( $term ): string {
	return ( $term instanceof WP_Term ) ? $term->name : '—';
}

/**
 * Atrybuty `data-*` filtra 4A dla KONTENERA karty (slice filters je konsumuje).
 *
 * Jedno źródło prawdy kontraktu karta→filtr: wszystkie trzy partiale kart wołają
 * ten helper na swoim `<a>` zamiast każdy budować atrybuty po swojemu. Emitujemy
 * KOMPLET atrybutów (także puste) — `filters.js` zakłada ich obecność.
 *
 *  - data-teams       kody FIFA gospodarza+gościa (term meta `fifa_code`, UPPER) —
 *                     po tym filtruje chip drużyny (chip też niesie FIFA).
 *  - data-rozgrywki / data-sezon / data-kanal  slugi termów (po nich filtrują chipy
 *                     pozostałych taksonomii).
 *  - data-team-names  ZNORMALIZOWANE nazwy PL drużyn (home+away) — po nich szuka
 *                     pole tekstowe (substring; normalizacja spójna z filters.js).
 *
 * @param array{home:?WP_Term,away:?WP_Term,rozgrywki?:string[],sezon?:string[],kanal?:string[]} $entry
 *   Wpis z hajlajty_match_lists_resolve_terms() dla jednego posta.
 * @return string Ciąg atrybutów z wiodącą spacją, gotowy do wstawienia w `<a …>`.
 */
function hajlajty_match_lists_card_filter_attrs( array $entry ): string {
	$teams = array();
	$names = array();
	foreach ( array( 'home', 'away' ) as $side ) {
		$term = isset( $entry[ $side ] ) ? $entry[ $side ] : null;
		if ( ! ( $term instanceof WP_Term ) ) {
			continue;
		}
		$code = strtoupper( (string) get_term_meta( $term->term_id, 'fifa_code', true ) );
		if ( '' !== $code ) {
			$teams[] = $code;
		}
		$names[] = hajlajty_filters_normalize_pl( $term->name );
	}

	$attrs = array(
		'data-teams'      => implode( ' ', $teams ),
		'data-rozgrywki'  => implode( ' ', isset( $entry['rozgrywki'] ) ? $entry['rozgrywki'] : array() ),
		'data-sezon'      => implode( ' ', isset( $entry['sezon'] ) ? $entry['sezon'] : array() ),
		'data-kanal'      => implode( ' ', isset( $entry['kanal'] ) ? $entry['kanal'] : array() ),
		'data-team-names' => implode( ' ', $names ),
	);

	$out = '';
	foreach ( $attrs as $key => $value ) {
		$out .= ' ' . $key . '="' . esc_attr( $value ) . '"';
	}
	return $out;
}
