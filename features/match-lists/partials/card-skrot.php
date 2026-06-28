<?php
/**
 * Karta SKRÓTU (.vcard) — lista/grid „skroty", sekcja skrótów strony głównej,
 * terminarz (stan ZAKOŃCZONY ze skrótem) ORAZ aside „Inne skróty" na single
 * (single-ft.php). JEDNO źródło markupu karty skrótu (MVP-h) — bez rozjazdu.
 * Cała karta linkuje do single meczu (NIE osadza playera — to robi single-ft).
 *
 * Układ scalony (MVP-h): GÓRA = miniatura/facade YouTube (chip rozgrywki w lewym
 * górnym rogu, kanał w lewym dolnym, czas trwania w prawym dolnym); DÓŁ = blok
 * meczowy jak w karcie WYNIKU (flagi + pełne nazwy państw + wynik) z DATĄ
 * ROZEGRANIA meczu (płaska meta `kickoff`, UTC→PL) w miejscu badge „Zakończony".
 * Datę DODANIA skrótu (`skrot_published_at`) świadomie IGNORUJEMY (MVP-h).
 *
 * Kontrakt: partial dostaje z ZEWNĄTRZ $post_id ORAZ rozwiązane termy
 * {home,away} (batch-resolver zrobił JEDEN get_terms na całą listę) — TU zero
 * resolucji drużyn → zero N+1. Wynik/round/teams czyta z `match_data` (jak
 * card-wynik); taksonomie (rozgrywki/kanal) i ACF skrótu osobno niżej.
 *
 * Zmienne wejściowe (z get_template_part $args):
 *   - $post_id int
 *   - $terms   array{home:?WP_Term,away:?WP_Term,...} (z hajlajty_match_lists_resolve_terms)
 *   - $data    array (opcjonalnie; gdy brak — dekodujemy z post_id)
 *   - $match_no int (opcjonalnie; numer meczu FIFA — tylko terminarz)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
// Termy z batch-resolvera (home/away + slugi taksonomii) — używane do bloku
// meczowego (flagi/nazwy) ORAZ do `data-*` filtra 4A (kontener karty).
$terms = isset( $args['terms'] ) && is_array( $args['terms'] ) ? $args['terms'] : array(
	'home' => null,
	'away' => null,
);
// match_data: dekodowane raz przez wołającego (terminarz) albo tu (archiwum/home/aside).
$data = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : hajlajty_get_match_data( $post_id );

// ACF skrótu (get_field gdy ACF aktywny; fallback na surowe meta) — jak single-ft.
$skrot_url = function_exists( 'get_field' ) ? get_field( 'skrot_url', $post_id ) : get_post_meta( $post_id, 'skrot_url', true );
$skrot_dur = function_exists( 'get_field' ) ? get_field( 'skrot_duration', $post_id ) : get_post_meta( $post_id, 'skrot_duration', true );
$yt_id     = hajlajty_youtube_id( is_string( $skrot_url ) ? $skrot_url : '' );
$poster    = '' !== $yt_id ? 'https://i.ytimg.com/vi/' . $yt_id . '/hqdefault.jpg' : '';

// Kanał (źródło skrótu) = pierwszy term taksonomii `kanal`.
$kanal_terms = get_the_terms( $post_id, 'kanal' );
$kanal_name  = ( is_array( $kanal_terms ) && ! is_wp_error( $kanal_terms ) && ! empty( $kanal_terms ) ) ? $kanal_terms[0]->name : '';

// Chip rozgrywki (overlay na miniaturze) = pierwszy term taksonomii `rozgrywki`.
$roz_terms = get_the_terms( $post_id, 'rozgrywki' );
$roz_name  = ( is_array( $roz_terms ) && ! is_wp_error( $roz_terms ) && ! empty( $roz_terms ) ) ? $roz_terms[0]->name : '';

// Faza/runda (w bloku meczowym, jak karta wyniku) = match_data.round → PL.
$round_pl = hajlajty_lookup_round( $data['round'] ?? null );

// Blok meczowy: flagi + pełne nazwy + wynik (jak card-wynik). Drużyny z $terms,
// wynik z match_data.goals (null przed/bez wyniku → „–").
$home_flag  = hajlajty_flag_url( $terms['home'] );
$away_flag  = hajlajty_flag_url( $terms['away'] );
$home_name  = hajlajty_match_lists_team_name( $terms['home'] );
$away_name  = hajlajty_match_lists_team_name( $terms['away'] );
$goals_home = $data['goals']['home'] ?? null;
$goals_away = $data['goals']['away'] ?? null;

// DATA ROZEGRANIA = płaska meta `kickoff` (UTC, „Y-m-d H:i:s") → etykieta PL przez
// wp_date (strefa serwisu), wzorzec spójny z card-zapowiedz. NIE skrot_published_at.
$kickoff_raw = get_post_meta( $post_id, 'kickoff', true );
$kickoff_dt  = ( is_string( $kickoff_raw ) && '' !== $kickoff_raw )
	? date_create_immutable( $kickoff_raw, new DateTimeZone( 'UTC' ) )
	: false;
$played_label = $kickoff_dt ? wp_date( 'j M Y', $kickoff_dt->getTimestamp() ) : '';

$match_no = isset( $args['match_no'] ) ? (int) $args['match_no'] : 0;
?>
<a class="vcard card-video" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"<?php echo hajlajty_match_lists_card_filter_attrs( $terms ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — atrybuty escapowane w helperze. ?>>
	<div class="thumb<?php echo '' !== $poster ? ' has-poster' : ''; ?>">
		<?php if ( '' !== $poster ) : ?>
			<img class="thumb__img" src="<?php echo esc_url( $poster ); ?>" alt="" loading="lazy" />
		<?php endif; ?>
		<?php if ( '' !== $roz_name ) : ?>
			<span class="vcard__chip"><?php echo esc_html( $roz_name ); ?></span>
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
		<?php if ( '' !== $round_pl || 0 < $match_no || '' !== $played_label ) : ?>
			<div class="vcard__top">
				<?php if ( '' !== $round_pl ) : ?><span class="vcard__phase">⚽ <?php echo esc_html( $round_pl ); ?></span><?php endif; ?>
				<?php if ( 0 < $match_no ) : ?><span class="card__matchno">Mecz <?php echo (int) $match_no; ?></span><?php endif; ?>
				<?php if ( '' !== $played_label ) : ?><span class="vcard__date"><?php echo esc_html( $played_label ); ?></span><?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="vcard__match">
			<div class="vcard__team">
				<?php if ( '' !== $home_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?>
				<span class="vcard__name"><?php echo esc_html( $home_name ); ?></span>
			</div>
			<span class="vcard__score">
				<b><?php echo esc_html( null === $goals_home ? '–' : $goals_home ); ?></b><span class="vcard__sep">:</span><b><?php echo esc_html( null === $goals_away ? '–' : $goals_away ); ?></b>
			</span>
			<div class="vcard__team vcard__team--away">
				<?php if ( '' !== $away_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?>
				<span class="vcard__name"><?php echo esc_html( $away_name ); ?></span>
			</div>
		</div>
	</div>
</a>
