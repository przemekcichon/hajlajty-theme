<?php
/**
 * Pochodne renderu meczu — CZYSTE funkcje (zero WordPressa, zero HTML), jak
 * lookups.php. Wejście: surowe `match_data` / pojedyncze wartości; wyjście:
 * struktury gotowe do renderu. Plik wprowadzony w E3 (ekstrakcja YouTube ID);
 * rozszerzany w E4 (oś czasu z narastającym wynikiem) i E5 (indeks zdarzeń
 * zawodnika). Trzymane razem, bo wszystkie znikają razem z widokiem meczu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wyłuskuje 11-znakowy identyfikator wideo YouTube z `skrot_url`.
 *
 * Obsługiwane formy: czysty 11-zn. ID, watch?v=, youtu.be/, /embed/, /shorts/.
 * ID YouTube to dokładnie 11 znaków z alfabetu [A-Za-z0-9_-].
 *
 * @param string|null $url Wartość pola ACF `skrot_url` (pełny link lub ID).
 * @return string 11-znakowy ID albo "" gdy nie rozpoznano (render decyduje:
 *   pokaż facade z data-yt albo stan „brak skrótu"). Nigdy null — render robi
 *   prosty test pustości.
 */
function hajlajty_youtube_id( ?string $url ): string {
	if ( null === $url || '' === $url ) {
		return '';
	}

	$url = trim( $url );

	// Już sam ID (np. wklejony bez URL-a).
	if ( preg_match( '~^[A-Za-z0-9_-]{11}$~', $url ) ) {
		return $url;
	}

	// Kolejność bez znaczenia — każdy wzorzec wyłapuje jeden wariant linku.
	$patterns = array(
		'~[?&]v=([A-Za-z0-9_-]{11})~',   // watch?v=ID
		'~youtu\.be/([A-Za-z0-9_-]{11})~', // youtu.be/ID
		'~/embed/([A-Za-z0-9_-]{11})~',  // /embed/ID
		'~/shorts/([A-Za-z0-9_-]{11})~', // /shorts/ID
	);
	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $url, $m ) ) {
			return $m[1];
		}
	}

	return '';
}

/**
 * Buduje oś czasu meczu z NARASTAJĄCYM wynikiem (pochodna). Wejście: `events[]`
 * z match_data w kolejności CHRONOLOGICZNEJ (jak zaimportowane). Wyjście: ta
 * sama lista, chronologicznie rosnąco, wzbogacona o `score` (bieżący wynik) przy
 * golach, semantyczny `key`/`label` (z lookups) i echo pól pomocniczych do
 * renderu. Render odwraca kolejność (najnowsze u góry) i dokleja ikony/teksty.
 *
 * Reguły zliczania (potwierdzone w §3b):
 *  - zwykły gol / gol z karnego → liczy dla strony eventu (`side`);
 *  - `own_goal` → liczy dla PRZECIWNIKA strony;
 *  - `missed_penalty` → NIE liczy (event widoczny, bez bumpa wyniku);
 *  - `Var` (np. gol anulowany) → patrz TODO niżej.
 * `goals.*` w nagłówku (ibar) pozostaje autorytatywny — oś tylko ilustruje przebieg.
 *
 * TODO (VAR — spójnie z api-mapping „VAR DO USTALENIA"): jeśli API zwróci event
 * `Goal` ORAZ osobny `Var` „Goal Disallowed/cancelled", narastający wynik z
 * samych `Goal` może podwójnie policzyć anulowaną bramkę. Na zmapowanych danych
 * (4 endpointy) to znany brak — render pomija eventy `Var`. Pełna obsługa
 * (parowanie Goal↔Var) odłożona; brak źródła pewnego oznaczenia w match_data.
 *
 * @param array $events Lista eventów (każdy: minute, extra, side, type, detail,
 *   player, assist, player_id, [assist_id]).
 * @return array Lista wzbogacona; pusta tablica dla braku/niepoprawnych eventów.
 */
