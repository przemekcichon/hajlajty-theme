<?php
/**
 * Czysta logika tabeli grupowej — strefy awansu po `rank` i etykieta „rozegrane".
 * BEZ WordPressa, BEZ I/O: funkcje testowalne `php tests/mvp-e-standings.php`
 * (wzór: lookups.php / tests/3a-lookups.php).
 *
 * DLACZEGO strefy PO `rank`, a NIE po `zone`: runtime MVP-d potwierdził, że
 * `zone` (pole `description` z API) nie odróżnia miejsca 1/2 od 3 — w Mundialu
 * 2026 rank 1,2 → „Round of 32", rank 3,4 → null. Kolor strefy musi więc wynikać
 * z `rank`, nie ze stringa `zone` (który zmienia się per turniej — pamięć
 * „standings-zone-varies"). `zone` w renderze IGNORUJEMY.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Progi formatu 4-zespołowej grupy Mundialu 2026 (12 grup po 4: bezpośredni
 * awans top 2 + tabela najlepszych trzecich miejsc). Format-specyficzne —
 * tabela ligowa (Faza 5) dostanie własne reguły, NIE rozszerzamy tu na zapas (#8).
 */
const HAJLAJTY_STANDINGS_QUAL_MAX_RANK = 2; // miejsca 1–2: bezpośredni awans → `.qual`
const HAJLAJTY_STANDINGS_PLAYOFF_RANK  = 3; // miejsce 3: najlepsze trzecie → `.play`
const HAJLAJTY_STANDINGS_GROUP_MATCHES = 3; // każdy w grupie 4-zespołowej gra 3 mecze

/**
 * Klasa CSS strefy wiersza wg pozycji w tabeli grupy.
 *
 * @param mixed $rank Pozycja (`rank` z wiersza; nullable wg kontraktu MVP-d).
 * @return string 'qual' (1–2), 'play' (3) albo '' (4+ / brak rank → odpada).
 */
function hajlajty_standings_zone_class( $rank ): string {
	$rank = (int) $rank; // null/'' → 0 → '' (bezpieczny fallback).
	if ( $rank >= 1 && $rank <= HAJLAJTY_STANDINGS_QUAL_MAX_RANK ) {
		return 'qual';
	}
	if ( HAJLAJTY_STANDINGS_PLAYOFF_RANK === $rank ) {
		return 'play';
	}
	return '';
}

/**
 * Etykieta „rozegrane" dla nagłówka karty grupy, np. „3/3".
 *
 * Licznik = `max(played)` po wierszach (played bywa NIEjednolite — mecz w toku
 * lub przełożony, FAKT z runtime: grupa G miała 2 i 1). Mianownik stały dla
 * grupy 4-zespołowej (3 mecze na drużynę) — format-specyficzny.
 *
 * @param array $rows Wiersze jednej grupy.
 * @return string „<max>/<HAJLAJTY_STANDINGS_GROUP_MATCHES>".
 */
function hajlajty_standings_played_label( array $rows ): string {
	$max = 0;
	foreach ( $rows as $row ) {
		$played = (int) ( $row['played'] ?? 0 );
		if ( $played > $max ) {
			$max = $played;
		}
	}
	return $max . '/' . HAJLAJTY_STANDINGS_GROUP_MATCHES;
}
