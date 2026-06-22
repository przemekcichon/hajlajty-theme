<?php
/**
 * Template Name: Reprezentacje
 *
 * Szablon Strony „Reprezentacje" (MVP-g) — lista wszystkich reprezentacji
 * pogrupowana po grupach A–L (wg tabeli MVP-d). Redaktor tworzy w WP admin Stronę
 * z tym szablonem (sugerowany slug `reprezentacje`, spójnie z linkiem w sidebarze)
 * — bez nowego rewrite ani flushu.
 *
 * Plik MUSI leżeć w roocie motywu: WP wykrywa szablony stron tylko do głębokości 1
 * (WP_Theme::get_post_templates → get_files('php',1)). Szablon CIENKI — powłoka
 * (header/footer ze slice'a layout) + delegacja treści do partiala slice'a
 * teams-view (tam żyje logika i render). Vertical slice zachowany (wzór:
 * template-tabela-rozgrywek.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'features/layout/partials/header' );

// Pasek filtra drużyn (slice filters) — pod headerem, w `.content` przed `<main>`,
// jak archive-mecz.php / tabela rozgrywek. Wyszukiwarka w topbarze wstrzykuje się
// sama (hook hajlajty_topbar_center, gated przez hajlajty_filters_is_list_view,
// która obejmuje już Reprezentacje). Guard, gdyby slice filters zniknął.
if ( function_exists( 'hajlajty_filters_render_bar' ) ) {
	hajlajty_filters_render_bar();
}

get_template_part( 'features/teams-view/partials/reprezentacje' );
get_template_part( 'features/layout/partials/footer' );
