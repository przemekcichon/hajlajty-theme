<?php
/**
 * Diagnostyka 3bi (P1) — łańcuch subst → indeks → render strzałek zmian.
 * Uruchom w Open Site Shell Locala (z roota WP):
 *
 *   wp eval-file wp-content/themes/hajlajty-theme/tests/3bi-diag.eval.php
 *
 * Domyślnie mecz 11; inny: HAJ_MATCH=42 wp eval-file ...
 *
 * Pokazuje GDZIE pęka łańcuch wejść/zejść (a NIE goli/kartek, które działają):
 *  1) ile eventów i jakich typów jest w match_data,
 *  2) surowe eventy `subst` z player_id (wchodzący) / assist_id (schodzący),
 *  3) które wpisy indeksu faktycznie dostały `wszedł`/`zszedł`.
 */

require_once __DIR__ . '/../features/match-display/helpers.php';
require_once __DIR__ . '/../features/match-display/lookups.php';
require_once __DIR__ . '/../features/match-display/derive.php';

$id     = (int) ( getenv( 'HAJ_MATCH' ) ?: 11 );
$d      = hajlajty_get_match_data( $id );
$events = isset( $d['events'] ) && is_array( $d['events'] ) ? $d['events'] : array();
$line   = str_repeat( '-', 64 );

echo "$line\n# Mecz $id — events w match_data: " . count( $events ) . "\n$line\n";

/* 1) Liczność typów eventów. */
$by_type = array();
foreach ( $events as $ev ) {
	$t             = $ev['type'] ?? '(null)';
	$by_type[ $t ] = ( $by_type[ $t ] ?? 0 ) + 1;
}
echo '# Typy eventów: ';
foreach ( $by_type as $t => $n ) {
	echo "$t=$n ";
}
echo "\n";

/* 2) Surowe eventy subst (player_id=wchodzący, assist_id=schodzący). */
echo "\n# Eventy type='subst' (klucze obecne w match_data):\n";
$subst_n = 0;
foreach ( $events as $ev ) {
	if ( ( $ev['type'] ?? '' ) !== 'subst' ) {
		continue;
	}
	++$subst_n;
	printf(
		"  min=%s side=%s | player_id=%s (%s) | assist_id=%s (%s) | klucz assist_id: %s\n",
		var_export( $ev['minute'] ?? null, true ),
		$ev['side'] ?? '?',
		var_export( $ev['player_id'] ?? null, true ),
		$ev['player'] ?? '?',
		var_export( $ev['assist_id'] ?? null, true ),
		$ev['assist'] ?? '?',
		array_key_exists( 'assist_id', $ev ) ? 'JEST' : 'BRAK'
	);
}
if ( ! $subst_n ) {
	echo "  (ZERO eventów subst — to tłumaczyłoby brak WSZYSTKICH strzałek; problem = DANE, nie render)\n";
}

/* 3) Indeks: które wpisy mają wszedł/zszedł. */
echo "\n# Indeks zdarzeń — wpisy z wszedł/zszedł (z player.id łączymy w renderze):\n";
$idx  = hajlajty_player_event_index( $events );
$have = 0;
foreach ( $idx as $pid => $e ) {
	if ( null !== $e['wszedl'] || null !== $e['zszedl'] ) {
		++$have;
		printf(
			"  #%d  wszedł=%s  zszedł=%s  gole=%d\n",
			$pid,
			var_export( $e['wszedl'], true ),
			var_export( $e['zszedl'], true ),
			$e['gole']
		);
	}
}
if ( ! $have ) {
	echo "  (ŻADEN wpis nie ma wszedł/zszedł — indeks ich nie dostał)\n";
}

echo "\n# PODSUMOWANIE: subst_events=$subst_n | wpisy_z_wszedl_zszedl=$have | wpisy_indeksu=" . count( $idx ) . "\n";
echo "# Interpretacja:\n";
echo "#  subst_events=0            → DANE (import nie zapisał zmian) — fix w core/imporcie.\n";
echo "#  subst>0, player_id/assist_id=null → DANE/kontrakt (pola wycięte) — fix w transform.\n";
echo "#  subst>0, id obecne, have=0 → derive (mało prawdopodobne — kod zweryfikowany).\n";
echo "#  have>0                    → indeks OK → problem czysto w renderze (partial).\n";
echo "$line\n";
