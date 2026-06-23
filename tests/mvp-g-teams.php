<?php
/**
 * Test czystych funkcji widżetu statystyk drużyny (MVP-g) — czysty PHP, BEZ
 * WordPressa.
 *
 * Uruchom w Open Site Shell Locala (z katalogu motywu):
 *   php tests/mvp-g-teams.php
 *
 * Pokrywa mapowanie CURATED JSON MVP-f → wiersze widżetu (.stat-row), pigułki
 * formy i etykietę seeda. Fixtury wg ground-truth MVP-f (realny zapis Belgii:
 * `form:"DD"`, średnie STRINGAMI, kartki intami) + przypadki brzegowe (null/0).
 */

// stats.php ma guard ABSPATH (żyje w motywie WP). Definiujemy go, by odpalić plik
// poza WordPressem (wzór: tests/mvp-e-standings.php).
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require __DIR__ . '/../features/teams-view/stats.php';

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

// Realny zapis Belgii (ground-truth runtime MVP-f).
$belgia = array(
	'league_id'       => 1,
	'season'          => '2026',
	'form'            => 'DD',
	'fixtures'        => array( 'played' => 2, 'wins' => 0, 'draws' => 2, 'loses' => 0 ),
	'goals'           => array(
		'for'     => array( 'total' => 1, 'average' => '0.5' ),
		'against' => array( 'total' => 1, 'average' => '0.5' ),
	),
	'clean_sheet'     => 1,
	'failed_to_score' => 1,
	'cards'           => array( 'yellow' => 3, 'red' => 1 ),
);

echo "=== STAT ROWS (Belgia, realny zapis) ===\n";
$rows = hajlajty_teams_view_stat_rows( $belgia );
check( 'liczba wierszy = 5', 5, count( $rows ) );
check( 'śr. goli zdobytych = STRING „0.5" (zero koercji)', '0.5', $rows[0]['val'] );
check( 'śr. goli zdobytych bez paska', null, $rows[0]['bar'] );
check( 'śr. goli straconych = „0.5"', '0.5', $rows[1]['val'] );
check( 'czyste konta = „1 / 2"', '1 / 2', $rows[2]['val'] );
check( 'czyste konta pasek = 50%', 50, $rows[2]['bar'] );
check( 'żółte kartki = „3"', '3', $rows[3]['val'] );
check( 'czerwone kartki = „1"', '1', $rows[4]['val'] );

echo "\n=== STAT ROWS — przypadki brzegowe (null/0) ===\n";
// Pusty curated → brak wierszy (render pokazuje notkę).
check( 'pusty curated → []', array(), hajlajty_teams_view_stat_rows( array() ) );

// Średnia null → „–", brak paska. clean/played brak → „–" + brak paska.
$puste = array(
	'fixtures' => array( 'played' => null ),
	'goals'    => array(
		'for'     => array( 'average' => null ),
		'against' => array( 'average' => null ),
	),
	'clean_sheet' => null,
	'cards'       => array( 'yellow' => 0, 'red' => 0 ),
);
$rows_puste = hajlajty_teams_view_stat_rows( $puste );
check( 'śr. null → „–"', '–', $rows_puste[0]['val'] );
check( 'czyste konta (null/null) → „–"', '–', $rows_puste[2]['val'] );
check( 'czyste konta bez danych → brak paska', null, $rows_puste[2]['bar'] );
check( 'żółte 0 → „0" (nie „–")', '0', $rows_puste[3]['val'] );

// played=0 → bez dzielenia przez zero (pasek null), wartość „0 / 0".
$zero = array(
	'fixtures'    => array( 'played' => 0 ),
	'clean_sheet' => 0,
	'cards'       => array( 'yellow' => 0, 'red' => 0 ),
	'goals'       => array(),
);
$rows_zero = hajlajty_teams_view_stat_rows( $zero );
check( 'played 0 → „0 / 0"', '0 / 0', $rows_zero[2]['val'] );
check( 'played 0 → brak paska (bez /0)', null, $rows_zero[2]['bar'] );

echo "\n=== FORM PILLS ===\n";
check( 'null → []', array(), hajlajty_teams_view_form_pills( null ) );
check( "'' → []", array(), hajlajty_teams_view_form_pills( '' ) );
check( '„DD" → 2 pigułki', 2, count( hajlajty_teams_view_form_pills( 'DD' ) ) );
check(
	'„WDL" → klasy win/draw/lose',
	array( 'win', 'draw', 'lose' ),
	array_column( hajlajty_teams_view_form_pills( 'WDL' ), 'cls' )
);
check( '„w" (małe) → rozpoznane jako W', 'win', hajlajty_teams_view_form_pills( 'w' )[0]['cls'] );
check( 'śmieci „X1" → pominięte', array(), hajlajty_teams_view_form_pills( 'X1' ) );
// Twardość typu: nie-string (gdyby API zmieniło schemat) NIE rzuca TypeError → [].
check( 'tablica → [] (bez TypeError)', array(), hajlajty_teams_view_form_pills( array( 'W' ) ) );
check( 'int → [] (bez TypeError)', array(), hajlajty_teams_view_form_pills( 5 ) );

echo "\n=== SEED LABEL ===\n";
check( '(„G", 3) → „G3"', 'G3', hajlajty_teams_view_seed_label( 'G', 3 ) );
check( '(„A", „1" string) → „A1"', 'A1', hajlajty_teams_view_seed_label( 'A', '1' ) );
check( 'brak litery → „"', '', hajlajty_teams_view_seed_label( '', 2 ) );
check( 'ranga null → „"', '', hajlajty_teams_view_seed_label( 'G', null ) );

printf( "\n=== WYNIK: %d/%d PASS ===\n", $pass, $pass + $fail );
exit( $fail > 0 ? 1 : 0 );
