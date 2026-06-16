<?php
/**
 * Szablon pojedynczego meczu (CPT „mecz"). Leży w roocie motywu, bo hierarchia
 * szablonów WP szuka single-{post_type}.php właśnie tu. Plik jest CIENKI:
 * otwiera powłokę (slice layout), USTALA STAN meczu i deleguje render do
 * właściwego wariantu w slice match-display, domyka powłokę.
 *
 * Rozgałęzienie wg 4 stanów (D3.1). 3b implementuje TYLKO ZAKOŃCZONY (→ E3);
 * ZAPOWIEDŹ/LIVE/ODWOŁANY to jawne TODO dla 3c (pusty placeholder, zero renderu).
 * Stan liczony 1:1 z lookups.php (3a): status.short → state, z bezpiecznym
 * fallbackiem ZAPOWIEDŹ dla nieznanego/null kodu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'features/layout/partials/header' );

while ( have_posts() ) :
	the_post();

	$post_id = get_the_ID();
	$data    = hajlajty_get_match_data( $post_id );
	$state   = hajlajty_lookup_status( $data['status']['short'] ?? null )['state'];

	switch ( $state ) {
		case 'ZAKONCZONY':
			// Wariant skrótu/po meczu — jedyny render 3b (E3).
			get_template_part(
				'features/match-display/partials/single-ft',
				null,
				array(
					'post_id' => $post_id,
					'data'    => $data,
				)
			);
			break;

		case 'ZAPOWIEDZ':
		case 'LIVE':
		case 'ODWOLANY':
		default:
			// TODO 3c: warianty ZAPOWIEDŹ (odliczanie), LIVE (telebim), ODWOŁANY
			// (oznaczenie). NIE implementowane w 3b — patrz plan §3c. Pusty,
			// neutralny placeholder zamiast renderu (i dla nieznanego stanu).
			?>
			<main class="watch container">
				<p style="padding: var(--space-xl) 0; color: var(--text-muted);">
					Ten widok meczu pojawi się wkrótce.
				</p>
			</main>
			<?php
			break;
	}

endwhile;

get_template_part( 'features/layout/partials/footer' );
