<?php
/**
 * Drabinka pucharowa (bracket R32 → … → Finał) — CZYSTA logika view-modelu, zero
 * WordPressa, zero I/O (poza tym, że dane bierze z `hajlajty_knockout_schedule()`,
 * którą wstrzykuje wołający). Realizuje plan „Faza pucharowa" DECYZJA 3: krzyżowania
 * (graf zasilania meczów) NIE istnieją w danych strukturalnie — żyją WYŁĄCZNIE jako
 * stringi `home`/`away` typu „Zwycięzca meczu {N}" / „Przegrany meczu {N}" w
 * kuracyjnym harmonogramie. Ten plik wyłuskuje z nich numer feedera ({N}) i układa
 * komórki w kolumny rund w porządku DRZEWA (feederzy sąsiadują pionowo).
 *
 * Granica (CLAUDE.md): żyje w slice match-lists (tam żyje harmonogram pucharowy i
 * jego helpery — knockout.php). Bez nowego slice'a na zapas (#8). Funkcje są CZYSTE
 * i testowalne bez WP (tests/bracket.php) — render (partials/faza-pucharowa.php)
 * dokłada realne mecze, drużyny i WordPressa.
 *
 * Kontrakt wejścia = wiersz `hajlajty_knockout_schedule()` (ground-truth §1):
 *   { no:int, round:string, kickoff:string, home?:string, away?:string }
 * R32 (no 73–88) NIE ma `home`/`away` (realne drużyny z importu) → feeder = 0,
 * etykieta = null. R16…Finał mają etykiety placeholderowe i z nich czytamy krawędzie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Numer meczu-feedera ({N}) z etykiety placeholderowej „Zwycięzca meczu {N}" lub
 * „Przegrany meczu {N}". To JEDYNE źródło krawędzi drabinki R16→Finał (krzyżowania
 * istnieją tylko jako stringi — ground-truth §1). Prefiks (Zwycięzca/Przegrany)
 * jest do WYŚWIETLENIA; strukturalnie oba wskazują ten sam feeder {N}.
 *
 * @param string|null $label Etykieta home/away z harmonogramu (np. „Zwycięzca meczu 74").
 * @return int Numer feedera (73–104) albo 0, gdy brak liczby / null (R32 lub nieznane).
 */
function hajlajty_bracket_feeder_no( ?string $label ): int {
	if ( null === $label || '' === $label ) {
		return 0;
	}
	return preg_match( '/(\d+)/', $label, $m ) ? (int) $m[1] : 0;
}

/**
 * Kolejność KOLUMN drabinki (lewo→prawo). Główne drzewo kulminuje w Finale; mecz
 * o 3. miejsce to gałąź boczna (feederzy = PRZEGRANI półfinałów), więc ląduje jako
 * ostatnia kolumna-aneks, nie w głównym ciągu R32→Finał.
 *
 * @return string[] Literały rund w kolejności renderu (klucze `hajlajty_lookup_round`).
 */
function hajlajty_bracket_round_order(): array {
	return array(
		'Round of 32',
		'Round of 16',
		'Quarter-finals',
		'Semi-finals',
		'Final',
		'3rd Place Final',
	);
}

/**
 * Tryb komórki drabinki — która warstwa wygrywa o jej obsadę. „Realny WYGRYWA"
 * (spójnie z `hajlajty_knockout_merge`): gdy istnieje realny mecz tego numeru,
 * pokazujemy go niezależnie od etykiet placeholderowych.
 *
 *  - 'real'        — jest realny post `mecz` dopasowany do numeru;
 *  - 'placeholder' — brak realnego, ale są etykiety feederów (R16…Finał) →
 *                    „Zwycięzca meczu N" (nieklikalne, bez flag);
 *  - 'tbd'         — brak realnego i brak etykiet (R32, którego pary API jeszcze
 *                    nie ma — ground-truth: 9/16; NIE wymyślamy par) → „Do ustalenia".
 *
 * @param bool $has_real  Czy jest realny mecz dla tego numeru.
 * @param bool $has_label Czy komórka ma etykiety feederów (home+away z harmonogramu).
 * @return string 'real' | 'placeholder' | 'tbd'.
 */
function hajlajty_bracket_cell_mode( bool $has_real, bool $has_label ): string {
	if ( $has_real ) {
		return 'real';
	}
	return $has_label ? 'placeholder' : 'tbd';
}

