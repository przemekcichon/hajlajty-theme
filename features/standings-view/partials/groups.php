<?php
/**
 * Partial: render 12 kart grup A–L (wariant GRUPOWY tabeli rozgrywek). READ-ONLY.
 * Delegowany z szablonu `template-tabela-rozgrywek.php` (get_template_part) —
 * sam ustala kontekst (Strona → meta `league_id`+`season`), czyta dane przez
 * warstwę slice'a i renderuje markup z designu (design/Hajlajty - Tabele Grup.html).
 *
 * Markup rdzenia: `.groups-grid` → `.group-card[data-group][data-label][data-teams]`
 * → `.group-card__head` → `<table class="standings">`. Strefy `.qual`/`.play`/brak
 * WYŁĄCZNIE po `rank` (zones.php). Kolumna `diff` z danych NIE jest renderowana
 * (design nie ma kolumny). Bez klas warstwy JS (.reveal/.is-focusing/.is-target).
 *
 * Escaping: esc_html (liczby/nazwy), esc_url (flaga), esc_attr (data-*). Liczby
 * nullable wg kontraktu MVP-d → fallback „–".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hajlajty_page_id   = get_queried_object_id();
$hajlajty_league_id = (int) get_post_meta( $hajlajty_page_id, 'league_id', true );
$hajlajty_season    = (string) get_post_meta( $hajlajty_page_id, 'season', true );

$hajlajty_term_id   = hajlajty_standings_view_find_league_term( $hajlajty_league_id );
$hajlajty_term      = $hajlajty_term_id ? get_term( $hajlajty_term_id, 'rozgrywki' ) : null;
$hajlajty_eyebrow   = ( $hajlajty_term instanceof WP_Term ) ? $hajlajty_term->name : '';

$hajlajty_table = hajlajty_get_standings( $hajlajty_term_id, $hajlajty_season );

// Pusty stan — przyjazna podpowiedź zamiast białego ekranu / warninga (READ-ONLY,
// charakter edukacyjny). Rozróżniamy POWÓD, by redaktor wiedział, co zrobić.
$hajlajty_notice = '';
if ( '' === $hajlajty_season || $hajlajty_league_id <= 0 ) {
	$hajlajty_notice = 'Ustaw ligę (league_id) i sezon (season) w polach tej strony.';
} elseif ( ! $hajlajty_term_id ) {
	$hajlajty_notice = sprintf( 'Nie znaleziono rozgrywek dla league_id %d. Sprawdź term meta „league_id" taksonomii „rozgrywki".', $hajlajty_league_id );
} elseif ( empty( $hajlajty_table ) ) {
	$hajlajty_notice = sprintf( 'Brak zaimportowanej tabeli dla sezonu %s. Uruchom import: wp hajlajty standings --league=%d --season=%s.', $hajlajty_season, $hajlajty_league_id, preg_replace( '/\D/', '', $hajlajty_season ) );
}

// Batch-resolucja drużyn JEDNYM zapytaniem (bez N+1) — wszystkie team_id z tabeli.
$hajlajty_team_ids = array();
foreach ( $hajlajty_table as $hajlajty_rows ) {
	foreach ( $hajlajty_rows as $hajlajty_row ) {
		if ( isset( $hajlajty_row['team_id'] ) ) {
			$hajlajty_team_ids[] = (int) $hajlajty_row['team_id'];
		}
	}
}
$hajlajty_teams = hajlajty_standings_resolve_teams( $hajlajty_team_ids );
?>
<div class="page-head">
	<?php if ( '' !== $hajlajty_eyebrow ) : ?>
		<span class="page-head__eyebrow"><span class="dot"></span> <?php echo esc_html( $hajlajty_eyebrow ); ?></span>
	<?php endif; ?>
	<h1 class="page-head__title"><?php echo esc_html( get_the_title( $hajlajty_page_id ) ); ?></h1>
	<div class="legend">
		<span class="legend__item"><span class="legend__dot qual"></span> Miejsca 1–2 · awans</span>
		<span class="legend__item"><span class="legend__dot play"></span> Miejsce 3 · najlepsze trzecie</span>
		<span class="legend__item"><span class="legend__dot out"></span> Miejsce 4 · odpada</span>
	</div>
</div>

<?php if ( '' !== $hajlajty_notice ) : ?>
	<p class="standings-empty"><?php echo esc_html( $hajlajty_notice ); ?></p>
<?php else : ?>
	<div class="groups-grid" data-filterable>
		<?php foreach ( $hajlajty_table as $hajlajty_letter => $hajlajty_rows ) : ?>
			<?php
			// JEDNA pętla po wierszach grupy buduje widok-modele (nazwa, flaga, kod
			// FIFA, strefa) — wspólne dla agregatów karty (data-teams/data-team-names)
			// i dla tbody. Dzięki temu term meta `fifa_code` i flagę czytamy RAZ na
			// drużynę (nie dwa razy). Kontrakt filtra (slice filters / filters.js):
			// karta niesie `data-teams` (FIFA → chipy) + `data-team-names`
			// (znormalizowane PL → tekst), jak karty list (match-lists/terms.php).
			$hajlajty_vm    = array();
			$hajlajty_codes = array();
			$hajlajty_names = array();
			foreach ( $hajlajty_rows as $hajlajty_row ) {
				$hajlajty_term_row = $hajlajty_teams[ (int) ( $hajlajty_row['team_id'] ?? 0 ) ] ?? null;
				$hajlajty_is_term  = ( $hajlajty_term_row instanceof WP_Term );
				$hajlajty_code     = $hajlajty_is_term ? strtoupper( (string) get_term_meta( $hajlajty_term_row->term_id, 'fifa_code', true ) ) : '';

				if ( '' !== $hajlajty_code ) {
					$hajlajty_codes[] = $hajlajty_code;
				}
				if ( $hajlajty_is_term && function_exists( 'hajlajty_filters_normalize_pl' ) ) {
					$hajlajty_names[] = hajlajty_filters_normalize_pl( $hajlajty_term_row->name );
				}

				$hajlajty_vm[] = array(
					'row'  => $hajlajty_row,
					'name' => $hajlajty_is_term ? $hajlajty_term_row->name : ( '#' . (int) ( $hajlajty_row['team_id'] ?? 0 ) ),
					'flag' => $hajlajty_is_term ? hajlajty_flag_url( $hajlajty_term_row ) : '',
					'zone' => hajlajty_standings_zone_class( $hajlajty_row['rank'] ?? 0 ),
				);
			}
			?>
			<article class="group-card" data-group="<?php echo esc_attr( $hajlajty_letter ); ?>" data-label="<?php echo esc_attr( 'Grupa ' . $hajlajty_letter ); ?>" data-teams="<?php echo esc_attr( implode( ' ', $hajlajty_codes ) ); ?>" data-team-names="<?php echo esc_attr( implode( ' ', $hajlajty_names ) ); ?>">
				<div class="group-card__head">
					<span class="group-badge"><?php echo esc_html( $hajlajty_letter ); ?></span>
					<span class="group-card__title"><?php echo esc_html( 'Grupa ' . $hajlajty_letter ); ?></span>
					<span class="group-card__meta">Rozegrane <?php echo esc_html( hajlajty_standings_played_label( $hajlajty_rows ) ); ?></span>
				</div>
				<table class="standings">
					<thead>
						<tr>
							<th class="pos" title="Pozycja">#</th>
							<th class="team">Drużyna</th>
							<th title="Mecze rozegrane">M</th>
							<th title="Zwycięstwa">Z</th>
							<th title="Remisy">R</th>
							<th title="Porażki">P</th>
							<th title="Bramki zdobyte i stracone">Br.</th>
							<th title="Punkty">Pkt</th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $hajlajty_vm as $hajlajty_item ) :
							$hajlajty_row = $hajlajty_item['row'];
							?>
							<tr<?php echo '' !== $hajlajty_item['zone'] ? ' class="' . esc_attr( $hajlajty_item['zone'] ) . '"' : ''; ?>>
								<td class="pos"><?php echo esc_html( $hajlajty_row['rank'] ?? '–' ); ?></td>
								<td class="team">
									<span class="std-team">
										<?php if ( '' !== $hajlajty_item['flag'] ) : ?>
											<img class="country-flag" src="<?php echo esc_url( $hajlajty_item['flag'] ); ?>" alt="<?php echo esc_attr( $hajlajty_item['name'] ); ?>">
										<?php endif; ?>
										<span class="nm"><?php echo esc_html( $hajlajty_item['name'] ); ?></span>
									</span>
								</td>
								<td><?php echo esc_html( $hajlajty_row['played'] ?? '–' ); ?></td>
								<td><?php echo esc_html( $hajlajty_row['win'] ?? '–' ); ?></td>
								<td><?php echo esc_html( $hajlajty_row['draw'] ?? '–' ); ?></td>
								<td><?php echo esc_html( $hajlajty_row['lose'] ?? '–' ); ?></td>
								<td class="gf"><?php echo esc_html( ( $hajlajty_row['gf'] ?? '–' ) . ':' . ( $hajlajty_row['ga'] ?? '–' ) ); ?></td>
								<td class="pts"><?php echo esc_html( $hajlajty_row['points'] ?? '–' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
