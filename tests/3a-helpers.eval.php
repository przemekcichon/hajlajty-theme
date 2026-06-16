<?php
/**
 * Test helperów 3a na ŻYWYM WordPressie — wymaga get_post_meta / get_terms.
 *
 * Uruchom w Open Site Shell Locala (z katalogu root WP):
 *   wp eval-file wp-content/themes/hajlajty-theme/tests/3a-helpers.eval.php
 *
 * Testuje na meczach o ID 11 (FT, ZAKOŃCZONY) i 12 (NS, ZAPOWIEDŹ) — danych
 * ground-truth Fazy 2.
 *
 * Ścieżka require: świadomie __DIR__ (fizyczna lokalizacja tego pliku, ZAWSZE
 * poprawna), NIE get_stylesheet_directory() — w 3a motyw może NIE być aktywny
 * (wiring functions.php to dopiero 3b), więc get_stylesheet_directory() mógłby
 * wskazać inny motyw. __DIR__ jest niezależne od aktywnego motywu.
 */

require __DIR__ . '/../features/match-display/helpers.php';

$line = str_repeat( '-', 60 );

/* ========================================================================
 * hajlajty_get_match_data( 11 ) — mecz ZAKOŃCZONY (FT)
 * ===================================================================== */
echo "$line\n# hajlajty_get_match_data(11) — oczekiwany FT z sekcjami\n$line\n";
$d11 = hajlajty_get_match_data( 11 );
printf( "is_array:           %s\n", var_export( is_array( $d11 ), true ) );
printf( "niepusta:           %s\n", var_export( ! empty( $d11 ), true ) );
printf( "status.short:       %s\n", var_export( $d11['status']['short'] ?? '(brak)', true ) );
printf( "liczba events:      %s\n", isset( $d11['events'] ) ? count( $d11['events'] ) : '(brak klucza events)' );
printf( "isset(lineups):     %s\n", var_export( isset( $d11['lineups'] ), true ) );
printf( "isset(statistics):  %s\n", var_export( isset( $d11['statistics'] ), true ) );

/* ========================================================================
 * hajlajty_get_match_data( 12 ) — mecz ZAPOWIEDŹ (NS), bez sekcji live
 * ===================================================================== */
echo "\n$line\n# hajlajty_get_match_data(12) — oczekiwany NS bez events\n$line\n";
$d12 = hajlajty_get_match_data( 12 );
printf( "is_array:           %s\n", var_export( is_array( $d12 ), true ) );
printf( "status.short:       %s\n", var_export( $d12['status']['short'] ?? '(brak)', true ) );
// Dowód, że isset() działa i [] jest bezpieczne: zapowiedź NS nie ma events.
printf( "isset(events):      %s  (oczekiwane false dla NS)\n", var_export( isset( $d12['events'] ), true ) );

/* ========================================================================
 * hajlajty_match_get_team_terms — resolucja api_id → term (BEZ N+1)
 * Oba api_id idą JEDNYM get_terms (meta_query IN) — nie dwoma zapytaniami.
 * ===================================================================== */
$report_terms = function ( int $post_id ) {
	$data  = hajlajty_get_match_data( $post_id );
	$terms = hajlajty_match_get_team_terms( $post_id );

	foreach ( array( 'home', 'away' ) as $side ) {
		$expected_api = $data['teams'][ $side ]['api_id'] ?? '(brak w match_data)';
		$term         = $terms[ $side ];

		if ( null === $term ) {
			printf(
				"  %-4s → null (term niewysiany lub brak api_id) | match_data api_id: %s\n",
				$side,
				var_export( $expected_api, true )
			);
			continue;
		}

		$term_api = (int) get_term_meta( $term->term_id, 'api_id', true );
		$match    = ( $term_api === (int) $expected_api ) ? 'ZGODNY' : 'NIEZGODNY (!)';
		printf(
			"  %-4s → \"%s\" | term api_id: %d | match_data api_id: %s | %s\n",
			$side,
			$term->name,
			$term_api,
			var_export( $expected_api, true ),
			$match
		);
	}
};

echo "\n$line\n# hajlajty_match_get_team_terms(11)\n$line\n";
$report_terms( 11 );

echo "\n$line\n# hajlajty_match_get_team_terms(12)\n$line\n";
$report_terms( 12 );

echo "\n$line\n";
echo "# Brak N+1: resolucja OBU drużyn idzie jednym get_terms (meta_query IN),\n";
echo "# nie per-drużyna. Term null dla niewysianej drużyny to stan oczekiwany.\n";
echo "$line\n";
