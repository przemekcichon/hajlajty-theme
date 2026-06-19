<?php
/**
 * Test słowników 3a — czysty PHP, BEZ WordPressa.
 *
 * Uruchom w Open Site Shell Locala (z katalogu motywu):
 *   php tests/3a-lookups.php
 *
 * Pokrywa KAŻDĄ gałąź lookups.php. Eventy są budowane RĘCZNIE wg kontraktu
 * (type, detail) — w realnych meczach 11/12 nie występują, więc to dane
 * syntetyczne, nie próbka.
 */

// lookups.php ma guard ABSPATH (bo żyje w motywie WP). Definiujemy go, by móc
// odpalić plik poza WordPressem.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require __DIR__ . '/../features/match-display/lookups.php';

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

echo "=== STATUS ===\n";
// Po jednym kodzie z każdego stanu.
check( 'status NS → ZAPOWIEDZ', array( 'state' => 'ZAPOWIEDZ', 'show_minute' => false, 'live_label' => null ), hajlajty_lookup_status( 'NS' ) );
check( 'status FT → ZAKONCZONY', array( 'state' => 'ZAKONCZONY', 'show_minute' => false, 'live_label' => null ), hajlajty_lookup_status( 'FT' ) );
check( 'status CANC → ODWOLANY', array( 'state' => 'ODWOLANY', 'show_minute' => false, 'live_label' => null ), hajlajty_lookup_status( 'CANC' ) );
// Tykające LIVE (show_minute=true).
check( 'status 1H → LIVE +minuta', array( 'state' => 'LIVE', 'show_minute' => true, 'live_label' => null ), hajlajty_lookup_status( '1H' ) );
check( 'status 2H → LIVE +minuta', array( 'state' => 'LIVE', 'show_minute' => true, 'live_label' => null ), hajlajty_lookup_status( '2H' ) );
check( 'status ET → LIVE +minuta', array( 'state' => 'LIVE', 'show_minute' => true, 'live_label' => null ), hajlajty_lookup_status( 'ET' ) );
// Każda etykieta pauzy/trybu LIVE.
check( 'status HT → Przerwa', array( 'state' => 'LIVE', 'show_minute' => false, 'live_label' => 'Przerwa' ), hajlajty_lookup_status( 'HT' ) );
check( 'status BT → Przerwa', array( 'state' => 'LIVE', 'show_minute' => false, 'live_label' => 'Przerwa' ), hajlajty_lookup_status( 'BT' ) );
check( 'status P → Karne', array( 'state' => 'LIVE', 'show_minute' => false, 'live_label' => 'Karne' ), hajlajty_lookup_status( 'P' ) );
check( 'status SUSP → Zawieszony', array( 'state' => 'LIVE', 'show_minute' => false, 'live_label' => 'Zawieszony' ), hajlajty_lookup_status( 'SUSP' ) );
check( 'status INT → Przerwany', array( 'state' => 'LIVE', 'show_minute' => false, 'live_label' => 'Przerwany' ), hajlajty_lookup_status( 'INT' ) );
check( 'status LIVE → Na żywo', array( 'state' => 'LIVE', 'show_minute' => false, 'live_label' => 'Na żywo' ), hajlajty_lookup_status( 'LIVE' ) );
// Fallback nieznany / null.
check( 'status ZZZ → fallback ZAPOWIEDZ', array( 'state' => 'ZAPOWIEDZ', 'show_minute' => false, 'live_label' => null ), hajlajty_lookup_status( 'ZZZ' ) );
check( 'status null → fallback ZAPOWIEDZ', array( 'state' => 'ZAPOWIEDZ', 'show_minute' => false, 'live_label' => null ), hajlajty_lookup_status( null ) );

echo "\n=== KODY LIVE (3e-i: hajlajty_status_live_codes) ===\n";
// Filtr list „Na żywo" (status IN …) zależy WYŁĄCZNIE od tego zbioru. Musi być
// dokładnie grupą „In Play" z mapy — ani mniej (mecz live znika z listy), ani
// więcej (np. FT wisiałby jako live). Porównanie po posortowaniu = niezależne
// od kolejności iteracji mapy.
$live_codes = hajlajty_status_live_codes();
sort( $live_codes );
$expected_live = array( '1H', '2H', 'BT', 'ET', 'HT', 'INT', 'LIVE', 'P', 'SUSP' );
sort( $expected_live );
check( 'live codes = dokładnie grupa „In Play"', $expected_live, $live_codes );

// Spójność derywacji z mapą: każdy zwrócony kod faktycznie ma stan LIVE w
// hajlajty_lookup_status (gdyby derywacja rozjechała się z mapą — FAIL).
$all_live = true;
foreach ( hajlajty_status_live_codes() as $code ) {
	if ( 'LIVE' !== hajlajty_lookup_status( $code )['state'] ) {
		$all_live = false;
	}
}
check( 'każdy kod live → stan LIVE w lookup', true, $all_live );

