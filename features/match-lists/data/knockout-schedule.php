<?php
/**
 * Kuracyjna tabela meczów fazy pucharowej Mundialu 2026 (mecze FIFA 73–104).
 * Spisana z oficjalnego bracketu FIFA „Knockout stage match schedule & bracket"
 * (link w docs/plan.md). Dewelopersko, jak roster CSV — NIE jest UI redaktora i
 * NIE trafia do bazy. Dwa zastosowania (decyzja #10 + plan.md „Faza pucharowa"):
 *
 *  1. PLACEHOLDERY terminarza (warstwa WIDOKU, nigdy post `mecz`) — TYLKO rundy,
 *     których fixtures jeszcze NIE MA w API (pojawiają się, gdy znane są OBIE
 *     drużyny). To wiersze z etykietami `home`/`away`: Round of 16 … Final.
 *  2. NUMERY MECZÓW — `no` dla KAŻDEGO meczu pucharowego (także Round of 32).
 *     api-football NIE podaje numeru meczu FIFA (tylko `fixture.id`), więc numer
 *     jest kuracyjny. Render pokazuje „Mecz N", żeby referencje placeholderów
 *     („Zwycięzca meczu 74") były rozwiązywalne na kartach feederów.
 *
 * Round of 32 (73–88) jest tu WYŁĄCZNIE dla numeru — BEZ `home`/`away`, więc
 * `hajlajty_knockout_placeholders()` go pomija (R32 ma realne drużyny z importu).
 *
 * KONTRAKT (spójny z importem — ground-truth §1/§2):
 *  - `no`      = numer meczu FIFA (73–104). Klucz: (round,kickoff) → `no`.
 *  - `round`   = LITERAŁ jak `match_data.round` z api-football (klucz dedup +
 *                wejście `hajlajty_lookup_round`): „Round of 32"/„Round of 16"/
 *                „Quarter-finals"/„Semi-finals"/„3rd Place Final"/„Final".
 *  - `kickoff` = UTC w formacie płaskiej meta `kickoff` („Y-m-d H:i:s"). Druga
 *                połowa klucza dedup ORAZ klucz lookupu numeru. Czas lokalny FIFA → UTC.
 *  - `home`/`away` = etykieta placeholderowa PL (tylko rundy bez fixtures w API).
 *
 * ⚠ WERYFIKACJA RUNTIME (klucz dedup + numer): godziny FIFA muszą zgadzać się 1:1
 * z `fixture.date` api-football. Walidacja krzyżowa dla R32 meczu 73 (FIFA 28.06
 * 12:00 UTC−7 = 19:00 UTC; api-football „2026-06-28T19:00:00+00:00", RPA vs Kanada)
 * — ZGADZA SIĘ. Pozostałe R32 (74–88) z bracketu FIFA, NIEzweryfikowane vs API:
 * przy rozjeździe godziny lookup numeru zwróci 0 → po prostu brak plakietki na tej
 * karcie (degradacja łagodna, nigdy błędny numer). Potwierdź R16+ realnym importem.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	// --- Round of 32 (73–88) — TYLKO numer (realne drużyny z importu, brak etykiet) ---
	array( 'no' => 73, 'round' => 'Round of 32', 'kickoff' => '2026-06-28 19:00:00' ),
	array( 'no' => 74, 'round' => 'Round of 32', 'kickoff' => '2026-06-29 20:30:00' ),
	array( 'no' => 75, 'round' => 'Round of 32', 'kickoff' => '2026-06-30 01:00:00' ),
	array( 'no' => 76, 'round' => 'Round of 32', 'kickoff' => '2026-06-29 17:00:00' ),
	array( 'no' => 77, 'round' => 'Round of 32', 'kickoff' => '2026-06-30 21:00:00' ),
	array( 'no' => 78, 'round' => 'Round of 32', 'kickoff' => '2026-06-30 17:00:00' ),
	array( 'no' => 79, 'round' => 'Round of 32', 'kickoff' => '2026-07-01 01:00:00' ),
	array( 'no' => 80, 'round' => 'Round of 32', 'kickoff' => '2026-07-01 16:00:00' ),
	array( 'no' => 81, 'round' => 'Round of 32', 'kickoff' => '2026-07-02 00:00:00' ),
	array( 'no' => 82, 'round' => 'Round of 32', 'kickoff' => '2026-07-01 20:00:00' ),
	array( 'no' => 83, 'round' => 'Round of 32', 'kickoff' => '2026-07-02 23:00:00' ),
	array( 'no' => 84, 'round' => 'Round of 32', 'kickoff' => '2026-07-02 19:00:00' ),
	array( 'no' => 85, 'round' => 'Round of 32', 'kickoff' => '2026-07-03 03:00:00' ),
	array( 'no' => 86, 'round' => 'Round of 32', 'kickoff' => '2026-07-03 22:00:00' ),
	array( 'no' => 87, 'round' => 'Round of 32', 'kickoff' => '2026-07-04 01:30:00' ),
	array( 'no' => 88, 'round' => 'Round of 32', 'kickoff' => '2026-07-03 18:00:00' ),

	// --- Round of 16 (89–96) ---
	array( 'no' => 90, 'round' => 'Round of 16', 'kickoff' => '2026-07-04 17:00:00', 'home' => 'Zwycięzca meczu 73', 'away' => 'Zwycięzca meczu 75' ),
	array( 'no' => 89, 'round' => 'Round of 16', 'kickoff' => '2026-07-04 21:00:00', 'home' => 'Zwycięzca meczu 74', 'away' => 'Zwycięzca meczu 77' ),
	array( 'no' => 91, 'round' => 'Round of 16', 'kickoff' => '2026-07-05 20:00:00', 'home' => 'Zwycięzca meczu 76', 'away' => 'Zwycięzca meczu 78' ),
	array( 'no' => 92, 'round' => 'Round of 16', 'kickoff' => '2026-07-06 00:00:00', 'home' => 'Zwycięzca meczu 79', 'away' => 'Zwycięzca meczu 80' ),
	array( 'no' => 93, 'round' => 'Round of 16', 'kickoff' => '2026-07-06 19:00:00', 'home' => 'Zwycięzca meczu 83', 'away' => 'Zwycięzca meczu 84' ),
	array( 'no' => 94, 'round' => 'Round of 16', 'kickoff' => '2026-07-07 00:00:00', 'home' => 'Zwycięzca meczu 81', 'away' => 'Zwycięzca meczu 82' ),
	array( 'no' => 95, 'round' => 'Round of 16', 'kickoff' => '2026-07-07 16:00:00', 'home' => 'Zwycięzca meczu 86', 'away' => 'Zwycięzca meczu 88' ),
	array( 'no' => 96, 'round' => 'Round of 16', 'kickoff' => '2026-07-07 20:00:00', 'home' => 'Zwycięzca meczu 85', 'away' => 'Zwycięzca meczu 87' ),

	// --- Ćwierćfinały (97–100) ---
	array( 'no' => 97,  'round' => 'Quarter-finals', 'kickoff' => '2026-07-09 20:00:00', 'home' => 'Zwycięzca meczu 89', 'away' => 'Zwycięzca meczu 90' ),
	array( 'no' => 98,  'round' => 'Quarter-finals', 'kickoff' => '2026-07-10 19:00:00', 'home' => 'Zwycięzca meczu 93', 'away' => 'Zwycięzca meczu 94' ),
	array( 'no' => 99,  'round' => 'Quarter-finals', 'kickoff' => '2026-07-11 21:00:00', 'home' => 'Zwycięzca meczu 91', 'away' => 'Zwycięzca meczu 92' ),
	array( 'no' => 100, 'round' => 'Quarter-finals', 'kickoff' => '2026-07-12 01:00:00', 'home' => 'Zwycięzca meczu 95', 'away' => 'Zwycięzca meczu 96' ),

	// --- Półfinały (101–102) ---
	array( 'no' => 101, 'round' => 'Semi-finals', 'kickoff' => '2026-07-14 19:00:00', 'home' => 'Zwycięzca meczu 97', 'away' => 'Zwycięzca meczu 98' ),
	array( 'no' => 102, 'round' => 'Semi-finals', 'kickoff' => '2026-07-15 19:00:00', 'home' => 'Zwycięzca meczu 99', 'away' => 'Zwycięzca meczu 100' ),

	// --- Mecz o 3. miejsce (103) ---
	array( 'no' => 103, 'round' => '3rd Place Final', 'kickoff' => '2026-07-18 21:00:00', 'home' => 'Przegrany meczu 101', 'away' => 'Przegrany meczu 102' ),

	// --- Finał (104) ---
	array( 'no' => 104, 'round' => 'Final', 'kickoff' => '2026-07-19 19:00:00', 'home' => 'Zwycięzca meczu 101', 'away' => 'Zwycięzca meczu 102' ),
);
