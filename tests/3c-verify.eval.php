<?php
/**
 * Weryfikacja 3c na ŻYWYM WordPressie — klonuje istniejący zaimportowany mecz
 * do TRZECH rekordów testowych i wymusza stany NS / LIVE / CANC, żeby obejrzeć
 * render wariantów bez czekania na realne dane live.
 *
 * Uruchom w „Open Site Shell" Locala (z katalogu root WP):
 *   wp eval-file wp-content/themes/hajlajty-theme/tests/3c-verify.eval.php
 *
 * Idempotentny: każde uruchomienie kasuje wcześniejsze rekordy testowe (po meta
 * _hajlajty_3c_test) i tworzy je od nowa. Po zakończeniu wypisuje permalinki do
 * otwarcia + checklistę renderu per wariant. NIE dotyka realnych meczów ani
 * importu — czyta jeden mecz źródłowy (z events+lineups+statistics) i kopiuje
 * jego match_data, po czym nadpisuje status/goals/kickoff i przycina sekcje.
 *
 * SPRZĄTANIE po weryfikacji (usuwa 3 rekordy testowe):
 *   wp post list --post_type=mecz --meta_key=_hajlajty_3c_test --field=ID \
 *     | xargs -r -n1 wp post delete --force
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$line = str_repeat( '-', 64 );
echo "$line\n# 3c — generator rekordów testowych NS / LIVE / CANC\n$line\n";

/* ------------------------------------------------------------------ *
 * 1. Znajdź mecz ŹRÓDŁOWY: pierwszy `mecz` z events + lineups + statistics.
 * ------------------------------------------------------------------ */
$source_id = 0;
$candidates = get_posts(
	array(
		'post_type'      => 'mecz',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => 'match_data',
	)
);
foreach ( $candidates as $cid ) {
	$d = hajlajty_get_match_data( $cid );
	if ( isset( $d['events'], $d['lineups'], $d['statistics'] ) ) {
		$source_id = (int) $cid;
		break;
	}
}
if ( ! $source_id ) {
	echo "BŁĄD: nie znaleziono meczu z events+lineups+statistics do sklonowania.\n";
	echo "Zaimportuj najpierw mecz ZAKOŃCZONY (z pełnymi sekcjami) i powtórz.\n";
	return;
}
$src = hajlajty_get_match_data( $source_id );
printf( "Mecz źródłowy: ID %d (status %s)\n", $source_id, $src['status']['short'] ?? '?' );

/* ------------------------------------------------------------------ *
 * 2. Skasuj poprzednie rekordy testowe (idempotencja).
 * ------------------------------------------------------------------ */
$old = get_posts(
	array(
		'post_type'      => 'mecz',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_hajlajty_3c_test',
	)
);
foreach ( $old as $oid ) {
	wp_delete_post( (int) $oid, true );
}
if ( $old ) {
	printf( "Skasowano %d poprzednich rekordów testowych.\n", count( $old ) );
}

/* ------------------------------------------------------------------ *
 * 3. Helpery tworzenia rekordu.
 * ------------------------------------------------------------------ */
$now = time();

// Kopiuje taksonomie ze źródła, by drużyny się rozwiązały i aside miał kontekst.
$copy_terms = static function ( $from, $to ) {
	foreach ( array( 'druzyna', 'rozgrywki', 'sezon', 'kanal' ) as $tax ) {
		if ( ! taxonomy_exists( $tax ) ) {
			continue;
		}
		$ids = wp_get_object_terms( $from, $tax, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $ids ) && $ids ) {
			wp_set_object_terms( $to, $ids, $tax, false );
		}
	}
};

// $kickoff_ts = instant UTC; match_data.kickoff (ISO) + płaska meta kickoff (Y-m-d H:i:s).
$make = static function ( $slug, $title, array $match_data, $kickoff_ts ) use ( $source_id, $copy_terms ) {
	$match_data['kickoff'] = gmdate( 'c', $kickoff_ts );

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'mecz',
			'post_status' => 'publish',
			'post_title'  => $title,
			'post_name'   => $slug,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		printf( "  BŁĄD wstawiania %s: %s\n", $slug, $post_id->get_error_message() );
		return 0;
	}
	update_post_meta( $post_id, '_hajlajty_3c_test', 1 );
	update_post_meta( $post_id, 'match_data', wp_slash( wp_json_encode( $match_data ) ) );
	update_post_meta( $post_id, 'kickoff', gmdate( 'Y-m-d H:i:s', $kickoff_ts ) );
	$copy_terms( $source_id, $post_id );
	return (int) $post_id;
};

