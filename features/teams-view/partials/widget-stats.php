<?php
/**
 * Widżet „Statystyki drużyny" (.widget → .stats → .stat-row) na Profilu. READ-ONLY.
 * Renderuje CURATED JSON MVP-f zmapowany czystą funkcją hajlajty_teams_view_stat_rows()
 * + pigułki formy. „Posiadanie piłki" CELOWO pominięte (stat per-mecz, brak w
 * /teams/statistics — ground-truth MVP-f / trim #4). Pasek tylko dla „czystych
 * kont" (realny ułamek); średnie/kartki bez paska (nie zmyślamy skali).
 *
 * Wejście (get_template_part $args):
 *   - $stats array  CURATED JSON (hajlajty_teams_view_get_team_stats) albo [].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hajlajty_stats = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
$hajlajty_rows  = hajlajty_teams_view_stat_rows( $hajlajty_stats );
$hajlajty_form  = hajlajty_teams_view_form_pills( $hajlajty_stats['form'] ?? null );

// Podtytuł „N meczów" z fixtures.played (gdy liczba) — kontekst próbki statystyk.
$hajlajty_played = $hajlajty_stats['fixtures']['played'] ?? null;
$hajlajty_sub    = is_numeric( $hajlajty_played ) ? ( (int) $hajlajty_played . ' mecz.' ) : '';
?>
<div class="widget">
	<div class="widget__head">
		<h3 class="widget__title">Statystyki drużyny</h3>
		<?php if ( '' !== $hajlajty_sub ) : ?>
			<span class="widget__sub"><?php echo esc_html( $hajlajty_sub ); ?></span>
		<?php endif; ?>
	</div>

	<?php if ( empty( $hajlajty_rows ) ) : ?>
		<p class="widget__cap">Brak zaimportowanych statystyk tej drużyny. Uruchom import: <code>wp hajlajty team-stats</code>.</p>
	<?php else : ?>
		<div class="stats">
			<?php foreach ( $hajlajty_rows as $hajlajty_row ) : ?>
				<div class="stat-row">
					<div class="stat-row__top">
						<span class="stat-row__lab"><?php echo esc_html( $hajlajty_row['lab'] ); ?></span>
						<span class="stat-row__val"><?php echo esc_html( $hajlajty_row['val'] ); ?></span>
					</div>
					<?php if ( null !== $hajlajty_row['bar'] ) : ?>
						<div class="stat-bar"><span class="stat-bar__fill" style="width:<?php echo esc_attr( (int) $hajlajty_row['bar'] ); ?>%"></span></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<?php if ( ! empty( $hajlajty_form ) ) : ?>
				<div class="stat-row">
					<div class="stat-row__top">
						<span class="stat-row__lab">Forma</span>
						<span class="form-pills">
							<?php foreach ( $hajlajty_form as $hajlajty_pill ) : ?>
								<span class="form-pill <?php echo esc_attr( $hajlajty_pill['cls'] ); ?>" title="<?php echo esc_attr( $hajlajty_pill['title'] ); ?>"><?php echo esc_html( $hajlajty_pill['ch'] ); ?></span>
							<?php endforeach; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
