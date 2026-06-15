<?php
/**
 * Helpery dostępu do danych meczu na froncie (Faza 3a).
 *
 * Render JEST READ-ONLY: czyta dokładnie to, co zapisał import Fazy 2
 * (match_data + taksonomia `druzyna` z term meta `api_id`). Tu mieszka:
 *  - jedyne miejsce dekodowania match_data (json_decode pilnowany w jednym helperze),
 *  - WŁASNA resolucja api_id → term drużyny (render NIE reużywa helperów importu —
 *    `hajlajty_import_find_term_id_by_meta()` jest gated WP_CLI i na froncie NIE
 *    ISTNIEJE; ground-truth Fazy 2).
 *
 * Zależy od WordPressa (get_post_meta, get_terms, get_term_meta).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dekoduje pole meta `match_data` posta meczu do tablicy.
 *
 * @param int $post_id ID posta meczu.
 * @return array Zdekodowane match_data albo [] gdy meta pusta / nie-string /
 *   json_decode zwróci null lub nie-tablicę. ZAWSZE tablica (nigdy null/false) —
 *   render robi isset() na kluczach, więc pusta tablica jest bezpieczna.
 *
 * Jedyne miejsce dostępu do match_data w całym renderze: żadnego json_decode poza tym.
 */
function hajlajty_get_match_data( int $post_id ): array {
	$raw = get_post_meta( $post_id, 'match_data', true );

	if ( ! is_string( $raw ) || '' === $raw ) {
		return array();
	}

	$decoded = json_decode( $raw, true );

	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Ustala termy taksonomii `druzyna` dla gospodarza i gościa meczu — po api_id,
 * NIE po kolejności (get_the_terms zwraca termy nieuporządkowane).
 *
 * @param int $post_id ID posta meczu.
 * @return array{home:?WP_Term,away:?WP_Term} Term strony albo null, gdy:
 *   brak api_id w match_data dla tej strony, albo drużyna niewysiana (brak termu).
 *   null to oczekiwany stan (luka w seedzie), nie błąd.
 *
 * BEZ N+1: oba api_id resolwowane JEDNYM get_terms (meta_query IN, ≤2 termy).
 */
function hajlajty_match_get_team_terms( int $post_id ): array {
	$data     = hajlajty_get_match_data( $post_id );
	$home_api = isset( $data['teams']['home']['api_id'] ) ? (int) $data['teams']['home']['api_id'] : 0;
	$away_api = isset( $data['teams']['away']['api_id'] ) ? (int) $data['teams']['away']['api_id'] : 0;

	$result = array(
		'home' => null,
		'away' => null,
	);

	// Zbierz tylko niepuste api_id do jednego zapytania.
	$wanted = array_values( array_filter( array( $home_api, $away_api ) ) );
	if ( empty( $wanted ) ) {
		return $result;
	}

	// JEDEN get_terms na całą resolucję (≤2 termy) — brak N+1.
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
		return $result;
	}

	// Przypisz stronę porównując term meta api_id do home/away (NIE po kolejności).
	foreach ( $terms as $term ) {
		$term_api = (int) get_term_meta( $term->term_id, 'api_id', true );
		if ( $home_api && $term_api === $home_api ) {
			$result['home'] = $term;
		}
		if ( $away_api && $term_api === $away_api ) {
			$result['away'] = $term;
		}
	}

	return $result;
}