/* ------------------------------------------------------------------ *
 * 4a. NS — kickoff w PRZYSZŁOŚCI, goals null, bez events/lineups/statistics.
 * ------------------------------------------------------------------ */
$ns = $src;
$ns['status'] = array(
	'short'   => 'NS',
	'elapsed' => null,
	'extra'   => null,
);
$ns['goals'] = array(
	'home' => null,
	'away' => null,
);
unset( $ns['events'], $ns['lineups'], $ns['statistics'] );
$ns_id = $make( '3c-test-ns', '[3c-TEST] Zapowiedź (NS)', $ns, $now + 3 * DAY_IN_SECONDS );

/* ------------------------------------------------------------------ *
 * 4b. LIVE — status 2H, elapsed 64, goals 2:1, z events/lineups/statistics.
 * ------------------------------------------------------------------ */
$live = $src;
$live['status'] = array(
	'short'   => '2H',
	'elapsed' => 64,
	'extra'   => null,
);
$live['goals'] = array(
	'home' => 2,
	'away' => 1,
);
// events/lineups/statistics zostają ze źródła (klucze obecne).
$live_id = $make( '3c-test-live', '[3c-TEST] Na żywo (2H, 64)', $live, $now - HOUR_IN_SECONDS );

/* ------------------------------------------------------------------ *
 * 4c. CANC — status CANC; sekcje przycięte (stan terminalny).
 * ------------------------------------------------------------------ */
$canc = $src;
$canc['status'] = array(
	'short'   => 'CANC',
	'elapsed' => null,
	'extra'   => null,
);
$canc['goals'] = array(
	'home' => null,
	'away' => null,
);
unset( $canc['events'], $canc['lineups'], $canc['statistics'] );
$canc_id = $make( '3c-test-canc', '[3c-TEST] Odwołany (CANC)', $canc, $now + 5 * DAY_IN_SECONDS );

/* ------------------------------------------------------------------ *
 * 5. Permalinki + checklista.
 * ------------------------------------------------------------------ */
echo "\n$line\n# PERMALINKI (otwórz w przeglądarce, sprawdź checklistę niżej)\n$line\n";
foreach ( array( 'NS (ZAPOWIEDŹ)' => $ns_id, 'LIVE' => $live_id, 'CANC (ODWOŁANY)' => $canc_id ) as $label => $id ) {
	if ( $id ) {
		printf( "%-16s ID %-5d %s\n", $label, $id, get_permalink( $id ) );
	}
}

echo "\n$line\n# CHECKLISTA RENDERU\n$line\n";
echo <<<TXT
NS (ZAPOWIEDŹ):
  [ ] ekran „preview" z badge „Zapowiedź" + termin (klocek „dzień · godzina")
  [ ] licznik hero TYKA (dni/godz/min/sek maleją co sekundę)
  [ ] match-head: faza + tytuł „Dom – Gość" + data i godzina PL
  [ ] BRAK telebimu, BRAK osi/składów/statystyk (chyba że lineups były)
  [ ] aside „Inne mecze": pozostałe rekordy z mini-licznikiem
LIVE:
  [ ] telebim: badge LIVE, wynik 2 : 1, minuta „64'" + etykieta „2. połowa"
  [ ] oś czasu (najnowsze u góry), składy half-pitch + ławka, wskaźniki zdarzeń
  [ ] aside: „Statystyki na żywo" (słupki animują się) + „Inne mecze"
  [ ] F5 NIE zmienia minuty (statyczna z elapsed) — brak demo/overlayów
CANC (ODWOŁANY):
  [ ] jeden badge „Odwołany" + „Mecz odwołany" w miejscu licznika
  [ ] tożsamość meczu (sloty drużyn, faza/tytuł/data) — BEZ godziny
  [ ] ZERO wyniku, składów, osi, statystyk, odliczania, „Przypomnij mi"
TXT;
echo "\n$line\n";
echo "Sprzątanie po teście — patrz nagłówek pliku (wp post delete --force).\n";
