<?php
/**
 * Test czystych funkcji decyzyjnych overlayu zdarzeń LIVE (MVP-b) — czysty PHP,
 * BEZ WordPressa.
 *
 * Uruchom w Open Site Shell Locala (z katalogu motywu):
 *   php tests/live-overlay.php
 *
 * Pokrywa: klasę efektu (key→goal/card/sub/''), sygnaturę zdarzenia (id vs nazwa)
 * i decyzję autoodtworzenia gola „z ostatnich 4 min" (okno minut gry). Render
 * overlayu (JS/DOM) sprawdzamy ręcznie na żywym meczu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require __DIR__ . '/../features/match-display/lookups.php'; // hajlajty_lookup_event (build_timeline)
require __DIR__ . '/../features/match-display/derive.php';

$pass = 0;
$fail = 0;

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

echo "=== KLASA EFEKTU (key → goal|card|sub|'') ===\n";
check( 'goal → goal', 'goal', hajlajty_event_overlay_kind( 'goal' ) );
check( 'penalty_goal → goal', 'goal', hajlajty_event_overlay_kind( 'penalty_goal' ) );
check( 'own_goal → goal', 'goal', hajlajty_event_overlay_kind( 'own_goal' ) );
check( 'yellow → card', 'card', hajlajty_event_overlay_kind( 'yellow' ) );
check( 'red → card', 'card', hajlajty_event_overlay_kind( 'red' ) );
check( 'second_yellow → card', 'card', hajlajty_event_overlay_kind( 'second_yellow' ) );
check( 'subst → sub', 'sub', hajlajty_event_overlay_kind( 'subst' ) );
check( 'missed_penalty → "" (bez efektu)', '', hajlajty_event_overlay_kind( 'missed_penalty' ) );
check( 'var → ""', '', hajlajty_event_overlay_kind( 'var' ) );
check( 'other → ""', '', hajlajty_event_overlay_kind( 'other' ) );

echo "\n=== SYGNATURA ZDARZENIA (id vs nazwa) ===\n";
check(
	'z player_id → używa id',
	'goal|home|64||10',
	hajlajty_event_signature( array( 'key' => 'goal', 'side' => 'home', 'minute' => 64, 'extra' => null, 'player_id' => 10, 'player' => 'X' ) )
);
check(
	'bez player_id → fallback nazwa',
	'yellow|away|33||De Paul',
	hajlajty_event_signature( array( 'key' => 'yellow', 'side' => 'away', 'minute' => 33, 'extra' => null, 'player_id' => null, 'player' => 'De Paul' ) )
);
check(
	'extra (doliczony) w sygnaturze',
	'goal|home|45|2|7',
	hajlajty_event_signature( array( 'key' => 'goal', 'side' => 'home', 'minute' => 45, 'extra' => 2, 'player_id' => 7, 'player' => 'Y' ) )
);

echo "\n=== AUTOODTWORZENIE GOLA (okno 4 min gry) ===\n";
// Oś z dwóch goli: 60' (home) i 64' (away).
$events = array(
	array( 'minute' => 60, 'side' => 'home', 'type' => 'Goal', 'detail' => 'Normal Goal', 'player' => 'A', 'player_id' => 10 ),
	array( 'minute' => 64, 'side' => 'away', 'type' => 'Goal', 'detail' => 'Normal Goal', 'player' => 'B', 'player_id' => 20 ),
);
$timeline = hajlajty_build_timeline( $events );

check( 'elapsed 66, gol 64 → w oknie (najnowszy)', 'goal|away|64||20', hajlajty_recent_goal_signature( $timeline, 66, 4 ) );
check( 'elapsed 64, gol 64 → diff 0 → w oknie', 'goal|away|64||20', hajlajty_recent_goal_signature( $timeline, 64, 4 ) );
check( 'elapsed 68, gol 64 → diff 4 → w oknie (granica)', 'goal|away|64||20', hajlajty_recent_goal_signature( $timeline, 68, 4 ) );
check( 'elapsed 69, najnowszy 64 poza oknem, 60 też → ""', '', hajlajty_recent_goal_signature( $timeline, 69, 4 ) );
check( 'elapsed 62, gol 60 w oknie, 64 jeszcze nie padł (diff -2) → gol 60', 'goal|home|60||10', hajlajty_recent_goal_signature( $timeline, 62, 4 ) );
check( 'elapsed null → "" (bez historii)', '', hajlajty_recent_goal_signature( $timeline, null, 4 ) );
check( 'pusta oś → ""', '', hajlajty_recent_goal_signature( array(), 66, 4 ) );

// Kartka w oknie NIE jest autoodtwarzana (tylko gole).
$only_card = hajlajty_build_timeline( array(
	array( 'minute' => 65, 'side' => 'home', 'type' => 'Card', 'detail' => 'Yellow Card', 'player' => 'C', 'player_id' => 30 ),
) );
check( 'tylko kartka w oknie → "" (kartki nie autoodtwarzamy)', '', hajlajty_recent_goal_signature( $only_card, 66, 4 ) );

// Niewykorzystany karny (nie liczy) → nie autoodtwarzany.
$missed = hajlajty_build_timeline( array(
	array( 'minute' => 64, 'side' => 'home', 'type' => 'Goal', 'detail' => 'Missed Penalty', 'player' => 'D', 'player_id' => 40 ),
) );
check( 'missed_penalty w oknie → "" (nie liczy)', '', hajlajty_recent_goal_signature( $missed, 66, 4 ) );

printf( "\n=== WYNIK: %d/%d PASS ===\n", $pass, $pass + $fail );
exit( $fail > 0 ? 1 : 0 );
