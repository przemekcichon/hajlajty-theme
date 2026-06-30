<?php
/**
 * Karta SKRÓTU „rail" (.rvideo) — POZIOMA, sidebarowa odmiana karty skrótu
 * (miniatura po lewej + meta/wynik po prawej). Przeznaczona WYŁĄCZNIE do wąskich
 * kolumn aside („Inne skróty", docelowo „Polecane dla Ciebie") — P-c.
 *
 * Współistnieje z pionową `.vcard` (`card-skrot.php`): tamta zostaje kartą
 * list/gridów/home (szeroki kontekst), TA jest kartą sidebara. Cała karta linkuje
 * do single meczu (NIE osadza playera).
 *
 * Kontrakt danych = IDENTYCZNY jak `card-skrot.php` (świadoma drobna duplikacja
 * resolucji; dwóch konsumentów, więc bez wyciągania helpera „na zapas" — CLAUDE.md
 * #8). Różnica jest WYŁĄCZNIE w markupie (poziomy `.rvideo`) i przygaszeniu
 * przegranej (`.rvideo__row--lose`). Partial dostaje z ZEWNĄTRZ $post_id ORAZ
 * rozwiązane termy {home,away} (batch-resolver → zero N+1).
 *
 * Zmienne wejściowe (z get_template_part $args):
 *   - $post_id int
 *   - $terms   array{home:?WP_Term,away:?WP_Term,...} (z hajlajty_match_lists_resolve_terms)
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

// Miniatura/duration ze skrótu (ACF gdy aktywne; fallback na surowe meta) — jak card-skrot.
$skrot_url = function_exists( 'get_field' ) ? get_field( 'skrot_url', $post_id ) : get_post_meta( $post_id, 'skrot_url', true );
$skrot_dur = function_exists( 'get_field' ) ? get_field( 'skrot_duration', $post_id ) : get_post_meta( $post_id, 'skrot_duration', true );
$yt_id     = hajlajty_youtube_id( is_string( $skrot_url ) ? $skrot_url : '' );
$poster    = '' !== $yt_id ? 'https://i.ytimg.com/vi/' . $yt_id . '/hqdefault.jpg' : '';

// Meta: rozgrywki (lewa) + faza/runda (środek) + „skrót". Bez kanału (design -2
// nie pokazuje go w wierszu rail; źródło skrótu zostaje na szerokiej `.vcard`).
$roz_terms = get_the_terms( $post_id, 'rozgrywki' );
$roz_name  = ( is_array( $roz_terms ) && ! is_wp_error( $roz_terms ) && ! empty( $roz_terms ) ) ? $roz_terms[0]->name : '';
$round_pl  = hajlajty_lookup_round( $data['round'] ?? null );

// Blok meczowy: flagi + pełne nazwy + wynik. Drużyny z $terms, wynik z match_data.goals.
$home_flag  = hajlajty_flag_url( $terms['home'] );
$away_flag  = hajlajty_flag_url( $terms['away'] );
$home_name  = hajlajty_match_lists_team_name( $terms['home'] );
$away_name  = hajlajty_match_lists_team_name( $terms['away'] );
$goals_home = $data['goals']['home'] ?? null;
$goals_away = $data['goals']['away'] ?? null;

// Przygaszenie przegranej: tylko gdy ZNAMY oba wyniki i jeden jest ściśle mniejszy
// (remis i brak/niepełny wynik → bez przygaszenia). Decyzja P-c.
$has_result = ( null !== $goals_home && null !== $goals_away );
$home_lose  = $has_result && ( (int) $goals_home < (int) $goals_away );
$away_lose  = $has_result && ( (int) $goals_away < (int) $goals_home );

// Segmenty meta złączone kropką (.dot-sep) — tylko obecne. „skrót" zawsze na końcu.
$meta_segments = array();
if ( '' !== $roz_name ) {
	$meta_segments[] = '<span class="rvideo__comp">' . esc_html( $roz_name ) . '</span>';
}
if ( '' !== $round_pl ) {
	$meta_segments[] = esc_html( $round_pl );
}
$meta_segments[] = 'skrót';
?>
<a class="rvideo card-video" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
	<div class="thumb">
		<?php if ( '' !== $poster ) : ?>
			<img class="thumb__img" src="<?php echo esc_url( $poster ); ?>" alt="" loading="lazy" />
		<?php endif; ?>
		<?php if ( ! empty( $skrot_dur ) ) : ?>
			<span class="thumb__dur"><?php echo esc_html( $skrot_dur ); ?></span>
		<?php endif; ?>
		<span class="thumb__play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
	</div>
	<div class="rvideo__body">
		<div class="rvideo__meta"><?php echo implode( '<span class="dot-sep"></span>', $meta_segments ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — segmenty zescape'owane wyżej. ?></div>
		<div class="rvideo__match">
			<div class="rvideo__row<?php echo $home_lose ? ' rvideo__row--lose' : ''; ?>">
				<span class="rvideo__team"><?php if ( '' !== $home_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?><span class="rvideo__tname"><?php echo esc_html( $home_name ); ?></span></span>
				<span class="rvideo__num"><?php echo esc_html( null === $goals_home ? '–' : $goals_home ); ?></span>
			</div>
			<div class="rvideo__row<?php echo $away_lose ? ' rvideo__row--lose' : ''; ?>">
				<span class="rvideo__team"><?php if ( '' !== $away_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?><span class="rvideo__tname"><?php echo esc_html( $away_name ); ?></span></span>
				<span class="rvideo__num"><?php echo esc_html( null === $goals_away ? '–' : $goals_away ); ?></span>
			</div>
		</div>
	</div>
</a>