function hajlajty_build_timeline( $events ): array {
	if ( empty( $events ) || ! is_array( $events ) ) {
		return array();
	}

	$home  = 0;
	$away  = 0;
	$out   = array();

	foreach ( $events as $ev ) {
		$type   = (string) ( $ev['type'] ?? '' );
		$detail = (string) ( $ev['detail'] ?? '' );
		$side   = ( 'home' === ( $ev['side'] ?? '' ) ) ? 'home' : 'away';
		$meta   = hajlajty_lookup_event( $type, $detail );
		$key    = $meta['key'];

		$score    = null;
		$counts   = false;

		if ( 'Goal' === $type ) {
			if ( 'missed_penalty' === $key ) {
				$counts = false; // Niewykorzystany karny — bez bumpa.
			} elseif ( 'own_goal' === $key ) {
				// Samobój liczy dla PRZECIWNIKA strony zdarzenia.
				if ( 'home' === $side ) {
					++$away;
				} else {
					++$home;
				}
				$counts = true;
			} else {
				// Zwykła bramka / bramka z karnego — liczy dla strony eventu.
				if ( 'home' === $side ) {
					++$home;
				} else {
					++$away;
				}
				$counts = true;
			}

			if ( $counts ) {
				$score = array(
					'home' => $home,
					'away' => $away,
				);
			}
		}

		$out[] = array(
			'minute'    => $ev['minute'] ?? null,
			'extra'     => $ev['extra'] ?? null,
			'side'      => $side,
			'type'      => $type,
			'detail'    => $detail,
			'key'       => $key,
			'label'     => $meta['label'],
			'player'    => $ev['player'] ?? null,
			'player_id' => $ev['player_id'] ?? null, // stabilny identyfikator (sygnatura efektu MVP-b)
			'assist'    => $ev['assist'] ?? null,
			'score'     => $score,   // narastający wynik tylko przy liczących golach
			'counts'    => $counts,  // czy to liczący gol (render: węzeł „is-goal")
		);
	}

	return $out;
}

/**
 * Wiersze statystyk do renderu: wybiera USTALONY podzbiór `statistics` (ten sam
 * zestaw kluczy i kolejność co render single-ft/live), tłumaczy etykietę przez
 * `hajlajty_lookup_stat_label` i zachowuje SUROWE wartości stron. Pomija klucz,
 * którego nie ma po ŻADNEJ stronie. Jedno źródło LISTY kluczy statystyk —
 * używane i przez render (partial), i przez warunek widoczności aside w
 * single-live (czy w ogóle są statystyki). Format wartości / słupki = render.
 *
 * @param array $data Zdekodowane match_data.
 * @return array<int,array{label:string,vh:mixed,va:mixed}> Pusta tablica, gdy
 *   żadnego z wybranych kluczy nie ma w danych (zapowiedź / brak statystyk).
 */
function hajlajty_build_stat_rows( array $data ): array {
	$stats_home = ( isset( $data['statistics']['home'] ) && is_array( $data['statistics']['home'] ) ) ? $data['statistics']['home'] : array();
	$stats_away = ( isset( $data['statistics']['away'] ) && is_array( $data['statistics']['away'] ) ) ? $data['statistics']['away'] : array();

	// TEN SAM podzbiór i kolejność co render (single-ft/live).
	$stat_keys = array( 'Ball Possession', 'Total Shots', 'Shots on Goal', 'Fouls', 'Corner Kicks', 'Offsides', 'Total passes' );

	$rows = array();
	foreach ( $stat_keys as $key ) {
		$sh = array_key_exists( $key, $stats_home );
		$sa = array_key_exists( $key, $stats_away );
		if ( ! $sh && ! $sa ) {
			continue;
		}
		$rows[] = array(
			'label' => hajlajty_lookup_stat_label( $key ),
			'vh'    => $sh ? $stats_home[ $key ] : null,
			'va'    => $sa ? $stats_away[ $key ] : null,
		);
	}

	return $rows;
}