/**
 * Buduje view-model drabinki z harmonogramu: kolumny rund (lewo→prawo) z komórkami
 * ułożonymi w porządku DRZEWA (feederzy sąsiadują). CZYSTA funkcja — bez WP, bez
 * realnych meczów (te dokłada render po `no`).
 *
 * Porządek pionowy: BFS od korzenia (Finał) w dół po feederach. Dzięki temu w każdej
 * kolumnie dwaj feederzy danego meczu leżą obok siebie (czytelne drzewo), niezależnie
 * od kolejności wierszy w harmonogramie (tam są wg kickoffa). Komórki spoza drzewa
 * (mecz o 3. miejsce — nie jest feederem Finału) dostają porządek na końcu, wg `no`.
 *
 * @param array<int,array{no:int,round:string,kickoff:string,home?:string,away?:string}> $schedule
 *   Wynik `hajlajty_knockout_schedule()` (albo równoważny — do testów).
 * @return array<int,array{round:string,cells:array<int,array{
 *     no:int,round:string,kickoff:string,
 *     home_label:?string,away_label:?string,
 *     home_feeder:int,away_feeder:int
 *   }>}> Kolumny w kolejności `hajlajty_bracket_round_order()` (pomijane puste rundy).
 */
function hajlajty_bracket_build( array $schedule ): array {
	// Indeks po numerze + znormalizowana komórka (etykiety i feederzy wyłuskane raz).
	$by_no = array();
	foreach ( $schedule as $row ) {
		$no = (int) ( $row['no'] ?? 0 );
		if ( $no <= 0 ) {
			continue;
		}
		$home_label = isset( $row['home'] ) ? (string) $row['home'] : null;
		$away_label = isset( $row['away'] ) ? (string) $row['away'] : null;
		$by_no[ $no ] = array(
			'no'          => $no,
			'round'       => (string) ( $row['round'] ?? '' ),
			'kickoff'     => (string) ( $row['kickoff'] ?? '' ),
			'home_label'  => $home_label,
			'away_label'  => $away_label,
			'home_feeder' => hajlajty_bracket_feeder_no( $home_label ),
			'away_feeder' => hajlajty_bracket_feeder_no( $away_label ),
		);
	}

	// Porządek DRZEWA: BFS od Finału (korzeń) po feederach (home, potem away).
	$order = array(); // no => indeks pozycji
	$pos   = 0;
	$final_no = 0;
	foreach ( $by_no as $no => $cell ) {
		if ( 'Final' === $cell['round'] ) {
			$final_no = $no;
			break;
		}
	}
	$current = $final_no > 0 ? array( $final_no ) : array();
	while ( ! empty( $current ) ) {
		$next = array();
		foreach ( $current as $no ) {
			if ( isset( $order[ $no ] ) ) {
				continue; // strażnik przed cyklem (nie powinno wystąpić).
			}
			$order[ $no ] = $pos++;
			foreach ( array( 'home_feeder', 'away_feeder' ) as $fk ) {
				$fno = $by_no[ $no ][ $fk ] ?? 0;
				if ( $fno > 0 && isset( $by_no[ $fno ] ) ) {
					$next[] = $fno;
				}
			}
		}
		$current = $next;
	}
	// Komórki spoza drzewa (np. mecz o 3. miejsce) — porządek na końcu, deterministycznie.
	foreach ( $by_no as $no => $cell ) {
		if ( ! isset( $order[ $no ] ) ) {
			$order[ $no ] = $pos++;
		}
	}

	// Grupowanie po rundzie w kolejności kolumn; w kolumnie sort po porządku drzewa.
	$columns = array();
	foreach ( hajlajty_bracket_round_order() as $round ) {
		$cells = array();
		foreach ( $by_no as $no => $cell ) {
			if ( $cell['round'] === $round ) {
				$cells[] = $cell;
			}
		}
		if ( empty( $cells ) ) {
			continue;
		}
		usort(
			$cells,
			static function ( $a, $b ) use ( $order ) {
				return ( $order[ $a['no'] ] ?? PHP_INT_MAX ) <=> ( $order[ $b['no'] ] ?? PHP_INT_MAX );
			}
		);
		$columns[] = array(
			'round' => $round,
			'cells' => $cells,
		);
	}

	return $columns;
}
