<?php
/**
 * Czysta logika widżetu statystyk drużyny (MVP-g) — mapowanie CURATED JSON z
 * MVP-f (`team_stats_<liga>_<sezon>` na termie „druzyna") na wiersze widżetu
 * `.stat-row` i pigułki formy. BEZ WordPressa, BEZ I/O: funkcje testowalne
 * `php tests/mvp-g-teams.php` (wzór: zones.php / tests/mvp-e-standings.php).
 *
 * Kontrakt wejścia = ground-truth MVP-f (kod core, NIE pamięć):
 *  - `goals.for.average` / `goals.against.average` to STRINGI (np. „0.5") —
 *    renderujemy WPROST, zero koercji (mogłaby zmienić „0.5"→0). Mogą być null.
 *  - `clean_sheet` (int|null) / `fixtures.played` (int|null) → „X / Y", a pasek
 *    tylko gdy znamy oba (realny ułamek, nie zmyślony).
 *  - `cards.yellow` / `cards.red` (int, MVP-f zawsze sumuje do liczby; brak → 0).
 *  - `form` (string W/D/L, np. „WWDLW") albo null → pigułki.
 * Pole nullowe → „–" (spójnie ze standings). „Posiadanie piłki" CELOWO NIEOBECNE:
 * to stat per-mecz, którego `/teams/statistics` nie ma (ground-truth MVP-f / #4 trim).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Buduje uporządkowaną listę wierszy widżetu statystyk z CURATED JSON MVP-f.
 *
 * @param array $curated Zdekodowane `team_stats_<liga>_<sezon>` (albo []).
 * @return array<int,array{lab:string,val:string,bar:?int}> Wiersze do renderu.
 *   `bar` = procent wypełnienia paska (0–100) albo null = bez paska (nie zmyślamy
 *   skali tam, gdzie dane nie dają naturalnego ułamka). Pusty `$curated` → [].
 */
function hajlajty_teams_view_stat_rows( array $curated ): array {
	if ( empty( $curated ) ) {
		return array();
	}

	$goals    = is_array( $curated['goals'] ?? null ) ? $curated['goals'] : array();
	$fixtures = is_array( $curated['fixtures'] ?? null ) ? $curated['fixtures'] : array();
	$cards    = is_array( $curated['cards'] ?? null ) ? $curated['cards'] : array();

	$gf_avg = $goals['for']['average'] ?? null;     // STRING (zero koercji) lub null.
	$ga_avg = $goals['against']['average'] ?? null;

	$clean  = $curated['clean_sheet'] ?? null;       // licznik „czystych kont" (int|null).
	$played = $fixtures['played'] ?? null;           // mianownik (int|null).

	$rows = array();

	$rows[] = array(
		'lab' => 'Średnia goli zdobytych',
		'val' => hajlajty_teams_view_stat_value( $gf_avg ),
		'bar' => null, // średnia nie ma naturalnej skali 0–100 → bez paska.
	);
	$rows[] = array(
		'lab' => 'Średnia goli straconych',
		'val' => hajlajty_teams_view_stat_value( $ga_avg ),
		'bar' => null,
	);

	// Czyste konta: „X / Y" + pasek = X/Y (pasek TYLKO gdy oba są liczbami i Y>0).
	$clean_num  = is_numeric( $clean ) ? (int) $clean : null;
	$played_num = is_numeric( $played ) ? (int) $played : null;
	$rows[]     = array(
		'lab' => 'Czyste konta',
		'val' => ( null === $clean_num || null === $played_num )
			? hajlajty_teams_view_stat_value( $clean )
			: $clean_num . ' / ' . $played_num,
		'bar' => ( null !== $clean_num && null !== $played_num && $played_num > 0 )
			? (int) round( min( 100, ( $clean_num / $played_num ) * 100 ) )
			: null,
	);

	$rows[] = array(
		'lab' => 'Żółte kartki',
		'val' => hajlajty_teams_view_stat_value( $cards['yellow'] ?? null ),
		'bar' => null,
	);
	$rows[] = array(
		'lab' => 'Czerwone kartki',
		'val' => hajlajty_teams_view_stat_value( $cards['red'] ?? null ),
		'bar' => null,
	);

	return $rows;
}

/**
 * Formatuje wartość statystyki do wyświetlenia. STRING (np. średnia „0.5")
 * przepisany WPROST — zero koercji. Liczba → string. null/'' → „–".
 *
 * @param mixed $value Surowa wartość z CURATED JSON.
 * @return string Wartość do renderu albo „–".
 */
function hajlajty_teams_view_stat_value( $value ): string {
	if ( null === $value || '' === $value ) {
		return '–';
	}
	return (string) $value;
}

/**
 * Rozkłada string formy (`form`, np. „WWDLW") na pigułki W/D/L. Nieznane znaki
 * pomijane (odporne na śmieci z API). null/'' → [] (render ukrywa wiersz formy).
 *
 * Typ `mixed` (nie `?string`) świadomie: `form` to passthrough z API (zero koercji
 * w producencie MVP-f), więc nie-string (np. tablica przy zmianie schematu API)
 * NIE może rzucić TypeError w renderze — `is_string` go miękko odrzuca → [].
 *
 * @param mixed $form Surowa wartość `form` z CURATED JSON (oczekiwany string|null).
 * @return array<int,array{ch:string,cls:string,title:string}> Pigułki w kolejności.
 */
function hajlajty_teams_view_form_pills( $form ): array {
	if ( ! is_string( $form ) || '' === $form ) {
		return array();
	}

	$map = array(
		'W' => array( 'cls' => 'win', 'title' => 'Wygrana' ),
		'D' => array( 'cls' => 'draw', 'title' => 'Remis' ),
		'L' => array( 'cls' => 'lose', 'title' => 'Porażka' ),
	);

	$pills = array();
	$len   = strlen( $form );
	for ( $i = 0; $i < $len; $i++ ) {
		$ch = strtoupper( $form[ $i ] );
		if ( isset( $map[ $ch ] ) ) {
			$pills[] = array(
				'ch'    => $ch,
				'cls'   => $map[ $ch ]['cls'],
				'title' => $map[ $ch ]['title'],
			);
		}
	}
	return $pills;
}

/**
 * Etykieta „seeda" drużyny (litera grupy + ranga, np. „G3") na kartę/hero.
 * Brak litery albo nienumeryczna ranga → '' (render ukrywa badge, nie zgaduje).
 *
 * @param string $letter Litera grupy (np. „G") albo ''.
 * @param mixed  $rank   Ranga w grupie (int|null wg kontraktu MVP-d).
 * @return string np. „G3" albo ''.
 */
function hajlajty_teams_view_seed_label( string $letter, $rank ): string {
	if ( '' === $letter || ! is_numeric( $rank ) ) {
		return '';
	}
	return $letter . (int) $rank;
}
