<?php
/**
 * Strona główna — trzy sekcje zestawień: LIVE, ZAPOWIEDZI, SKRÓTY. Plik w roocie
 * motywu (front-page.php wygrywa w hierarchii dla strony startowej).
 *
 * Każda sekcja = WŁASNY WP_Query (no_found_rows=true — sekcja nie paginuje) i
 * WŁASNY batch-resolve drużyn RAZ (JEDEN get_terms na sekcję, zero N+1 na kartę).
 * Reguła: „jeden WP_Query per lista", nie per karta. Markup kart zduplikowany z
 * archiwum świadomie (VSA); warstwa danych (resolver + lookups) reużywana.
 *
 * Stany zapytań spójne ze slice match-lists (pre_get_posts): te same meta_query/
 * orderby, tylko z limitem i no_found_rows dla podglądu na home. Sekcja LIVE
 * filtruje po REALNYM statusie (`status IN (kody live)`, 3e-i), jak archiwum.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'features/layout/partials/header' );

// Pasek filtra (slice filters) — pod headerem, w `.content` przed `<main>`. Spójnie
// z archiwum; widoki single go nie wołają. Guard, gdyby slice zniknął.
if ( function_exists( 'hajlajty_filters_render_bar' ) ) {
	hajlajty_filters_render_bar();
}

$now = gmdate( 'Y-m-d H:i:s' );

/**
 * Renderuje jedną sekcję strony głównej: nagłówek + lista kart. Resolwuje drużyny
 * RAZ dla całej sekcji. Closure (nie globalna funkcja) — logika znika razem z home.
 *
 * @param array  $head      ['title'=>..,'title_class'=>..,'more_url'=>..,'more_text'=>..,'id'=>..]
 * @param WP_Query $query    Zapytanie sekcji.
 * @param string $partial    Nazwa partiala karty (np. 'card-live').
 * @param string $container  Klasa kontenera listy ('row-scroll'|'grid-videos').
 */
$render_section = static function ( $head, $query, $partial, $container ) {
	if ( ! $query->have_posts() ) {
		wp_reset_postdata();
		return; // Pustą sekcję pomijamy na home (cisza lepsza niż pusty nagłówek).
	}

	$post_ids = wp_list_pluck( $query->posts, 'ID' );
	$resolved = hajlajty_match_lists_resolve_terms( $post_ids );
	?>
	<section class="section" id="<?php echo esc_attr( $head['id'] ); ?>">
		<div class="section__head">
			<h2 class="section__title<?php echo esc_attr( $head['title_class'] ); ?>"><span class="kicker-dot"></span> <?php echo esc_html( $head['title'] ); ?></h2>
			<a class="section__more" href="<?php echo esc_url( $head['more_url'] ); ?>"><?php echo esc_html( $head['more_text'] ); ?> <svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg></a>
		</div>
		<div class="<?php echo esc_attr( $container ); ?>" data-filterable>
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				$pid   = get_the_ID();
				$terms = isset( $resolved[ $pid ] ) ? $resolved[ $pid ] : array(
					'home' => null,
					'away' => null,
				);
				get_template_part(
					'features/match-lists/partials/' . $partial,
					null,
					array(
						'post_id' => $pid,
						'terms'   => $terms,
					)
				);
			endwhile;
			?>
		</div>
	</section>
	<?php
	wp_reset_postdata();
};
?>
<main class="container">

	<?php
	// ===== SEKCJA 1: LIVE (realny status — status IN kody live, 3e-i) =====
	$render_section(
		array(
			'id'          => 'live',
			'title'       => 'Aktualnie trwające',
			'title_class' => ' live',
			'more_url'    => home_url( '/na-zywo/' ),
			'more_text'   => 'Wszystkie na żywo',
		),
		new WP_Query(
			array(
				'post_type'      => 'mecz',
				'posts_per_page' => 4,
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'AND',
					'stat'     => array(
						'key'     => 'status',
						'value'   => hajlajty_status_live_codes(),
						'compare' => 'IN',
					),
					'kick'     => array(
						'key'     => 'kickoff',
						'compare' => 'EXISTS',
					),
				),
				'orderby'        => array( 'kick' => 'ASC' ),
			)
		),
		'card-live',
		'row-scroll'
	);

	// ===== SEKCJA 2: ZAPOWIEDZI (kickoff >= teraz, ASC) =====
	$render_section(
		array(
			'id'          => 'zapowiedzi',
			'title'       => 'Najbliższe zapowiedzi',
			'title_class' => '',
			'more_url'    => home_url( '/zapowiedzi/' ),
			'more_text'   => 'Wszystkie zapowiedzi',
		),
		new WP_Query(
			array(
				'post_type'      => 'mecz',
				'posts_per_page' => 4,
				'no_found_rows'  => true,
				'meta_query'     => array(
					'kick' => array(
						'key'     => 'kickoff',
						'value'   => $now,
						'compare' => '>=',
						'type'    => 'CHAR',
					),
				),
				'orderby'        => array( 'kick' => 'ASC' ),
			)
		),
		'card-zapowiedz',
		'row-scroll'
	);

	// ===== SEKCJA 3: SKRÓTY (ma wideo, najnowsze DESC) =====
	$render_section(
		array(
			'id'          => 'skroty',
			'title'       => 'Ostatnio dodane skróty',
			'title_class' => '',
			'more_url'    => home_url( '/skroty/' ),
			'more_text'   => 'Zobacz wszystkie',
		),
		new WP_Query(
			array(
				'post_type'      => 'mecz',
				'posts_per_page' => 8,
				'no_found_rows'  => true,
				'meta_query'     => array(
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
				'orderby'        => array( 'kick' => 'DESC' ),
			)
		),
		'card-skrot',
		'grid-videos'
	);
	?>

</main>
<?php
get_template_part( 'features/layout/partials/footer' );
