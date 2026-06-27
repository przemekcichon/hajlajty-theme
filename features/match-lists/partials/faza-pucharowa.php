<?php
/**
 * Treść Strony „Faza pucharowa" — drabinka R32 → … → Finał jako drzewo kolumn rund.
 * READ-ONLY z importu + kuracyjnego harmonogramu (plan DECYZJA 1/6): ZERO zapisu,
 * drabinka to WARSTWA WIDOKU (nigdy post `mecz`). Wołane przez root-template
 * `template-faza-pucharowa.php` (WP wykrywa szablony tylko w roocie); logika żyje tu,
 * w slice match-lists (vertical slice).
 *
 * Jak działa:
 *  - jeden WP_Query po wszystkich meczach (meta `kickoff` EXISTS — jak terminarz);
 *  - dla każdego posta dopasowanie do numeru FIFA przez
 *    `hajlajty_knockout_match_no($round, $kickoff)`. KRYTYCZNE (ground-truth):
 *    `round` żyje TYLKO w match_data → dekodujemy je; do klucza bierzemy PŁASKĄ meta
 *    `kickoff` (Y-m-d H:i:s), NIE match_data.kickoff (ISO);
 *  - JEDEN batch `hajlajty_match_lists_resolve_terms()` na komplet postów (zero N+1);
 *  - `hajlajty_bracket_build()` (czysta) buduje kolumny + porządek drzewa; render
 *    dokłada realne mecze. „Realny WYGRYWA" nad placeholderem (tryb komórki).
 *
 * Komórki:
 *  - real        → kompaktowa karta linkująca do single (flaga + kod FIFA + wynik/czas),
 *                  niesie atrybuty filtra (data-team-names…) → filtr ją „zapala/gasi";
 *  - placeholder → etykieta „Zwycięzca meczu N" (R16…Finał bez realnego), NIEklikalna,
 *                  bez flag, PUSTE atrybuty filtra;
 *  - tbd         → R32 bez fixture'a w API (obsada nieznana, 9/16) → „Do ustalenia",
 *                  PUSTE atrybuty filtra. NIE wymyślamy par.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Wszystkie mecze z płaską meta `kickoff` (jak terminarz) — z nich budujemy mapę
// numer→realny mecz. round dekodujemy z match_data (brak płaskiego klucza rundy).
$bracket_query = new WP_Query(
	array(
		'post_type'      => 'mecz',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'meta_query'     => array(
			'kick' => array(
				'key'     => 'kickoff',
				'compare' => 'EXISTS',
			),
		),
		'orderby'        => array( 'kick' => 'ASC' ),
	)
);

$bracket_post_ids = wp_list_pluck( $bracket_query->posts, 'ID' );
$bracket_resolved = hajlajty_match_lists_resolve_terms( $bracket_post_ids );
$bracket_real_by_no = array(); // numer FIFA => { post_id, data }
foreach ( $bracket_post_ids as $bracket_pid ) {
	$bracket_pid     = (int) $bracket_pid;
	$bracket_kickoff = (string) get_post_meta( $bracket_pid, 'kickoff', true );
	if ( '' === $bracket_kickoff ) {
		continue;
	}
	$bracket_data = hajlajty_get_match_data( $bracket_pid );
	$bracket_no   = hajlajty_knockout_match_no( $bracket_data['round'] ?? null, $bracket_kickoff );
	if ( $bracket_no <= 0 ) {
		continue; // faza grupowa albo rozjazd godziny FIFA↔API — nie ma miejsca w drabince.
	}
	$bracket_real_by_no[ $bracket_no ] = array(
		'post_id' => $bracket_pid,
		'data'    => $bracket_data,
	);
}

$bracket_columns = hajlajty_bracket_build( hajlajty_knockout_schedule() );
wp_reset_postdata();

// Etykieta placeholderowa („Zwycięzca meczu 74") bywa dłuższa niż kolumna — rozbijamy
// na słowa (czytelność, zero przycięcia numeru). Domknięcie, nie funkcja (partial bywa
// include'owany wielokrotnie). Wzór: card-placeholder.php.
$bracket_words = static function ( ?string $label ): array {
	$label = trim( (string) $label );
	return '' === $label ? array() : preg_split( '/\s+/', $label );
};
?>
<div class="page-head">
	<span class="page-head__eyebrow"><span class="dot"></span> Mundial 2026 · Kanada · Meksyk · USA</span>
	<h1 class="page-head__title">Faza pucharowa</h1>
	<p class="page-head__sub">Drabinka pucharowa od 1/16 finału po finał. Rozegrane i zaplanowane mecze pojawiają się automatycznie z importu; sloty bez ustalonej obsady czekają jako „do ustalenia".</p>
	<div class="legend">
		<span class="legend__item"><span class="legend__dot skrot"></span> Mecz z terminarza (klikalny)</span>
		<span class="legend__item"><span class="legend__dot soon"></span> Zwycięzca/przegrany meczu N</span>
		<span class="legend__item"><span class="legend__dot live"></span> Obsada do ustalenia</span>
	</div>
</div>

<?php if ( empty( $bracket_columns ) ) : ?>
	<div class="empty-state is-visible">
		<h3>Brak danych drabinki</h3>
		<p>Harmonogram fazy pucharowej jest pusty. Wróć, gdy ruszy faza pucharowa.</p>
	</div>
	<?php
	return;
endif;
?>

<div class="bracket-scroll">
	<div class="bracket" data-filterable>
		<?php foreach ( $bracket_columns as $bracket_col ) : ?>
			<?php $bracket_round_pl = hajlajty_lookup_round( $bracket_col['round'] ); ?>
			<section class="bracket__col" data-round="<?php echo esc_attr( $bracket_col['round'] ); ?>">
				<h2 class="bracket__round"><?php echo esc_html( '' !== $bracket_round_pl ? $bracket_round_pl : $bracket_col['round'] ); ?></h2>
				<div class="bracket__cells">
					<?php
					foreach ( $bracket_col['cells'] as $bracket_cell ) :
						$bracket_no   = (int) $bracket_cell['no'];
						$bracket_real = $bracket_real_by_no[ $bracket_no ] ?? null;
						$bracket_has_label = ( null !== $bracket_cell['home_label'] && null !== $bracket_cell['away_label'] );
						$bracket_mode = hajlajty_bracket_cell_mode( null !== $bracket_real, $bracket_has_label );

						if ( 'real' === $bracket_mode ) :
							$bracket_card_id = (int) $bracket_real['post_id'];
							$bracket_data    = $bracket_real['data'];
							$bracket_terms   = isset( $bracket_resolved[ $bracket_card_id ] )
								? $bracket_resolved[ $bracket_card_id ]
								: array(
									'home' => null,
									'away' => null,
								);

							$bracket_state = hajlajty_lookup_status( $bracket_data['status']['short'] ?? null )['state'];
							$bracket_short = (string) ( $bracket_data['status']['short'] ?? '' );
							$bracket_gh    = $bracket_data['goals']['home'] ?? null;
							$bracket_ga    = $bracket_data['goals']['away'] ?? null;

							$bracket_home_flag = hajlajty_flag_url( $bracket_terms['home'] );
							$bracket_away_flag = hajlajty_flag_url( $bracket_terms['away'] );
							$bracket_home_code = hajlajty_match_lists_team_code( $bracket_terms['home'] );
							$bracket_away_code = hajlajty_match_lists_team_code( $bracket_terms['away'] );

							// Dopisek rozstrzygnięcia po 90' (NOWY odczyt, dziś nieużywany w
							// repo): AET = po dogrywce; PEN = po karnych (+ wynik karnych, gdy
							// jest). score.penalty.* bywa null — obsłuż bezpiecznie.
							$bracket_note = '';
							if ( 'AET' === $bracket_short ) {
								$bracket_note = 'po dogrywce';
							} elseif ( 'PEN' === $bracket_short ) {
								$bracket_pen_h = $bracket_data['score']['penalty']['home'] ?? null;
								$bracket_pen_a = $bracket_data['score']['penalty']['away'] ?? null;
								$bracket_note  = ( null !== $bracket_pen_h && null !== $bracket_pen_a )
									? 'karne ' . (int) $bracket_pen_h . ':' . (int) $bracket_pen_a
									: 'po karnych';
							}

							// Etykieta środka: wynik (zakończony/live) albo godzina (zapowiedź).
							$bracket_show_score = in_array( $bracket_state, array( 'ZAKONCZONY', 'LIVE' ), true );
							$bracket_when = '';
							if ( ! $bracket_show_score ) {
								$bracket_kickoff_raw = (string) get_post_meta( $bracket_card_id, 'kickoff', true );
								$bracket_kdt = ( '' !== $bracket_kickoff_raw )
									? date_create_immutable( $bracket_kickoff_raw, new DateTimeZone( 'UTC' ) )
									: false;
								$bracket_when = $bracket_kdt ? wp_date( 'j M · H:i', $bracket_kdt->getTimestamp() ) : '';
							}
							?>
							<a class="bracket-cell bracket-cell--real is-<?php echo esc_attr( strtolower( $bracket_state ) ); ?>" href="<?php echo esc_url( get_permalink( $bracket_card_id ) ); ?>"<?php echo hajlajty_match_lists_card_filter_attrs( $bracket_terms ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — atrybuty escapowane w helperze. ?>>
								<span class="bracket-cell__head">
									<span class="bracket-cell__no">Mecz <?php echo (int) $bracket_no; ?></span>
									<?php if ( 'LIVE' === $bracket_state ) : ?>
										<span class="bracket-cell__live">● LIVE</span>
									<?php endif; ?>
								</span>
								<span class="bracket-cell__team">
									<?php if ( '' !== $bracket_home_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $bracket_home_flag ); ?>" alt="" /><?php endif; ?>
									<span class="bracket-cell__code"><?php echo esc_html( $bracket_home_code ); ?></span>
									<?php if ( $bracket_show_score ) : ?><b class="bracket-cell__g"><?php echo esc_html( null === $bracket_gh ? '–' : $bracket_gh ); ?></b><?php endif; ?>
								</span>
								<span class="bracket-cell__team">
									<?php if ( '' !== $bracket_away_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $bracket_away_flag ); ?>" alt="" /><?php endif; ?>
									<span class="bracket-cell__code"><?php echo esc_html( $bracket_away_code ); ?></span>
									<?php if ( $bracket_show_score ) : ?><b class="bracket-cell__g"><?php echo esc_html( null === $bracket_ga ? '–' : $bracket_ga ); ?></b><?php endif; ?>
								</span>
								<?php if ( $bracket_show_score && '' !== $bracket_note ) : ?>
									<span class="bracket-cell__note"><?php echo esc_html( $bracket_note ); ?></span>
								<?php elseif ( ! $bracket_show_score && '' !== $bracket_when ) : ?>
									<span class="bracket-cell__when"><?php echo esc_html( $bracket_when ); ?></span>
								<?php endif; ?>
							</a>
							<?php
						else :
							// placeholder / tbd — NIEklikalne, bez flag, PUSTE atrybuty filtra
							// (filtr drużyny je wygasi; atrybut data-team-names MUSI istnieć).
							$bracket_empty_attrs = hajlajty_match_lists_card_filter_attrs(
								array(
									'home' => null,
									'away' => null,
								)
							);
							?>
							<div class="bracket-cell bracket-cell--<?php echo esc_attr( $bracket_mode ); ?>"<?php echo $bracket_empty_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — atrybuty escapowane w helperze. ?>>
								<span class="bracket-cell__head">
									<span class="bracket-cell__no">Mecz <?php echo (int) $bracket_no; ?></span>
								</span>
								<?php if ( 'placeholder' === $bracket_mode ) : ?>
									<span class="bracket-cell__feeder">
										<?php foreach ( $bracket_words( $bracket_cell['home_label'] ) as $bracket_w ) : ?>
											<span class="bracket-cell__word"><?php echo esc_html( $bracket_w ); ?></span>
										<?php endforeach; ?>
									</span>
									<span class="bracket-cell__vs">vs</span>
									<span class="bracket-cell__feeder">
										<?php foreach ( $bracket_words( $bracket_cell['away_label'] ) as $bracket_w ) : ?>
											<span class="bracket-cell__word"><?php echo esc_html( $bracket_w ); ?></span>
										<?php endforeach; ?>
									</span>
								<?php else : ?>
									<span class="bracket-cell__tbd">Obsada do ustalenia</span>
								<?php endif; ?>
							</div>
							<?php
						endif;
					endforeach;
					?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
</div>
