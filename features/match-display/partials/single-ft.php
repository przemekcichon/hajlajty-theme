<?php
/**
 * Wariant ZAKOŃCZONY (skrót / po meczu) — render single CPT „mecz".
 * Wywoływany przez single-mecz.php (get_template_part z $args: post_id, data).
 *
 * Render READ-ONLY z match_data + taksonomii + ACF skrótu. Tłumaczenia RAW→PL
 * przez lookups.php (3a); YouTube ID przez derive.php. Drużyny rozwiązywane po
 * api_id do termu (helpers.php) — null = drużyna niewysiana, degradujemy bez
 * fatala. E3 dostarcza: pasek powrotu, player16, ibar, zakładki + statystyki.
 * Oś czasu (E4), składy (E5) i prawy aside (E6) to placeholdery do wypełnienia.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
$data    = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : hajlajty_get_match_data( $post_id );

$state    = hajlajty_lookup_status( $data['status']['short'] ?? null )['state'];
$round_pl = hajlajty_lookup_round( $data['round'] ?? null );
$terms    = hajlajty_match_get_team_terms( $post_id );

// ACF skrótu (get_field gdy ACF aktywny; fallback na surowe meta).
$skrot_url = function_exists( 'get_field' ) ? get_field( 'skrot_url', $post_id ) : get_post_meta( $post_id, 'skrot_url', true );
$skrot_dur = function_exists( 'get_field' ) ? get_field( 'skrot_duration', $post_id ) : get_post_meta( $post_id, 'skrot_duration', true );
$yt_id     = hajlajty_youtube_id( is_string( $skrot_url ) ? $skrot_url : '' );

// Źródło wideo = taksonomia `kanal` (NIE ACF). Pomijamy span, gdy brak termu.
$kanal_terms = get_the_terms( $post_id, 'kanal' );
$kanal_name  = ( is_array( $kanal_terms ) && ! is_wp_error( $kanal_terms ) && ! empty( $kanal_terms ) ) ? $kanal_terms[0]->name : '';

$goals_home = $data['goals']['home'] ?? null;
$goals_away = $data['goals']['away'] ?? null;

// --- Lokalne helpery renderu (closures: brak redeklaracji między pętlami) ---
$flag_url = static function ( $term ) {
	if ( ! ( $term instanceof WP_Term ) ) {
		return '';
	}
	$code = strtolower( (string) get_term_meta( $term->term_id, 'fifa_code', true ) );
	return '' !== $code ? 'https://flagcdn.com/' . $code . '.svg' : '';
};
$team_name = static function ( $term ) {
	return ( $term instanceof WP_Term ) ? $term->name : '—'; // degraduj: drużyna niewysiana.
};
$team_code = static function ( $term ) use ( $team_name ) {
	if ( ! ( $term instanceof WP_Term ) ) {
		return '—';
	}
	$code = strtoupper( (string) get_term_meta( $term->term_id, 'fifa_code', true ) );
	return '' !== $code ? $code : $term->name;
};

$home_name = $team_name( $terms['home'] );
$away_name = $team_name( $terms['away'] );
$home_flag = $flag_url( $terms['home'] );
$away_flag = $flag_url( $terms['away'] );
$match_label = $home_name . ' – ' . $away_name;
?>
<div class="watch-top container">
	<a class="back-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<svg viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></svg>
		Wróć
	</a>
	<?php if ( '' !== $round_pl ) : ?>
		<span class="crumb">Skróty · <b><?php echo esc_html( $round_pl ); ?></b></span>
	<?php endif; ?>
</div>

<main class="watch container">
	<div class="watch__grid">
		<div class="watch__main">

			<!-- ===== PLAYER 16:9 (osadzony skrót — YouTube przez iframe) ===== -->
			<div class="player16 reveal" id="player"<?php echo $yt_id ? ' data-yt="' . esc_attr( $yt_id ) . '"' : ''; ?> data-title="<?php echo esc_attr( 'Skrót meczu ' . $match_label ); ?>">
				<?php if ( $yt_id ) : ?>
					<button class="player16__facade" id="playBtn" type="button" aria-label="<?php echo esc_attr( 'Odtwórz skrót meczu ' . $match_label ); ?>">
						<span class="player16__src"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23 7.5a3 3 0 0 0-2.1-2.1C19 4.8 12 4.8 12 4.8s-7 0-8.9.6A3 3 0 0 0 1 7.5C.4 9.4.4 12 .4 12s0 2.6.6 4.5a3 3 0 0 0 2.1 2.1c1.9.6 8.9.6 8.9.6s7 0 8.9-.6a3 3 0 0 0 2.1-2.1c.6-1.9.6-4.5.6-4.5s0-2.6-.6-4.5z"/><path fill="currentColor" d="M9.8 15.3V8.7l5.7 3.3z" style="fill:#000"/></svg> Oficjalny skrót</span>
						<span class="player16__play"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></span>
						<?php if ( ! empty( $skrot_dur ) ) : ?>
							<span class="player16__dur"><?php echo esc_html( $skrot_dur ); ?></span>
						<?php endif; ?>
					</button>
				<?php else : ?>
					<div class="player16__empty">Skrót wideo pojawi się wkrótce.</div>
				<?php endif; ?>
			</div>

			<!-- ===== BELKA INTERAKCJI (pod playerem) ===== -->
			<div class="ibar">
				<div class="ibar__main">
					<div class="ibar__tags">
						<?php if ( '' !== $round_pl ) : ?>
							<span class="ibar__phase">⚽ <?php echo esc_html( $round_pl ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $kanal_name ) : ?>
							<span class="ibar__source" title="Materiał opublikowany przez kanał">
								<svg viewBox="0 0 24 24"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8z"/><path d="M10 15V9l5.2 3z"/></svg>
								Źródło wideo: <b><?php echo esc_html( $kanal_name ); ?></b>
							</span>
						<?php endif; ?>
					</div>
					<div class="ibar__match">
						<span class="ibar__team">
							<?php if ( '' !== $home_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?>
							<span class="tname"><?php echo esc_html( $home_name ); ?></span>
						</span>
						<span class="ibar__score"><?php echo esc_html( null === $goals_home ? '–' : $goals_home ); ?> <i>:</i> <?php echo esc_html( null === $goals_away ? '–' : $goals_away ); ?></span>
						<span class="ibar__team away">
							<?php if ( '' !== $away_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?>
							<span class="tname"><?php echo esc_html( $away_name ); ?></span>
						</span>
						<?php if ( 'ZAKONCZONY' === $state ) : ?>
							<span class="ibar__ft">KONIEC</span>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- ===== ZAKŁADKI ===== -->
			<div class="tabs" role="tablist" aria-label="Szczegóły meczu">
				<button class="tab is-active" data-tab="timeline" role="tab" aria-selected="true" type="button"><svg viewBox="0 0 24 24"><path d="M12 8v4l2.5 2.5"/><circle cx="12" cy="12" r="9"/></svg> Oś czasu</button>
				<button class="tab" data-tab="lineups" role="tab" aria-selected="false" type="button"><svg viewBox="0 0 24 24"><path d="M6 4h12v3a6 6 0 0 1-12 0z"/><path d="M9 14h6M10 14v4M14 14v4M8 21h8"/></svg> Składy</button>
				<button class="tab" data-tab="stats" role="tab" aria-selected="false" type="button"><svg viewBox="0 0 24 24"><path d="M5 20V10M12 20V4M19 20v-7"/></svg> Statystyki</button>
			</div>

			<!-- ===== PANELE ZAKŁADEK ===== -->
			<div class="tabpanels">

				<!-- OŚ CZASU (narastający wynik — derive.php; najnowsze u góry) -->
				<section class="tabpanel is-active" data-tab="timeline" role="tabpanel" aria-label="Oś czasu">
					<?php
					$timeline = hajlajty_build_timeline( $data['events'] ?? array() );

					// Ikona per semantyczny klucz (render dokleja emoji — lookups go nie zna).
					$event_icon = static function ( $key ) {
						switch ( $key ) {
							case 'goal':
							case 'penalty_goal':
							case 'own_goal':
								return '⚽';
							case 'yellow':
								return '🟨';
							case 'red':
							case 'second_yellow':
								return '🟥';
							case 'subst':
								return '⇄';
							case 'missed_penalty':
								return '❌';
							default:
								return '•';
						}
					};

					// Minuta + doliczony czas: „45+1'", inaczej „58'".
					$minute_txt = static function ( $item ) {
						$min = $item['minute'];
						if ( null === $min ) {
							return '';
						}
						$extra = $item['extra'];
						return ( null !== $extra && '' !== $extra ) ? ( $min . '+' . $extra . "'" ) : ( $min . "'" );
					};

					// Najnowsze u góry: kolejność MALEJĄCA (wynik narastający policzony rosnąco).
					$timeline_desc = array_reverse( $timeline );
					$visible = array_filter(
						$timeline_desc,
						static function ( $it ) {
							return 'var' !== $it['key']; // TODO VAR (derive.php): eventy Var pomijamy.
						}
					);
					?>
					<?php if ( empty( $visible ) ) : ?>
						<p style="color: var(--text-muted);">Brak zarejestrowanych zdarzeń.</p>
					<?php else : ?>
						<div class="timeline">
							<?php
							foreach ( $visible as $item ) :
								$side    = $item['side'];
								$term    = $terms[ $side ];
								$tflag   = $flag_url( $term );
								$tname   = $team_name( $term );
								$is_goal = in_array( $item['key'], array( 'goal', 'penalty_goal', 'own_goal', 'missed_penalty' ), true );
								$is_sub  = ( 'subst' === $item['key'] );

								// Tytuł: zmiana → po drużynie; reszta → po zawodniku (gdy jest).
								if ( $is_sub ) {
									$title = $item['label'] . ' — ' . $tname;
								} elseif ( ! empty( $item['player'] ) ) {
									$title = $item['label'] . ' — ' . $item['player'];
								} else {
									$title = $item['label'];
								}
								?>
								<div class="tl-item">
									<span class="tl-min"><?php echo esc_html( $minute_txt( $item ) ); ?></span>
									<span class="tl-rail"><span class="tl-node<?php echo $item['counts'] ? ' is-goal' : ''; ?>"><?php echo $event_icon( $item['key'] ); // phpcs:ignore — statyczne emoji ?></span></span>
									<div class="tl-content">
										<div class="tl-row">
											<span class="tl-title"><?php echo esc_html( $title ); ?></span>
											<?php if ( is_array( $item['score'] ) ) : ?>
												<span class="tl-score"><?php echo esc_html( $item['score']['home'] . ':' . $item['score']['away'] ); ?></span>
											<?php endif; ?>
										</div>
										<span class="tl-sub">
											<?php if ( '' !== $tflag ) : ?><img class="country-flag" src="<?php echo esc_url( $tflag ); ?>" alt="" /><?php endif; ?>
											<?php
											if ( $is_sub ) {
												// player=wchodzący, assist=schodzący (potwierdzone empirycznie).
												$in  = $item['player'] ? $item['player'] : '—';
												$out = $item['assist'] ? $item['assist'] : '—';
												echo esc_html( $in . ' za ' . $out );
											} elseif ( $is_goal && ! empty( $item['assist'] ) ) {
												echo esc_html( $tname . ' · asysta ' . $item['assist'] );
											} else {
												echo esc_html( $tname );
											}
											?>
										</span>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</section>

				<!-- SKŁADY — render w E5 (half-pitch + ławka + wskaźniki) -->
				<section class="tabpanel" data-tab="lineups" role="tabpanel" aria-label="Składy">
					<p style="color: var(--text-muted);">Składy (boisko + ławka) dochodzą w E5.</p>
				</section>

				<!-- STATYSTYKI -->
				<section class="tabpanel" data-tab="stats" role="tabpanel" aria-label="Statystyki">
					<?php
					// Jawna lista renderu — klucze VERBATIM (case-sensitive), w tej kolejności.
					$stat_keys = array( 'Ball Possession', 'Total Shots', 'Shots on Goal', 'Fouls', 'Corner Kicks', 'Offsides', 'Total passes' );
					$stats_home = ( isset( $data['statistics']['home'] ) && is_array( $data['statistics']['home'] ) ) ? $data['statistics']['home'] : array();
					$stats_away = ( isset( $data['statistics']['away'] ) && is_array( $data['statistics']['away'] ) ) ? $data['statistics']['away'] : array();

					// Wartość do wyświetlenia: surowa (z „%" gdy string), null → „0".
					$stat_disp = static function ( $v ) {
						if ( null === $v ) {
							return '0';
						}
						return is_string( $v ) ? $v : (string) $v;
					};

					// Zbierz wiersze: pomiń, gdy klucza nie ma po ŻADNEJ stronie.
					$rows = array();
					foreach ( $stat_keys as $key ) {
						$has_home = array_key_exists( $key, $stats_home );
						$has_away = array_key_exists( $key, $stats_away );
						if ( ! $has_home && ! $has_away ) {
							continue; // brak klucza → pomiń wiersz.
						}
						$rows[] = array(
							'label' => hajlajty_lookup_stat_label( $key ),
							'vh'    => $has_home ? $stats_home[ $key ] : null,
							'va'    => $has_away ? $stats_away[ $key ] : null,
						);
					}
					?>
					<?php if ( empty( $rows ) ) : ?>
						<p style="color: var(--text-muted);">Brak statystyk dla tego meczu.</p>
					<?php else : ?>
						<div class="stats-wrap">
							<div class="stats-head">
								<span class="side home"><span class="swatch home"></span><?php if ( '' !== $home_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?> <?php echo esc_html( $team_code( $terms['home'] ) ); ?></span>
								<span class="side away"><?php echo esc_html( $team_code( $terms['away'] ) ); ?> <?php if ( '' !== $away_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?><span class="swatch away"></span></span>
							</div>
							<?php
							foreach ( $rows as $row ) :
								// Słupek = proporcja po sparsowanej liczbie (JS liczy h/(h+a)); null → 0.
								$nh = ( null === $row['vh'] ) ? 0 : (float) $row['vh'];
								$na = ( null === $row['va'] ) ? 0 : (float) $row['va'];
								?>
								<div class="stat" data-h="<?php echo esc_attr( $nh ); ?>" data-a="<?php echo esc_attr( $na ); ?>">
									<div class="stat__top">
										<span class="vh"><?php echo esc_html( $stat_disp( $row['vh'] ) ); ?></span>
										<span class="lab"><?php echo esc_html( $row['label'] ); ?></span>
										<span class="va"><?php echo esc_html( $stat_disp( $row['va'] ) ); ?></span>
									</div>
									<div class="stat__bar"><span class="stat__fill home"></span><span class="stat__fill away"></span></div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</section>

			</div>
		</div><!-- /.watch__main -->

		<?php // PRAWY ASIDE (Inne skróty) — render w E6. ?>

	</div>
</main>
