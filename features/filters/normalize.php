<?php
/**
 * Normalizator nazw PL → forma porównywalna w wyszukiwaniu drużyn (slice filters).
 *
 * Jedno źródło prawdy kontraktu PHP↔JS: karta listy niesie ZNORMALIZOWANE nazwy
 * drużyn w `data-team-names` (zbudowane tym helperem przy renderze), a `filters.js`
 * normalizuje wpisany tekst DOKŁADNIE tak samo (port reguły z designu) i dopasowuje
 * przez substring. Obie strony MUSZĄ dawać ten sam wynik — stąd jeden, jawny słownik.
 *
 * Reguła (spójna z designem, components/inline `norm()`):
 *   lowercase  +  ł→l  +  zdjęcie diakrytyków (ą/ć/ę/ń/ó/ś/ź/ż → a/c/e/n/o/s/z/z).
 * W JS robi to `toLowerCase().replace(/ł/,"l").normalize("NFD").replace(marks,"")`;
 * w PHP — mały słownik strukturalny (CLAUDE.md „Lokalizacja nazw": dosłowna mapa
 * dozwolona dla małych zbiorów strukturalnych). `ł` NIE rozkłada się w NFD, więc
 * w obu światach mapujemy go jawnie.
 *
 * Dokładamy garść łacińskich znaków spoza polskiego alfabetu (ç, á, é…), które w
 * JS i tak sprowadza NFD — żeby nieliczne nie-polskie nazwy serwisowe (np.
 * „Curaçao") wypadały identycznie po obu stronach.
 *
 * @param string $value Surowa nazwa (np. term->name drużyny).
 * @return string Forma znormalizowana (lowercase ASCII-podobna).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function hajlajty_filters_normalize_pl( string $value ): string {
	$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );

	$map = array(
		'ą' => 'a',
		'ć' => 'c',
		'ę' => 'e',
		'ł' => 'l',
		'ń' => 'n',
		'ó' => 'o',
		'ś' => 's',
		'ź' => 'z',
		'ż' => 'z',
		// Spoza polskiego alfabetu — spójność z NFD w JS (np. „Curaçao").
		'ç' => 'c',
		'á' => 'a',
		'à' => 'a',
		'ã' => 'a',
		'â' => 'a',
		'é' => 'e',
		'è' => 'e',
		'ê' => 'e',
		'í' => 'i',
		'î' => 'i',
		'ñ' => 'n',
		'ô' => 'o',
		'õ' => 'o',
		'ö' => 'o',
		'ú' => 'u',
		'ü' => 'u',
	);

	return strtr( $lower, $map );
}
