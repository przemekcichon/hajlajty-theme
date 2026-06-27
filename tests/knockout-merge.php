<?php
/**
 * Test czystej logiki scalania placeholderów pucharowych (terminarz) — czysty PHP,
 * BEZ WordPressa. Wzór: tests/mvp-e-standings.php / tests/3a-lookups.php.
 *
 * Uruchom w Open Site Shell Locala (z katalogu motywu):
 *   php tests/knockout-merge.php
 *
 * Pokrywa: klucz dedup (round,kickoff), „realny WYGRYWA" z placeholderem,
 * sortowanie chronologiczne, oznaczanie `type`, oraz spójność kuracyjnego
 * harmonogramu (literały rund + format kickoffa pod dedup).
 */

// knockout.php ma guard ABSPATH (żyje w motywie WP). Definiujemy go, by odpalić
// plik poza WordPressem (wzór: tests/mvp-e-standings.php).
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require __DIR__ . '/../features/match-lists/knockout.php';

$pass = 0;
$fail = 0;

/** Asercja równości z czytelnym wypisem PASS/FAIL. */
function check( string $name, $expected, $actual ): void {
	global $pass, $fail;
	$ok = ( $expected === $actual );
	if ( $ok ) {
		$pass++;
	} else {
		$fail++;
	}
	printf(
		"[%s] %s\n        oczekiwano: %s\n        otrzymano:  %s\n",
		$ok ? 'PASS' : 'FAIL',
		$name,
		var_export( $expected, true ),
		var_export( $actual, true )
	);
}

/** Skrót: lista kluczy round|kickoff scalonej listy (kolejność = po sorcie). */
function keys_of( array $merged ): array {
	return array_map(
		static function ( $i ) {
			return ( $i['type'] ?? '?' ) . ':' . hajlajty_knockout_key( $i['round'] ?? null, $i['kickoff'] ?? null );
		},
		$merged
	);
}

echo "=== KLUCZ DEDUP (round,kickoff) ===\n";
check( 'klucz łączy round + kickoff', 'Final|2026-07-19 19:00:00', hajlajty_knockout_key( 'Final', '2026-07-19 19:00:00' ) );
check( 'klucz trimuje białe znaki', 'Final|2026-07-19 19:00:00', hajlajty_knockout_key( ' Final ', ' 2026-07-19 19:00:00 ' ) );
check( 'klucz null → puste segmenty', '|', hajlajty_knockout_key( null, null ) );

echo "\n=== SCALANIE: REALNY WYGRYWA Z PLACEHOLDEREM ===\n";
// Placeholder R16 o tym samym (round,kickoff) co realny mecz → POMIJANY.
$real = array(
	array( 'post_id' => 11, 'round' => 'Round of 16', 'kickoff' => '2026-07-04 21:00:00' ),
);
$placeholders = array(
	array( 'round' => 'Round of 16', 'kickoff' => '2026-07-04 21:00:00', 'home' => 'Zwycięzca meczu 74', 'away' => 'Zwycięzca meczu 77' ),
	array( 'round' => 'Round of 16', 'kickoff' => '2026-07-04 17:00:00', 'home' => 'Zwycięzca meczu 73', 'away' => 'Zwycięzca meczu 75' ),
);
$merged = hajlajty_knockout_merge( $real, $placeholders );
check( 'kolizyjny placeholder odsiany; zostają 2 pozycje', 2, count( $merged ) );
check(
	'kolejność po kickoff: placeholder 17:00 przed realnym 21:00',
	array( 'placeholder:Round of 16|2026-07-04 17:00:00', 'post:Round of 16|2026-07-04 21:00:00' ),
	keys_of( $merged )
);
check( 'realny zachowuje passthrough post_id', 11, $merged[1]['post_id'] );
check( 'realny dostaje type=post', 'post', $merged[1]['type'] );
check( 'placeholder dostaje type=placeholder', 'placeholder', $merged[0]['type'] );

echo "\n=== DEDUP WYMAGA OBU CZĘŚCI KLUCZA ===\n";
// Ta sama godzina, INNA runda → NIE kolizja (placeholder zostaje).
$merged2 = hajlajty_knockout_merge(
	array( array( 'post_id' => 5, 'round' => 'Quarter-finals', 'kickoff' => '2026-07-04 21:00:00' ) ),
	array( array( 'round' => 'Round of 16', 'kickoff' => '2026-07-04 21:00:00', 'home' => 'A', 'away' => 'B' ) )
);
check( 'inna runda przy tej samej godzinie → brak dedup', 2, count( $merged2 ) );
// Ta sama runda, INNA godzina → NIE kolizja.
$merged3 = hajlajty_knockout_merge(
	array( array( 'post_id' => 6, 'round' => 'Final', 'kickoff' => '2026-07-19 19:00:00' ) ),
	array( array( 'round' => 'Final', 'kickoff' => '2026-07-19 20:00:00', 'home' => 'A', 'away' => 'B' ) )
);
check( 'ta sama runda, inna godzina → brak dedup', 2, count( $merged3 ) );