/**
 * Indeks zdarzeń zawodnika: `events[]` → mapa `player_id` → agregat zdarzeń.
 * Łącznik events↔lineups po `player_id` (i `assist_id` dla schodzących w subst).
 * Zasila wskaźniki przy koszulce (boisko) i „↑ minuta" na ławce (wchodzący).
 *
 * Pola agregatu (każdy zawodnik z ≥1 zdarzeniem):
 *  - gole (int)        — zwykłe + z karnego (samobójcze liczone OSOBNO, bo
 *                        wskaźnik ⚽ przy zawodniku oznaczałby zdobytą bramkę),
 *  - samoboje (int),
 *  - zolta (int)       — żółte kartki,
 *  - druga_zolta (int) — druga żółta (skutkuje czerwoną),
 *  - czerwona (int)    — czerwone (proste + z drugiej żółtej),
 *  - zszedl (?int)     — minuta zejścia (z subst, assist_id),
 *  - wszedl (?int)     — minuta wejścia (z subst, player_id).
 *
 * @param array $events Lista eventów z match_data.
 * @return array<int,array> Mapa player_id → agregat (pusta dla braku eventów).
 */
function hajlajty_player_event_index( $events ): array {
	if ( empty( $events ) || ! is_array( $events ) ) {
		return array();
	}

	$idx = array();

	$ensure = static function ( &$idx, $pid ) {
		if ( ! isset( $idx[ $pid ] ) ) {
			$idx[ $pid ] = array(
				'gole'        => 0,
				'samoboje'    => 0,
				'zolta'       => 0,
				'druga_zolta' => 0,
				'czerwona'    => 0,
				'zszedl'      => null,
				'wszedl'      => null,
			);
		}
	};

	foreach ( $events as $ev ) {
		$type   = (string) ( $ev['type'] ?? '' );
		$detail = (string) ( $ev['detail'] ?? '' );
		$minute = $ev['minute'] ?? null;
		$pid    = isset( $ev['player_id'] ) ? (int) $ev['player_id'] : null;

		// subst KIERUNEK — USTALONY z ground-truth rozkładu składów (mecz 11):
		// player_id = SCHODZĄCY (jest w startXI → startował → schodzi),
		// assist_id = WCHODZĄCY (jest w substitutes → z ławki → wchodzi).
		// To ODWROTNIE niż api-mapping.md/transform.php „potwierdzone empirycznie"
		// (player=wchodzący) — diagnostyka JOIN lineups↔indeks pokazała, że tamto
		// było błędne: gracze z startXI dostawali „wszedł", a z ławki „zszedł".
		if ( 'subst' === $type ) {
			if ( null !== $pid ) {
				$ensure( $idx, $pid );
				$idx[ $pid ]['zszedl'] = $minute;
			}
			$aid = isset( $ev['assist_id'] ) ? (int) $ev['assist_id'] : null;
			if ( null !== $aid ) {
				$ensure( $idx, $aid );
				$idx[ $aid ]['wszedl'] = $minute;
			}
			continue;
		}

		if ( null === $pid ) {
			continue;
		}

		$key = hajlajty_lookup_event( $type, $detail )['key'];
		$ensure( $idx, $pid );

		switch ( $key ) {
			case 'goal':
			case 'penalty_goal':
				++$idx[ $pid ]['gole'];
				break;
			case 'own_goal':
				++$idx[ $pid ]['samoboje'];
				break;
			case 'yellow':
				++$idx[ $pid ]['zolta'];
				break;
			case 'second_yellow':
				++$idx[ $pid ]['druga_zolta'];
				++$idx[ $pid ]['czerwona'];
				break;
			case 'red':
				++$idx[ $pid ]['czerwona'];
				break;
		}
	}

	return $idx;
}
