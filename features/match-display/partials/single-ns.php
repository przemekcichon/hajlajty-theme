<?php
/**
 * Wariant ZAPOWIEDŹ (NS/TBD/PST) — render single CPT „mecz".
 * Wywoływany przez single-mecz.php (get_template_part z $args: post_id, data).
 *
 * Render READ-ONLY z match_data + taksonomii. Rdzeń meczu (teams/kickoff/round/
 * status) zawsze jest; goals=null, brak events/statistics; lineups zwykle
 * NIEOBECNE (renderujemy składy „gdy są"). Warstwa DANYCH reużywana z 3a/3b
 * (helpers/lookups/derive) — NIC nie modyfikujemy. Markup zduplikowany z designu
 * zapowiedzi i z single-ft.php (DRY wolno łamać, VSA nie).
 *
 * WYCIĘTE w 3c (brak danych / później): Forma zespołów, Historia starć,
 * Głosowanie kibiców, litera grupy (team-slot__sub), stadion.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
$data    = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : hajlajty_get_match_data( $post_id );

$round_pl = hajlajty_lookup_round( $data['round'] ?? null );
$terms    = hajlajty_match_get_team_terms( $post_id );

// --- Czas: PŁASKA meta `kickoff` (Y-m-d H:i:s, UTC) — NIE match_data.kickoff. ---
// Instant absolutny (ISO z offsetem) dla JS; etykiety PL przez wp_date (strefa +
// locale serwisu). Brak meta → degradujemy (bez licznika, bez faktów czasu).
$kickoff_raw = get_post_meta( $post_id, 'kickoff', true );
$kickoff_dt  = ( is_string( $kickoff_raw ) && '' !== $kickoff_raw )
	? date_create_immutable( $kickoff_raw, new DateTimeZone( 'UTC' ) )
	: false;
$kickoff_iso = $kickoff_dt ? $kickoff_dt->format( 'c' ) : '';
$kickoff_ts  = $kickoff_dt ? $kickoff_dt->getTimestamp() : 0;
$date_label  = $kickoff_ts ? wp_date( 'l, j F Y', $kickoff_ts ) : '';
$time_label  = $kickoff_ts ? wp_date( 'H:i', $kickoff_ts ) : '';
$mini_label  = $kickoff_ts ? wp_date( 'j M · H:i', $kickoff_ts ) : '';

// --- Lokalne helpery renderu (closures, jak w single-ft.php) ---
// Flaga przez współdzielony helper (flags.php): mapuje fifa_code (3-lit. FIFA)
// na slug flagcdn (ISO alpha-2). Wcześniej strtolower(fifa_code) dawał 404.
$flag_url = static function ( $term ) {
	return hajlajty_flag_url( $term );
};
$team_name = static function ( $term ) {
	return ( $term instanceof WP_Term ) ? $term->name : '—'; // degraduj: drużyna niewysiana.
};

$home_name   = $team_name( $terms['home'] );
$away_name   = $team_name( $terms['away'] );
$home_flag   = $flag_url( $terms['home'] );
$away_flag   = $flag_url( $terms['away'] );
$match_label = $home_name . ' – ' . $away_name;

// Slug meczu = stabilny identyfikator fav/remind (Faza 4 hajlajty-user;
// w 3c TYLKO markup data-*, brak JS/CSS tej warstwy — ją dostarcza hajlajty-user).
$match_slug = get_post_field( 'post_name', $post_id );
?>
<div class="watch-top container">
	<a class="back-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<svg viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></svg>
		Wróć
	</a>
	<?php if ( '' !== $round_pl ) : ?>
		<span class="crumb">Zapowiedzi · <b><?php echo esc_html( $round_pl ); ?></b></span>
	<?php endif; ?>
</div>

<main class="watch container">
	<div class="watch__grid">
		<div class="watch__main">

			<!-- ===== PLAYER OCZEKIWANIA (ekran odliczania zamiast wideo) ===== -->
			<section class="preview reveal" aria-label="<?php echo esc_attr( 'Odliczanie do meczu ' . $match_label ); ?>">
				<span class="preview__badge"><span class="dot"></span> Zapowiedź</span>
				<?php if ( '' !== $mini_label ) : ?>
					<span class="preview__live-soon"><?php echo esc_html( $mini_label ); ?></span>
				<?php endif; ?>

				<div class="preview__inner">
					<div class="preview__match">
						<div class="team-slot">
							<?php if ( '' !== $home_flag ) : ?><img class="country-flag team-slot__flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?>
							<span class="team-slot__name"><?php echo esc_html( $home_name ); ?></span>
						</div>
						<span class="preview__vs">VS</span>
						<div class="team-slot">
							<?php if ( '' !== $away_flag ) : ?><img class="country-flag team-slot__flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?>
							<span class="team-slot__name"><?php echo esc_html( $away_name ); ?></span>
						</div>
					</div>

					<?php if ( '' !== $kickoff_iso ) : ?>
						<span class="preview__count-label">Pierwszy gwizdek za</span>
						<div class="countdown countdown--hero" data-countdown data-kickoff="<?php echo esc_attr( $kickoff_iso ); ?>">
							<div class="unit"><span class="val" data-d>00</span><span class="lab">dni</span></div>
							<div class="unit"><span class="val" data-h>00</span><span class="lab">godz</span></div>
							<div class="unit"><span class="val" data-m>00</span><span class="lab">min</span></div>
							<div class="unit"><span class="val" data-s>00</span><span class="lab">sek</span></div>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<!-- ===== AKCJE KIBICA (markup data-* dla hajlajty-fav.js — Faza 4) ===== -->
			<div class="hf-actions">
				<button class="hf-btn hf-btn--fav" type="button" data-fav="<?php echo esc_attr( $match_slug ); ?>" data-label="<?php echo esc_attr( $match_label ); ?>">
					<svg viewBox="0 0 24 24"><path d="M12 20.5 4.2 12.7a4.7 4.7 0 0 1 6.6-6.6l1.2 1.2 1.2-1.2a4.7 4.7 0 0 1 6.6 6.6z"/></svg>
					<span class="hf-btn__txt">Dodaj do ulubionych</span>
				</button>
				<button class="hf-btn hf-btn--remind" type="button" data-remind="<?php echo esc_attr( $match_slug ); ?>" data-label="<?php echo esc_attr( $match_label ); ?>">
					<svg viewBox="0 0 24 24"><path d="M18 8.4A6 6 0 0 0 6 8.4c0 6.6-2.6 8.6-2.6 8.6h17.2S18 15 18 8.4"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
					<span class="hf-btn__txt" data-remind-text>Przypomnij mi</span>
				</button>
			</div>

			<!-- ===== METADANE MECZU ===== -->
			<div class="match-head">
				<?php if ( '' !== $round_pl ) : ?>
					<span class="match-phase">⚽ <?php echo esc_html( $round_pl ); ?></span>
				<?php endif; ?>
				<h1 class="match-title"><?php echo esc_html( $match_label ); ?></h1>
				<?php if ( '' !== $date_label || '' !== $time_label ) : ?>
					<div class="match-facts">
						<?php if ( '' !== $date_label ) : ?>
							<span class="fact"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4M16 2v4M3 10h18"/></svg> <b><?php echo esc_html( $date_label ); ?></b></span>
						<?php endif; ?>
						<?php if ( '' !== $time_label ) : ?>
							<span class="fact"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> <b><?php echo esc_html( $time_label ); ?></b> (czasu PL)</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php
			// ===== SKŁADY — tylko gdy lineups istnieją (NS zwykle ich nie ma). =====
			// Markup half-pitch zduplikowany z single-ft.php; BEZ wskaźników zdarzeń
			// (NS nie ma events → indeks pusty). Trener/kolory koszulek jeśli w danych.
			$lineups  = isset( $data['lineups'] ) && is_array( $data['lineups'] ) ? $data['lineups'] : array();
			$has_home = isset( $lineups['home'] ) && is_array( $lineups['home'] );
			$has_away = isset( $lineups['away'] ) && is_array( $lineups['away'] );

			// Rozkład startXI po `grid` ("rząd:kolumna"); fallback do kubełków G/D/M/F.
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

			if ( $has_home || $has_away ) :
				?>
				<div class="panels">
					<section class="panel reveal">
						<h2 class="panel__title"><span class="kicker-dot"></span> Składy</h2>

						<!-- Sprite koszulki (reużywalny w obu połowach boiska) -->
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
														<span class="pl"><span class="pl__badge"><svg class="jersey" viewBox="0 0 44 40"><use href="#jersey"/></svg><span class="pl__num"><?php echo esc_html( $p['number'] ?? '' ); ?></span></span><span class="pl__name"><?php echo esc_html( $p['name'] ?? '' ); ?></span></span>
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
													<?php foreach ( $subs as $p ) : ?>
														<li class="squad-row">
															<span class="squad-row__num"><?php echo esc_html( $p['number'] ?? '' ); ?></span>
															<span class="squad-row__name"><?php echo esc_html( $p['name'] ?? '' ); ?></span>
															<?php $pos_pl = hajlajty_lookup_position( $p['pos'] ?? null ); ?>
															<span class="squad-row__end">
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
				</div>
			<?php endif; ?>

		</div><!-- /.watch__main -->

		<?php
		// ===== PRAWY ASIDE: „Inne mecze" w tych samych rozgrywkach (nadchodzące). =====
		// Filtr: kickoff >= teraz (UTC), te same `rozgrywki`, bez bieżącego; orderby
		// kickoff rosnąco (najbliższe u góry). Drużyny rozwiązane JEDNYM get_terms
		// (bez N+1), jak w single-ft.php. Mini-licznik tyka po stronie klienta.
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
		$other = new WP_Query( $other_args );

		if ( $other->have_posts() ) :
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
					'post'    => $rp,
					'h_api'   => $h,
					'a_api'   => $a,
					'round'   => hajlajty_lookup_round( $md['round'] ?? null ),
					'ko_iso'  => $dt ? $dt->format( 'c' ) : '',
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
			<aside class="watch__aside">
				<section class="aside-sec">
					<h2 class="aside-sec__title"><span class="kicker-dot"></span> Inne mecze</h2>
					<?php
					foreach ( $cards as $card ) :
						$rp     = $card['post'];
						$hn     = $api_name( $card['h_api'] );
						$an     = $api_name( $card['a_api'] );
						$hf     = $api_flag( $card['h_api'] );
						$af     = $api_flag( $card['a_api'] );
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
			</aside>
			<?php
			wp_reset_postdata();
		endif;
		?>

	</div>
</main>
