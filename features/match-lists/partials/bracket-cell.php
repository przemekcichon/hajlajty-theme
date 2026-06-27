<?php
/**
 * Pojedyncza komórka drabinki (real / placeholder / tbd). Wydzielona z
 * partials/faza-pucharowa.php, bo w układzie dwustronnym komórki renderują się w
 * wielu miejscach (lewa strona, środek, prawa strona) — jedno źródło markupu.
 *
 * Wejście ($args):
 *  - cell        array  komórka view-modelu (no, round, kickoff, home_label,
 *                       away_label, home_feeder, away_feeder),
 *  - real_by_no  array  mapa numer→{post_id,data} realnych meczów,
 *  - resolved    array  mapa post_id→termy (batch resolver, zero N+1),
 *  - col         int    globalny indeks kolumny lewo→prawo (graf linii w bracket.js),
 *  - with_feeders bool  czy emitować data-feeder-* (false dla meczu o 3. miejsce —
 *                       ma zostać NIEpołączony liniami).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bcell        = $args['cell'];
$breal_by_no  = isset( $args['real_by_no'] ) && is_array( $args['real_by_no'] ) ? $args['real_by_no'] : array();
$bresolved    = isset( $args['resolved'] ) && is_array( $args['resolved'] ) ? $args['resolved'] : array();
$bcol         = (int) ( $args['col'] ?? 0 );
$bwith_feeder = ! isset( $args['with_feeders'] ) || (bool) $args['with_feeders'];

$bno         = (int) $bcell['no'];
$breal       = $breal_by_no[ $bno ] ?? null;
$bhas_label  = ( null !== $bcell['home_label'] && null !== $bcell['away_label'] );
$bmode       = hajlajty_bracket_cell_mode( null !== $breal, $bhas_label );

// Data · godzina (PL) z płaskiego UTC „Y-m-d H:i:s" — na KAŻDYM bloczku.
$bwhen = static function ( $utc ) {
	$utc = (string) $utc;
	if ( '' === $utc ) {
		return '';
	}
	$dt = date_create_immutable( $utc, new DateTimeZone( 'UTC' ) );
	return $dt ? wp_date( 'j M · H:i', $dt->getTimestamp() ) : '';
};

// Atrybuty grafu linii (bracket.js): numer, kolumna, feederzy (>0) — chyba że
// komórka ma być niepołączona (mecz o 3. miejsce).
$bgraph = ' data-no="' . $bno . '" data-col="' . $bcol . '"';
if ( $bwith_feeder && (int) $bcell['home_feeder'] > 0 ) {
	$bgraph .= ' data-feeder-a="' . (int) $bcell['home_feeder'] . '"';
}
if ( $bwith_feeder && (int) $bcell['away_feeder'] > 0 ) {
	$bgraph .= ' data-feeder-b="' . (int) $bcell['away_feeder'] . '"';
}

if ( 'real' === $bmode ) :
	$bcard_id = (int) $breal['post_id'];
	$bdata    = $breal['data'];
	$bterms   = isset( $bresolved[ $bcard_id ] )
		? $bresolved[ $bcard_id ]
		: array(
			'home' => null,
			'away' => null,
		);

	$bstate = hajlajty_lookup_status( $bdata['status']['short'] ?? null )['state'];
	$bshort = (string) ( $bdata['status']['short'] ?? '' );
	$bgh    = $bdata['goals']['home'] ?? null;
	$bga    = $bdata['goals']['away'] ?? null;

	$bhome_flag = hajlajty_flag_url( $bterms['home'] );
	$baway_flag = hajlajty_flag_url( $bterms['away'] );
	$bhome_code = hajlajty_match_lists_team_code( $bterms['home'] );
	$baway_code = hajlajty_match_lists_team_code( $bterms['away'] );

	// Rozstrzygnięcie po 90' (NOWY odczyt): AET=po dogrywce; PEN=po karnych (+ wynik).
	$bnote = '';
	if ( 'AET' === $bshort ) {
		$bnote = 'po dogrywce';
	} elseif ( 'PEN' === $bshort ) {
		$bpen_h = $bdata['score']['penalty']['home'] ?? null;
		$bpen_a = $bdata['score']['penalty']['away'] ?? null;
		$bnote  = ( null !== $bpen_h && null !== $bpen_a )
			? 'karne ' . (int) $bpen_h . ':' . (int) $bpen_a
			: 'po karnych';
	}

	$bshow_score = in_array( $bstate, array( 'ZAKONCZONY', 'LIVE' ), true );
	$bwhen_label = $bwhen( get_post_meta( $bcard_id, 'kickoff', true ) );
	?>
	<a class="bracket-cell bracket-cell--real is-<?php echo esc_attr( strtolower( $bstate ) ); ?>" href="<?php echo esc_url( get_permalink( $bcard_id ) ); ?>"<?php echo $bgraph; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — literały int. ?><?php echo hajlajty_match_lists_card_filter_attrs( $bterms ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escapowane w helperze. ?>>
		<?php if ( 'LIVE' === $bstate ) : ?>
			<span class="bracket-cell__head"><span class="bracket-cell__live">● LIVE</span></span>
		<?php endif; ?>
		<span class="bracket-cell__team">
			<?php if ( '' !== $bhome_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $bhome_flag ); ?>" alt="" /><?php else : ?><span class="bracket-cell__qmark" aria-hidden="true">?</span><?php endif; ?>
			<span class="bracket-cell__code"><?php echo esc_html( $bhome_code ); ?></span>
			<?php if ( $bshow_score ) : ?><b class="bracket-cell__g"><?php echo esc_html( null === $bgh ? '–' : $bgh ); ?></b><?php endif; ?>
		</span>
		<span class="bracket-cell__team">
			<?php if ( '' !== $baway_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $baway_flag ); ?>" alt="" /><?php else : ?><span class="bracket-cell__qmark" aria-hidden="true">?</span><?php endif; ?>
			<span class="bracket-cell__code"><?php echo esc_html( $baway_code ); ?></span>
			<?php if ( $bshow_score ) : ?><b class="bracket-cell__g"><?php echo esc_html( null === $bga ? '–' : $bga ); ?></b><?php endif; ?>
		</span>
		<?php if ( '' !== $bwhen_label ) : ?><span class="bracket-cell__when"><?php echo esc_html( $bwhen_label ); ?></span><?php endif; ?>
		<?php if ( $bshow_score && '' !== $bnote ) : ?><span class="bracket-cell__note"><?php echo esc_html( $bnote ); ?></span><?php endif; ?>
	</a>
	<?php
else :
	// Obsada nieustalona: ten sam układ co komórka realna, ale flagę zastępuje
	// kwadracik „?". Data+godzina z harmonogramu FIFA. PUSTE atrybuty filtra.
	$bwhen_label  = $bwhen( $bcell['kickoff'] );
	$bempty_attrs = hajlajty_match_lists_card_filter_attrs(
		array(
			'home' => null,
			'away' => null,
		)
	);
	?>
	<div class="bracket-cell bracket-cell--<?php echo esc_attr( $bmode ); ?>"<?php echo $bgraph; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — literały int. ?><?php echo $bempty_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escapowane w helperze. ?>>
		<span class="bracket-cell__team"><span class="bracket-cell__qmark" aria-hidden="true">?</span></span>
		<span class="bracket-cell__team"><span class="bracket-cell__qmark" aria-hidden="true">?</span></span>
		<?php if ( '' !== $bwhen_label ) : ?><span class="bracket-cell__when"><?php echo esc_html( $bwhen_label ); ?></span><?php endif; ?>
	</div>
	<?php
endif;
