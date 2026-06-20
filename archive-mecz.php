<?php
/**
 * Archiwum CPT „mecz" — trzy listy w jednym szablonie (live / zapowiedzi /
 * skroty), rozróżniane przez query var `hajlajty_lista` (routing + kształt
 * zapytania w slice match-lists). Plik leży w roocie motywu, bo hierarchia
 * szablonów WP szuka archive-{post_type}.php właśnie tu.
 *
 * Powłoka jak w single-mecz.php (header/footer ze slice'a layout, treść w
 * .container). Pętla GŁÓWNEGO zapytania → batch-resolve drużyn RAZ dla wszystkich
 * postów (JEDEN get_terms, zero N+1) → render właściwej karty per stan listy.
 *
 * CHIPSBAR i data-filterable (filtry klienckie) NIE są tu portowane — to Faza 4A.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lista = (string) get_query_var( 'hajlajty_lista' );
if ( '' === $lista ) {
	$lista = 'skroty';
}

// Konfiguracja per stan: nagłówek sekcji, partial karty, modyfikator tytułu, pusty stan.
$config = array(
	'live'       => array(
		'title'         => 'Na żywo',
		'title_class'   => ' live',
		'partial'       => 'card-live',
		'empty_head'    => 'Brak meczów na żywo',
		'empty_text'    => 'Teraz nic nie jest rozgrywane. Zajrzyj do zapowiedzi nadchodzących spotkań.',
	),
	'zapowiedzi' => array(
		'title'         => 'Zapowiedzi',
		'title_class'   => '',
		'partial'       => 'card-zapowiedz',
		'empty_head'    => 'Brak zapowiedzi',
		'empty_text'    => 'Nie ma jeszcze zaplanowanych meczów. Wróć wkrótce.',
	),
	'skroty'     => array(
		'title'         => 'Skróty',
		'title_class'   => '',
		'partial'       => 'card-skrot',
		'empty_head'    => 'Brak skrótów',
		'empty_text'    => 'Nie dodano jeszcze żadnego skrótu wideo. Wróć po najbliższych meczach.',
	),
);
$cfg = isset( $config[ $lista ] ) ? $config[ $lista ] : $config['skroty'];

get_template_part( 'features/layout/partials/header' );
?>
<main class="container">
	<section class="section">
		<div class="section__head">
			<h2 class="section__title<?php echo esc_attr( $cfg['title_class'] ); ?>"><span class="kicker-dot"></span> <?php echo esc_html( $cfg['title'] ); ?></h2>
		</div>

		<?php if ( have_posts() ) : ?>
			<?php
			// Batch-resolve drużyn RAZ dla wszystkich postów listy (zero N+1).
			$post_ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
			$resolved = hajlajty_match_lists_resolve_terms( $post_ids );
			?>
			<div class="grid-videos" data-filterable>
				<?php
				while ( have_posts() ) :
					the_post();
					$pid   = get_the_ID();
					$terms = isset( $resolved[ $pid ] ) ? $resolved[ $pid ] : array(
						'home' => null,
						'away' => null,
					);
					get_template_part(
						'features/match-lists/partials/' . $cfg['partial'],
						null,
						array(
							'post_id' => $pid,
							'terms'   => $terms,
						)
					);
				endwhile;
				?>
			</div>

			<?php
			the_posts_pagination(
				array(
					'mid_size'  => 1,
					'prev_text' => '‹ Poprzednia',
					'next_text' => 'Następna ›',
				)
			);
			?>
		<?php else : ?>
			<div class="empty-state is-visible">
				<h3><?php echo esc_html( $cfg['empty_head'] ); ?></h3>
				<p><?php echo esc_html( $cfg['empty_text'] ); ?></p>
			</div>
		<?php endif; ?>
	</section>
</main>
<?php
get_template_part( 'features/layout/partials/footer' );
