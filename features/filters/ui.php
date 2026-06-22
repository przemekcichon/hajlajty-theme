<?php
/**
 * Render warstwy FILTRA na widokach LIST. Dwa tryby zależne od szerokości (CSS):
 *
 *  DESKTOP (≥769px):
 *   - hajlajty_filters_render_search() → pole szukania w ŚRODKU topbara,
 *   - hajlajty_filters_render_bar() → chipsbar (chipy + reset) pod topbarem.
 *
 *  MOBILE (≤768px) — jak w prototypie:
 *   - w topbarze LUPA (otwiera modal); pole inline ukryte,
 *   - pod headerem PIGUŁKA filtra (podsumowanie aktywnych + „wyczyść”),
 *   - MODAL pełnoekranowy: pole szukania + siatka chipów + „Zatwierdź i pokaż mecze”.
 *
 * Chipy renderujemy DWUKROTNIE tym samym partialem (pasek desktop + siatka modalu)
 * — to JEDEN kontrakt; filters.js wiąże WSZYSTKIE `.chip[data-filter-tax]` w obrębie
 * `[data-filters]` z tym samym, lepkim stanem, a CSS pokazuje właściwy tryb. Bez
 * klonowania w JS. „Obserwowane” (oko) to przyszła funkcja (hajlajty-user) — tu NIE.
 *
 * Widoczność: oba elementy tylko na widokach list (search self-gated; pasek wołają
 * tylko szablony list). Single nie woła paska, a gate wyklucza search.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Czy bieżący widok dostaje filtr drużyn (wyszukiwarka + chipsbar): publiczna
 * LISTA meczów (home, archiwum „mecz", terminarz MVP-c), tabela grup (MVP-e) ALBO
 * lista Reprezentacje (MVP-g). Widoki spoza slice'a filters pytamy przez
 * function_exists (luźne sprzężenie: filters nie zależy twardo od match-lists/
 * standings-view/teams-view, tylko konsumuje ich predykat gdy obecny). Ani tabela
 * grup, ani lista reprezentacji nie są „listą meczów", ale dzielą ten sam filtr po
 * drużynach (znajdź swoją drużynę). Profil pojedynczej drużyny (single) NIE wpada
 * tu — to skupiony widok bez filtra.
 */
function hajlajty_filters_is_list_view(): bool {
	return is_post_type_archive( 'mecz' )
		|| is_front_page()
		|| ( function_exists( 'hajlajty_match_lists_is_terminarz' ) && hajlajty_match_lists_is_terminarz() )
		|| ( function_exists( 'hajlajty_standings_view_is_table' ) && hajlajty_standings_view_is_table() )
		|| ( function_exists( 'hajlajty_teams_view_is_list' ) && hajlajty_teams_view_is_list() );
}

/**
 * Pole szukania + lupa w środkowej kolumnie topbara (hook hajlajty_topbar_center).
 * Desktop pokazuje pole, mobile lupę (CSS). Self-gated do widoków list.
 */
function hajlajty_filters_render_search() {
	if ( ! hajlajty_filters_is_list_view() ) {
		return;
	}
	?>
	<div class="topbar__center">
		<form class="search filters-search" role="search" onsubmit="return false">
			<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
			<input type="search" class="filters-search__input" placeholder="Szukaj drużyny…" aria-label="Szukaj drużyny" data-filter-search />
			<button type="button" class="search__clear filters-search__clear" data-filter-clear-text hidden aria-label="Wyczyść tekst">
				<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>
		</form>
		<button type="button" class="icon-btn filters-search__open" data-filter-open aria-label="Szukaj drużyny" aria-haspopup="dialog">
			<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
		</button>
	</div>
	<?php
}

/**
 * Pigułka filtra (mobile), chipsbar (desktop), komunikat pustego wyniku i MODAL
 * wyszukiwania (mobile). Wołane przez szablony list tuż po headerze.
 */
function hajlajty_filters_render_bar() {
	?>
	<div data-filters>

		<?php // CHIPSBAR (chipy drużyn) — tylko desktop (CSS); mobile ma modal. ?>
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
			</div>
		</div>

		<?php // PIGUŁKA aktywnego filtra (nazwy drużyn) + czyszczenie — desktop i mobile. ?>
		<div class="filter-pill" data-filter-pill hidden>
			<span class="filter-pill__txt">Filtrujesz: <b data-filter-pill-text></b></span>
			<button type="button" class="filter-pill__clear" data-filter-reset aria-label="Wyczyść filtry">
				<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>
		</div>

		<?php // Komunikat pustego wyniku — w normalnym przepływie treści. ?>
		<p class="filters-empty container" data-filter-empty hidden>
			Brak meczów pasujących do wybranego filtra. Zmień wybór lub
			<button type="button" class="filters-empty__reset" data-filter-reset>wyczyść filtry</button>.
		</p>

		<?php // MODAL wyszukiwania — tylko mobile (CSS); otwiera lupa z topbara. ?>
		<div class="search-overlay" data-filter-modal role="dialog" aria-modal="true" aria-label="Szukaj drużyny">
			<div class="search-overlay__head">
				<button type="button" class="icon-btn search-overlay__back" data-filter-close aria-label="Zamknij wyszukiwanie">
					<svg viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></svg>
				</button>
				<form class="search" role="search" onsubmit="return false">
					<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
					<input type="search" class="filters-search__input" placeholder="Szukaj drużyny…" aria-label="Szukaj drużyny" data-filter-search />
					<button type="button" class="search__clear filters-search__clear" data-filter-clear-text hidden aria-label="Wyczyść tekst">
						<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
					</button>
				</form>
			</div>
			<div class="search-overlay__body">
				<div class="search-overlay__grid" data-filter-chips>
					<?php get_template_part( 'features/filters/partials/chips-bar' ); ?>
				</div>
				<div class="search-overlay__foot">
					<button type="button" class="search-overlay__apply" data-filter-apply>Zatwierdź i pokaż mecze</button>
				</div>
			</div>
		</div>

	</div>
	<?php
}
