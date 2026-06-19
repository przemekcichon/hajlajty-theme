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
 * @param int[] $post_ids ID postów meczu (z jednego WP_Query).
 * @return array<int,array{home:?WP_Term,away:?WP_Term}> Mapa post_id → strony.
 *   Każdy podany post_id ma wpis (z null-ami, gdy brak danych).
 */
function hajlajty_match_lists_resolve_terms( array $post_ids ): array {
	$result = array();
	$sides  = array();   // post_id => [home_api, away_api]
	$api_ids = array();

	foreach ( $post_ids as $pid ) {
		$pid = (int) $pid;
		$result[ $pid ] = array(
			'home' => null,
			'away' => null,
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
