<?php
/**
 * Chipy filtra z NATYWNEJ taksonomii `druzyna` — publiczny filtr po DRUŻYNACH
 * (CLAUDE.md: front filtruje po natywnych taksonomiach + lekki JS).
 *
 * Zakres 4A = TYLKO drużyny. Rozgrywki i sezon wrócą jako chipy w Fazie 5 (patrz
 * docs/plan.md); kanał świadomie nie jest filtrem publicznym (brak wartości dla
 * użytkownika). Stąd jedna grupa i bez etykiet grup.
 *
 * Zakres chipów = wszystkie UŻYWANE termy globalnie (`hide_empty => true`): drużyny
 * z ≥1 meczem w całym CPT. Dzięki temu aktywny LEPKI chip jest widoczny i
 * odznaczalny także na liście, która akurat nie zawiera takiej drużyny.
 *
 * Kontrakt z `filters.js` i kartami: `data-filter-tax="druzyna"`,
 * `data-filter-val` = kod FIFA (term meta `fifa_code`, jak `data-teams` karty).
 * Drużyny bez `fifa_code` pomijamy — nie da się ich sparować z kartą. Flagę
 * budujemy reużywalnym `hajlajty_flag_url()` (slice match-display) — spójnie z kartą.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hajlajty_chip_check = '<span class="chip__check"><svg viewBox="0 0 24 24"><path d="m5 12 5 5 9-11"/></svg></span>';

$hajlajty_terms = get_terms(
	array(
		'taxonomy'   => 'druzyna',
		'hide_empty' => true,
		'orderby'    => 'name',
	)
);
if ( is_wp_error( $hajlajty_terms ) || empty( $hajlajty_terms ) ) {
	return;
}

foreach ( $hajlajty_terms as $hajlajty_term ) {
	$hajlajty_code = strtoupper( (string) get_term_meta( $hajlajty_term->term_id, 'fifa_code', true ) );
	if ( '' === $hajlajty_code ) {
		continue;
	}
	$hajlajty_flag = hajlajty_flag_url( $hajlajty_term );
	?>
	<button type="button" class="chip" data-filter-tax="druzyna" data-filter-val="<?php echo esc_attr( $hajlajty_code ); ?>">
		<?php if ( '' !== $hajlajty_flag ) : ?>
			<img class="country-flag" src="<?php echo esc_url( $hajlajty_flag ); ?>" alt="" />
		<?php endif; ?>
		<?php echo esc_html( $hajlajty_term->name ); ?>
		<?php echo $hajlajty_chip_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — statyczny markup SVG. ?>
	</button>
	<?php
}
