<?php
/**
 * Test czystej logiki drabinki pucharowej (view-model) — czysty PHP, BEZ WordPressa.
 * Wzór: tests/knockout-merge.php / tests/mvp-e-standings.php.
 *
 * Uruchom w Open Site Shell Locala (z katalogu motywu):
 *   php tests/bracket.php
 *
 * Pokrywa: parser feedera „Zwycięzca/Przegrany meczu {N}" → numer; tryb komórki
 * (real wygrywa nad placeholderem; R32-bez-realnego → TBD); budowę kolumn (kolejność
 * rund + porządek DRZEWA, w którym feederzy sąsiadują) na realnym harmonogramie FIFA.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require __DIR__ . '/../features/match-lists/knockout.php'; // hajlajty_knockout_schedule()
require __DIR__ . '/../features/match-lists/bracket.php';

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

/** Skrót: lista numerów meczów w kolumnie danej rundy (z view-modelu). */
function nos_of_round( array $columns, string $round ): array {
	foreach ( $columns as $col ) {
		if ( $col['round'] === $round ) {
			return array_map(
				static function ( $c ) {
					return $c['no'];
				},
				$col['cells']
			);
		}
	}
	return array();
}

echo "=== PARSER FEEDERA (krawędzie z etykiet) ===\n";
check( 'Zwycięzca meczu 74 → 74', 74, hajlajty_bracket_feeder_no( 'Zwycięzca meczu 74' ) );
check( 'Przegrany meczu 101 → 101', 101, hajlajty_bracket_feeder_no( 'Przegrany meczu 101' ) );
check( 'null → 0', 0, hajlajty_bracket_feeder_no( null ) );
check( 'pusty string → 0', 0, hajlajty_bracket_feeder_no( '' ) );
check( 'bez liczby → 0', 0, hajlajty_bracket_feeder_no( 'Do ustalenia' ) );

echo "\n=== TRYB KOMÓRKI (realny WYGRYWA; R32-bez-realnego = TBD) ===\n";
check( 'realny + etykiety → real', 'real', hajlajty_bracket_cell_mode( true, true ) );
check( 'realny bez etykiet → real (R32 z importu)', 'real', hajlajty_bracket_cell_mode( true, false ) );
check( 'brak realnego + etykiety → placeholder', 'placeholder', hajlajty_bracket_cell_mode( false, true ) );
check( 'brak realnego + brak etykiet → tbd', 'tbd', hajlajty_bracket_cell_mode( false, false ) );

echo "\n=== BUDOWA KOLUMN (kolejność rund + porządek drzewa) ===\n";
$columns = hajlajty_bracket_build( hajlajty_knockout_schedule() );

$round_seq = array_map(
	static function ( $c ) {
		return $c['round'];
	},
	$columns
);
check(
	'kolumny w kolejności bracket (R32…Finał, 3. miejsce na końcu)',
	array( 'Round of 32', 'Round of 16', 'Quarter-finals', 'Semi-finals', 'Final', '3rd Place Final' ),
	$round_seq
);

check( 'R32: 16 komórek', 16, count( nos_of_round( $columns, 'Round of 32' ) ) );
check( 'R16: 8 komórek', 8, count( nos_of_round( $columns, 'Round of 16' ) ) );
check( 'Ćwierćfinały: 4 komórki', 4, count( nos_of_round( $columns, 'Quarter-finals' ) ) );
check( 'Półfinały: 2 komórki', 2, count( nos_of_round( $columns, 'Semi-finals' ) ) );
check( 'Finał: 1 komórka', 1, count( nos_of_round( $columns, 'Final' ) ) );
check( 'Mecz o 3. miejsce: 1 komórka', 1, count( nos_of_round( $columns, '3rd Place Final' ) ) );

// Porządek DRZEWA (BFS od Finału po feederach) — feederzy sąsiadują pionowo.
check( 'Finał = mecz 104', array( 104 ), nos_of_round( $columns, 'Final' ) );
check( 'Półfinały w porządku drzewa: 101,102', array( 101, 102 ), nos_of_round( $columns, 'Semi-finals' ) );
check( 'Ćwierćfinały w porządku drzewa: 97,98,99,100', array( 97, 98, 99, 100 ), nos_of_round( $columns, 'Quarter-finals' ) );
check(
	'R16 w porządku drzewa (feederzy ćwierćfinałów po kolei)',
	array( 89, 90, 93, 94, 91, 92, 95, 96 ),
	nos_of_round( $columns, 'Round of 16' )
);
check(
	'R32 w porządku drzewa (feederzy R16 po kolei; wszystkie 16 unikalnych)',
	array( 74, 77, 73, 75, 83, 84, 81, 82, 76, 78, 79, 80, 86, 88, 85, 87 ),
	nos_of_round( $columns, 'Round of 32' )
);

echo "\n=== KOMÓRKA: ETYKIETY I FEEDERZY ===\n";
// Wyłuskaj komórkę 89 (R16) i sprawdź krawędzie + etykiety.
$cell89 = null;
foreach ( $columns as $col ) {
	foreach ( $col['cells'] as $c ) {
		if ( 89 === $c['no'] ) {
			$cell89 = $c;
		}
	}
}
check( 'mecz 89 istnieje w modelu', true, null !== $cell89 );
check( 'mecz 89 feeder home = 74', 74, $cell89['home_feeder'] );
check( 'mecz 89 feeder away = 77', 77, $cell89['away_feeder'] );
check( 'mecz 89 etykieta home = „Zwycięzca meczu 74"', 'Zwycięzca meczu 74', $cell89['home_label'] );

// R32 komórka 73: BEZ etykiet (realne drużyny z importu), feederzy 0.
$cell73 = null;
foreach ( $columns as $col ) {
	foreach ( $col['cells'] as $c ) {
		if ( 73 === $c['no'] ) {
			$cell73 = $c;
		}
	}
}
check( 'R32 mecz 73: brak etykiety home (null)', null, $cell73['home_label'] );
check( 'R32 mecz 73: feeder home = 0', 0, $cell73['home_feeder'] );

// Mecz o 3. miejsce (103): feederzy = PRZEGRANI półfinałów (101,102).
$cell103 = null;
foreach ( $columns as $col ) {
	foreach ( $col['cells'] as $c ) {
		if ( 103 === $c['no'] ) {
			$cell103 = $c;
		}
	}
}
check( 'mecz 103 feeder home = 101 (Przegrany meczu 101)', 101, $cell103['home_feeder'] );
check( 'mecz 103 feeder away = 102 (Przegrany meczu 102)', 102, $cell103['away_feeder'] );

printf( "\n=== WYNIK: %d/%d PASS ===\n", $pass, $pass + $fail );
exit( $fail > 0 ? 1 : 0 );
