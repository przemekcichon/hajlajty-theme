<?php
/**
 * Test ścieżek ZALEŻNYCH od WordPressa (MVP-e) — wymaga get_term_meta / get_terms,
 * więc uruchamiany na żywym WP (Open Site Shell Locala), NIE jako czysty php:
 *   wp eval-file wp-content/themes/hajlajty-theme/tests/mvp-e-standings.eval.php
 *
 * Domyka lukę po `tests/mvp-e-standings.php` (czyste funkcje stref/„X/3"): tu
 * sprawdzamy getter, resolver termu i batch-resolver drużyn na REALNYCH danych
 * MVP-d (Mundial 2026: term „rozgrywki" #3, league_id=1, meta standings_2026).
 * Wzór: tests/3a-helpers.eval.php.
 *
 * Wartości oczekiwane wynikają z potwierdzonego runtime (12 grup A–L po 4 drużyny;
 * Polska = term drużyny api_id 24, fifa POL). Jeśli dane sezonu się zmienią,
 * dostosuj stałe na górze.
 */

// Slice jest już załadowany, gdy motyw aktywny (autoloader features/*). Gdyby nie —
// dociągamy pliki (z guardem, by nie redeklarować funkcji na aktywnym motywie).
if ( ! function_exists( 'hajlajty_get_standings' ) ) {
	require __DIR__ . '/../features/standings-view/data.php';
}
if ( ! function_exists( 'hajlajty_standings_zone_class' ) ) {
	require __DIR__ . '/../features/standings-view/zones.php';
}

$league_id = 1;
$season    = '2026';

$pass = 0;
$fail = 0;

function check( string $name, $expected, $actual ): void {
	global $pass, $fail;
	$ok = ( $expected === $actual );
	$ok ? $pass++ : $fail++;
	printf(
		"[%s] %s\n        oczekiwano: %s\n        otrzymano:  %s\n",
		$ok ? 'PASS' : 'FAIL',
		$name,
		var_export( $expected, true ),
		var_export( $actual, true )
	);
}

$line = str_repeat( '-', 60 );

/* ========================================================================
 * Resolver termu „rozgrywki" po league_id (WŁASNY, nie core).
 * ===================================================================== */
echo "$line\n# hajlajty_standings_view_find_league_term($league_id)\n$line\n";
$term_id = hajlajty_standings_view_find_league_term( $league_id );
printf( "term_id: %d (runtime potwierdził #3 dla Mundialu)\n", $term_id );
check( 'league_id 1 → term_id > 0', true, $term_id > 0 );
check( 'league_id 0 → 0 (brak resolucji)', 0, hajlajty_standings_view_find_league_term( 0 ) );

/* ========================================================================
 * Getter: jeden json_decode, klucz standings_<sezon>, normalizacja cyfr.
 * ===================================================================== */
echo "\n$line\n# hajlajty_get_standings($term_id, '$season')\n$line\n";
$table = hajlajty_get_standings( $term_id, $season );
check( 'zwraca tablicę', true, is_array( $table ) );
check( 'klucze grup = A..L', range( 'A', 'L' ), array_keys( $table ) );

$counts_ok = true;
foreach ( $table as $letter => $rows ) {
	printf( "  grupa %s: %d wierszy\n", $letter, count( $rows ) );
	if ( 4 !== count( $rows ) ) {
		$counts_ok = false;
	}
}
check( 'każda grupa ma 4 drużyny', true, $counts_ok );

// Normalizacja sezonu: „ 2026 " trafia w ten sam klucz co „2026".
check( 'sezon „ 2026 " (spacje) → ten sam JSON', $table, hajlajty_get_standings( $term_id, ' 2026 ' ) );
// Fallbacki: brak termu / pusty sezon → [] (nie warning, nie null).
check( 'term 0 → []', array(), hajlajty_get_standings( 0, $season ) );
check( 'sezon "" → []', array(), hajlajty_get_standings( $term_id, '' ) );

/* ========================================================================
 * Kontrakt wiersza MVP-d — pola VERBATIM obecne w realnym rekordzie.
 * ===================================================================== */
echo "\n$line\n# Kształt wiersza (pierwszy wiersz grupy A)\n$line\n";
$first = isset( $table['A'][0] ) ? $table['A'][0] : array();
$keys  = array( 'rank', 'team_id', 'points', 'played', 'win', 'draw', 'lose', 'gf', 'ga', 'diff', 'group', 'zone' );
foreach ( $keys as $k ) {
	printf( "  %-8s = %s\n", $k, var_export( $first[ $k ] ?? '(brak)', true ) );
}
check( 'wiersz ma wszystkie klucze kontraktu', $keys, array_keys( $first ) );

/* ========================================================================
 * Batch-resolver drużyn (api_id → WP_Term) — bez N+1, mapa po term meta.
 * ===================================================================== */
echo "\n$line\n# hajlajty_standings_resolve_teams(<wszystkie team_id>)\n$line\n";
$team_ids = array();
foreach ( $table as $rows ) {
	foreach ( $rows as $row ) {
		$team_ids[] = (int) ( $row['team_id'] ?? 0 );
	}
}
$unique = array_values( array_unique( array_filter( $team_ids ) ) );
$teams  = hajlajty_standings_resolve_teams( $team_ids );
printf( "team_id w tabeli: %d (unikalnych %d) → rozwiązano termów: %d\n", count( $team_ids ), count( $unique ), count( $teams ) );

$gaps = array_values( array_diff( $unique, array_keys( $teams ) ) );
if ( ! empty( $gaps ) ) {
	printf( "  LUKI w seedzie (api_id bez termu „drużyna”): %s\n", implode( ', ', $gaps ) );
}
check( 'mapa kluczowana po api_id (Polska 24 obecna)', true, isset( $teams[24] ) );
if ( isset( $teams[24] ) ) {
	$pol = $teams[24];
	printf( "  api_id 24 → „%s” | fifa_code %s | flaga %s\n", $pol->name, get_term_meta( $pol->term_id, 'fifa_code', true ), hajlajty_flag_url( $pol ) );
	check( 'api_id 24 → nazwa termu „Polska"', 'Polska', $pol->name );
}
check( 'pusta lista → []', array(), hajlajty_standings_resolve_teams( array() ) );

/* ========================================================================
 * Integracja stref: rank z realnych danych → klasa CSS.
 * ===================================================================== */
echo "\n$line\n# Strefy po rank (grupa A, realne rank)\n$line\n";
foreach ( ( $table['A'] ?? array() ) as $row ) {
	printf( "  rank %s → %s\n", var_export( $row['rank'] ?? null, true ), var_export( hajlajty_standings_zone_class( $row['rank'] ?? 0 ), true ) );
}

printf( "\n=== WYNIK: %d/%d PASS ===\n", $pass, $pass + $fail );
