<?php
/**
 * Słowniki RAW (api-football, EN) → PL dla renderu meczu (Faza 3a).
 *
 * To CZYSTE funkcje string→string/array: zero zależności od WordPressa, zero
 * HTML/emoji (ikony i tekst UI dokleja render w 3b–3d). Każda mapa trzyma
 * WSZYSTKIE kody enuma (nie tylko te z próbek) i ma jawny fallback, żeby nowy
 * kod z API nie wywrócił renderu.
 *
 * Źródło prawdy mapowań: hajlajty-meta/docs/api-mapping.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Status meczu: kod `fixture.status.short` → stan UI + flagi renderu.
 *
 * @param string|null $short Surowy kod statusu z match_data.status.short.
 * @return array{state:string,show_minute:bool,live_label:?string}
 *   - state ∈ { ZAPOWIEDZ, LIVE, ZAKONCZONY, ODWOLANY } — enum stanu (ASCII,
 *     bez ogonków; tekst PL do wyświetlenia dokleja render).
 *   - show_minute = true TYLKO dla trwającej gry (zegar tyka): 1H, 2H, ET.
 *   - live_label = etykieta pauzy/trybu LIVE (Przerwa/Karne/...) albo null.
 * Fallback (kod nieznany / null): ZAPOWIEDZ, show_minute=false, live_label=null.
 */
function hajlajty_lookup_status( ?string $short ): array {
	// Mapa 1:1 z api-mapping.md „Mapowanie statusu".
	$map = array(
		'TBD'  => array( 'ZAPOWIEDZ', false, null ),
		'NS'   => array( 'ZAPOWIEDZ', false, null ),
		'1H'   => array( 'LIVE', true, null ),
		'HT'   => array( 'LIVE', false, 'Przerwa' ),
		'2H'   => array( 'LIVE', true, null ),
		'ET'   => array( 'LIVE', true, null ),
		'BT'   => array( 'LIVE', false, 'Przerwa' ),
		'P'    => array( 'LIVE', false, 'Karne' ),
		'SUSP' => array( 'LIVE', false, 'Zawieszony' ),
		'INT'  => array( 'LIVE', false, 'Przerwany' ),
		'LIVE' => array( 'LIVE', false, 'Na żywo' ),
		'FT'   => array( 'ZAKONCZONY', false, null ),
		'AET'  => array( 'ZAKONCZONY', false, null ),
		'PEN'  => array( 'ZAKONCZONY', false, null ),
		'PST'  => array( 'ZAPOWIEDZ', false, null ),
		'CANC' => array( 'ODWOLANY', false, null ),
		'ABD'  => array( 'ODWOLANY', false, null ),
		'AWD'  => array( 'ODWOLANY', false, null ),
		'WO'   => array( 'ODWOLANY', false, null ),
	);

	$row = ( null !== $short && isset( $map[ $short ] ) )
		? $map[ $short ]
		: array( 'ZAPOWIEDZ', false, null ); // Fallback bezpieczny.

	return array(
		'state'       => $row[0],
		'show_minute' => $row[1],
		'live_label'  => $row[2],
	);
}

/**
 * Pozycja zawodnika: kod `player.pos` (G/D/M/F) → skrót PL.
 *
 * @param string|null $pos Surowa pozycja z lineups.
 * @return string Br/O/P/N albo "" dla nieznanej/null (render decyduje, czy pokazać).
 */
function hajlajty_lookup_position( ?string $pos ): string {
	$map = array(
		'G' => 'Br', // Bramkarz.
		'D' => 'O',  // Obrońca.
		'M' => 'P',  // Pomocnik.
		'F' => 'N',  // Napastnik.
	);

	return ( null !== $pos && isset( $map[ $pos ] ) ) ? $map[ $pos ] : '';
}

/**
 * Typ wydarzenia: (`type`, `detail`) z api-football → semantyczny klucz + etykieta PL.
 *
 * @param string|null $type   Typ eventu (Goal/Card/subst/Var/...).
 * @param string|null $detail Doprecyzowanie (np. "Own Goal", "Yellow Card").
 * @return array{key:string,label:string}
 *   - key = enum semantyczny (render mapuje na ikonę/emoji — TU bez HTML/emoji).
 *   - label = polski opis wydarzenia.
 * Fallback (type nieznany/null): key='other', label=$type jeśli niepusty, inaczej "".
 *
 * UWAGA: kierunek subst (player=wchodzący, assist=schodzący) to sprawa RENDERU,
 * nie tego lookupu — tu tylko etykieta typu.
 */
