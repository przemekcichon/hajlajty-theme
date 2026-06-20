<?php
/**
 * Render warstwy FILTRA pod headerem na widokach LIST: pole szukania po drużynach
 * + chipsbar z natywnych taksonomii + zbiorczy reset + komunikat pustego wyniku.
 *
 * Wołane PRZEZ szablony list (archive-mecz.php, front-page.php) tuż po headerze —
 * fizycznie ląduje w `.content`, przed `<main>` (jak `.chipsbar` w designie). Tym
 * samym pojawia się tylko tam, gdzie są listy, a NIGDY na single (single-mecz.php
 * tej funkcji nie woła). Enqueue zasobów (filters.php) bramkowany jest niezależnie.
 *
 * Markup/klasy sportowane z designu (`.chipsbar`, `.chips-scroll`, `.chip`,
 * `.chip__check`, `.search`, `.chips-arrow`, `.filter-pill`). Stan i interakcje
 * obsługuje `assets/filters.js` przez atrybuty `data-filter-*` (deklaratywnie,
 * null-safe). Tu — wyłącznie statyczny szkielet renderowany serwerowo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wypisuje pasek filtra (pole szukania + chipsbar). Idempotentny względem treści —
 * chipy generuje partial chips-bar z natywnych taksonomii (zawsze świeże termy).
 */
function hajlajty_filters_render_bar() {
	?>
	<div data-filters>
	<div class="chipsbar">
		<div class="chipsbar__inner container">

			<form class="search filters-search" role="search" onsubmit="return false">
				<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
				<input type="search" class="filters-search__input" placeholder="Szukaj drużyny…" aria-label="Szukaj drużyny" data-filter-search />
				<button type="button" class="search__clear filters-search__clear" data-filter-clear-text hidden aria-label="Wyczyść tekst">
					<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
				</button>
			</form>

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
