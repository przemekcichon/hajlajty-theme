<?php
/**
 * Szablon pojedynczego meczu (CPT „mecz"). Leży w roocie motywu, bo hierarchia
 * szablonów WP szuka single-{post_type}.php właśnie tu. Plik jest CIENKI:
 * otwiera powłokę (slice layout), USTALA STAN meczu i deleguje render do
 * właściwego wariantu w slice match-display, domyka powłokę.
 *
 * Rozgałęzienie wg 4 stanów (D3.1). Każdy stan deleguje do własnego partiala
 * w slice match-display TYM SAMYM wzorcem (get_template_part z $args). Stan
 * liczony 1:1 z lookups.php (3a): status.short → state, z bezpiecznym
 * fallbackiem ZAPOWIEDŹ dla nieznanego/null kodu (→ single-ns).
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

		case 'LIVE':
			// Telebim + składy/oś/statystyki (statyczne, bez auto-refresh — 3e).
			get_template_part(
				'features/match-display/partials/single-live',
				null,
				array(
					'post_id' => $post_id,
					'data'    => $data,
				)
			);
			break;

		case 'ODWOLANY':
			// Stan terminalny — minimalny szkielet z badge „Odwołany".
			get_template_part(
				'features/match-display/partials/single-canc',
				null,
				array(
					'post_id' => $post_id,
					'data'    => $data,
				)
			);
			break;

		case 'ZAPOWIEDZ':
		default:
			// Odliczanie do pierwszego gwizdka. Także bezpieczny fallback dla
			// nieznanego/null kodu statusu (lookups.php → ZAPOWIEDZ).
			get_template_part(
				'features/match-display/partials/single-ns',
				null,
				array(
					'post_id' => $post_id,
					'data'    => $data,
				)
			);
			break;
	}

endwhile;

get_template_part( 'features/layout/partials/footer' );
