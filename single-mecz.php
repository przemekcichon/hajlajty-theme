<?php
/**
 * Szablon pojedynczego meczu (CPT „mecz"). Leży w roocie motywu, bo hierarchia
 * szablonów WP szuka single-{post_type}.php właśnie tu. Plik jest CIENKI:
 * otwiera powłokę (slice layout), deleguje render do slice'a match-display,
 * domyka powłokę. Rozgałęzienie wg stanu meczu dochodzi w E2.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'features/layout/partials/header' );

while ( have_posts() ) :
	the_post();
	?>
	<main class="watch container">
		<!-- E1: szkielet powłoki działa; render wariantów (E2 routing → E3 FT) tutaj. -->
		<p style="padding: var(--space-xl) 0; color: var(--text-muted);">
			Szkielet motywu 3b — render meczu „<?php echo esc_html( get_the_title() ); ?>" dochodzi w kolejnych etapach.
		</p>
	</main>
	<?php
endwhile;

get_template_part( 'features/layout/partials/footer' );
