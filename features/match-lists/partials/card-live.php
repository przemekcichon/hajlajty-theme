<?php
/**
 * Karta LIVE (.live-card) — lista „na żywo" i sekcja LIVE strony głównej.
 * BEZ .live-events (oś zdarzeń to widok single, nie karta listy). Cała karta
 * linkuje do single meczu.
 *
 * Kontrakt: partial dostaje z ZEWNĄTRZ $post_id ORAZ rozwiązane termy {home,away}
 * (batch-resolver zrobił JEDEN get_terms) → TU zero resolucji drużyn → zero N+1.
 * Z match_data używa TYLKO: goals.{home,away} (wynik) i status.{short,elapsed,extra}
 * (minuta). Strona home/away rozróżniana po termach z resolvera (po api_id), nie
 * po kolejności.
 *
 * Minuta jest STATYCZNA z elapsed (jak single-live) — auto-refresh to 3e.
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

$home_flag = hajlajty_flag_url( $terms['home'] );
$away_flag = hajlajty_flag_url( $terms['away'] );
$home_code = hajlajty_match_lists_team_code( $terms['home'] );
$away_code = hajlajty_match_lists_team_code( $terms['away'] );

$goals_home = $data['goals']['home'] ?? null;
$goals_away = $data['goals']['away'] ?? null;

// Minuta: elapsed (+extra)' gdy zegar tyka; inaczej etykieta pauzy (Przerwa/Karne…).
$status     = hajlajty_lookup_status( $data['status']['short'] ?? null );
$elapsed    = $data['status']['elapsed'] ?? null;
$extra      = $data['status']['extra'] ?? null;
$minute_txt = '';
if ( $status['show_minute'] && null !== $elapsed ) {
	$minute_txt = $elapsed . ( ( null !== $extra && '' !== $extra ) ? '+' . $extra : '' ) . "'";
} elseif ( null !== $status['live_label'] ) {
	$minute_txt = $status['live_label'];
}
?>
<a class="live-card" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
	<div class="thumb">
		<span class="live-badge"><span class="dot"></span> LIVE</span>
		<?php if ( '' !== $minute_txt ) : ?>
			<span class="live-minute"><?php echo esc_html( $minute_txt ); ?></span>
		<?php endif; ?>
		<div class="live-score">
			<span class="team">
				<?php if ( '' !== $home_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?>
				<?php echo esc_html( $home_code ); ?>
			</span>
			<span class="num"><?php echo esc_html( null === $goals_home ? '–' : $goals_home ); ?></span><span class="sep">:</span><span class="num"><?php echo esc_html( null === $goals_away ? '–' : $goals_away ); ?></span>
			<span class="team away">
				<?php if ( '' !== $away_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?>
				<?php echo esc_html( $away_code ); ?>
			</span>
		</div>
	</div>
</a>
