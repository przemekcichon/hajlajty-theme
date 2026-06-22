<?php
/**
 * Test czystych funkcji tabeli grup (MVP-e) — czysty PHP, BEZ WordPressa.
 *
 * Uruchom w Open Site Shell Locala (z katalogu motywu):
 *   php tests/mvp-e-standings.php
 *
 * Pokrywa logikę stref (po `rank`, NIE po `zone`) i etykiety „rozegrane".
 * Wiersze budowane RĘCZNIE wg kontraktu MVP-d (skrócone do testowanych pól).
 */

// zones.php ma guard ABSPATH (żyje w motywie WP). Definiujemy go, by odpalić
// plik poza WordPressem (wzór: tests/3a-lookups.php).
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require __DIR__ . '/../features/standings-view/zones.php';

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

echo "=== STREFY PO RANK (qual 1-2 / play 3 / brak 4+) ===\n";
check( 'rank 1 → qual', 'qual', hajlajty_standings_zone_class( 1 ) );
check( 'rank 2 → qual', 'qual', hajlajty_standings_zone_class( 2 ) );
check( 'rank 3 → play', 'play', hajlajty_standings_zone_class( 3 ) );
check( 'rank 4 → brak', '', hajlajty_standings_zone_class( 4 ) );
check( 'rank 5 → brak (5-zespołowa hipotetycznie)', '', hajlajty_standings_zone_class( 5 ) );
// Fallbacki: rank nullable wg kontraktu — null/0/string nie mogą wybuchać.
check( 'rank null → brak', '', hajlajty_standings_zone_class( null ) );
check( 'rank 0 → brak', '', hajlajty_standings_zone_class( 0 ) );
check( 'rank „2" (string) → qual', 'qual', hajlajty_standings_zone_class( '2' ) );

echo "\n=== ETYKIETA ROZEGRANE (max(played)/3) ===\n";
// Komplet rozegrany: 3/3.
check(
	'wszystkie played=3 → 3/3',
	'3/3',
	hajlajty_standings_played_label( array(
		array( 'played' => 3 ),
		array( 'played' => 3 ),
		array( 'played' => 3 ),
		array( 'played' => 3 ),
	) )
);
// NIEjednolite played (mecz w toku/przełożony, FAKT runtime grupa G) → max.
check(
	'played [2,2,1,1] → 2/3 (max, nie min/avg)',
	'2/3',
	hajlajty_standings_played_label( array(
		array( 'played' => 2 ),
		array( 'played' => 2 ),
		array( 'played' => 1 ),
		array( 'played' => 1 ),
	) )
);
// played nullable → traktowane jak 0.
check(
	'played z null → 1/3',
	'1/3',
	hajlajty_standings_played_label( array(
		array( 'played' => null ),
		array( 'played' => 1 ),
		array(),
	) )
);
check( 'pusta grupa → 0/3', '0/3', hajlajty_standings_played_label( array() ) );

printf( "\n=== WYNIK: %d/%d PASS ===\n", $pass, $pass + $fail );
exit( $fail > 0 ? 1 : 0 );
