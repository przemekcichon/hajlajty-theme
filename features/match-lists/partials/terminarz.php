<?php
/**
 * Treść strony „Terminarz turnieju" (MVP-c) — WSZYSTKIE mecze turnieju w jednym
 * chronologicznym ciągu, pogrupowane po DNIU. READ-ONLY z importu, zero nowego
 * źródła. Wołane przez root-template `template-terminarz.php` (WP wykrywa szablony
 * tylko w roocie — patrz hajlajty_match_lists_is_terminarz()); logika listy żyje
 * tu, w slice match-lists (vertical slice).
 *
 * Reużycie bez duplikacji renderu:
 *  - jeden `WP_Query` na całą listę (NIE pre_get_posts — to inny zbiór niż archiwa),
 *  - JEDEN batch `hajlajty_match_lists_resolve_terms()` na komplet postów (zero N+1),
 *  - karta per stan przez `hajlajty_lookup_status()` (LIVE/ZAPOWIEDZ/ZAKONCZONY/
 *    ODWOLANY) — istniejące partiale + nowa card-wynik dla FT-bez-wideo i odwołanych.
 *
 * Grupowanie (decyzja MVP-c #2): klucz dnia = `substr(kickoff,0,10)` z PŁASKIEJ
 * meta `kickoff` (UTC). Nagłówek dnia formatujemy też w UTC (wp_date z tz UTC),
 * żeby etykieta == klucz grupy (mecz nie wpada pod nagłówek innego dnia). Czas na
 * kartach pozostaje w strefie PL (jak na listach) — świadoma różnica przy meczach
 * blisko północy UTC.
 *
 * Filtr: terminarz JEST listą (slice filters renderuje pasek/szukajkę, body class
 * i JS — gate poszerzony predykatem). Karty niosą `data-*` filtra; siatki dnia są
 * `[data-filterable]`, a sekcje mają klasę `.section`, więc pusty po filtrze dzień
 * znika natywnie (`.section.is-empty-by-filter`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Polska odmiana „mecz/mecze/meczów" dla licznika dnia. */
if ( ! function_exists( 'hajlajty_terminarz_count_label' ) ) {
	function hajlajty_terminarz_count_label( int $n ): string {
		$mod10  = $n % 10;
		$mod100 = $n % 100;
		if ( 1 === $n ) {
			return '1 mecz';
		}
		if ( $mod10 >= 2 && $mod10 <= 4 && ( $mod100 < 12 || $mod100 > 14 ) ) {
			return $n . ' mecze';
		}
		return $n . ' meczów';
	}
}

