<?php
/**
 * Karta ZAPOWIEDZI (.card--preview) — lista „zapowiedzi" i sekcja zapowiedzi
 * strony głównej. BEZ .card__fav / .card__bell (akcje kibica to Faza 4). Cała
 * karta linkuje do single meczu.
 *
 * Kontrakt: partial dostaje z ZEWNĄTRZ $post_id ORAZ rozwiązane termy {home,away}
 * (batch-resolver) → zero N+1. Z match_data używa TYLKO `round` (faza). Termin:
 * PŁASKA meta `kickoff` (UTC, Y-m-d H:i:s) — instant absolutny (ISO z offsetem)
 * dla JS odliczania, etykieta PL przez wp_date (KONWENCJA 3c, jak single-ns/ft).
 *
 * Faza (card__phase) = hajlajty_lookup_round(round) — BEZ litery grupy (grupa
 * nie jest dostępna w fixtures).
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

// Czas: PŁASKA meta `kickoff` (UTC) → ISO z offsetem dla JS + etykieta PL przez wp_date.
$kickoff_raw = get_post_meta( $post_id, 'kickoff', true );
$kickoff_dt  = ( is_string( $kickoff_raw ) && '' !== $kickoff_raw )
	? date_create_immutable( $kickoff_raw, new DateTimeZone( 'UTC' ) )
	: false;
$kickoff_iso = $kickoff_dt ? $kickoff_dt->format( 'c' ) : '';
$when_label  = $kickoff_dt ? wp_date( 'j M Y · H:i', $kickoff_dt->getTimestamp() ) : '';
?>
<a class="card--preview" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"<?php echo '' !== $kickoff_iso ? ' data-kickoff="' . esc_attr( $kickoff_iso ) . '"' : ''; ?><?php echo hajlajty_match_lists_card_filter_attrs( $terms ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — atrybuty escapowane w helperze. ?>>
	<?php if ( '' !== $round_pl ) : ?>
		<span class="card__phase">⚽ <?php echo esc_html( $round_pl ); ?></span>
	<?php endif; ?>
	<?php if ( '' !== $kickoff_iso ) : ?>
		<div class="card__countdown" data-countdown>
			<div class="card__unit"><span class="card__val" data-d>00</span><span class="card__lab">dni</span></div>
			<div class="card__unit"><span class="card__val" data-h>00</span><span class="card__lab">godz</span></div>
			<div class="card__unit"><span class="card__val" data-m>00</span><span class="card__lab">min</span></div>
			<div class="card__unit"><span class="card__val" data-s>00</span><span class="card__lab">sek</span></div>
		</div>
	<?php endif; ?>
	<?php if ( '' !== $when_label ) : ?>
		<p class="card__when"><?php echo esc_html( $when_label ); ?> (czasu PL)</p>
	<?php endif; ?>
	<div class="card__teams">
		<div class="card__team">
			<?php if ( '' !== $home_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?>
			<span class="card__team-name"><?php echo esc_html( $home_name ); ?></span>
		</div>
		<span class="card__vs">VS</span>
		<div class="card__team">
			<?php if ( '' !== $away_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?>
			<span class="card__team-name"><?php echo esc_html( $away_name ); ?></span>
		</div>
	</div>
</a>
