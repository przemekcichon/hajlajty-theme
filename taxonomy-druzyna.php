<?php
/**
 * Archiwum termu taksonomii „druzyna" = Profil kraju (reprezentacji), MVP-g.
 * Plik leży w roocie motywu, bo hierarchia szablonów WP szuka taxonomy-{tax}.php
 * właśnie tu. Świadoma decyzja routingu (#7 stabilny URL, #8 bez nowego CPT):
 * Profil to archiwum istniejącego termu „druzyna" (URL `/druzyna/{slug}/`), nie
 * osobny typ treści.
 *
 * Szablon CIENKI — powłoka (header/footer ze slice'a layout) + delegacja CAŁEJ
 * treści do partiala slice'a teams-view (tam żyje logika: odczyt meta/standings/
 * meczów, render hero + widżetów). Vertical slice zachowany (wzór:
 * template-tabela-rozgrywek.php / archive-mecz.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'features/layout/partials/header' );
get_template_part( 'features/teams-view/partials/profile' );
get_template_part( 'features/layout/partials/footer' );
