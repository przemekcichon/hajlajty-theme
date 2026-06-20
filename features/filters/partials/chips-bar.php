<?php
/**
 * Chipy filtra z NATYWNYCH taksonomii WP — pojedyncze źródło prawdy filtra
 * publicznego (CLAUDE.md: front filtruje po natywnych taksonomiach + lekki JS).
 *
 * Zakres chipów = wszystkie UŻYWANE termy globalnie (`hide_empty => true`):
 * termy z ≥1 meczem w całym CPT. Dzięki temu aktywny LEPKI chip jest widoczny i
 * odznaczalny także na liście, która akurat nie zawiera takiej drużyny (filtr
 * trzyma się między listami — USTALENIA 4A).
 *
 * Kontrakt z `filters.js` i z kartami:
 *  - `data-filter-tax` = slug taksonomii (druzyna|rozgrywki|sezon|kanal),
 *  - `data-filter-val` = wartość parowana z `data-*` karty:
 *       druzyna → kod FIFA (term meta `fifa_code`, jak `data-teams` karty),
 *       pozostałe → slug termu (jak `data-rozgrywki`/`data-sezon`/`data-kanal`).
 * Drużyny bez `fifa_code` pomijamy — nie da się ich sparować z kartą.
 *
 * Flagę drużyny budujemy reużywalnym `hajlajty_flag_url()` (slice match-display),
 * tym samym, którym renderują się karty — spójny wygląd, jedno źródło prawdy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hajlajty_filter_groups = array(
	'druzyna'   => 'Drużyny',
	'rozgrywki' => 'Rozgrywki',
	'sezon'     => 'Sezon',
	'kanal'     => 'Kanał',
);

$hajlajty_chip_check = '<span class="chip__check"><svg viewBox="0 0 24 24"><path d="m5 12 5 5 9-11"/></svg></span>';

foreach ( $hajlajty_filter_groups as $hajlajty_tax => $hajlajty_label ) {
	$hajlajty_terms = get_terms(
		array(
			'taxonomy'   => $hajlajty_tax,
			'hide_empty' => true,
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $hajlajty_terms ) || empty( $hajlajty_terms ) ) {
		continue;
	}

	// Drużyny bez kodu FIFA odpadają — najpierw zbierz renderowalne, by nie
	// wypisać etykiety grupy nad pustką.
	$hajlajty_rows = array();
	foreach ( $hajlajty_terms as $hajlajty_term ) {
		if ( 'druzyna' === $hajlajty_tax ) {
			$hajlajty_val = strtoupper( (string) get_term_meta( $hajlajty_term->term_id, 'fifa_code', true ) );
			if ( '' === $hajlajty_val ) {
				continue;
			}
			$hajlajty_flag = hajlajty_flag_url( $hajlajty_term );
		} else {
			$hajlajty_val  = $hajlajty_term->slug;
			$hajlajty_flag = '';
		}
		$hajlajty_rows[] = array(
			'val'  => $hajlajty_val,
			'name' => $hajlajty_term->name,
			'flag' => $hajlajty_flag,
		);
	}
	if ( empty( $hajlajty_rows ) ) {
		continue;
	}
	?>
	<span class="chips-group-label" aria-hidden="true"><?php echo esc_html( $hajlajty_label ); ?></span>
	<?php
	foreach ( $hajlajty_rows as $hajlajty_row ) {
		?>
		<button type="button" class="chip" data-filter-tax="<?php echo esc_attr( $hajlajty_tax ); ?>" data-filter-val="<?php echo esc_attr( $hajlajty_row['val'] ); ?>">
			<?php if ( '' !== $hajlajty_row['flag'] ) : ?>
				<img class="country-flag" src="<?php echo esc_url( $hajlajty_row['flag'] ); ?>" alt="" />
			<?php endif; ?>
			<?php echo esc_html( $hajlajty_row['name'] ); ?>
			<?php echo $hajlajty_chip_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — statyczny markup SVG. ?>
		</button>
		<?php
	}
}