echo "\n=== SORTOWANIE CHRONOLOGICZNE + REMIS ===\n";
// Realny i placeholder o IDENTYCZNYM kickoffie (różne rundy) → realny pierwszy.
$merged4 = hajlajty_knockout_merge(
	array( array( 'post_id' => 9, 'round' => 'Quarter-finals', 'kickoff' => '2026-07-11 21:00:00' ) ),
	array( array( 'round' => 'Round of 16', 'kickoff' => '2026-07-11 21:00:00', 'home' => 'A', 'away' => 'B' ) )
);
check( 'remis kickoff: realny przed placeholderem', 'post', $merged4[0]['type'] );
// Pusty wkład.
check( 'puste wejście → pusta lista', array(), hajlajty_knockout_merge( array(), array() ) );
// Same placeholdery, bez realnych — wszystkie zostają, posortowane.
$only_ph = hajlajty_knockout_merge(
	array(),
	array(
		array( 'round' => 'Final', 'kickoff' => '2026-07-19 19:00:00', 'home' => 'A', 'away' => 'B' ),
		array( 'round' => 'Semi-finals', 'kickoff' => '2026-07-14 19:00:00', 'home' => 'C', 'away' => 'D' ),
	)
);
check(
	'same placeholdery sort ASC po kickoff',
	array( 'placeholder:Semi-finals|2026-07-14 19:00:00', 'placeholder:Final|2026-07-19 19:00:00' ),
	keys_of( $only_ph )
);

echo "\n=== SPÓJNOŚĆ KURACYJNEJ TABELI MECZÓW ===\n";
$schedule    = hajlajty_knockout_schedule();
// Tabela niesie też Round of 32 (TYLKO dla numeru); dozwolone literały rund:
$allowed     = array( 'Round of 32', 'Round of 16', 'Quarter-finals', 'Semi-finals', '3rd Place Final', 'Final' );
$rounds_ok   = true;
$kickoffs_ok = true;
$nos_ok      = true;
$keys_unique = array();
$nos_unique  = array();
$dupe        = false;
$dupe_no     = false;
foreach ( $schedule as $row ) {
	if ( ! in_array( $row['round'] ?? null, $allowed, true ) ) {
		$rounds_ok = false;
	}
	// Format kickoffa pod dedup/lookup: dokładnie „RRRR-MM-DD HH:MM:SS" (jak płaska meta).
	if ( ! is_string( $row['kickoff'] ?? null ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['kickoff'] ) ) {
		$kickoffs_ok = false;
	}
	// Numer meczu FIFA w zakresie 73–104.
	$no = (int) ( $row['no'] ?? 0 );
	if ( $no < 73 || $no > 104 ) {
		$nos_ok = false;
	}
	$k = hajlajty_knockout_key( $row['round'] ?? null, $row['kickoff'] ?? null );
	if ( isset( $keys_unique[ $k ] ) ) {
		$dupe = true;
	}
	$keys_unique[ $k ] = true;
	if ( isset( $nos_unique[ $no ] ) ) {
		$dupe_no = true;
	}
	$nos_unique[ $no ] = true;
}
check( 'tabela niepusta', true, count( $schedule ) > 0 );
check( 'komplet 32 meczów pucharowych (73–104)', 32, count( $schedule ) );
check( 'wszystkie rundy z dozwolonych literałów (klucze lookup_round)', true, $rounds_ok );
check( 'wszystkie kickoffy w formacie meta „Y-m-d H:i:s"', true, $kickoffs_ok );
check( 'numery meczów w zakresie 73–104', true, $nos_ok );
check( 'klucze (round,kickoff) unikalne', false, $dupe );
check( 'numery meczów unikalne', false, $dupe_no );

echo "\n=== PLACEHOLDERY: TYLKO RUNDY BEZ FIXTURES (R16…Final) ===\n";
$placeholders = hajlajty_knockout_placeholders();
$labels_ok    = true;
$ph_has_r32   = false;
foreach ( $placeholders as $row ) {
	if ( '' === (string) ( $row['home'] ?? '' ) || '' === (string) ( $row['away'] ?? '' ) ) {
		$labels_ok = false;
	}
	if ( 'Round of 32' === ( $row['round'] ?? null ) ) {
		$ph_has_r32 = true;
	}
}
check( 'placeholdery: 16 meczów (R16 8 + ćwierć 4 + pół 2 + 3. miejsce 1 + finał 1)', 16, count( $placeholders ) );
check( 'każdy placeholder ma etykiety home+away', true, $labels_ok );
check( 'placeholdery NIE zawierają Round of 32 (realne z importu)', false, $ph_has_r32 );

echo "\n=== NUMER MECZU PO (round,kickoff) ===\n";
check( 'R32 mecz 73 (RPA vs Kanada, zwalidowany runtime)', 73, hajlajty_knockout_match_no( 'Round of 32', '2026-06-28 19:00:00' ) );
check( 'R16 mecz 89', 89, hajlajty_knockout_match_no( 'Round of 16', '2026-07-04 21:00:00' ) );
check( 'Final mecz 104', 104, hajlajty_knockout_match_no( 'Final', '2026-07-19 19:00:00' ) );
check( 'godzina spoza bracketu → 0 (brak plakietki)', 0, hajlajty_knockout_match_no( 'Round of 32', '2026-06-28 18:00:00' ) );
check( 'faza grupowa → 0', 0, hajlajty_knockout_match_no( 'Group Stage - 1', '2026-06-12 19:00:00' ) );
check( 'null → 0', 0, hajlajty_knockout_match_no( null, null ) );

printf( "\n=== WYNIK: %d/%d PASS ===\n", $pass, $pass + $fail );
exit( $fail > 0 ? 1 : 0 );
