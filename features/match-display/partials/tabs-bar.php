<?php
/**
 * Wspólny pasek zakładek meczu (P-d, pkt 6) — JEDNO źródło markupu `.tabs`
 * używane przez single-ft (skrót) i single-live (na żywo). Trzy stałe zakładki:
 * Oś czasu | Składy | Statystyki (te same etykiety i ikony w obu widokach).
 *
 * Różnice między widokami sprowadzają się do DWÓCH rzeczy, oba sterowane z
 * zewnątrz — bez rozjeżdżania markupu:
 *  - `$args['active']` — która zakładka jest aktywna na starcie (skrót: timeline;
 *    live: lineups — bo na desktopie oś czasu wychodzi z zestawu zakładek do
 *    osobnego, zawsze widocznego panelu, więc domyślną treścią głównej kolumny
 *    są Składy).
 *  - przycisk „Oś czasu" jest na desktopie UKRYWANY w kontekście live
 *    (`.watch__grid--live .tab[data-tab="timeline"]`, match-single.css) — tu
 *    renderujemy go zawsze, CSS decyduje o widoczności per widok/breakpoint.
 *
 * JS (`match-display.js`) działa na dowolnych `.tabs .tab` + `.tabpanel` w
 * dokumencie, więc panele mogą żyć poza tym partialem (single-live trzyma je w
 * dwóch kolumnach, single-ft w `.tabpanels`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active = isset( $args['active'] ) ? (string) $args['active'] : 'timeline';

// Stałe zakładki: klucz (data-tab) → [etykieta, ścieżka SVG]. Ikony 1:1 z designu.
$tabs = array(
	'timeline' => array( 'Oś czasu', '<path d="M12 8v4l2.5 2.5"/><circle cx="12" cy="12" r="9"/>' ),
	'lineups'  => array( 'Składy', '<path d="M6 4h12v3a6 6 0 0 1-12 0z"/><path d="M9 14h6M10 14v4M14 14v4M8 21h8"/>' ),
	'stats'    => array( 'Statystyki', '<path d="M5 20V10M12 20V4M19 20v-7"/>' ),
);
?>
<div class="tabs" role="tablist" aria-label="Szczegóły meczu">
	<?php
	foreach ( $tabs as $key => $tab ) :
		$is_on = ( $key === $active );
		?>
		<button class="tab<?php echo $is_on ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>" role="tab" aria-selected="<?php echo $is_on ? 'true' : 'false'; ?>" type="button"><svg viewBox="0 0 24 24"><?php echo $tab[1]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — statyczne ścieżki SVG ?></svg> <?php echo esc_html( $tab[0] ); ?></button>
	<?php endforeach; ?>
</div>
