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
