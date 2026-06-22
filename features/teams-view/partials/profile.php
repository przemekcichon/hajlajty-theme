<?php
/**
 * Partial: Profil kraju (reprezentacji). READ-ONLY. Delegowany z root
 * `taxonomy-druzyna.php` (archiwum termu „druzyna"). Sam ustala kontekst
 * (queried term), czyta dane przez warstwę slice'a i renderuje markup z designu
 * (design/Hajlajty - Profil Belgia.html): hero + układ 2-kolumnowy (mecze | widżety).
 *
 * REUŻYCIE (nie duplikujemy): karty meczów = partiale match-lists (card-zapowiedz/
 * card-skrot/card-wynik) + batch-resolver drużyn; flaga = hajlajty_flag_url;
 * lookupy/round = match-display. Widżety (tabela grupy, statystyki) = partiale tego
 * slice'a.
 *
 * TRIM (design ma, dane NIE pokrywają — #4, NIE fabrykujemy):
 *  - `.squad-block` (kadra 26 imienna) — MVP-f nie daje kadry (lineups = formacje
 *    konkretnego meczu, nie oficjalna kadra). Cała sekcja POMINIĘTA.
 *  - chip „26 zawodników" — brak liczby kadry. hero-follow / „Obserwuj" — Faza 4.
 *  - wiersz „Posiadanie piłki" w statystykach — stat per-mecz, brak w MVP-f.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hajlajty_term = get_queried_object();
if ( ! ( $hajlajty_term instanceof WP_Term ) ) {
	return;
}

$hajlajty_api  = hajlajty_teams_view_term_api_id( $hajlajty_term->term_id );
$hajlajty_flag = hajlajty_flag_url( $hajlajty_term );
$hajlajty_fifa = strtoupper( (string) get_term_meta( $hajlajty_term->term_id, 'fifa_code', true ) );

// Tabela grup + grupa/ranga tej drużyny (jedno źródło dla seeda i widżetu tabeli).
$hajlajty_standings = hajlajty_teams_view_find_standings();
$hajlajty_group     = hajlajty_teams_view_find_team_group( $hajlajty_api, $hajlajty_standings );
$hajlajty_seed      = $hajlajty_group ? hajlajty_teams_view_seed_label( $hajlajty_group['letter'], $hajlajty_group['rank'] ) : '';

// Statystyki drużyny (MVP-f).
$hajlajty_stats = hajlajty_teams_view_get_team_stats( $hajlajty_term->term_id );

// Mecze drużyny: na żywo (status) + nadchodzące (ASC) + ostatnie (DESC). Mecz live
// ma kickoff w przeszłości, więc 'recent' też by go zwrócił — ODEJMUJEMY live od
// recent, by nie pokazać go dwa razy (i jako wynik). Selekcjoner best-effort z live
// (gra teraz) → potem z ostatnich. Zero N+1: jeden batch termów na wszystkie listy.
$hajlajty_live     = hajlajty_teams_view_match_ids( $hajlajty_term->term_id, 'live', 6 );
$hajlajty_upcoming = hajlajty_teams_view_match_ids( $hajlajty_term->term_id, 'upcoming', 6 );
$hajlajty_recent   = hajlajty_teams_view_match_ids( $hajlajty_term->term_id, 'recent', 6 );
$hajlajty_recent   = array_values( array_diff( $hajlajty_recent, $hajlajty_live ) );
$hajlajty_coach    = hajlajty_teams_view_coach_name( array_merge( $hajlajty_live, $hajlajty_recent ), $hajlajty_api );

$hajlajty_all_ids  = array_values( array_unique( array_merge( $hajlajty_live, $hajlajty_upcoming, $hajlajty_recent ) ) );
$hajlajty_resolved = ! empty( $hajlajty_all_ids ) ? hajlajty_match_lists_resolve_terms( $hajlajty_all_ids ) : array();

$hajlajty_empty_terms = array(
	'home' => null,
	'away' => null,
);

// Eyebrow: „Reprezentacja" + nazwa rozgrywek (gdy znamy z tabeli grup).
$hajlajty_eyebrow = 'Reprezentacja';
if ( $hajlajty_group && $hajlajty_group['rozgrywki'] instanceof WP_Term ) {
	$hajlajty_eyebrow .= ' · ' . $hajlajty_group['rozgrywki']->name;
}
?>
<main class="container">

	<!-- ===== HERO ===== -->
	<section class="profile-hero">
		<div class="profile-hero__inner">
			<?php if ( '' !== $hajlajty_flag ) : ?>
				<img class="profile-hero__flag" src="<?php echo esc_url( $hajlajty_flag ); ?>" alt="<?php echo esc_attr( 'Flaga: ' . $hajlajty_term->name ); ?>" />
			<?php endif; ?>
			<div class="profile-hero__id">
				<span class="profile-hero__eyebrow"><span class="dot"></span> <?php echo esc_html( $hajlajty_eyebrow ); ?></span>
				<h1 class="profile-hero__name"><?php echo esc_html( $hajlajty_term->name ); ?></h1>
				<?php if ( '' !== $hajlajty_coach ) : ?>
					<span class="profile-hero__coach">Selekcjoner: <b><?php echo esc_html( $hajlajty_coach ); ?></b></span>
				<?php endif; ?>
				<div class="hero-meta">
					<?php if ( $hajlajty_group && '' !== $hajlajty_group['letter'] ) : ?>
						<span class="hero-chip"><?php echo esc_html( 'Grupa ' . $hajlajty_group['letter'] ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $hajlajty_fifa ) : ?>
						<span class="hero-chip"><?php echo esc_html( 'Kod FIFA: ' . $hajlajty_fifa ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<!-- ===== UKŁAD DWUKOLUMNOWY ===== -->
	<div class="profile-grid">

		<!-- KOLUMNA LEWA: mecze -->
		<div class="profile-col profile-col--main">
			<section class="section">
				<div class="section__head">
					<h2 class="section__title"><span class="kicker-dot"></span> Mecze reprezentacji</h2>
				</div>

				<?php if ( ! empty( $hajlajty_live ) ) : ?>
					<h3 class="subhead"><span class="subhead__dot live"></span> Na żywo</h3>
					<div class="grid-videos">
						<?php
						foreach ( $hajlajty_live as $hajlajty_pid ) :
							get_template_part(
								'features/match-lists/partials/card-live',
								null,
								array(
									'post_id' => (int) $hajlajty_pid,
									'terms'   => $hajlajty_resolved[ $hajlajty_pid ] ?? $hajlajty_empty_terms,
								)
							);
						endforeach;
						?>
					</div>
				<?php endif; ?>

				<h3 class="subhead<?php echo empty( $hajlajty_live ) ? '' : ' subhead--mt'; ?>"><span class="subhead__dot soon"></span> Nadchodzące</h3>
				<?php if ( empty( $hajlajty_upcoming ) ) : ?>
					<p class="matches-empty">Brak zaplanowanych meczów dla tej reprezentacji.</p>
				<?php else : ?>
					<div class="grid-videos">
						<?php
						foreach ( $hajlajty_upcoming as $hajlajty_pid ) :
							get_template_part(
								'features/match-lists/partials/card-zapowiedz',
								null,
								array(
									'post_id' => (int) $hajlajty_pid,
									'terms'   => $hajlajty_resolved[ $hajlajty_pid ] ?? $hajlajty_empty_terms,
								)
							);
						endforeach;
						?>
					</div>
				<?php endif; ?>

				<h3 class="subhead subhead--mt"><span class="subhead__dot"></span> Ostatnie mecze</h3>
				<?php if ( empty( $hajlajty_recent ) ) : ?>
					<p class="matches-empty">Brak rozegranych meczów dla tej reprezentacji.</p>
				<?php else : ?>
					<div class="grid-videos">
						<?php
						foreach ( $hajlajty_recent as $hajlajty_pid ) :
							$hajlajty_pid = (int) $hajlajty_pid;
							// „Ma wideo" = niepuste skrot_url (decyzja #9) → karta skrótu; inaczej karta wyniku.
							// EDGE (świadomie akceptowany): „ostatnie" = kickoff w przeszłości. Mecz
							// przełożony/w toku (status ≠ FT) z przeszłym kickoffem trafi w card-wynik,
							// która degraduje go do „zakończony bez wyniku" (— : —) — nie wywraca układu,
							// może chwilowo zmylić etykietą. Pełne rozróżnienie stanów na profilu to
							// przyszły krok (#8: nie dorabiamy gałęzi LIVE/NS na zapas).
							$hajlajty_skrot = function_exists( 'get_field' )
								? get_field( 'skrot_url', $hajlajty_pid )
								: get_post_meta( $hajlajty_pid, 'skrot_url', true );
							$hajlajty_card  = ( is_string( $hajlajty_skrot ) && '' !== $hajlajty_skrot ) ? 'card-skrot' : 'card-wynik';
							get_template_part(
								'features/match-lists/partials/' . $hajlajty_card,
								null,
								array(
									'post_id' => $hajlajty_pid,
									'terms'   => $hajlajty_resolved[ $hajlajty_pid ] ?? $hajlajty_empty_terms,
								)
							);
						endforeach;
						?>
					</div>
				<?php endif; ?>
			</section>

			<?php
			/*
			 * TRIM: sekcja „Kadra na Mundial 2026" (.squad / .squad-block) POMINIĘTA —
			 * MVP-f nie dostarcza imiennej kadry 26 (ground-truth). Render nie zgaduje
			 * zawodników; sekcja wróci, gdy powstanie źródło kadry.
			 */
			?>
		</div>

		<!-- KOLUMNA PRAWA: widżety -->
		<aside class="profile-col profile-col--side">
			<?php
			if ( $hajlajty_group ) {
				get_template_part(
					'features/teams-view/partials/widget-standings',
					null,
					array(
						'group'  => $hajlajty_group,
						'api_id' => $hajlajty_api,
					)
				);
			}
			get_template_part(
				'features/teams-view/partials/widget-stats',
				null,
				array( 'stats' => $hajlajty_stats )
			);
			?>
		</aside>

	</div>

</main>
