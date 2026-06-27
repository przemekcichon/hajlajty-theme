<?php
/**
 * Karta WYNIKU (.rcard) — mecz ZAKOŃCZONY bez skrótu wideo ORAZ mecz ODWOŁANY.
 * Powstaje dla terminarza (MVP-c): w jednym chronologicznym ciągu są też mecze
 * rozegrane, których redaktor nie wzbogacił jeszcze linkiem YT (#10 — wzbogacanie
 * ręczne). Zamiast chować je w ciszy, pokazujemy minimalną kartę wyniku — redaktor
 * od razu widzi, co zostało do uzupełnienia. Cała karta linkuje do single meczu.
 *
 * Dwa stany (z `hajlajty_lookup_status`):
 *  - ZAKONCZONY → wynik z `match_data.goals` (badge „Zakończony"),
 *  - ODWOLANY   → BEZ wyniku, badge „Odwołany" (spójnie z single-canc.php).
 * LIVE/ZAPOWIEDZ NIE trafiają tu (terminarz wybiera kartę per stan) — fallback
 * renderuje je jak ZAKONCZONY-bez-wyniku, żeby nigdy nie wywrócić układu.
 *
 * Minimalna, na istniejących tokenach (zero nowych zmiennych) — styl w terminarz.css.
 * Markup kart NIE niesie akcji kibica (fav/bell) — spójnie z resztą list (Faza 4).
 *
 * Zmienne wejściowe (z get_template_part $args):
 *   - $post_id int
 *   - $terms   array{home:?WP_Term,away:?WP_Term}
 *   - $data    array (opcjonalnie; gdy brak — dekodujemy z post_id)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
$terms   = isset( $args['terms'] ) && is_array( $args['terms'] ) ? $args['terms'] : array(
	'home' => null,
	'away' => null,
);
$data = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : hajlajty_get_match_data( $post_id );

$round_pl  = hajlajty_lookup_round( $data['round'] ?? null );
$home_flag = hajlajty_flag_url( $terms['home'] );
$away_flag = hajlajty_flag_url( $terms['away'] );
$home_name = hajlajty_match_lists_team_name( $terms['home'] );
$away_name = hajlajty_match_lists_team_name( $terms['away'] );

$state    = hajlajty_lookup_status( $data['status']['short'] ?? null )['state'];
$is_canc  = ( 'ODWOLANY' === $state );

$goals_home = $data['goals']['home'] ?? null;
$goals_away = $data['goals']['away'] ?? null;
?>
<a class="rcard<?php echo $is_canc ? ' rcard--off' : ''; ?>" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"<?php echo hajlajty_match_lists_card_filter_attrs( $terms ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — atrybuty escapowane w helperze. ?>>
	<div class="rcard__top">
		<?php if ( '' !== $round_pl ) : ?>
			<span class="rcard__phase">⚽ <?php echo esc_html( $round_pl ); ?></span>
			<?php $hajl_no = isset( $args['match_no'] ) ? (int) $args['match_no'] : 0; if ( $hajl_no > 0 ) : ?><span class="card__matchno">Mecz <?php echo (int) $hajl_no; ?></span><?php endif; ?>
		<?php endif; ?>
		<span class="rcard__status">
			<?php echo esc_html( $is_canc ? 'Odwołany' : 'Zakończony' ); ?>
		</span>
	</div>
	<div class="rcard__match">
		<div class="rcard__team">
			<?php if ( '' !== $home_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?>
			<span class="rcard__name"><?php echo esc_html( $home_name ); ?></span>
		</div>
		<?php if ( $is_canc ) : ?>
			<span class="rcard__score rcard__score--off">—</span>
		<?php else : ?>
			<span class="rcard__score">
				<b><?php echo esc_html( null === $goals_home ? '–' : $goals_home ); ?></b><span class="rcard__sep">:</span><b><?php echo esc_html( null === $goals_away ? '–' : $goals_away ); ?></b>
			</span>
		<?php endif; ?>
		<div class="rcard__team rcard__team--away">
			<?php if ( '' !== $away_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?>
			<span class="rcard__name"><?php echo esc_html( $away_name ); ?></span>
		</div>
	</div>
</a>
