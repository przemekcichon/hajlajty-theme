<?php
/**
 * Render warstwy FILTRA na widokach LIST:
 *  - hajlajty_filters_render_search() — pole szukania po drużynach, wstrzykiwane do
 *    ŚRODKA topbara (hook `hajlajty_topbar_center`), wypośrodkowane na poziomie logo,
 *  - hajlajty_filters_render_bar() — chipsbar (chipy + reset) tuż pod topbarem, na
 *    poziomie pierwszego linku nawigacji, wołany przez szablony list po headerze.
 *
 * Oba elementy pokazują się WYŁĄCZNIE na widokach list (search self-gated, bo hook
 * topbara odpala się wszędzie; pasek — bo wołają go tylko szablony list). Single
 * (single-mecz.php) nie woła paska, a search-gate go wyklucza.
 *
 * Markup/klasy sportowane z designu (.topbar .search, .chipsbar/.chips-scroll/.chip/
 * .chip__check/.chips-arrow). Stan i interakcje obsługuje assets/filters.js przez
 * atrybuty `data-filter-*` (deklaratywnie, null-safe). Tu — statyczny szkielet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Czy bieżący widok to publiczna LISTA meczów (home albo archiwum „mecz").
 * Wspólny warunek widoczności filtra — spójny z bramkowaniem enqueue.
 */
function hajlajty_filters_is_list_view(): bool {
	return is_post_type_archive( 'mecz' ) || is_front_page();
}

/**
 * Pole szukania po drużynach — środkowa kolumna topbara (hook hajlajty_topbar_center).
 * Self-gated: topbar renderuje się na każdej stronie, pole tylko na listach.
 */
function hajlajty_filters_render_search() {
	if ( ! hajlajty_filters_is_list_view() ) {
		return;
	}
	?>
	<form class="search filters-search" role="search" onsubmit="return false">
		<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
		<input type="search" class="filters-search__input" placeholder="Szukaj drużyny…" aria-label="Szukaj drużyny" data-filter-search />
		<button type="button" class="search__clear filters-search__clear" data-filter-clear-text hidden aria-label="Wyczyść tekst">
			<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
		</button>
	</form>
	<?php
}

/**
 * Chipsbar (chipy taksonomii + zbiorczy reset) + komunikat pustego wyniku. Wołany
 * przez szablony list tuż po headerze — ląduje w `.content`, przed `<main>`.
 */
function hajlajty_filters_render_bar() {
	?>
	<div data-filters>
	<div class="chipsbar">
		<div class="chipsbar__inner container">

			<div class="chips-wrap">
				<button type="button" class="chips-arrow prev" data-filter-arrow="prev" aria-label="Przewiń chipy w lewo">
					<svg viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></svg>
				</button>
				<div class="chips-scroll" data-filter-chips>
					<?php get_template_part( 'features/filters/partials/chips-bar' ); ?>
				</div>
				<button type="button" class="chips-arrow next" data-filter-arrow="next" aria-label="Przewiń chipy w prawo">
					<svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg>
				</button>
			</div>

			<button type="button" class="filter-pill__clear filters-reset" data-filter-reset hidden>
				<span class="filters-reset__txt">Wyczyść filtry</span>
				<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>

		</div>
	</div>

	<?php // Komunikat pustego wyniku — POZA lepkim paskiem, w normalnym przepływie treści. ?>
	<p class="filters-empty container" data-filter-empty hidden>
		Brak meczów pasujących do wybranego filtra. Zmień wybór lub
		<button type="button" class="filters-empty__reset" data-filter-reset>wyczyść filtry</button>.
	</p>
	</div>
	<?php
}
