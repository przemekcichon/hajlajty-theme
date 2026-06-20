<?php
/**
 * Karta SKRÓTU (.vcard) — lista/grid „skroty" i sekcja skrótów na stronie głównej.
 * Cała karta linkuje do single meczu (NIE osadza playera — to robi single-ft).
 *
 * Kontrakt: partial dostaje z ZEWNĄTRZ $post_id ORAZ rozwiązane termy
 * {home,away} (batch-resolver zrobił JEDEN get_terms na całą listę) — TU zero
 * resolucji drużyn → zero N+1. Z match_data karta nie używa nic (wynik/teams są
 * w tytule wpisu zapisanym przy imporcie); potrzebne tylko taksonomie + ACF skrótu.
 *
 * Zmienne wejściowe (z get_template_part $args):
 *   - $post_id int
 *   - $terms   array{home:?WP_Term,away:?WP_Term} (tu nieużywane wprost, ale
 *              przekazywane spójnie z pozostałymi kartami)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
// Termy z batch-resolvera (home/away + slugi taksonomii) — tu używane WYŁĄCZNIE do
// `data-*` filtra 4A (kontener karty). Dane do wyświetlenia karta czyta osobno niżej.
$terms = isset( $args['terms'] ) && is_array( $args['terms'] ) ? $args['terms'] : array(
	'home' => null,
	'away' => null,
);

// ACF skrótu (get_field gdy ACF aktywny; fallback na surowe meta) — jak single-ft.
$skrot_url = function_exists( 'get_field' ) ? get_field( 'skrot_url', $post_id ) : get_post_meta( $post_id, 'skrot_url', true );
$skrot_dur = function_exists( 'get_field' ) ? get_field( 'skrot_duration', $post_id ) : get_post_meta( $post_id, 'skrot_duration', true );
$yt_id     = hajlajty_youtube_id( is_string( $skrot_url ) ? $skrot_url : '' );
$poster    = '' !== $yt_id ? 'https://i.ytimg.com/vi/' . $yt_id . '/hqdefault.jpg' : '';

// Kanał (źródło skrótu) = pierwszy term taksonomii `kanal`.
$kanal_terms = get_the_terms( $post_id, 'kanal' );
$kanal_name  = ( is_array( $kanal_terms ) && ! is_wp_error( $kanal_terms ) && ! empty( $kanal_terms ) ) ? $kanal_terms[0]->name : '';

// Rozgrywki (etykieta meta) = pierwszy term taksonomii `rozgrywki`.
$roz_terms = get_the_terms( $post_id, 'rozgrywki' );
$roz_name  = ( is_array( $roz_terms ) && ! is_wp_error( $roz_terms ) && ! empty( $roz_terms ) ) ? $roz_terms[0]->name : '';

// Czas „… temu" względem publikacji skrótu (ACF); fallback: data wpisu.
$pub    = function_exists( 'get_field' ) ? get_field( 'skrot_published_at', $post_id ) : get_post_meta( $post_id, 'skrot_published_at', true );
$pub_ts = ( is_string( $pub ) && '' !== $pub ) ? strtotime( $pub ) : false;
if ( ! $pub_ts ) {
	$pub_ts = (int) get_post_time( 'U', true, $post_id );
}
$ago = $pub_ts ? human_time_diff( $pub_ts, time() ) . ' temu' : '';

$title = get_the_title( $post_id );
?>
<a class="vcard card-video" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"<?php echo hajlajty_match_lists_card_filter_attrs( $terms ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — atrybuty escapowane w helperze. ?>>
	<div class="thumb<?php echo '' !== $poster ? ' has-poster' : ''; ?>">
		<?php if ( '' !== $poster ) : ?>
			<img class="thumb__img" src="<?php echo esc_url( $poster ); ?>" alt="" loading="lazy" />
		<?php endif; ?>
		<?php if ( ! empty( $skrot_dur ) ) : ?>
			<span class="thumb__dur"><?php echo esc_html( $skrot_dur ); ?></span>
		<?php endif; ?>
		<?php if ( '' !== $kanal_name ) : ?>
			<span class="card__channel"><?php echo esc_html( $kanal_name ); ?></span>
		<?php endif; ?>
		<span class="thumb__play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
	</div>
	<div class="vcard__body">
		<h3 class="vcard__title"><?php echo esc_html( $title ); ?></h3>
		<?php if ( '' !== $roz_name || '' !== $ago ) : ?>
			<div class="vcard__meta">
				<?php if ( '' !== $roz_name ) : ?><span class="vcard__comp"><?php echo esc_html( $roz_name ); ?></span><?php endif; ?>
				<?php if ( '' !== $roz_name && '' !== $ago ) : ?><span class="dot-sep"></span><?php endif; ?>
				<?php echo esc_html( $ago ); ?>
			</div>
		<?php endif; ?>
	</div>
</a>
