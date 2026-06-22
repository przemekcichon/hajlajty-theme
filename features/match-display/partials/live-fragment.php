<?php
/**
 * Żywy fragment widoku LIVE — JEDNO źródło znacznika dla sekcji odświeżanych bez
 * F5: telebim (wynik + minuta), oś czasu (events) i statystyki. Wołany w DWÓCH
 * miejscach (3e-iii):
 *  1. przez single-live.php — render single, każda sekcja w swojej kolumnie
 *     (`part` = board / timeline / stats), markup IDENTYCZNY jak przed ekstrakcją;
 *  2. przez REST `hajlajty/v1/mecz/{id}/live` (rest-live.php) — `part` = all,
 *     trzy kontenery naraz, które poller podmienia w DOM.
 *
 * Headless-friendly: ten sam partial pójdzie przez Next.js (decyzja #6).
 *
 * KOTWICE DOM: każda sekcja owinięta w `<div class="hajlajty-live" id="hajlajty-
 * live-{board|timeline|stats}">`. Wrapper jest layout-neutralny (`display:
 * contents` w match-single.css), więc owinięcie NIE zmienia układu (`.panels`
 * grid, kolumny `.watch__grid`) — pusty wrapper nie zostawia pudełka ani marginesu.
 * `data-live` = "1" gdy status to kod LIVE, "0" gdy nie — poller czyta to z
 * ODŚWIEŻONEGO fragmentu i przy "0" kończy interwał (B1: sygnał w HTML, bez JSON).
 *
 * Render READ-ONLY z `match_data` (jak single-live). Self-contained: liczy
 * wszystko z `post_id` + `data`, żeby działać też spod endpointu (poza pętlą WP).
 * NIE renderuje: składów (statyczne — D-A), aside „Inne mecze" (statyczny chrome —
 * D-D), nagłówka. Te zostają w single-live.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
$data    = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : hajlajty_get_match_data( $post_id );
$part    = isset( $args['part'] ) ? (string) $args['part'] : 'all';

$do_board    = ( 'all' === $part || 'board' === $part );
$do_timeline = ( 'all' === $part || 'timeline' === $part );
$do_stats    = ( 'all' === $part || 'stats' === $part );

// --- Wspólne podstawy (status + drużyny + flagi) ---
$short   = $data['status']['short'] ?? null;
$status  = hajlajty_lookup_status( $short );
$is_live = in_array( (string) $short, hajlajty_status_live_codes(), true );

$terms = hajlajty_match_get_team_terms( $post_id );

$flag_url  = static function ( $term ) {
	return hajlajty_flag_url( $term );
};
$team_name = static function ( $term ) {
	return ( $term instanceof WP_Term ) ? $term->name : '—';
};
$team_code = static function ( $term ) {
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

// --- Atrybuty kotwic (wspólne dla wszystkich wrapperów) ---
// data-endpoint na telebimie (kotwica „prymarna", którą poller czyta na starcie).
$live_attr = $is_live ? '1' : '0';
$anchor    = static function ( $id ) use ( $post_id, $live_attr ) {
	return 'class="hajlajty-live" id="' . esc_attr( $id ) . '" data-match="' . esc_attr( $post_id ) . '" data-live="' . esc_attr( $live_attr ) . '"';
};
?>

<?php if ( $do_board ) : ?>
	<?php
	// ===== TELEBIM / SCOREBOARD ===== (żywe: wynik + minuta/etykieta statusu)
	$elapsed = $data['status']['elapsed'] ?? null;
	$extra   = $data['status']['extra'] ?? null;
	// Mapa połów LOKALNA dla fragmentu (lookups.php nietknięte) — D-D.
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

	$goals_home  = $data['goals']['home'] ?? null;
	$goals_away  = $data['goals']['away'] ?? null;
	$match_label = $home_name . ' – ' . $away_name;

	$endpoint = esc_url( rest_url( 'hajlajty/v1/mecz/' . $post_id . '/live' ) );

	// Autoodtworzenie overlayu „GOL" przy WEJŚCIU: tylko gdy najnowszy gol padł
	// w ostatnich 4 min gry (inaczej '' → bez historii). Sygnatura wskazuje element
	// osi, z którego JS odczyta dane efektu. Kartki/zmiany NIE są autoodtwarzane —
	// tylko nowe w trakcie pollingu (decyzja właściciela).
	$autoplay_sig = hajlajty_recent_goal_signature(
		hajlajty_build_timeline( $data['events'] ?? array() ),
		$data['status']['elapsed'] ?? null,
		4
	);
	?>
	<div <?php echo $anchor( 'hajlajty-live-board' ); // phpcs:ignore — atrybuty zescape'owane ?> data-endpoint="<?php echo $endpoint; // już esc_url ?>"<?php echo '' !== $autoplay_sig ? ' data-ev-autoplay="' . esc_attr( $autoplay_sig ) . '"' : ''; ?>>
		<section class="board reveal" aria-label="<?php echo esc_attr( 'Tablica wyników na żywo: ' . $match_label ); ?>">
			<div class="board__top">
				<span class="board__live"><span class="dot"></span> LIVE</span>
				<?php if ( $status['show_minute'] && '' !== $minute_txt ) : ?>
					<span class="board__min"><span class="pip"></span><?php echo esc_html( $minute_txt ); ?></span>
				<?php elseif ( '' !== (string) $live_label ) : ?>
					<span class="board__min"><span class="pip"></span><?php echo esc_html( $live_label ); ?></span>
				<?php endif; ?>
			</div>

			<div class="board__score">
				<div class="board__team">
					<?php if ( '' !== $home_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?>
					<span class="nm"><?php echo esc_html( $home_name ); ?></span>
				</div>
				<div class="board__nums">
					<?php // data-side: hook dla scoreBump (MVP-b) — poller porównuje wartość przed/po. ?>
					<span class="n" data-side="home"><?php echo esc_html( null === $goals_home ? '–' : $goals_home ); ?></span>
					<span class="sep">:</span>
					<span class="n" data-side="away"><?php echo esc_html( null === $goals_away ? '–' : $goals_away ); ?></span>
				</div>
				<div class="board__team">
					<?php if ( '' !== $away_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?>
					<span class="nm"><?php echo esc_html( $away_name ); ?></span>
				</div>
			</div>

			<?php if ( '' !== $half_label ) : ?>
				<span class="board__half"><?php echo esc_html( $half_label ); ?></span>
			<?php endif; ?>

			<?php
			// OVERLAYE ZDARZEŃ (pełnoekranowy telebim) — szkielety puste, JS wypełnia
			// sloty z `data-ev-*` osi i aktywuje `.is-active` (live-refresh.js). Port
			// markupu z design „Mecz na Żywo". Sloty na tekst (bez innerHTML w JS).
			?>
			<div class="ev ev--goal" aria-hidden="true">
				<div class="ev__goal-box">
					<div class="gol">GOOOL!</div>
					<div class="gol-sub"><b data-ev-goal-player></b><span data-ev-goal-min></span></div>
				</div>
			</div>
			<div class="ev ev--card" aria-hidden="true">
				<div class="ev__inner">
					<div class="card-graphic" data-ev-card-gfx></div>
					<div class="ev__meta">
						<div class="lab" data-ev-card-lab></div>
						<div class="who" data-ev-card-who></div>
						<div class="team"><img class="country-flag" data-ev-card-flag alt="" hidden /><span data-ev-card-team></span></div>
					</div>
				</div>
			</div>
			<div class="ev ev--sub" aria-hidden="true">
				<div class="ev__inner">
					<span class="lab">Zmiana</span>
					<div class="sub-row in"><span class="arrow"><svg viewBox="0 0 24 24"><path d="M12 19V5M6 11l6-6 6 6"/></svg></span><span data-ev-sub-in></span></div>
					<div class="sub-row out"><span class="arrow"><svg viewBox="0 0 24 24"><path d="M12 5v14M6 13l6 6 6-6"/></svg></span><span data-ev-sub-out></span></div>
				</div>
			</div>
		</section>
	</div>
<?php endif; ?>

<?php if ( $do_timeline ) : ?>
	<?php
	// ===== OŚ CZASU ===== (żywa: nowe zdarzenia + narastający wynik)
	$timeline = hajlajty_build_timeline( $data['events'] ?? array() );
	// Najnowsze u góry, eventy Var pominięte (jak w single-ft.php).
	$timeline_desc = array_reverse( $timeline );
	$visible       = array_filter(
		$timeline_desc,
		static function ( $it ) {
			return 'var' !== $it['key'];
		}
	);
	$has_timeline = ! empty( $visible );
	?>
	<div <?php echo $anchor( 'hajlajty-live-timeline' ); // phpcs:ignore — atrybuty zescape'owane ?>>
		<?php if ( $has_timeline ) : ?>
			<section class="panel reveal">
				<h2 class="panel__title"><span class="kicker-dot"></span> Oś czasu</h2>
				<?php
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
				$min_txt = static function ( $item ) {
					$min = $item['minute'];
					if ( null === $min ) {
						return '';
					}
					$ex = $item['extra'];
					return ( null !== $ex && '' !== $ex ) ? ( $min . '+' . $ex . "'" ) : ( $min . "'" );
				};
				?>
				<div class="timeline">
					<?php
					foreach ( $visible as $item ) :
						$side    = $item['side'];
						$term    = $terms[ $side ];
						$tflag   = $flag_url( $term );
						$tname   = $team_name( $term );
						$is_goal = in_array( $item['key'], array( 'goal', 'penalty_goal', 'own_goal', 'missed_penalty' ), true );
						$is_sub  = ( 'subst' === $item['key'] );

						// MVP-b: sygnatura zdarzenia (stabilna) + klasa efektu — JEDNO źródło
						// (derive.php), współdzielone z decyzją o autoodtworzeniu gola (board).
						// Poller wykrywa PRZYROST po sygnaturze i animuje tylko nowe zdarzenia.
						$ev_sig  = hajlajty_event_signature( $item );
						$ev_kind = hajlajty_event_overlay_kind( $item['key'] ); // goal|card|sub|''

						// Dane overlayu (pełnoekranowy telebim) NIESIONE w `data-ev-*` na elemencie
						// osi — JS czyta je przy odtwarzaniu efektu (zero JSON, headless-friendly).
						// Tylko dla zdarzeń z efektem (goal/card/sub); reszta bez atrybutów.
						$ev_attrs = '';
						if ( '' !== $ev_kind ) {
							$ev_attrs .= ' data-ev-kind="' . esc_attr( $ev_kind ) . '"';
							$ev_attrs .= ' data-ev-min="' . esc_attr( $min_txt( $item ) ) . '"';
							$ev_attrs .= ' data-ev-team="' . esc_attr( $tname ) . '"';
							if ( '' !== $tflag ) {
								$ev_attrs .= ' data-ev-flag="' . esc_url( $tflag ) . '"';
							}
							$ev_attrs .= ' data-ev-label="' . esc_attr( (string) $item['label'] ) . '"';
							if ( 'sub' === $ev_kind ) {
								// player=schodzący, assist=wchodzący (transform/derive).
								$ev_attrs .= ' data-ev-in="' . esc_attr( (string) ( $item['assist'] ?? '' ) ) . '"';
								$ev_attrs .= ' data-ev-out="' . esc_attr( (string) ( $item['player'] ?? '' ) ) . '"';
							} else {
								$ev_attrs .= ' data-ev-player="' . esc_attr( (string) ( $item['player'] ?? '' ) ) . '"';
								if ( 'card' === $ev_kind ) {
									$card_kind = in_array( $item['key'], array( 'red', 'second_yellow' ), true ) ? 'red' : 'yellow';
									$ev_attrs .= ' data-ev-cardkind="' . esc_attr( $card_kind ) . '"';
								} else {
									// GOL: barwa overlayu = kolor koszulki strzelającej drużyny z API
									// (`lineups[side].colors.player.primary`) — to samo źródło co boisko
									// (single-live). Walidacja hex jak tam; brak/niepoprawny → bez atrybutu
									// (JS zostawia fallback --accent).
									$ev_primary = $data['lineups'][ $side ]['colors']['player']['primary'] ?? '';
									if ( is_string( $ev_primary ) && preg_match( '/^[0-9a-fA-F]{3,8}$/', $ev_primary ) ) {
										$ev_attrs .= ' data-ev-color="#' . esc_attr( $ev_primary ) . '"';
									}
								}
							}
						}

						if ( $is_sub ) {
							$title = $item['label'] . ' — ' . $tname;
						} elseif ( ! empty( $item['player'] ) ) {
							$title = $item['label'] . ' — ' . $item['player'];
						} else {
							$title = $item['label'];
						}
						?>
						<div class="tl-item" data-ev="<?php echo esc_attr( $ev_sig ); ?>"<?php echo $ev_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — każdy atrybut zescape'owany przy budowie ?>>
							<span class="tl-min"><?php echo esc_html( $min_txt( $item ) ); ?></span>
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
										// player=schodzący, assist=wchodzący → „{wchodzący} za {schodzący}".
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
			</section>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $do_stats ) : ?>
	<?php
	// ===== STATYSTYKI ===== (żywe: wartości aktualizowane w trakcie)
	$stat_rows = hajlajty_build_stat_rows( $data );
	$stat_disp = static function ( $v ) {
		if ( null === $v ) {
			return '0';
		}
		return is_string( $v ) ? $v : (string) $v;
	};
	?>
	<div <?php echo $anchor( 'hajlajty-live-stats' ); // phpcs:ignore — atrybuty zescape'owane ?>>
		<?php if ( ! empty( $stat_rows ) ) : ?>
			<section class="aside-sec">
				<h2 class="aside-sec__title"><span class="kicker-dot"></span> Statystyki na żywo</h2>
				<div class="panel" style="padding: var(--space-md)">
					<div class="stats-head">
						<span class="side home"><span class="swatch home"></span><?php if ( '' !== $home_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $home_flag ); ?>" alt="" /><?php endif; ?> <?php echo esc_html( $team_code( $terms['home'] ) ); ?></span>
						<span class="side away"><?php echo esc_html( $team_code( $terms['away'] ) ); ?> <?php if ( '' !== $away_flag ) : ?><img class="country-flag" src="<?php echo esc_url( $away_flag ); ?>" alt="" /><?php endif; ?><span class="swatch away"></span></span>
					</div>
					<?php
					foreach ( $stat_rows as $row ) :
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
			</section>
		<?php endif; ?>
	</div>
<?php endif; ?>
