<?php
/**
 * Template Name: Tabela rozgrywek
 *
 * Szablon Strony tabeli rozgrywek (MVP-e). Redaktor tworzy w WP admin Stronę z
 * tym szablonem (sugerowany slug `tabele-grup`) i wpisuje w jej polach `league_id`
 * + `season` (slice standings-view rejestruje pola) — bez nowego rewrite ani flushu.
 *
 * Neutralna nazwa „Tabela rozgrywek" (nie „grupy"), bo ten sam szablon przyjmie w
 * przyszłości wariant LIGOWY (piłka klubowa, Faza 5). Teraz implementuje wariant
 * GRUPOWY — jedyny kształt, jaki MVP-d realnie produkuje (#8: bez gałęzi ligowej).
 *
 * Plik MUSI leżeć w roocie motywu: WP wykrywa szablony stron tylko do głębokości 1.
 * Szablon CIENKI — powłoka (header/footer ze slice'a layout) + delegacja treści do
 * partiala slice'a standings-view (tam żyje cała logika: odczyt meta, resolucja,
 * render). Vertical slice zachowany (wzór: template-terminarz.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'features/layout/partials/header' );
?>
<main class="container">
	<?php get_template_part( 'features/standings-view/partials/groups' ); ?>
</main>
<?php
get_template_part( 'features/layout/partials/footer' );
