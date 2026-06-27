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

echo "\n=== SPÓJNOŚĆ KURACYJNEGO HARMONOGRAMU ===\n";
$schedule    = hajlajty_knockout_schedule();
$allowed     = array( 'Round of 16', 'Quarter-finals', 'Semi-finals', '3rd Place Final', 'Final' );
$rounds_ok   = true;
$kickoffs_ok = true;
$keys_unique = array();
$dupe        = false;
foreach ( $schedule as $row ) {
	if ( ! in_array( $row['round'] ?? null, $allowed, true ) ) {
		$rounds_ok = false;
	}
	// Format kickoffa pod dedup: dokładnie „RRRR-MM-DD HH:MM:SS" (jak płaska meta).
	if ( ! is_string( $row['kickoff'] ?? null ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['kickoff'] ) ) {
		$kickoffs_ok = false;
	}
	$k = hajlajty_knockout_key( $row['round'] ?? null, $row['kickoff'] ?? null );
	if ( isset( $keys_unique[ $k ] ) ) {
		$dupe = true;
	}
	$keys_unique[ $k ] = true;
}
check( 'harmonogram niepusty', true, count( $schedule ) > 0 );
check( 'wszystkie rundy z dozwolonych literałów (klucze lookup_round)', true, $rounds_ok );
check( 'wszystkie kickoffy w formacie meta „Y-m-d H:i:s"', true, $kickoffs_ok );
check( 'klucze (round,kickoff) unikalne w harmonogramie', false, $dupe );
// R32 świadomie NIE jest placeholderem (realne fixtures z importu).
$has_r32 = false;
foreach ( $schedule as $row ) {
	if ( 'Round of 32' === ( $row['round'] ?? null ) ) {
		$has_r32 = true;
	}
}
check( 'harmonogram NIE zawiera Round of 32 (realne z importu)', false, $has_r32 );

printf( "\n=== WYNIK: %d/%d PASS ===\n", $pass, $pass + $fail );
exit( $fail > 0 ? 1 : 0 );
