<?php
/**
 * Karta drużyny (.team-card) na liście Reprezentacje. READ-ONLY. Cała karta
 * prowadzi do Profilu (archiwum termu „druzyna"). Markup z
 * design/Hajlajty - Reprezentacje.html (TRIM: selekcjoner — N+1 dla całej listy;
 * .team-fav — Faza 4).
 *
 * Wejście (get_template_part $args):
 *   - $term  WP_Term  Term „druzyna".
 *   - $seed  string   Etykieta seeda („G3") albo '' (ukryj badge).
 *   - $group string   Litera grupy (data-group) albo ''.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hajlajty_term = isset( $args['term'] ) && $args['term'] instanceof WP_Term ? $args['term'] : null;
if ( null === $hajlajty_term ) {
	return;
}

$hajlajty_seed  = isset( $args['seed'] ) ? (string) $args['seed'] : '';
$hajlajty_group = isset( $args['group'] ) ? (string) $args['group'] : '';

$hajlajty_flag = hajlajty_flag_url( $hajlajty_term );
$hajlajty_code = strtoupper( (string) get_term_meta( $hajlajty_term->term_id, 'fifa_code', true ) );
$hajlajty_url  = get_term_link( $hajlajty_term );
$hajlajty_url  = is_wp_error( $hajlajty_url ) ? '' : $hajlajty_url;
?>
<article class="team-card" data-team="<?php echo esc_attr( $hajlajty_code ); ?>" data-group="<?php echo esc_attr( $hajlajty_group ); ?>">
	<div class="team-card__head">
		<?php if ( '' !== $hajlajty_flag ) : ?>
			<img class="country-flag team-card__flag" src="<?php echo esc_url( $hajlajty_flag ); ?>" alt="<?php echo esc_attr( $hajlajty_term->name ); ?>" />
		<?php endif; ?>
		<div class="team-card__id">
			<span class="team-card__name"><?php echo esc_html( $hajlajty_term->name ); ?></span>
		</div>
		<?php if ( '' !== $hajlajty_seed ) : ?>
			<span class="team-seed" title="<?php echo esc_attr( 'Pozycja w grupie ' . $hajlajty_group ); ?>"><?php echo esc_html( $hajlajty_seed ); ?></span>
		<?php endif; ?>
	</div>
	<?php if ( '' !== $hajlajty_url ) : ?>
		<div class="team-card__foot">
			<a class="team-btn" href="<?php echo esc_url( $hajlajty_url ); ?>" aria-label="<?php echo esc_attr( 'Zobacz profil reprezentacji ' . $hajlajty_term->name ); ?>">Zobacz profil <svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg></a>
		</div>
	<?php endif; ?>
</article>
