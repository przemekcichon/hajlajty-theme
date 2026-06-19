<?php
/**
 * Wariant ODWOŁANY (CANC/ABD/AWD/WO) — render single CPT „mecz".
 * Wywoływany przez single-mecz.php (get_template_part z $args: post_id, data).
 *
 * Projekt MINIMALNY (bez designu): degeneratywny szkielet zapowiedzi zredukowany
 * do stanu terminalnego — tożsamość meczu (sloty drużyn, match-head: faza/tytuł/
 * data, BEZ godziny-jako-zapowiedzi) + JEDEN badge „Odwołany" w miejscu hero/
 * licznika. BEZ odliczania, BEZ remind, BEZ wyniku (nawet AWD/WO), BEZ składów/
 * osi/statystyk — niczego, co zakłada rozegranie. Spójne tokeny designu (istniejące
 * zmienne), zero nowych zmiennych kolorów. fav zostawiony (markup data-* — Faza 4).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
$data    = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : hajlajty_get_match_data( $post_id );

$round_pl = hajlajty_lookup_round( $data['round'] ?? null );
$terms    = hajlajty_match_get_team_terms( $post_id );

// Data meczu (etykieta PL) z PŁASKIEJ meta `kickoff` (UTC). BEZ godziny — nie
// zapowiadamy terminu meczu, który się nie odbędzie.
$kickoff_raw = get_post_meta( $post_id, 'kickoff', true );
$kickoff_dt  = ( is_string( $kickoff_raw ) && '' !== $kickoff_raw )
	? date_create_immutable( $kickoff_raw, new DateTimeZone( 'UTC' ) )
	: false;
$date_label  = $kickoff_dt ? wp_date( 'l, j F Y', $kickoff_dt->getTimestamp() ) : '';

// Flaga przez współdzielony helper (flags.php): mapuje fifa_code (3-lit. FIFA)
// na slug flagcdn (ISO alpha-2). Wcześniej strtolower(fifa_code) dawał 404.
$flag_url = static function ( $term ) {
	return hajlajty_flag_url( $term );
};
$team_name = static function ( $term ) {
	return ( $term instanceof WP_Term ) ? $term->name : '—';
};

$home_name   = $team_name( $terms['home'] );
$away_name   = $team_name( $terms['away'] );
$home_flag   = $flag_url( $terms['home'] );
$away_flag   = $flag_url( $terms['away'] );
$match_label = $home_name . ' – ' . $away_name;
$match_slug  = get_post_field( 'post_name', $post_id );
?>
<div class="watch-top container">
	<a class="back-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<svg viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></svg>
		Wróć
	</a>
	<?php if ( '' !== $round_pl ) : ?>
		<span class="crumb"><b><?php echo esc_html( $round_pl ); ?></b></span>
	<?php endif; ?>
</div>

<main class="watch container">
	<div class="watch__grid">
		<div class="watch__main">

			<!-- ===== STAN TERMINALNY: badge „Odwołany" w miejscu hero/licznika ===== -->
			<section class="preview reveal" aria-label="<?php echo esc_attr( 'Mecz odwołany: ' . $match_label ); ?>">
				<span class="preview__badge preview__badge--off"><span class="dot"></span> Odwołany</span>

				<div class="preview__inner">
					<div class="preview__match">
						<div class="team-slot">
							<?php if ( '' !== $home_flag ) : ?><img class="country-flag team-slot__flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?>
							<span class="team-slot__name"><?php echo esc_html( $home_name ); ?></span>
						</div>
						<span class="preview__vs">VS</span>
						<div class="team-slot">
							<?php if ( '' !== $away_flag ) : ?><img class="country-flag team-slot__flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?>
							<span class="team-slot__name"><?php echo esc_html( $away_name ); ?></span>
						</div>
					</div>

					<span class="preview__status">Mecz odwołany</span>
				</div>
			</section>

			<!-- ===== AKCJE KIBICA (CANC: tylko fav; bez „Przypomnij mi") ===== -->
			<div class="hf-actions">
				<button class="hf-btn hf-btn--fav" type="button" data-fav="<?php echo esc_attr( $match_slug ); ?>" data-label="<?php echo esc_attr( $match_label ); ?>">
					<svg viewBox="0 0 24 24"><path d="M12 20.5 4.2 12.7a4.7 4.7 0 0 1 6.6-6.6l1.2 1.2 1.2-1.2a4.7 4.7 0 0 1 6.6 6.6z"/></svg>
					<span class="hf-btn__txt">Dodaj do ulubionych</span>
				</button>
			</div>

			<!-- ===== METADANE MECZU (tożsamość; data bez godziny-jako-zapowiedzi) ===== -->
			<div class="match-head">
				<?php if ( '' !== $round_pl ) : ?>
					<span class="match-phase">⚽ <?php echo esc_html( $round_pl ); ?></span>
				<?php endif; ?>
				<h1 class="match-title"><?php echo esc_html( $match_label ); ?></h1>
				<?php if ( '' !== $date_label ) : ?>
					<div class="match-facts">
						<span class="fact"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4M16 2v4M3 10h18"/></svg> <b><?php echo esc_html( $date_label ); ?></b></span>
					</div>
				<?php endif; ?>
			</div>

		</div><!-- /.watch__main -->
	</div>
</main>
