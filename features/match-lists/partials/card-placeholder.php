<?php
/**
 * Karta PLACEHOLDER fazy pucharowej (.card--placeholder) — TYLKO terminarz.
 * Sloty rund, których realnych fixtures jeszcze NIE MA w API (Round of 16 … Final).
 * To WARSTWA WIDOKU, nie post `mecz` (#10): NIE linkuje do single, NIE ma flag
 * (etykiety typu „Zwycięzca meczu 89" nie mają `fifa_code`), NIE odlicza.
 *
 * Gdy import wciągnie realny mecz tej rundy/godziny, `hajlajty_knockout_merge`
 * odsiewa ten placeholder — kartę zastępuje realna (card-zapowiedz/live/skrot/wynik).
 *
 * Atrybuty filtra 4A: emitujemy KOMPLET (puste) przez współdzielony helper z
 * pustymi termami → `filters.js` traktuje placeholder jak kartę bez drużyn:
 * znika przy aktywnym filtrze/szukaniu drużyny, widoczna bez filtra. (filters.js
 * adresuje karty po `[data-team-names]`, więc atrybut MUSI być obecny.)
 *
 * Zmienne wejściowe (z get_template_part $args):
 *   - $round   string  literał rundy (np. „Round of 16") → hajlajty_lookup_round
 *   - $kickoff string  UTC „Y-m-d H:i:s" (etykieta czasu PL przez wp_date)
 *   - $home    string  etykieta placeholderowa gospodarza (PL)
 *   - $away    string  etykieta placeholderowa gościa (PL)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$round   = isset( $args['round'] ) ? (string) $args['round'] : '';
$kickoff = isset( $args['kickoff'] ) ? (string) $args['kickoff'] : '';
$home    = isset( $args['home'] ) ? (string) $args['home'] : '';
$away    = isset( $args['away'] ) ? (string) $args['away'] : '';

$round_pl = hajlajty_lookup_round( '' !== $round ? $round : null );

// Czas: PŁASKA konwencja jak karty/terminarz — UTC „Y-m-d H:i:s" → etykieta PL przez wp_date.
$kickoff_dt = ( '' !== $kickoff )
	? date_create_immutable( $kickoff, new DateTimeZone( 'UTC' ) )
	: false;
$when_label = $kickoff_dt ? wp_date( 'j M Y · H:i', $kickoff_dt->getTimestamp() ) : '';

// Puste atrybuty filtra (brak drużyn) — filters.js ukryje placeholder przy filtrze drużyny.
$filter_attrs = hajlajty_match_lists_card_filter_attrs(
	array(
		'home' => null,
		'away' => null,
	)
);

// Etykieta placeholderowa („Zwycięzca meczu 74") bywa dłuższa niż kolumna karty —
// rozbijamy na słowa, każde w osobnej linii (czytelność + zero przycięcia numeru).
// Domknięcie w zmiennej (NIE deklaracja funkcji) — partial bywa include'owany wielokrotnie.
$ph_words = static function ( string $label ): array {
	$label = trim( $label );
	return '' === $label ? array() : preg_split( '/\s+/', $label );
};
?>
<div class="card--preview card--placeholder"<?php echo $filter_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — atrybuty escapowane w helperze. ?>>
	<?php if ( '' !== $round_pl ) : ?>
		<span class="card__phase">⚽ <?php echo esc_html( $round_pl ); ?></span>
	<?php endif; ?>
	<?php if ( '' !== $when_label ) : ?>
		<p class="card__when"><?php echo esc_html( $when_label ); ?> (czasu PL)</p>
	<?php endif; ?>
	<div class="card__teams">
		<div class="card__team">
			<span class="card__team-name">
				<?php foreach ( $ph_words( $home ) as $ph_w ) : ?>
					<span class="card__ph-word"><?php echo esc_html( $ph_w ); ?></span>
				<?php endforeach; ?>
			</span>
		</div>
		<span class="card__vs">VS</span>
		<div class="card__team">
			<span class="card__team-name">
				<?php foreach ( $ph_words( $away ) as $ph_w ) : ?>
					<span class="card__ph-word"><?php echo esc_html( $ph_w ); ?></span>
				<?php endforeach; ?>
			</span>
		</div>
	</div>
	<p class="card__tbd">Drużyny do ustalenia</p>
</div>
