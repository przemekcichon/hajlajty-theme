<?php
/**
 * Placeholdery fazy pucharowej w terminarzu — CZYSTA logika scalania (zero
 * WordPressa, zero I/O poza wczytaniem kuracyjnego pliku danych). Realizuje
 * decyzję #10 + plan.md „Faza pucharowa": placeholder to WARSTWA WIDOKU, nigdy
 * post `mecz`. Backfill: gdy import wciągnie realny mecz danej rundy, placeholder
 * o tym samym (`round`, `kickoff`) znika — REALNY WYGRYWA.
 *
 * Funkcje `hajlajty_knockout_key` / `hajlajty_knockout_merge` są CZYSTE i
 * testowalne bez WP (tests/knockout-merge.php). `hajlajty_knockout_schedule`
 * tylko `require`-uje plik danych (czysta tablica) — też bezpieczne w teście.
 *
 * Granica: ten plik żyje w slice match-lists (terminarz tam żyje); ładowany przez
 * match-lists.php (vertical slice). Bez nowego slice'a na zapas (#8) — gdy później
 * dojdzie osobny widok drabinki, wtedy się wyodrębni.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wczytuje kuracyjny harmonogram pucharowy (R16…Final) z pliku danych slice'a.
 * Wynik cache'owany statycznie (jeden `require` na request).
 *
 * @return array<int,array{round:string,kickoff:string,home:string,away:string}>
 */
function hajlajty_knockout_schedule(): array {
	static $cache = null;
	if ( null === $cache ) {
		$loaded = require __DIR__ . '/data/knockout-schedule.php';
		$cache  = is_array( $loaded ) ? $loaded : array();
	}
	return $cache;
}

/**
 * Placeholdery do renderu w terminarzu: wiersze harmonogramu z etykietami drużyn
 * (`home`/`away`). Wiersze „tylko numer" (Round of 32) są pomijane — R32 ma realne
 * fixtures z importu, więc nie pokazujemy dla niego zaślepki.
 *
 * @return array<int,array{no:int,round:string,kickoff:string,home:string,away:string}>
 */
function hajlajty_knockout_placeholders(): array {
	$out = array();
	foreach ( hajlajty_knockout_schedule() as $row ) {
		if ( '' !== (string) ( $row['home'] ?? '' ) && '' !== (string) ( $row['away'] ?? '' ) ) {
			$out[] = $row;
		}
	}
	return $out;
}

/**
 * Numer meczu FIFA (73–104) po kluczu (`round`, `kickoff`) — z kuracyjnej tabeli.
 * Dla meczów spoza fazy pucharowej (faza grupowa) ORAZ przy rozjeździe godziny
 * FIFA↔API zwraca 0 (render: brak plakietki — degradacja łagodna, nigdy zły numer).
 * Mapa budowana raz (static) z `hajlajty_knockout_schedule()`.
 *
 * @param string|null $round
 * @param string|null $kickoff
 * @return int Numer meczu albo 0, gdy nieznany.
 */
function hajlajty_knockout_match_no( ?string $round, ?string $kickoff ): int {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( hajlajty_knockout_schedule() as $row ) {
			$no = (int) ( $row['no'] ?? 0 );
			if ( $no > 0 ) {
				$map[ hajlajty_knockout_key( $row['round'] ?? null, $row['kickoff'] ?? null ) ] = $no;
			}
		}
	}
	return $map[ hajlajty_knockout_key( $round, $kickoff ) ] ?? 0;
}

/**
 * Klucz dedup placeholder↔realny mecz: (`round`, `kickoff`). JEDNO miejsce
 * normalizacji — gdyby RUNTIME pokazał rozjazd godzin FIFA↔api-football, luźniejszy
 * klucz zmienia się tu (np. round + sama data dnia), bez ruszania scalania.
 *
 * @param string|null $round   Literał rundy (match_data.round / harmonogram FIFA).
 * @param string|null $kickoff Płaska meta `kickoff` (UTC „Y-m-d H:i:s").
 * @return string Klucz porównawczy.
 */
function hajlajty_knockout_key( ?string $round, ?string $kickoff ): string {
	return trim( (string) $round ) . '|' . trim( (string) $kickoff );
}

/**
 * Scala realne mecze terminarza z placeholderami FIFA w JEDNĄ listę chronologiczną.
 * REALNY mecz WYGRYWA: placeholder o tym samym kluczu (`round`,`kickoff`) co istniejący
 * realny mecz jest POMIJANY (zastąpiony danymi z importu). Czysta funkcja: bez WP,
 * bez zapisu — wejście/wyjście to tablice.
 *
 * Wyjście jest posortowane rosnąco po `kickoff` (string UTC „Y-m-d H:i:s" — zero-padded,
 * więc porównanie LEKSYKALNE jest chronologiczne; ta sama konwencja co terminarz/match-lists).
 * Każdy element wyjścia ma ustawiony `type` ('post' albo 'placeholder') — render rozróżnia
 * po nim kartę. Pola wejściowe są zachowane (passthrough, np. `post_id`).
 *
 * @param array<int,array> $real         Realne mecze: każdy z 'kickoff' (string) i 'round'
 *                                        (?string) + dowolne pola (np. 'post_id').
 * @param array<int,array> $placeholders Placeholdery: 'round','kickoff','home','away'.
 * @return array<int,array> Lista scalona, posortowana po `kickoff`, z polem `type`.
 */
function hajlajty_knockout_merge( array $real, array $placeholders ): array {
	// Zbiór kluczy realnych meczów — po nim odsiewamy placeholdery (realny wygrywa).
	$real_keys = array();
	foreach ( $real as $item ) {
		$key               = hajlajty_knockout_key( $item['round'] ?? null, $item['kickoff'] ?? null );
		$real_keys[ $key ] = true;
	}

	$merged = array();
	foreach ( $real as $item ) {
		$item['type'] = 'post';
		$merged[]     = $item;
	}
	foreach ( $placeholders as $item ) {
		$key = hajlajty_knockout_key( $item['round'] ?? null, $item['kickoff'] ?? null );
		if ( isset( $real_keys[ $key ] ) ) {
			continue; // Realny mecz tej rundy/godziny już jest — placeholder pomijamy.
		}
		$item['type'] = 'placeholder';
		$merged[]     = $item;
	}

	usort( $merged, 'hajlajty_knockout_compare' );
	return $merged;
}

/**
 * Komparator scalonej listy: po `kickoff` rosnąco; remis → realny przed placeholderem,
 * dalej po rundzie i etykiecie (determinizm niezależny od stabilności usort).
 *
 * @param array $a
 * @param array $b
 * @return int
 */
function hajlajty_knockout_compare( $a, $b ): int {
	$ka = (string) ( $a['kickoff'] ?? '' );
	$kb = (string) ( $b['kickoff'] ?? '' );
	if ( $ka !== $kb ) {
		return strcmp( $ka, $kb );
	}
	// Realny mecz przed placeholderem przy identycznym kickoffie.
	$ta = ( 'placeholder' === ( $a['type'] ?? '' ) ) ? 1 : 0;
	$tb = ( 'placeholder' === ( $b['type'] ?? '' ) ) ? 1 : 0;
	if ( $ta !== $tb ) {
		return $ta - $tb;
	}
	$ra = (string) ( $a['round'] ?? '' );
	$rb = (string) ( $b['round'] ?? '' );
	if ( $ra !== $rb ) {
		return strcmp( $ra, $rb );
	}
	return strcmp( (string) ( $a['home'] ?? '' ), (string) ( $b['home'] ?? '' ) );
}
