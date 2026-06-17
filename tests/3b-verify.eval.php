<?php
/**
 * Weryfikacja pochodnych 3b na ŻYWYM WordPressie (oś czasu, indeks zdarzeń
 * zawodnika, zapytanie aside). Uruchom w Open Site Shell Locala (z roota WP):
 *
 *   wp eval-file wp-content/themes/hajlajty-theme/tests/3b-verify.eval.php
 *
 * Domyślnie mecz ID 11 (FT). Inny mecz: ustaw zmienną środowiskową HAJ_MATCH,
 *   HAJ_MATCH=42 wp eval-file wp-content/themes/hajlajty-theme/tests/3b-verify.eval.php
 *
 * require_once (nie require): funkcje slice'a są już załadowane, gdy motyw jest
 * aktywny — require_once po realpath nie redeklaruje. Działa też przy nieaktywnym
 * motywie (dociąga sam). __DIR__ — fizyczna lokalizacja, niezależna od motywu.
 */

require_once __DIR__ . '/../features/match-display/helpers.php';
require_once __DIR__ . '/../features/match-display/lookups.php';
require_once __DIR__ . '/../features/match-display/derive.php';

$id   = (int) ( getenv( 'HAJ_MATCH' ) ?: 11 );
$line = str_repeat( '-', 64 );
$data = hajlajty_get_match_data( $id );

echo "$line\n# Mecz ID $id — status.short: " . ( $data['status']['short'] ?? '(brak)' ) . "\n$line\n";

/* ========================================================================
 * E4 — oś czasu z narastającym wynikiem; ostatni == goals.fulltime?
 * ===================================================================== */
echo "\n# E4 — OŚ CZASU (gwiazdka = liczący gol)\n";
$timeline = hajlajty_build_timeline( $data['events'] ?? array() );
$last     = null;
foreach ( $timeline as $r ) {
	if ( is_array( $r['score'] ) ) {
		$last = $r['score'];
	}
	$score_txt = is_array( $r['score'] ) ? ( $r['score']['home'] . ':' . $r['score']['away'] ) : '';
	printf( "  %3s%s  %-26s %s\n", $r['minute'], $r['counts'] ? '*' : ' ', $r['label'], $score_txt );
}
$ft_h    = $data['score']['fulltime']['home'] ?? null;
$ft_a    = $data['score']['fulltime']['away'] ?? null;
// Brak liczących goli = 0:0 (mecz bezbramkowy nie może dawać „ROZJAZD").
$eff     = is_array( $last ) ? $last : array( 'home' => 0, 'away' => 0 );
$last_tx = $eff['home'] . ':' . $eff['away'];
$ft_tx   = ( null === $ft_h && null === $ft_a ) ? '(brak)' : ( $ft_h . ':' . $ft_a );
$ok      = ( (int) $eff['home'] === (int) $ft_h && (int) $eff['away'] === (int) $ft_a );
echo "  ostatni narastajacy: $last_tx | goals.fulltime: $ft_tx | " . ( $ok ? 'ZGODNY' : 'ROZJAZD (sprawdz own_goal/VAR)' ) . "\n";

/* ========================================================================
 * E5 — indeks zdarzeń zawodnika (events ↔ lineups po player_id)
 * ===================================================================== */
echo "\n# E5 — INDEKS ZDARZEŃ ZAWODNIKA (player_id → agregat)\n";
$idx = hajlajty_player_event_index( $data['events'] ?? array() );
if ( empty( $idx ) ) {
	echo "  (brak eventów z player_id)\n";
} else {
	foreach ( $idx as $pid => $e ) {
		$bits = array();
		if ( $e['gole'] ) {
			$bits[] = $e['gole'] . '×gol';
		}
		if ( $e['samoboje'] ) {
			$bits[] = $e['samoboje'] . '×samob';
		}
		if ( $e['zolta'] ) {
			$bits[] = $e['zolta'] . '×żółta';
		}
		if ( $e['czerwona'] ) {
			$bits[] = 'czerwona';
		}
		if ( null !== $e['zszedl'] ) {
			$bits[] = 'zszedł ' . $e['zszedl'] . "'";
		}
		if ( null !== $e['wszedl'] ) {
			$bits[] = 'wszedł ' . $e['wszedl'] . "'";
		}
		printf( "  #%-8d %s\n", $pid, implode( ', ', $bits ) );
	}
}

/* ========================================================================
 * E6 — zapytanie „Inne skróty": skrot_url≠'' + te same rozgrywki + kickoff DESC
 * ===================================================================== */
echo "\n# E6 — ASIDE Inne skroty (max 4 ID, bez biezacego $id)\n";
$roz     = get_the_terms( $id, 'rozgrywki' );
$roz_ids = ( is_array( $roz ) && ! is_wp_error( $roz ) ) ? wp_list_pluck( $roz, 'term_id' ) : array();
$q_args  = array(
	'post_type'      => 'mecz',
	'posts_per_page' => 4,
	'post__not_in'   => array( $id ),
	'fields'         => 'ids',
	'no_found_rows'  => true,
	'meta_query'     => array(
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
	),
	'orderby'        => array( 'kick' => 'DESC' ),
);
if ( ! empty( $roz_ids ) ) {
	$q_args['tax_query'] = array(
		array(
			'taxonomy' => 'rozgrywki',
			'field'    => 'term_id',
			'terms'    => $roz_ids,
		),
	);
}
$q = new WP_Query( $q_args );
echo '  rozgrywki termy: ' . ( $roz_ids ? implode( ',', $roz_ids ) : '(brak)' ) . "\n";
echo '  zwrócone ID:     ' . ( $q->posts ? implode( ',', $q->posts ) : '(puste — brak meczów FT ze skrótem w tych rozgrywkach)' ) . "\n";
echo "$line\n";