function hajlajty_lookup_event( ?string $type, ?string $detail ): array {
	$type   = (string) $type;
	$detail = (string) $detail;

	switch ( $type ) {
		case 'Goal':
			// Kolejność reguł istotna: "Missed Penalty" SPRAWDŹ przed "Penalty",
			// inaczej "Missed Penalty" wpadłby w penalty_goal (zawiera "Penalty").
			if ( false !== stripos( $detail, 'Own Goal' ) ) {
				return array( 'key' => 'own_goal', 'label' => 'Bramka samobójcza' );
			}
			if ( false !== stripos( $detail, 'Missed Penalty' ) ) {
				return array( 'key' => 'missed_penalty', 'label' => 'Niewykorzystany karny' );
			}
			if ( false !== stripos( $detail, 'Penalty' ) ) {
				return array( 'key' => 'penalty_goal', 'label' => 'Bramka z karnego' );
			}
			return array( 'key' => 'goal', 'label' => 'Bramka' );

		case 'Card':
			if ( false !== stripos( $detail, 'Second Yellow' ) ) {
				return array( 'key' => 'second_yellow', 'label' => 'Druga żółta (czerwona)' );
			}
			if ( false !== stripos( $detail, 'Red Card' ) ) {
				return array( 'key' => 'red', 'label' => 'Czerwona kartka' );
			}
			if ( false !== stripos( $detail, 'Yellow Card' ) ) {
				return array( 'key' => 'yellow', 'label' => 'Żółta kartka' );
			}
			// Nieznany szczegół kartki — semantycznie wciąż kartka.
			return array( 'key' => 'other', 'label' => $type );

		case 'subst':
			return array( 'key' => 'subst', 'label' => 'Zmiana' );

		case 'Var':
			$label = ( '' !== $detail ) ? 'VAR — ' . $detail : 'VAR';
			return array( 'key' => 'var', 'label' => $label );
	}

	// Fallback: type nieznany/pusty.
	return array( 'key' => 'other', 'label' => ( '' !== $type ) ? $type : '' );
}

/**
 * Etykieta statystyki: klucz `type` z API (VERBATIM, case-sensitive) → PL.
 *
 * @param string $type Klucz statystyki dokładnie jak z API (np. "Ball Possession").
 * @return string Etykieta PL albo "" dla nieznanego klucza (render decyduje:
 *   pomiń albo pokaż surowy klucz; NIE zgadujemy tłumaczenia).
 *
 * To SAME etykiety. Selekcja „które pokazać", kolejność i format wartości
 * (% / int / xG-string) NALEŻĄ do renderu (3b/3d), nie tutaj.
 */
function hajlajty_lookup_stat_label( string $type ): string {
	$map = array(
		'Ball Possession'   => 'Posiadanie piłki',
		'Total Shots'       => 'Strzały (łącznie)',
		'Shots on Goal'     => 'Strzały celne',
		'Shots off Goal'    => 'Strzały niecelne',
		'Blocked Shots'     => 'Strzały zablokowane',
		'Shots insidebox'   => 'Strzały z pola karnego',
		'Shots outsidebox'  => 'Strzały spoza pola karnego',
		'Fouls'             => 'Faule',
		'Corner Kicks'      => 'Rzuty rożne',
		'Offsides'          => 'Spalone',
		'Yellow Cards'      => 'Żółte kartki',
		'Red Cards'         => 'Czerwone kartki',
		'Goalkeeper Saves'  => 'Interwencje bramkarza',
		'Total passes'      => 'Podania (łącznie)',
		'Passes accurate'   => 'Podania celne',
		'Passes %'          => 'Celność podań',
		'expected_goals'    => 'Oczekiwane gole (xG)',
		'goals_prevented'   => 'Gole obronione',
	);

	return $map[ $type ] ?? '';
}

/**
 * Runda/faza: `league.round` (EN) → PL.
 *
 * @param string|null $round Surowy string rundy (np. "Group Stage - 1", "Final").
 * @return string Polska nazwa fazy. Fallback: surowy $round (lub "" gdy null) —
 *   decyzja D3.3: round nie ma stabilnego ID, więc fallback na surowy string.
 *
 * - Grupowa: „Group Stage - {N}" → „Faza grupowa — {N}. kolejka" (N parsowane regexem).
 * - Pucharowa: mapa EN→PL poniżej.
 */
function hajlajty_lookup_round( ?string $round ): string {
	if ( null === $round ) {
		return '';
	}

	// Grupowa: numer kolejki parsowany regexem (nie statyczna mapa).
	if ( preg_match( '/^Group Stage\s*-\s*(\d+)$/i', $round, $m ) ) {
		return 'Faza grupowa — ' . $m[1] . '. kolejka';
	}

	// Pucharowe.
	// TODO: zweryfikować dokładne stringi round po realnych danych pucharowych.
	$knockout = array(
		'Round of 32'     => '1/16 finału',
		'Round of 16'     => '1/8 finału',
		'Quarter-finals'  => 'ćwierćfinał',
		'Semi-finals'     => 'półfinał',
		'3rd Place Final' => 'mecz o 3. miejsce',
		'Final'           => 'finał',
	);

	if ( isset( $knockout[ $round ] ) ) {
		return $knockout[ $round ];
	}

	// Fallback D3.3: nierozpoznana runda → surowy string.
	return $round;
}
