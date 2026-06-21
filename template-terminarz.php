<?php
/**
 * Template Name: Terminarz turnieju
 *
 * Szablon Strony „Terminarz turnieju" (MVP-c). Redaktor tworzy w WP admin Stronę
 * z tym szablonem (slug dowolny, sugerowany `terminarz`) — bez nowego rewrite ani
 * flushu (Strony używają istniejącego routingu WP).
 *
 * Plik MUSI leżeć w roocie motywu: WP wykrywa szablony stron tylko do głębokości 1
 * (patrz hajlajty_match_lists_is_terminarz()). Sam szablon jest CIENKI — powłoka
 * (header/footer ze slice'a layout, pasek filtra ze slice'a filters jak w archiwum)
 * + delegacja treści do partiala slice'a match-lists (tam żyje cała logika listy:
 * WP_Query, grupowanie po dniu, render kart). Vertical slice zachowany.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'features/layout/partials/header' );

// Pasek filtra (slice filters) — pod headerem, w `.content` przed `<main>`. Tak
// jak archive-mecz.php: terminarz jest listą, więc dostaje chipsbar + szukajkę.
// Guard, gdyby slice zniknął.
if ( function_exists( 'hajlajty_filters_render_bar' ) ) {
	hajlajty_filters_render_bar();
}
?>
<main class="container">
	<section class="section">
		<?php get_template_part( 'features/match-lists/partials/terminarz' ); ?>
	</section>
</main>
<?php
get_template_part( 'features/layout/partials/footer' );
