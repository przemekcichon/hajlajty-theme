<?php
/**
 * Wariant ZAKOŃCZONY (skrót / po meczu) — render single CPT „mecz".
 * Wywoływany przez single-mecz.php (get_template_part z $args: post_id, data).
 *
 * Render READ-ONLY z match_data + taksonomii + ACF skrótu. Tłumaczenia RAW→PL
 * przez lookups.php (3a); YouTube ID przez derive.php. Drużyny rozwiązywane po
 * api_id do termu (helpers.php) — null = drużyna niewysiana, degradujemy bez
 * fatala. Dostarcza: pasek powrotu (etap), player16 z nakładką „telebim"
 * (drużyny+wynik na środku, metadane w rogach), nagłówek+fakty, zakładki
 * (oś czasu / składy / statystyki) i prawy aside „Inne skróty".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
$data    = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : hajlajty_get_match_data( $post_id );

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
// Flaga przez współdzielony helper (flags.php): mapuje fifa_code (3-lit. FIFA)
// na slug flagcdn (ISO alpha-2). Wcześniej strtolower(fifa_code) dawał 404.
$flag_url = static function ( $term ) {
	return hajlajty_flag_url( $term );
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

// --- Czas: PŁASKA meta `kickoff` (Y-m-d H:i:s, UTC), jak w single-ns.php. ---
// Dwie maski PL przez wp_date (strefa+locale serwisu): róg placeholdera BEZ dnia
// tygodnia, fakty pod spodem Z dniem. Brak meta → degradujemy (pusty string).
$kickoff_raw = get_post_meta( $post_id, 'kickoff', true );
$kickoff_dt  = ( is_string( $kickoff_raw ) && '' !== $kickoff_raw )
	? date_create_immutable( $kickoff_raw, new DateTimeZone( 'UTC' ) )
	: false;
$kickoff_ts  = $kickoff_dt ? $kickoff_dt->getTimestamp() : 0;
$date_corner = $kickoff_ts ? wp_date( 'j M y · H:i', $kickoff_ts ) : ''; // P-górny róg: jak mini zapowiedzi (+ rok 2-cyfr).
$date_full   = $kickoff_ts ? wp_date( 'l, j F Y', $kickoff_ts ) : '';    // fakty pod placeholderem.

// Wynik do wyświetlenia (null → „–"); status meczu = literał renderu (lookup zna
// tylko stan ZAKONCZONY, bez tekstu PL). „Po meczu" = parytet z „LIVE"/„Zapowiedź".
$score_h   = null === $goals_home ? '–' : (string) $goals_home;
$score_a   = null === $goals_away ? '–' : (string) $goals_away;
$score_h1  = $home_name . ' ' . $score_h . '–' . $score_a . ' ' . $away_name; // h1 (SEO/a11y, ukryty wizualnie).
$status_pl = 'Po meczu';
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
			// ===== NAKŁADKA „telebim" placeholdera 16:9 =====
			// JEDNO źródło markupu dla OBU stanów (ze skrótem / bez): parytet szkieletu,
			// różnią się TYLKO 3 elementy (badge L-górny, środek: play vs glif, P-dolny
			// róg: czas trwania vs „…wkrótce"). Nakładka żyje WEWNĄTRZ fasady/stanu
			// pustego, więc znika, gdy iframe gra (`.player16.is-playing .player16__facade
			// { display:none }`). Duplikacja markupu „telebim" z `.board`/`.preview`
			// DOZWOLONA per-slice (DRY wolno łamać, VSA nie).
			$render_overlay = static function ( $has_skrot ) use (
				$home_name,
				$away_name,
				$home_flag,
				$away_flag,
				$score_h,
				$score_a,
				$status_pl,
				$date_corner,
				$kanal_name,
				$skrot_dur
			) {
				?>
				<span class="player16__badge <?php echo $has_skrot ? 'is-ready' : 'is-wait'; ?>">
					<span class="player16__dot"></span><?php echo $has_skrot ? 'Oficjalny skrót' : esc_html( $status_pl ); ?>
				</span>
				<?php if ( '' !== $date_corner ) : ?>
					<span class="player16__date"><?php echo esc_html( $date_corner ); ?></span>
				<?php endif; ?>

				<?php // Środek: home (flaga+nazwa+gole) | play/glif | away — liczba goli POD drużyną. ?>
				<div class="player16__center">
					<div class="team-slot">
						<?php if ( '' !== $home_flag ) : ?><img class="country-flag team-slot__flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?>
						<span class="team-slot__name"><?php echo esc_html( $home_name ); ?></span>
						<span class="player16__goals"><?php echo esc_html( $score_h ); ?></span>
					</div>
					<div class="player16__mid">
						<?php if ( $has_skrot ) : ?>
							<span class="player16__play"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></span>
						<?php else : ?>
							<span class="player16__glyph" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18M8 5v14M16 5v14"/></svg></span>
						<?php endif; ?>
					</div>
					<div class="team-slot">
						<?php if ( '' !== $away_flag ) : ?><img class="country-flag team-slot__flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?>
						<span class="team-slot__name"><?php echo esc_html( $away_name ); ?></span>
						<span class="player16__goals"><?php echo esc_html( $score_a ); ?></span>
					</div>
				</div>

				<?php if ( $has_skrot && '' !== $kanal_name ) : ?>
					<span class="player16__src" title="Materiał opublikowany przez kanał">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23 7.5a3 3 0 0 0-2.1-2.1C19 4.8 12 4.8 12 4.8s-7 0-8.9.6A3 3 0 0 0 1 7.5C.4 9.4.4 12 .4 12s0 2.6.6 4.5a3 3 0 0 0 2.1 2.1c1.9.6 8.9.6 8.9.6s7 0 8.9-.6a3 3 0 0 0 2.1-2.1c.6-1.9.6-4.5.6-4.5s0-2.6-.6-4.5z"/><path fill="currentColor" d="M9.8 15.3V8.7l5.7 3.3z" style="fill:#000"/></svg>
						<span><?php echo esc_html( $kanal_name ); ?></span>
					</span>
				<?php endif; ?>

				<?php if ( $has_skrot ) : ?>
					<?php if ( ! empty( $skrot_dur ) ) : ?>
						<span class="player16__dur"><?php echo esc_html( $skrot_dur ); ?></span>
					<?php endif; ?>
				<?php else : ?>
					<span class="player16__soon">Skrót wideo pojawi się wkrótce</span>
				<?php endif; ?>
				<?php
			};
			?>

			<!-- ===== PLAYER 16:9 (telebim → osadzony skrót YouTube przez iframe) ===== -->
			<div class="player16 reveal" id="player"<?php echo $yt_id ? ' data-yt="' . esc_attr( $yt_id ) . '"' : ''; ?> data-title="<?php echo esc_attr( 'Skrót meczu ' . $match_label ); ?>">
				<?php if ( $yt_id ) : ?>
					<button class="player16__facade" id="playBtn" type="button" aria-label="<?php echo esc_attr( 'Odtwórz skrót meczu ' . $match_label ); ?>">
						<?php $render_overlay( true ); ?>
					</button>
				<?php else : ?>
					<div class="player16__empty">
						<?php $render_overlay( false ); ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- ===== NAGŁÓWEK + FAKTY (pod placeholderem, wzorem zapowiedzi/live) =====
			     h1 = tekst „{home} {gh}–{ga} {away}" DLA SEO/a11y, ukryty wizualnie
			     (--compact). Widoczne pod telebimem tylko fakty: data + status,
			     wyśrodkowane jak w NS/live (NIE dublujemy nazw/wyniku — są na telebimie). -->
			<div class="match-head match-head--compact">
				<h1 class="match-title"><?php echo esc_html( $score_h1 ); ?></h1>
				<div class="match-facts">
					<?php if ( '' !== $date_full ) : ?>
						<span class="fact"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4M16 2v4M3 10h18"/></svg> <b><?php echo esc_html( $date_full ); ?></b></span>
					<?php endif; ?>
					<span class="fact"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> <b><?php echo esc_html( $status_pl ); ?></b></span>
				</div>
			</div>

			<!-- ===== ZAKŁADKI (wspólny pasek — P-d, jedno źródło markupu z single-live) ===== -->
			<?php
			get_template_part(
				'features/match-display/partials/tabs-bar',
				null,
				array( 'active' => 'timeline' )
			);
			?>

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
												// KIERUNEK subst (jak derive.php, ground-truth ze składów):
												// player=SCHODZĄCY, assist=WCHODZĄCY. „{wchodzący} za {schodzący}".
												$in  = $item['assist'] ? $item['assist'] : '—';
												$out = $item['player'] ? $item['player'] : '—';
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

				<!-- SKŁADY (half-pitch + ławka; wskaźniki z indeksu zdarzeń) -->
				<section class="tabpanel" data-tab="lineups" role="tabpanel" aria-label="Składy">
					<?php
					$lineups   = isset( $data['lineups'] ) && is_array( $data['lineups'] ) ? $data['lineups'] : array();
					$player_idx = hajlajty_player_event_index( $data['events'] ?? array() );

					// Markery gol/kartka z indeksu zdarzeń — WSPÓLNE dla boiska i ławki.
					// Render dokleja ikony (lookups/derive ich nie znają). ⚽ ×liczba goli;
					// samobóje pominięte (⚽ przy zawodniku = zdobyta bramka, nie samobój).
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

					// Rozkład startXI po `grid` ("rząd:kolumna"; rząd 1=bramkarz, w dół);
					// fallback do kubełków pozycji G/D/M/F, gdy grid brak.
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
									$c = 99; // brak/niepoprawny grid → na koniec.
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
						// Fallback: kubełki pozycji.
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

					$has_home = isset( $lineups['home'] ) && is_array( $lineups['home'] );
					$has_away = isset( $lineups['away'] ) && is_array( $lineups['away'] );
					?>
					<?php if ( ! $has_home && ! $has_away ) : ?>
						<p style="color: var(--text-muted);">Brak składów dla tego meczu.</p>
					<?php else : ?>
						<!-- Sprite koszulki (reużywalny w obu połowach boiska) -->
						<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>
							<symbol id="jersey" viewBox="0 0 44 40"><path d="M15 5 L22 8.5 L29 5 L36 9 L41 15 L34 21 L31.5 18 L31.5 38 L12.5 38 L12.5 18 L10 21 L3 15 L8 9 Z"/></symbol>
						</defs></svg>

						<div class="lineup-head">
							<div class="lineup-tabs" id="lineupTabs">
								<button class="lineup-tab is-active" data-pane="home" type="button"><?php if ( '' !== $home_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?> <?php echo esc_html( $home_name ); ?></button>
								<button class="lineup-tab" data-pane="away" type="button"><?php if ( '' !== $away_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?> <?php echo esc_html( $away_name ); ?></button>
							</div>
							<?php // NIE portujemy #followBtn „Obserwuj reprezentację" — Faza 4 (hajlajty-user). ?>
						</div>

						<?php foreach ( array( 'home', 'away' ) as $side ) : ?>
							<div class="lineup-pane<?php echo 'home' === $side ? ' is-active' : ''; ?>" data-pane="<?php echo esc_attr( $side ); ?>">
								<?php
								$lu        = isset( $lineups[ $side ] ) && is_array( $lineups[ $side ] ) ? $lineups[ $side ] : array();
								$formation = $lu['formation'] ?? '';
								$start_xi  = isset( $lu['startXI'] ) && is_array( $lu['startXI'] ) ? $lu['startXI'] : array();
								$subs      = isset( $lu['substitutes'] ) && is_array( $lu['substitutes'] ) ? $lu['substitutes'] : array();

								// 3bi: realny trener + kolory koszulki/numeru z lineups (kontrakt importu).
								$coach   = $lu['coach']['name'] ?? '';
								$primary = $lu['colors']['player']['primary'] ?? '';
								$number  = $lu['colors']['player']['number'] ?? '';
								$border  = $lu['colors']['player']['border'] ?? '';
								// Tylko poprawny hex (API daje bez „#"); inaczej pusto → CSS fallback.
								$hex     = static function ( $v ) {
									return ( is_string( $v ) && preg_match( '/^[0-9a-fA-F]{3,8}$/', $v ) ) ? '#' . $v : '';
								};
								$shirt   = $hex( $primary );  // koszulka  → --team-{side}        (fallback: STUB akcent/neutral)
								$numcol  = $hex( $number );   // numer     → --team-{side}-num    (fallback: #fff)
								$bordcol = $hex( $border );   // obrys     → --team-{side}-border (fallback: brak obrysu)
								$pitch_vars = array();
								if ( '' !== $shirt ) {
									$pitch_vars[] = '--team-' . $side . ': ' . $shirt;
								}
								if ( '' !== $numcol ) {
									$pitch_vars[] = '--team-' . $side . '-num: ' . $numcol;
								}
								// Obrys WIERNIE z danych — bez fallbacka gdy border==primary (np.
								// biała na białej, tak chce drużyna). var() niżej wchodzi WYŁĄCZNIE
								// gdy pole border puste/brak (wtedy koszulka bez obrysu, jak w 3b).
								if ( '' !== $bordcol ) {
									$pitch_vars[] = '--team-' . $side . '-border: ' . $bordcol;
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
										<div class="half-pitch" data-team="<?php echo esc_attr( $side ); ?>"<?php echo $pitch_style; // już esc_attr (hex + side) ?>>
											<?php foreach ( $pitch_rows( $start_xi ) as $row ) : ?>
												<div class="hp-row">
													<?php foreach ( $row as $p ) : ?>
														<span class="pl"><span class="pl__badge"><svg class="jersey" viewBox="0 0 44 40"><use href="#jersey"/></svg><span class="pl__num"><?php echo esc_html( $p['number'] ?? '' ); ?></span></span><?php echo $player_inds( isset( $p['id'] ) ? (int) $p['id'] : null ); // phpcs:ignore — HTML wskaźników budowany z esc ?><span class="pl__name"><?php echo esc_html( $p['name'] ?? '' ); ?></span></span>
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
																<?php echo $marks_goal_card( $pid ); // phpcs:ignore — HTML markerów budowany z esc ?>
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
											<?php // Blok „Nieobecni / pauzujący" POMINIĘTY w całości (3b) — brak pola w 4 zmapowanych endpointach (Faza 5 / injuries). ?>
										</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
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

		<?php
		// ===== PRAWY ASIDE: „Inne skróty" z tych samych rozgrywek =====
		// „Polecane dla Ciebie" (personalizacja) → Faza 4, POMINIĘTE w 3b.
		$roz     = get_the_terms( $post_id, 'rozgrywki' );
		$roz_ids = ( is_array( $roz ) && ! is_wp_error( $roz ) ) ? wp_list_pluck( $roz, 'term_id' ) : array();

		// Niepuste skrot_url = praktyczny wyznacznik ZAKOŃCZONEGO meczu (skrót
		// dodaje redaktor dopiero PO meczu) — status żyje w JSON-ie (niefiltrowalny
		// w SQL), więc filtrujemy po indeksowalnym skrot_url. orderby po płaskim
		// `kickoff` (malejąco). no_found_rows: nie liczymy paginacji.
		$other_args = array(
			'post_type'           => 'mecz',
			'posts_per_page'      => 4,
			'post__not_in'        => array( $post_id ),
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'meta_query'          => array(
				'relation' => 'AND',
				'skrot'    => array(
					'key'     => 'skrot_url',
					'value'   => '',
					'compare' => '!=',
				),
				'kick'     => array(
					'key'     => 'kickoff',
					'compare' => 'EXISTS',
				),
			),
			'orderby'             => array( 'kick' => 'DESC' ),
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
			// P-c: aside używa POZIOMEJ karty sidebarowej `card-skrot-rail` (.rvideo),
			// nie pionowej `.vcard` — ta zostaje kartą list/gridów. Oba partiale żyją
			// w slice match-lists. Drużyny rozwiązane JEDNYM batchem (zero N+1).
			$other_ids      = wp_list_pluck( $other->posts, 'ID' );
			$other_resolved = hajlajty_match_lists_resolve_terms( $other_ids );
			?>
			<aside class="watch__aside">
				<section class="aside-sec">
					<h2 class="aside-sec__title"><span class="kicker-dot"></span> Inne skróty</h2>
					<?php
					foreach ( $other->posts as $rp ) :
						$rid       = (int) $rp->ID;
						$rid_terms = isset( $other_resolved[ $rid ] ) ? $other_resolved[ $rid ] : array(
							'home' => null,
							'away' => null,
						);
						get_template_part(
							'features/match-lists/partials/card-skrot-rail',
							null,
							array(
								'post_id' => $rid,
								'terms'   => $rid_terms,
							)
						);
					endforeach;
					?>
				</section>
				<?php // „Polecane dla Ciebie" (personalizacja) → Faza 4 (hajlajty-user). POMINIĘTE. ?>
			</aside>
			<?php
			wp_reset_postdata();
		endif;
		?>

	</div>
</main>
