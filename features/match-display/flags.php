<?php
/**
 * Flagi drużyn — mapowanie kodu FIFA (term meta `fifa_code`, 3 litery) na slug
 * flagcdn i budowa URL-a flagi. CZYSTE funkcje (poza odczytem term meta), jak
 * lookups.php / derive.php.
 *
 * DLACZEGO mapa, a nie strtolower(fifa_code): flagcdn.com adresuje flagi kodem
 * ISO 3166-1 alpha-2 (2 litery: pl, de, us), a `fifa_code` to 3-literowy kod FIFA
 * (POL, GER, USA). `strtolower('USA')` → flagcdn.com/usa.svg → 404 (brak flagi).
 * Stąd mała stała mapa FIFA→ISO2 — dokładnie ten typ „małego słownika
 * strukturalnego" sankcjonowany w CLAUDE.md („Lokalizacja nazw").
 *
 * WIELKA BRYTANIA: Anglia/Szkocja nie mają własnego ISO alpha-2 — flagcdn ma dla
 * nich slugi `gb-eng` / `gb-sct` (analogicznie gb-wls, gb-nir, gdyby doszły).
 *
 * NOWA DRUŻYNA W SEEDZIE = NOWY WPIS TUTAJ. Kod nieznany → '' (brak flagi:
 * bezpieczna degradacja, lepsza niż 404). Stan na 2026-06 pokrywa cały zaseedowany
 * roster (49 reprezentacji).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kod FIFA (3 litery) → slug flagcdn (ISO 3166-1 alpha-2 lub gb-*).
 *
 * @param string $fifa Kod FIFA z term meta (dowolna wielkość liter).
 * @return string Slug flagcdn albo '' dla nieznanego kodu (render pomija flagę).
 */
function hajlajty_flag_code( string $fifa ): string {
	$map = array(
		'ALG' => 'dz', // Algieria
		'ENG' => 'gb-eng', // Anglia
		'KSA' => 'sa', // Arabia Saudyjska
		'ARG' => 'ar', // Argentyna
		'AUS' => 'au', // Australia
		'AUT' => 'at', // Austria
		'BEL' => 'be', // Belgia
		'BIH' => 'ba', // Bośnia i Hercegowina
		'BRA' => 'br', // Brazylia
		'CRO' => 'hr', // Chorwacja
		'CUR' => 'cw', // Curaçao
		'CZE' => 'cz', // Czechy
		'CGO' => 'cd', // Demokratyczna Republika Konga (term nosi ten kod)
		'EGY' => 'eg', // Egipt
		'ECU' => 'ec', // Ekwador
		'FRA' => 'fr', // Francja
		'GHA' => 'gh', // Ghana
		'HAI' => 'ht', // Haiti
		'ESP' => 'es', // Hiszpania
		'NED' => 'nl', // Holandia
		'IRQ' => 'iq', // Irak
		'IRN' => 'ir', // Iran
		'JPN' => 'jp', // Japonia
		'JOR' => 'jo', // Jordania
		'CAN' => 'ca', // Kanada
		'QAT' => 'qa', // Katar
		'COL' => 'co', // Kolumbia
		'KOR' => 'kr', // Korea Południowa
		'MAR' => 'ma', // Maroko
		'MEX' => 'mx', // Meksyk
		'GER' => 'de', // Niemcy
		'NOR' => 'no', // Norwegia
		'NZL' => 'nz', // Nowa Zelandia
		'PAN' => 'pa', // Panama
		'PAR' => 'py', // Paragwaj
		'POL' => 'pl', // Polska
		'POR' => 'pt', // Portugalia
		'RSA' => 'za', // Republika Południowej Afryki
		'CPV' => 'cv', // Republika Zielonego Przylądka
		'SEN' => 'sn', // Senegal
		'USA' => 'us', // Stany Zjednoczone
		'SCO' => 'gb-sct', // Szkocja
		'SUI' => 'ch', // Szwajcaria
		'SWE' => 'se', // Szwecja
		'TUN' => 'tn', // Tunezja
		'TUR' => 'tr', // Turcja
		'URU' => 'uy', // Urugwaj
		'UZB' => 'uz', // Uzbekistan
		'CIV' => 'ci', // Wybrzeże Kości Słoniowej
	);

	return $map[ strtoupper( $fifa ) ] ?? '';
}

/**
 * URL flagi drużyny z termu `druzyna` (po jego `fifa_code`).
 *
 * Jedno źródło prawdy budowy URL-a flagi dla CAŁEGO renderu (single-* + listy).
 *
 * @param ?WP_Term $term Term drużyny albo null.
 * @return string URL svg flagi albo '' (brak termu / nieznany kod → render pomija img).
 */
function hajlajty_flag_url( $term ): string {
	if ( ! ( $term instanceof WP_Term ) ) {
		return '';
	}
	$code = hajlajty_flag_code( (string) get_term_meta( $term->term_id, 'fifa_code', true ) );
	return '' !== $code ? 'https://flagcdn.com/' . $code . '.svg' : '';
}
