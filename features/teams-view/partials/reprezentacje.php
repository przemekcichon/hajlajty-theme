<?php
/**
 * Partial: lista Reprezentacje. READ-ONLY. Delegowany z root
 * `template-reprezentacje.php`. Grupuje drużyny po grupach A–L wg tabeli MVP-d
 * (team_section per grupa, karta per drużyna z „seedem" = litera+ranga). Gdy tabela
 * jeszcze nie zaimportowana → tryb FALLBACK: płaska siatka wszystkich termów
 * „druzyna" (bez grup/seedów). Markup z design/Hajlajty - Reprezentacje.html.
 *
 * Łączenie drużyn po `api_id` (term meta), NIGDY po term_id (ground-truth: w
 * standings `team_id` to api_id, nie term_id).
 *
 * TRIM (#4): selekcjoner na kartach listy POMINIĘTY — wymagałby odczytu meczu per
 * drużyna (N+1 dla ~48 reprezentacji). Selekcjoner pokazuje Profil (jeden mecz).
 * Przycisk „Obserwuj" (.team-fav) pominięty — Faza 4 (konto, hajlajty-user).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hajlajty_page_id   = get_queried_object_id();
$hajlajty_standings = hajlajty_teams_view_find_standings();
?>
<main class="container">
	<div class="page-head">
		<span class="page-head__eyebrow"><span class="dot"></span> Mundial 2026</span>
		<h1 class="page-head__title"><?php echo esc_html( get_the_title( $hajlajty_page_id ) ); ?></h1>
	</div>

	<?php if ( null === $hajlajty_standings || empty( $hajlajty_standings['table'] ) ) : ?>
		<?php
		// FALLBACK: brak tabeli → płaska siatka wszystkich reprezentacji (bez grup/seedów).
		$hajlajty_terms = hajlajty_teams_view_all_druzyna_terms();
		?>
		<?php if ( empty( $hajlajty_terms ) ) : ?>
			<p class="matches-empty">Brak reprezentacji. Zaseeduj roster: <code>wp hajlajty seed</code>.</p>
		<?php else : ?>
			<section class="team-section">
				<div class="team-section__head">
					<h2 class="team-section__title">Wszystkie reprezentacje</h2>
					<span class="team-section__meta"><?php echo esc_html( count( $hajlajty_terms ) . ' reprezentacji' ); ?></span>
				</div>
				<div class="teams-grid">
					<?php foreach ( $hajlajty_terms as $hajlajty_t ) : ?>
						<?php hajlajty_teams_view_render_team_card( $hajlajty_t, '', '' ); ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

	<?php else : ?>
		<?php
		// Grupowanie A–L wg tabeli MVP-d. Batch-resolucja WSZYSTKICH drużyn po api_id
		// (jeden get_terms na całą listę — zero N+1).
		$hajlajty_table = $hajlajty_standings['table'];
		ksort( $hajlajty_table ); // A…L niezależnie od kolejności kluczy.

		$hajlajty_all_ids = array();
		foreach ( $hajlajty_table as $hajlajty_rows ) {
			foreach ( (array) $hajlajty_rows as $hajlajty_row ) {
				$hajlajty_all_ids[] = (int) ( $hajlajty_row['team_id'] ?? 0 );
			}
		}
		$hajlajty_teams = hajlajty_teams_view_resolve_by_api( $hajlajty_all_ids );

		foreach ( $hajlajty_table as $hajlajty_letter => $hajlajty_rows ) :
			$hajlajty_rows   = (array) $hajlajty_rows;
			$hajlajty_letter = (string) $hajlajty_letter;
			?>
			<section class="team-section" data-group="<?php echo esc_attr( $hajlajty_letter ); ?>">
				<div class="team-section__head">
					<h2 class="team-section__title"><span class="group-badge"><?php echo esc_html( $hajlajty_letter ); ?></span> <?php echo esc_html( 'Grupa ' . $hajlajty_letter ); ?></h2>
					<span class="team-section__meta"><?php echo esc_html( count( $hajlajty_rows ) . ' reprezentacje' ); ?></span>
				</div>
				<div class="teams-grid">
					<?php
					foreach ( $hajlajty_rows as $hajlajty_row ) :
						$hajlajty_tid  = (int) ( $hajlajty_row['team_id'] ?? 0 );
						$hajlajty_term = $hajlajty_teams[ $hajlajty_tid ] ?? null;
						if ( ! ( $hajlajty_term instanceof WP_Term ) ) {
							continue; // drużyna spoza rostera (brak termu) → nie ma profilu do podlinkowania.
						}
						$hajlajty_seed = hajlajty_teams_view_seed_label( $hajlajty_letter, $hajlajty_row['rank'] ?? null );
						hajlajty_teams_view_render_team_card( $hajlajty_term, $hajlajty_seed, $hajlajty_letter );
					endforeach;
					?>
				</div>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</main>
