<?php
/**
 * Widżet „Tabela · Grupa X" (.widget → .gtable) na Profilu. READ-ONLY. Renderuje
 * grupę MVP-d, w której gra ta drużyna, z wierszem drużyny podświetlonym
 * (.is-target). Strefy `.qual`/`.play` WYŁĄCZNIE po `rank` (zones.php, MVP-e) —
 * NIE po stringu `zone` (różni się per turniej; ground-truth / pamięć).
 *
 * Drużyny w wierszach resolwowane po `api_id` (≤4 termy, batch) — NIGDY po term_id.
 *
 * Wejście (get_template_part $args):
 *   - $group  array  Wynik hajlajty_teams_view_find_team_group (letter/rank/rows/…).
 *   - $api_id int    api_id PROFILOWEJ drużyny (do .is-target).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hajlajty_group = isset( $args['group'] ) && is_array( $args['group'] ) ? $args['group'] : array();
$hajlajty_api   = isset( $args['api_id'] ) ? (int) $args['api_id'] : 0;
if ( empty( $hajlajty_group['rows'] ) || empty( $hajlajty_group['letter'] ) ) {
	return; // Bez grupy nie ma czego renderować — caller decyduje o całym widżecie.
}

$hajlajty_rows   = $hajlajty_group['rows'];
$hajlajty_letter = (string) $hajlajty_group['letter'];

// Batch-resolucja drużyn grupy po api_id (zero N+1).
$hajlajty_ids = array();
foreach ( $hajlajty_rows as $hajlajty_row ) {
	$hajlajty_ids[] = (int) ( $hajlajty_row['team_id'] ?? 0 );
}
$hajlajty_teams = hajlajty_teams_view_resolve_by_api( $hajlajty_ids );
?>
<div class="widget">
	<div class="widget__head">
		<h3 class="widget__title">Tabela · Grupa <?php echo esc_html( $hajlajty_letter ); ?></h3>
		<a class="widget__link" href="<?php echo esc_url( home_url( '/tabele-grup/' ) ); ?>">Pełna <svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg></a>
	</div>
	<table class="gtable">
		<thead>
			<tr>
				<th class="pos">#</th>
				<th class="team">Drużyna</th>
				<th title="Mecze rozegrane">M</th>
				<th title="Bramki">Br.</th>
				<th title="Punkty">Pkt</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $hajlajty_rows as $hajlajty_row ) :
				$hajlajty_tid    = (int) ( $hajlajty_row['team_id'] ?? 0 );
				$hajlajty_term   = $hajlajty_teams[ $hajlajty_tid ] ?? null;
				$hajlajty_is_t   = ( $hajlajty_term instanceof WP_Term );
				$hajlajty_name   = $hajlajty_is_t ? $hajlajty_term->name : ( '#' . $hajlajty_tid );
				$hajlajty_flag   = $hajlajty_is_t ? hajlajty_flag_url( $hajlajty_term ) : '';
				$hajlajty_zone   = hajlajty_standings_zone_class( $hajlajty_row['rank'] ?? 0 );
				$hajlajty_target = ( $hajlajty_tid === $hajlajty_api );
				$hajlajty_cls    = trim( $hajlajty_zone . ( $hajlajty_target ? ' is-target' : '' ) );
				?>
				<tr<?php echo '' !== $hajlajty_cls ? ' class="' . esc_attr( $hajlajty_cls ) . '"' : ''; ?>>
					<td class="pos"><?php echo esc_html( $hajlajty_row['rank'] ?? '–' ); ?></td>
					<td class="team">
						<span class="gt-team">
							<?php if ( '' !== $hajlajty_flag ) : ?>
								<img class="country-flag" src="<?php echo esc_url( $hajlajty_flag ); ?>" alt="<?php echo esc_attr( $hajlajty_name ); ?>">
							<?php endif; ?>
							<span class="nm"><?php echo esc_html( $hajlajty_name ); ?></span>
						</span>
					</td>
					<td><?php echo esc_html( $hajlajty_row['played'] ?? '–' ); ?></td>
					<td class="gf"><?php echo esc_html( ( $hajlajty_row['gf'] ?? '–' ) . ':' . ( $hajlajty_row['ga'] ?? '–' ) ); ?></td>
					<td class="pts"><?php echo esc_html( $hajlajty_row['points'] ?? '–' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div class="gt-zones">
		<span class="gt-zone"><span class="gt-zone__dot qual"></span> 1–2 · awans</span>
		<span class="gt-zone"><span class="gt-zone__dot play"></span> 3 · najlepsze trzecie</span>
	</div>
</div>