echo "\n=== POZYCJE ===\n";
check( 'pos G → Br', 'Br', hajlajty_lookup_position( 'G' ) );
check( 'pos D → O', 'O', hajlajty_lookup_position( 'D' ) );
check( 'pos M → P', 'P', hajlajty_lookup_position( 'M' ) );
check( 'pos F → N', 'N', hajlajty_lookup_position( 'F' ) );
check( 'pos nieznana → ""', '', hajlajty_lookup_position( 'X' ) );
check( 'pos null → ""', '', hajlajty_lookup_position( null ) );

echo "\n=== EVENTY (dane syntetyczne wg kontraktu type/detail) ===\n";
check( 'event Goal/Normal → goal', array( 'key' => 'goal', 'label' => 'Bramka' ), hajlajty_lookup_event( 'Goal', 'Normal Goal' ) );
check( 'event Goal/Own Goal → own_goal', array( 'key' => 'own_goal', 'label' => 'Bramka samobójcza' ), hajlajty_lookup_event( 'Goal', 'Own Goal' ) );
check( 'event Goal/Penalty → penalty_goal', array( 'key' => 'penalty_goal', 'label' => 'Bramka z karnego' ), hajlajty_lookup_event( 'Goal', 'Penalty' ) );
check( 'event Goal/Missed Penalty → missed_penalty', array( 'key' => 'missed_penalty', 'label' => 'Niewykorzystany karny' ), hajlajty_lookup_event( 'Goal', 'Missed Penalty' ) );
check( 'event Card/Yellow → yellow', array( 'key' => 'yellow', 'label' => 'Żółta kartka' ), hajlajty_lookup_event( 'Card', 'Yellow Card' ) );
check( 'event Card/Second Yellow → second_yellow', array( 'key' => 'second_yellow', 'label' => 'Druga żółta (czerwona)' ), hajlajty_lookup_event( 'Card', 'Second Yellow card' ) );
check( 'event Card/Red → red', array( 'key' => 'red', 'label' => 'Czerwona kartka' ), hajlajty_lookup_event( 'Card', 'Red Card' ) );
check( 'event subst → subst', array( 'key' => 'subst', 'label' => 'Zmiana' ), hajlajty_lookup_event( 'subst', 'Substitution 1' ) );
check( 'event Var/Goal cancelled → var', array( 'key' => 'var', 'label' => 'VAR — Goal cancelled' ), hajlajty_lookup_event( 'Var', 'Goal cancelled' ) );
check( 'event Var bez detail → VAR', array( 'key' => 'var', 'label' => 'VAR' ), hajlajty_lookup_event( 'Var', '' ) );
check( 'event nieznany type → other(label=type)', array( 'key' => 'other', 'label' => 'Mystery' ), hajlajty_lookup_event( 'Mystery', '' ) );
check( 'event null/null → other("")', array( 'key' => 'other', 'label' => '' ), hajlajty_lookup_event( null, null ) );

echo "\n=== STATYSTYKI ===\n";
check( 'stat Ball Possession', 'Posiadanie piłki', hajlajty_lookup_stat_label( 'Ball Possession' ) );
check( 'stat Total Shots', 'Strzały (łącznie)', hajlajty_lookup_stat_label( 'Total Shots' ) );
check( 'stat expected_goals', 'Oczekiwane gole (xG)', hajlajty_lookup_stat_label( 'expected_goals' ) );
check( 'stat Passes %', 'Celność podań', hajlajty_lookup_stat_label( 'Passes %' ) );
check( 'stat nieznany Foobar → ""', '', hajlajty_lookup_stat_label( 'Foobar' ) );

echo "\n=== RUNDA ===\n";
check( 'round Group Stage - 1', 'Faza grupowa — 1. kolejka', hajlajty_lookup_round( 'Group Stage - 1' ) );
check( 'round Group Stage - 3', 'Faza grupowa — 3. kolejka', hajlajty_lookup_round( 'Group Stage - 3' ) );
check( 'round Round of 16', '1/8 finału', hajlajty_lookup_round( 'Round of 16' ) );
check( 'round Final', 'finał', hajlajty_lookup_round( 'Final' ) );
check( 'round Mystery Cup → surowy', 'Mystery Cup', hajlajty_lookup_round( 'Mystery Cup' ) );
check( 'round null → ""', '', hajlajty_lookup_round( null ) );

printf( "\n=== WYNIK: %d/%d PASS ===\n", $pass, $pass + $fail );
exit( $fail > 0 ? 1 : 0 );
