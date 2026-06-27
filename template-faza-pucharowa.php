<?php
/**
 * Template Name: Faza pucharowa
 *
 * Szablon Strony „Faza pucharowa" — wizualizacja drabinki (R32 → … → Finał).
 * Redaktor tworzy w WP admin Stronę z tym szablonem (slug sugerowany
 * `faza-pucharowa`) — bez nowego rewrite ani flushu (Strony używają routingu WP).
 *
 * Plik MUSI leżeć w roocie motywu: WP wykrywa szablony stron tylko do głębokości 1
 * (patrz hajlajty_match_lists_is_faza_pucharowa()). Sam szablon jest CIENKI: powłoka
 * (header/footer ze slice'a layout, pasek filtra ze slice'a filters jak w archiwum/
 * terminarzu) + delegacja treści do partiala slice'a match-lists (tam żyje cała
 * logika drabinki: WP_Query, dopasowanie realnych meczów, render drzewa).
 * Vertical slice zachowany.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'features/layout/partials/header' );

// Pasek filtra (slice filters) — jak archive-mecz.php / terminarz: drabinka to widok
// list-podobny, więc dostaje chipsbar + szukajkę. Filtr „wygasza" niewybrane mecze
// (CSS w bracket.css), drabinka zostaje cała. Guard, gdyby slice zniknął.
if ( function_exists( 'hajlajty_filters_render_bar' ) ) {
	hajlajty_filters_render_bar();
}
?>
<main class="container">
	<?php get_template_part( 'features/match-lists/partials/faza-pucharowa' ); ?>
</main>
<?php
get_template_part( 'features/layout/partials/footer' );
