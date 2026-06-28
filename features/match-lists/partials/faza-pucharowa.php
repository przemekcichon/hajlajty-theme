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

// Układ DWUSTRONNY (Mundial): górna połowa drabinki w lewo, dolna w prawo, Finał na
// środku, mecz o 3. miejsce pod Finałem (osobno). Czysta funkcja (tests/bracket.php).
$bracket_sided = hajlajty_bracket_split( $bracket_columns );

// Renderer jednej komórki przez współdzielony sub-partial (JEDNO źródło markupu kom.
// — w układzie dwustronnym komórki renderują się w lewej, środkowej i prawej części).
$bracket_render_cell = static function ( $cell, $col, $with_feeders = true ) use ( $bracket_real_by_no, $bracket_resolved ) {
	get_template_part(
		'features/match-lists/partials/bracket-cell',
		null,
		array(
			'cell'         => $cell,
			'real_by_no'   => $bracket_real_by_no,
			'resolved'     => $bracket_resolved,
			'col'          => $col,
			'with_feeders' => $with_feeders,
		)
	);
};

// Renderer jednej kolumny rundy (nagłówek + komórki). $col_idx = globalny indeks
// kolumny lewo→prawo (graf linii w bracket.js).
$bracket_render_col = static function ( $round, $cells, $col_idx ) use ( $bracket_render_cell ) {
	$round_pl = hajlajty_lookup_round( $round );
	?>
	<section class="bracket__col" data-round="<?php echo esc_attr( $round ); ?>">
		<h2 class="bracket__round"><?php echo esc_html( '' !== $round_pl ? $round_pl : $round ); ?></h2>
		<div class="bracket__cells">
			<?php foreach ( $cells as $bracket_c ) { $bracket_render_cell( $bracket_c, $col_idx ); } ?>
		</div>
	</section>
	<?php
};
?>
<div class="page-head">
	<span class="page-head__eyebrow"><span class="dot"></span> Mundial 2026 · Kanada · Meksyk · USA</span>
	<h1 class="page-head__title">Faza pucharowa</h1>
	<p class="page-head__sub">Drabinka pucharowa od 1/16 finału po finał. Rozegrane i zaplanowane mecze pojawiają się automatycznie z importu; sloty bez ustalonej obsady czekają jako „do ustalenia".</p>
	<div class="legend">
		<span class="legend__item"><span class="legend__dot skrot"></span> Mecz z terminarza (klikalny)</span>
		<span class="legend__item"><span class="legend__dot soon"></span> Obsada do ustalenia (?)</span>
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
		<?php
		// LEWA strona: R32→SF (górne połowy), kolumny 0..n.
		$bracket_col_i = 0;
		foreach ( $bracket_sided['left'] as $bracket_lc ) {
			$bracket_render_col( $bracket_lc['round'], $bracket_lc['cells'], $bracket_col_i );
			$bracket_col_i++;
		}

		// ŚRODEK: Finał, a pod nim (NIEpołączony) mecz o 3. miejsce. Jedna kolumna.
		$bracket_center_i    = $bracket_col_i;
		$bracket_round_final = hajlajty_lookup_round( 'Final' );
		$bracket_round_third = hajlajty_lookup_round( '3rd Place Final' );
		?>
		<section class="bracket__col bracket__col--center" data-round="Final">
			<div class="bracket__group">
				<h2 class="bracket__round"><?php echo esc_html( '' !== $bracket_round_final ? $bracket_round_final : 'Final' ); ?></h2>
				<div class="bracket__cells">
					<?php foreach ( $bracket_sided['center']['final'] as $bracket_c ) { $bracket_render_cell( $bracket_c, $bracket_center_i, true ); } ?>
				</div>
			</div>
			<?php if ( ! empty( $bracket_sided['center']['third'] ) ) : ?>
				<div class="bracket__group bracket__group--third">
					<h2 class="bracket__round"><?php echo esc_html( '' !== $bracket_round_third ? $bracket_round_third : '3rd Place Final' ); ?></h2>
					<div class="bracket__cells">
						<?php foreach ( $bracket_sided['center']['third'] as $bracket_c ) { $bracket_render_cell( $bracket_c, $bracket_center_i, false ); } ?>
					</div>
				</div>
			<?php endif; ?>
		</section>
		<?php
		// PRAWA strona: SF→R32 (dolne połowy), od środka na zewnątrz.
		$bracket_col_i = $bracket_center_i + 1;
		foreach ( $bracket_sided['right'] as $bracket_rc ) {
			$bracket_render_col( $bracket_rc['round'], $bracket_rc['cells'], $bracket_col_i );
			$bracket_col_i++;
		}
		?>
	</div>
</div>