$terminarz_query = new WP_Query(
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
?>
<div class="page-head">
	<span class="page-head__eyebrow"><span class="dot"></span> Mundial 2026 · Kanada · Meksyk · USA</span>
	<h1 class="page-head__title">Terminarz turnieju</h1>
	<p class="page-head__sub">Pełny kalendarz spotkań dzień po dniu — rozegrane mecze ze skrótami, transmisje na żywo oraz nadchodzące zapowiedzi z odliczaniem do pierwszego gwizdka.</p>
	<div class="legend">
		<span class="legend__item"><span class="legend__dot skrot"></span> Rozegrane · skrót wideo</span>
		<span class="legend__item"><span class="legend__dot live"></span> Trwa na żywo</span>
		<span class="legend__item"><span class="legend__dot soon"></span> Nadchodzące · odliczanie</span>
	</div>
</div>

<?php if ( ! $terminarz_query->have_posts() ) : ?>
	<div class="empty-state is-visible">
		<h3>Brak meczów w terminarzu</h3>
		<p>Nie zaimportowano jeszcze żadnych spotkań. Wróć, gdy ruszy import meczów.</p>
	</div>
	<?php
	wp_reset_postdata();
	return;
endif;

// Komplet ID w kolejności chronologicznej + JEDEN batch-resolve drużyn (zero N+1).
$terminarz_post_ids = wp_list_pluck( $terminarz_query->posts, 'ID' );
$terminarz_resolved = hajlajty_match_lists_resolve_terms( $terminarz_post_ids );

// Grupowanie po dniu (UTC). PHP zachowuje kolejność wstawiania → dni rosnąco.
$terminarz_days = array(); // 'Y-m-d' => array( 'ts' => int, 'ids' => int[] )
foreach ( $terminarz_post_ids as $terminarz_pid ) {
	$terminarz_pid = (int) $terminarz_pid;
	$terminarz_raw = (string) get_post_meta( $terminarz_pid, 'kickoff', true );
	if ( '' === $terminarz_raw ) {
		continue; // Teoretycznie niemożliwe (meta_query EXISTS), ale bezpiecznie.
	}
	$terminarz_key = substr( $terminarz_raw, 0, 10 );
	if ( ! isset( $terminarz_days[ $terminarz_key ] ) ) {
		$terminarz_dt = date_create_immutable( $terminarz_raw, new DateTimeZone( 'UTC' ) );
		$terminarz_days[ $terminarz_key ] = array(
			'ts'  => $terminarz_dt ? $terminarz_dt->getTimestamp() : 0,
			'ids' => array(),
		);
	}
	$terminarz_days[ $terminarz_key ]['ids'][] = $terminarz_pid;
}

$terminarz_today = gmdate( 'Y-m-d' ); // Dzień bieżący w UTC (spójny z kluczem grupy).

foreach ( $terminarz_days as $terminarz_key => $terminarz_day ) :
	$terminarz_label = $terminarz_day['ts']
		? wp_date( 'l, j F Y', $terminarz_day['ts'], new DateTimeZone( 'UTC' ) )
		: $terminarz_key;
	$terminarz_is_today = ( $terminarz_key === $terminarz_today );
	?>
	<section class="schedule-day section" data-day="<?php echo esc_attr( $terminarz_key ); ?>">
		<div class="schedule-day__head">
			<h2 class="schedule-section__title"><?php echo esc_html( $terminarz_label ); ?></h2>
			<span class="schedule-day__count"><?php echo esc_html( hajlajty_terminarz_count_label( count( $terminarz_day['ids'] ) ) ); ?></span>
			<?php if ( $terminarz_is_today ) : ?>
				<span class="schedule-day__today"><span class="dot"></span> DZIŚ</span>
			<?php endif; ?>
		</div>
		<div class="schedule-grid" data-filterable>
			<?php
			foreach ( $terminarz_day['ids'] as $terminarz_card_id ) :
				$terminarz_card_id = (int) $terminarz_card_id;
				$terminarz_data    = hajlajty_get_match_data( $terminarz_card_id );
				$terminarz_state   = hajlajty_lookup_status( $terminarz_data['status']['short'] ?? null )['state'];
				$terminarz_terms   = isset( $terminarz_resolved[ $terminarz_card_id ] )
					? $terminarz_resolved[ $terminarz_card_id ]
					: array(
						'home' => null,
						'away' => null,
					);

				if ( 'LIVE' === $terminarz_state ) {
					$terminarz_partial = 'card-live';
				} elseif ( 'ZAPOWIEDZ' === $terminarz_state ) {
					$terminarz_partial = 'card-zapowiedz';
				} elseif ( 'ODWOLANY' === $terminarz_state ) {
					$terminarz_partial = 'card-wynik';
				} else {
					// ZAKONCZONY: ze skrótem → karta wideo; bez skrótu → karta wyniku.
					$terminarz_skrot = function_exists( 'get_field' )
						? get_field( 'skrot_url', $terminarz_card_id )
						: get_post_meta( $terminarz_card_id, 'skrot_url', true );
					$terminarz_partial = ( is_string( $terminarz_skrot ) && '' !== trim( $terminarz_skrot ) )
						? 'card-skrot'
						: 'card-wynik';
				}

				get_template_part(
					'features/match-lists/partials/' . $terminarz_partial,
					null,
					array(
						'post_id' => $terminarz_card_id,
						'terms'   => $terminarz_terms,
						'data'    => $terminarz_data,
					)
				);
			endforeach;
			?>
		</div>
	</section>
	<?php
endforeach;

wp_reset_postdata();
