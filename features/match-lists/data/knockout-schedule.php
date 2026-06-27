<?php
/**
 * Kuracyjny harmonogram fazy pucharowej Mundialu 2026 — ŹRÓDŁO PLACEHOLDERÓW
 * terminarza (warstwa WIDOKU, NIGDY posty `mecz`; decyzja #10 + plan.md „Faza
 * pucharowa", DECYZJE 1–2). Spisany z oficjalnego bracketu FIFA „Knockout stage
 * match schedule & bracket" (link w docs/plan.md). Dewelopersko, jak roster CSV —
 * NIE jest UI redaktora i NIE trafia do bazy.
 *
 * ZAKRES = Round of 16 … Final. Round of 32 NIE jest tu placeholderem: API ma już
 * R32 z realnymi drużynami (runtime 2026-06) → wciąga je zwykły `wp hajlajty import`
 * jako realne `mecz`. Placeholdery dotyczą rund, których fixtures jeszcze NIE MA
 * (pojawiają się dopiero, gdy znane są OBIE drużyny — patrz plan.md).
 *
 * KONTRAKT (spójny z importem — decyzja #2 + ground-truth §1/§2):
 *  - `round`   = LITERAŁ jak `match_data.round` z api-football (klucz dedup ORAZ
 *                wejście `hajlajty_lookup_round`). Dozwolone: „Round of 16",
 *                „Quarter-finals", „Semi-finals", „3rd Place Final", „Final".
 *  - `kickoff` = UTC w formacie płaskiej meta `kickoff` („Y-m-d H:i:s"). To DRUGA
 *                połowa klucza dedup (round + kickoff). Czas lokalny FIFA przeliczony
 *                na UTC.
 *  - `home` / `away` = placeholderowa etykieta PL (drabinka FIFA odwołuje się do
 *                numerów meczów 73–104; krzyżowania niżej). Bez flag (brak `fifa_code`).
 *
 * DEDUP / BACKFILL: gdy import wciągnie realny mecz danej rundy o tym samym
 * (`round`, `kickoff`), placeholder znika (realny WYGRYWA) — `hajlajty_knockout_merge`.
 *
 * ⚠ WERYFIKACJA RUNTIME (klucz dedup): godziny FIFA muszą zgadzać się 1:1 z
 * `fixture.date` api-football. Walidacja krzyżowa wykonana dla R32 meczu 73
 * (FIFA: 28.06 12:00 UTC−7 = 19:00 UTC; api-football: „2026-06-28T19:00:00+00:00",
 * RPA vs Kanada) — ZGADZA SIĘ. Potwierdź dla R16+ realnym importem (instrukcje w PR).
 * Jeśli godziny się rozjadą, klucz dedup wymaga decyzji (luźniejszy: round + dzień).
 *
 * Numery meczów (FIFA): R16 89–96, ćwierćfinały 97–100, półfinały 101–102,
 * mecz o 3. miejsce 103, finał 104.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	// --- Round of 16 (mecze 89–96) ---
	array( 'round' => 'Round of 16', 'kickoff' => '2026-07-04 17:00:00', 'home' => 'Zwycięzca meczu 73', 'away' => 'Zwycięzca meczu 75' ), // 90
	array( 'round' => 'Round of 16', 'kickoff' => '2026-07-04 21:00:00', 'home' => 'Zwycięzca meczu 74', 'away' => 'Zwycięzca meczu 77' ), // 89
	array( 'round' => 'Round of 16', 'kickoff' => '2026-07-05 20:00:00', 'home' => 'Zwycięzca meczu 76', 'away' => 'Zwycięzca meczu 78' ), // 91
	array( 'round' => 'Round of 16', 'kickoff' => '2026-07-06 00:00:00', 'home' => 'Zwycięzca meczu 79', 'away' => 'Zwycięzca meczu 80' ), // 92
	array( 'round' => 'Round of 16', 'kickoff' => '2026-07-06 19:00:00', 'home' => 'Zwycięzca meczu 83', 'away' => 'Zwycięzca meczu 84' ), // 93
	array( 'round' => 'Round of 16', 'kickoff' => '2026-07-07 00:00:00', 'home' => 'Zwycięzca meczu 81', 'away' => 'Zwycięzca meczu 82' ), // 94
	array( 'round' => 'Round of 16', 'kickoff' => '2026-07-07 16:00:00', 'home' => 'Zwycięzca meczu 86', 'away' => 'Zwycięzca meczu 88' ), // 95
	array( 'round' => 'Round of 16', 'kickoff' => '2026-07-07 20:00:00', 'home' => 'Zwycięzca meczu 85', 'away' => 'Zwycięzca meczu 87' ), // 96

	// --- Ćwierćfinały (mecze 97–100) ---
	array( 'round' => 'Quarter-finals', 'kickoff' => '2026-07-09 20:00:00', 'home' => 'Zwycięzca meczu 89', 'away' => 'Zwycięzca meczu 90' ),  // 97
	array( 'round' => 'Quarter-finals', 'kickoff' => '2026-07-10 19:00:00', 'home' => 'Zwycięzca meczu 93', 'away' => 'Zwycięzca meczu 94' ),  // 98
	array( 'round' => 'Quarter-finals', 'kickoff' => '2026-07-11 21:00:00', 'home' => 'Zwycięzca meczu 91', 'away' => 'Zwycięzca meczu 92' ),  // 99
	array( 'round' => 'Quarter-finals', 'kickoff' => '2026-07-12 01:00:00', 'home' => 'Zwycięzca meczu 95', 'away' => 'Zwycięzca meczu 96' ),  // 100

	// --- Półfinały (mecze 101–102) ---
	array( 'round' => 'Semi-finals', 'kickoff' => '2026-07-14 19:00:00', 'home' => 'Zwycięzca meczu 97', 'away' => 'Zwycięzca meczu 98' ),   // 101
	array( 'round' => 'Semi-finals', 'kickoff' => '2026-07-15 19:00:00', 'home' => 'Zwycięzca meczu 99', 'away' => 'Zwycięzca meczu 100' ),  // 102

	// --- Mecz o 3. miejsce (mecz 103) ---
	array( 'round' => '3rd Place Final', 'kickoff' => '2026-07-18 21:00:00', 'home' => 'Przegrany meczu 101', 'away' => 'Przegrany meczu 102' ), // 103

	// --- Finał (mecz 104) ---
	array( 'round' => 'Final', 'kickoff' => '2026-07-19 19:00:00', 'home' => 'Zwycięzca meczu 101', 'away' => 'Zwycięzca meczu 102' ), // 104
);
