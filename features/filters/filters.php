<?php
/**
 * Slice „filters" — PUBLICZNY filtr LIST meczów (READ-ONLY, KLIENCKI). Warstwa
 * FILTRA, świadomie oddzielona od warstwy RENDERU list (slice match-lists) —
 * granica vertical slice: match-lists produkuje karty + ich `data-*`, filters
 * dokłada nad nimi chipsbar, pole szukania i lekki JS, który ZAWĘŻA już
 * wyrenderowane karty.
 *
 * Wariant LEKKI (USTALENIA 4A, plan): chip = LEPKI filtr kliencki w
 * `sessionStorage` — NIE nawiguje, NIE robi tax_query, NIE tworzy archiwów
 * taksonomii ani rewrite. Serwer renderuje pełną listę stanu jak dziś; JS tylko
 * pokazuje/ukrywa karty. Headless-friendly: filtr żyje na `data-*`, które
 * produkują natywne taksonomie WP (te same dane pójdą przez WPGraphQL).
 *
 * Bootstrap CIENKI (CLAUDE.md): tylko dociąga pliki slice'a. Logika w:
 *  - normalize.php — normalizator nazw PL (kontrakt PHP↔JS wyszukiwania),
 *  - ui.php        — render chipsbara + pola szukania (dokładane przez szablony list),
 *  - assets/       — filters.js (lepki filtr) + filters.css (port z designu).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/normalize.php';
