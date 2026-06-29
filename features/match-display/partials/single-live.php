<?php
/**
 * Wariant LIVE (1H/HT/2H/ET/BT/P/SUSP/INT/LIVE) — render single CPT „mecz".
 * Wywoływany przez single-mecz.php (get_template_part z $args: post_id, data).
 *
 * Render READ-ONLY z match_data. Sekcje ŻYWE (telebim, oś czasu, statystyki)
 * renderuje WSPÓLNY partial `live-fragment.php` — to samo źródło znacznika, którym
 * karmi się REST endpoint odświeżania (3e-iii). Tu wołamy go po jednej sekcji
 * (`part`), żeby każda trafiła do swojej kolumny BEZ zmiany układu (kotwica
 * `display: contents`). Sekcje STATYCZNE (nagłówek, składy, aside „Inne mecze")
 * zostają inline tutaj — poller ich nie dotyka (zmiany zawodników i tak widać w
 * osi; D-A/D-D). Minuta w faktach nagłówka jest statyczna (odświeża telebim).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
$data    = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : hajlajty_get_match_data( $post_id );

$round_pl = hajlajty_lookup_round( $data['round'] ?? null );
$terms    = hajlajty_match_get_team_terms( $post_id );
$status   = hajlajty_lookup_status( $data['status']['short'] ?? null );

// --- Czas: data meczu z PŁASKIEJ meta `kickoff` (etykieta w faktach). ---
$kickoff_raw = get_post_meta( $post_id, 'kickoff', true );
$kickoff_dt  = ( is_string( $kickoff_raw ) && '' !== $kickoff_raw )
	? date_create_immutable( $kickoff_raw, new DateTimeZone( 'UTC' ) )
	: false;
$date_label  = $kickoff_dt ? wp_date( 'l, j F Y', $kickoff_dt->getTimestamp() ) : '';

// --- Linia statusu LIVE dla FAKTÓW nagłówka (statyczna — telebim ją odświeża). ---
$short       = $data['status']['short'] ?? null;
$elapsed     = $data['status']['elapsed'] ?? null;
$extra       = $data['status']['extra'] ?? null;
$half_labels = array(
	'1H' => '1. połowa',
	'2H' => '2. połowa',
	'ET' => 'Dogrywka',
);
$minute_txt = '';
if ( $status['show_minute'] && null !== $elapsed ) {
	$minute_txt = $elapsed . ( ( null !== $extra && '' !== $extra ) ? '+' . $extra : '' ) . "'";
}
$half_label = ( $status['show_minute'] && isset( $half_labels[ $short ] ) ) ? $half_labels[ $short ] : '';
$live_label = $status['live_label'];

// --- Lokalne helpery renderu (closures, jak w single-ft.php) ---
$flag_url  = static function ( $term ) {
	return hajlajty_flag_url( $term );
};
$team_name = static function ( $term ) {
	return ( $term instanceof WP_Term ) ? $term->name : '—';
};

$home_name   = $team_name( $terms['home'] );
$away_name   = $team_name( $terms['away'] );
$home_flag   = $flag_url( $terms['home'] );
$away_flag   = $flag_url( $terms['away'] );
$match_label = $home_name . ' – ' . $away_name;

$match_slug = get_post_field( 'post_name', $post_id );
?>
<div class="watch-top watch-top--compact container">
	<a class="back-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<svg viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></svg>
		Wróć
	</a>
	<?php if ( '' !== $round_pl ) : ?>
		<span class="match-phase">⚽ <?php echo esc_html( $round_pl ); ?></span>
	<?php endif; ?>
</div>

<main class="watch container">
	<div class="watch__grid">
		<div class="watch__main">

			<?php
			// ===== TELEBIM ===== (żywy — wspólny partial, kotwica #hajlajty-live-board)
			get_template_part(
				'features/match-display/partials/live-fragment',
				null,
				array(
					'post_id' => $post_id,
					'data'    => $data,
					'part'    => 'board',
				)
			);
			?>

			<!-- ===== AKCJE KIBICA (LIVE: tylko fav; markup data-* dla Fazy 4) ===== -->
			<div class="hf-actions">
				<button class="hf-btn hf-btn--fav" type="button" data-fav="<?php echo esc_attr( $match_slug ); ?>" data-label="<?php echo esc_attr( $match_label ); ?>">
					<svg viewBox="0 0 24 24"><path d="M12 20.5 4.2 12.7a4.7 4.7 0 0 1 6.6-6.6l1.2 1.2 1.2-1.2a4.7 4.7 0 0 1 6.6 6.6z"/></svg>
					<span class="hf-btn__txt">Dodaj do ulubionych</span>
				</button>
			</div>

			<!-- ===== METADANE MECZU (LIVE) — statyczne ===== -->
			<div class="match-head match-head--compact">
				<h1 class="match-title"><?php echo esc_html( $match_label ); ?></h1>
				<div class="match-facts">
					<?php if ( '' !== $date_label ) : ?>
						<span class="fact"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4M16 2v4M3 10h18"/></svg> <b><?php echo esc_html( $date_label ); ?></b></span>
					<?php endif; ?>
					<?php if ( $status['show_minute'] && '' !== $minute_txt ) : ?>
						<span class="fact"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> <?php echo esc_html( '' !== $half_label ? $half_label . ', ' : '' ); ?><b><?php echo esc_html( $minute_txt ); ?></b></span>
					<?php elseif ( '' !== (string) $live_label ) : ?>
						<span class="fact"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> <b><?php echo esc_html( $live_label ); ?></b></span>
					<?php endif; ?>
				</div>
			</div>

			<?php
			// ===== PANELE: Składy (statyczne, gdy lineups) + Oś czasu (żywa). =====
			$lineups    = isset( $data['lineups'] ) && is_array( $data['lineups'] ) ? $data['lineups'] : array();
			$has_home   = isset( $lineups['home'] ) && is_array( $lineups['home'] );
			$has_away   = isset( $lineups['away'] ) && is_array( $lineups['away'] );
			$has_lineup = $has_home || $has_away;

			$player_idx = hajlajty_player_event_index( $data['events'] ?? array() );

			// Czy są widoczne zdarzenia osi (Var pominięte) — decyduje o `.panels`,
			// żeby panel nie pojawiał się pusty. Sam markup osi renderuje partial.
			$timeline_visible = array_filter(
				hajlajty_build_timeline( $data['events'] ?? array() ),
				static function ( $it ) {
					return 'var' !== $it['key'];
				}
			);
			$has_timeline = ! empty( $timeline_visible );

			if ( $has_lineup || $has_timeline ) :
				?>
				<div class="panels">

					<?php if ( $has_lineup ) : ?>
						<section class="panel reveal">
							<h2 class="panel__title"><span class="kicker-dot"></span> Składy</h2>
							<?php
							// Markery gol/kartka z indeksu zdarzeń — wspólne dla boiska i ławki.
							$marks_goal_card = static function ( $pid ) use ( $player_idx ) {
								if ( null === $pid || ! isset( $player_idx[ $pid ] ) ) {
									return '';
								}
								$e    = $player_idx[ $pid ];
								$html = '';
								for ( $i = 0; $i < (int) $e['gole']; $i++ ) {
									$html .= '<span class="ind ind--goal">⚽</span>';
								}
								if ( $e['druga_zolta'] > 0 ) {
									$html .= '<span class="ind ind--card yellow"></span><span class="ind ind--card red"></span>';
								} elseif ( $e['czerwona'] > 0 ) {
									$html .= '<span class="ind ind--card red"></span>';
								} elseif ( $e['zolta'] > 0 ) {
									$html .= '<span class="ind ind--card yellow"></span>';
								}
								return $html;
							};
							// Wskaźniki przy koszulce (boisko): markery + strzałka zejścia.
							$player_inds = static function ( $pid ) use ( $marks_goal_card, $player_idx ) {
								$html = $marks_goal_card( $pid );
								if ( null !== $pid && isset( $player_idx[ $pid ] ) && null !== $player_idx[ $pid ]['zszedl'] ) {
									$html .= '<span class="ind ind--sub" title="' . esc_attr( 'Zszedł z boiska, ' . $player_idx[ $pid ]['zszedl'] . "'" ) . '"><svg viewBox="0 0 24 24"><path d="M12 5v14M6 13l6 6 6-6"/></svg></span>';
								}
								return '' !== $html ? '<span class="pl__inds">' . $html . '</span>' : '';
							};
							// Rozkład startXI po `grid`; fallback do kubełków pozycji.
							$pitch_rows = static function ( $start_xi ) {
								if ( empty( $start_xi ) || ! is_array( $start_xi ) ) {
									return array();
								}
								$has_grid = false;
								foreach ( $start_xi as $p ) {
									if ( ! empty( $p['grid'] ) ) {
										$has_grid = true;
										break;
									}
								}
								if ( $has_grid ) {
									$rows = array();
									foreach ( $start_xi as $p ) {
										if ( ! empty( $p['grid'] ) && preg_match( '/^(\d+)\s*:\s*(\d+)$/', $p['grid'], $m ) ) {
											$r = (int) $m[1];
											$c = (int) $m[2];
										} else {
											$r = 99;
											$c = 99;
										}
										$rows[ $r ][] = array(
											'c' => $c,
											'p' => $p,
										);
									}
									ksort( $rows );
									$out = array();
									foreach ( $rows as $list ) {
										usort(
											$list,
											static function ( $a, $b ) {
												return $a['c'] <=> $b['c'];
											}
										);
										$out[] = array_map(
											static function ( $x ) {
												return $x['p'];
											},
											$list
										);
									}
									return $out;
								}
								$buckets = array(
									'G' => array(),
									'D' => array(),
									'M' => array(),
									'F' => array(),
								);
								$other = array();
								foreach ( $start_xi as $p ) {
									$pos = $p['pos'] ?? '';
									if ( isset( $buckets[ $pos ] ) ) {
										$buckets[ $pos ][] = $p;
									} else {
										$other[] = $p;
									}
								}
								$out = array();
								foreach ( array( 'G', 'D', 'M', 'F' ) as $k ) {
									if ( ! empty( $buckets[ $k ] ) ) {
										$out[] = $buckets[ $k ];
									}
								}
								if ( ! empty( $other ) ) {
									$out[] = $other;
								}
								return $out;
							};
							?>
							<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>
								<symbol id="jersey" viewBox="0 0 44 40"><path d="M15 5 L22 8.5 L29 5 L36 9 L41 15 L34 21 L31.5 18 L31.5 38 L12.5 38 L12.5 18 L10 21 L3 15 L8 9 Z"/></symbol>
							</defs></svg>

							<div class="lineup-tabs" id="lineupTabs">
								<button class="lineup-tab is-active" data-pane="home" type="button"><?php if ( '' !== $home_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?> <?php echo esc_html( $home_name ); ?></button>
								<button class="lineup-tab" data-pane="away" type="button"><?php if ( '' !== $away_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?> <?php echo esc_html( $away_name ); ?></button>
							</div>

							<?php foreach ( array( 'home', 'away' ) as $side ) : ?>
								<div class="lineup-pane<?php echo 'home' === $side ? ' is-active' : ''; ?>" data-pane="<?php echo esc_attr( $side ); ?>">
									<?php
									$lu        = isset( $lineups[ $side ] ) && is_array( $lineups[ $side ] ) ? $lineups[ $side ] : array();
									$formation = $lu['formation'] ?? '';
									$start_xi  = isset( $lu['startXI'] ) && is_array( $lu['startXI'] ) ? $lu['startXI'] : array();
									$subs      = isset( $lu['substitutes'] ) && is_array( $lu['substitutes'] ) ? $lu['substitutes'] : array();
									$coach     = $lu['coach']['name'] ?? '';
									$primary   = $lu['colors']['player']['primary'] ?? '';
									$number    = $lu['colors']['player']['number'] ?? '';
									$border    = $lu['colors']['player']['border'] ?? '';
									$hex       = static function ( $v ) {
										return ( is_string( $v ) && preg_match( '/^[0-9a-fA-F]{3,8}$/', $v ) ) ? '#' . $v : '';
									};
									$pitch_vars = array();
									if ( '' !== $hex( $primary ) ) {
										$pitch_vars[] = '--team-' . $side . ': ' . $hex( $primary );
									}
									if ( '' !== $hex( $number ) ) {
										$pitch_vars[] = '--team-' . $side . '-num: ' . $hex( $number );
									}
									if ( '' !== $hex( $border ) ) {
										$pitch_vars[] = '--team-' . $side . '-border: ' . $hex( $border );
									}
									$pitch_style = $pitch_vars ? ' style="' . esc_attr( implode( '; ', $pitch_vars ) ) . '"' : '';
									?>
									<div class="lineup-split">
										<div class="lineup-col">
											<div class="lineup-meta">
												<span>Ustawienie: <b><?php echo esc_html( '' !== $formation ? $formation : '—' ); ?></b></span>
												<?php if ( '' !== $coach ) : ?>
													<span>Trener: <b><?php echo esc_html( $coach ); ?></b></span>
												<?php endif; ?>
											</div>
											<div class="half-pitch" data-team="<?php echo esc_attr( $side ); ?>"<?php echo $pitch_style; // już esc_attr ?>>
												<?php foreach ( $pitch_rows( $start_xi ) as $row ) : ?>
													<div class="hp-row">
														<?php foreach ( $row as $p ) : ?>
															<span class="pl"><span class="pl__badge"><svg class="jersey" viewBox="0 0 44 40"><use href="#jersey"/></svg><span class="pl__num"><?php echo esc_html( $p['number'] ?? '' ); ?></span></span><?php echo $player_inds( isset( $p['id'] ) ? (int) $p['id'] : null ); // phpcs:ignore — HTML wskaźników z esc ?><span class="pl__name"><?php echo esc_html( $p['name'] ?? '' ); ?></span></span>
														<?php endforeach; ?>
													</div>
												<?php endforeach; ?>
											</div>
										</div>

										<div class="lineup-col">
											<div class="squad-list">
												<div class="squad-block">
													<h3 class="squad-block__title"><svg viewBox="0 0 24 24"><path d="M5 19h14M7 19v-7l-2-1 2-4h3a2 2 0 0 0 4 0h3l2 4-2 1v7"/></svg> Ławka rezerwowych</h3>
													<ul class="squad-rows">
														<?php
														foreach ( $subs as $p ) :
															$pid    = isset( $p['id'] ) ? (int) $p['id'] : null;
															$in_min = ( null !== $pid && isset( $player_idx[ $pid ] ) && null !== $player_idx[ $pid ]['wszedl'] ) ? $player_idx[ $pid ]['wszedl'] : null;
															?>
															<li class="squad-row">
																<span class="squad-row__num"><?php echo esc_html( $p['number'] ?? '' ); ?></span>
																<span class="squad-row__name"><?php echo esc_html( $p['name'] ?? '' ); ?></span>
																<?php $pos_pl = hajlajty_lookup_position( $p['pos'] ?? null ); ?>
																<span class="squad-row__end">
																	<?php echo $marks_goal_card( $pid ); // phpcs:ignore — HTML markerów z esc ?>
																	<?php if ( null !== $in_min ) : ?>
																		<span class="squad-row__in"><svg viewBox="0 0 24 24"><path d="M12 19V5M6 11l6-6 6 6"/></svg><?php echo esc_html( $in_min . "'" ); ?></span>
																	<?php endif; ?>
																	<?php if ( '' !== $pos_pl ) : ?>
																		<span class="squad-row__pos"><?php echo esc_html( $pos_pl ); ?></span>
																	<?php endif; ?>
																</span>
															</li>
														<?php endforeach; ?>
													</ul>
												</div>
											</div>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</section>
					<?php endif; ?>

					<?php
					// ===== OŚ CZASU ===== (żywa — wspólny partial, kotwica #hajlajty-live-timeline)
					get_template_part(
						'features/match-display/partials/live-fragment',
						null,
						array(
							'post_id' => $post_id,
							'data'    => $data,
							'part'    => 'timeline',
						)
					);
					?>

				</div>
			<?php endif; ?>

		</div><!-- /.watch__main -->

		<?php
		// ===== PRAWY ASIDE: Statystyki (żywe, partial) + „Inne mecze" (statyczne). =====
		$stat_rows = hajlajty_build_stat_rows( $data );
		$has_stats = ! empty( $stat_rows );

		// „Inne mecze" w tych samych rozgrywkach (kickoff >= teraz), bez bieżącego.
		$roz     = get_the_terms( $post_id, 'rozgrywki' );
		$roz_ids = ( is_array( $roz ) && ! is_wp_error( $roz ) ) ? wp_list_pluck( $roz, 'term_id' ) : array();
		$other_args = array(
			'post_type'           => 'mecz',
			'posts_per_page'      => 4,
			'post__not_in'        => array( $post_id ),
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'meta_query'          => array(
				'kick' => array(
					'key'     => 'kickoff',
					'value'   => gmdate( 'Y-m-d H:i:s' ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			),
			'orderby'             => array( 'kick' => 'ASC' ),
		);
		if ( ! empty( $roz_ids ) ) {
			$other_args['tax_query'] = array(
				array(
					'taxonomy' => 'rozgrywki',
					'field'    => 'term_id',
					'terms'    => $roz_ids,
				),
			);
		}
		$other        = new WP_Query( $other_args );
		$has_other    = $other->have_posts();
		$has_anything = $has_stats || $has_other;

		if ( $has_anything ) :
			?>
			<aside class="watch__aside">

				<?php
				// ===== STATYSTYKI ===== (żywe — wspólny partial, kotwica #hajlajty-live-stats)
				get_template_part(
					'features/match-display/partials/live-fragment',
					null,
					array(
						'post_id' => $post_id,
						'data'    => $data,
						'part'    => 'stats',
					)
				);
				?>

				<?php
				if ( $has_other ) :
					$cards   = array();
					$api_ids = array();
					foreach ( $other->posts as $rp ) {
						$md = hajlajty_get_match_data( $rp->ID );
						$h  = isset( $md['teams']['home']['api_id'] ) ? (int) $md['teams']['home']['api_id'] : 0;
						$a  = isset( $md['teams']['away']['api_id'] ) ? (int) $md['teams']['away']['api_id'] : 0;
						if ( $h ) {
							$api_ids[] = $h;
						}
						if ( $a ) {
							$api_ids[] = $a;
						}
						$ko = (string) get_post_meta( $rp->ID, 'kickoff', true );
						$dt = ( '' !== $ko ) ? date_create_immutable( $ko, new DateTimeZone( 'UTC' ) ) : false;
						$cards[] = array(
							'post'   => $rp,
							'h_api'  => $h,
							'a_api'  => $a,
							'round'  => hajlajty_lookup_round( $md['round'] ?? null ),
							'ko_iso' => $dt ? $dt->format( 'c' ) : '',
						);
					}

					$term_by_api = array();
					$api_ids     = array_values( array_unique( array_filter( $api_ids ) ) );
					if ( ! empty( $api_ids ) ) {
						$tt = get_terms(
							array(
								'taxonomy'   => 'druzyna',
								'hide_empty' => false,
								'meta_query' => array(
									array(
										'key'     => 'api_id',
										'value'   => array_map( 'strval', $api_ids ),
										'compare' => 'IN',
									),
								),
							)
						);
						if ( ! is_wp_error( $tt ) ) {
							foreach ( $tt as $t ) {
								$term_by_api[ (int) get_term_meta( $t->term_id, 'api_id', true ) ] = $t;
							}
						}
					}

					$api_term = static function ( $api ) use ( $term_by_api ) {
						return ( ! empty( $api ) && isset( $term_by_api[ $api ] ) ) ? $term_by_api[ $api ] : null;
					};
					$api_name = static function ( $api ) use ( $api_term ) {
						$t = $api_term( $api );
						return ( $t instanceof WP_Term ) ? $t->name : '—';
					};
					$api_flag = static function ( $api ) use ( $api_term, $flag_url ) {
						return $flag_url( $api_term( $api ) );
					};
					?>
					<section class="aside-sec">
						<h2 class="aside-sec__title"><span class="kicker-dot"></span> Inne mecze</h2>
						<?php
						foreach ( $cards as $card ) :
							$rp = $card['post'];
							$hn = $api_name( $card['h_api'] );
							$an = $api_name( $card['a_api'] );
							$hf = $api_flag( $card['h_api'] );
							$af = $api_flag( $card['a_api'] );
							?>
							<a class="mini-match" href="<?php echo esc_url( get_permalink( $rp ) ); ?>">
								<?php if ( '' !== $card['round'] ) : ?>
									<span class="mini-match__phase"><?php echo esc_html( $card['round'] ); ?></span>
								<?php endif; ?>
								<div class="mini-match__row">
									<span class="mini-team"><?php if ( '' !== $hf ) : ?><img class="country-flag" src="<?php echo esc_url( $hf ); ?>" alt="" /><?php endif; ?><span class="mini-team__code"><?php echo esc_html( $hn ); ?></span></span>
									<span class="mini-clock"<?php echo '' !== $card['ko_iso'] ? ' data-mini data-kickoff="' . esc_attr( $card['ko_iso'] ) . '"' : ''; ?>><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span data-mini-val>—</span></span>
									<span class="mini-team away"><?php if ( '' !== $af ) : ?><img class="country-flag" src="<?php echo esc_url( $af ); ?>" alt="" /><?php endif; ?><span class="mini-team__code"><?php echo esc_html( $an ); ?></span></span>
								</div>
							</a>
						<?php endforeach; ?>
					</section>
					<?php
					wp_reset_postdata();
				endif;
				?>

			</aside>
			<?php
		endif;
		?>

	</div>
</main>
