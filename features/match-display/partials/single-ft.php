<?php
/**
 * Wariant ZAKOŃCZONY (skrót / po meczu) — render single CPT „mecz".
 * Wywoływany przez single-mecz.php przez get_template_part z $args:
 *   $args['post_id'] (int)  — ID meczu,
 *   $args['data']    (array) — zdekodowane match_data (hajlajty_get_match_data).
 *
 * E2: szkielet routingu — ten plik istnieje i potwierdza dotarcie do gałęzi
 * ZAKOŃCZONY. Pełny render (player16 + ibar + zakładki: oś czasu / składy /
 * statystyki + prawy aside) dochodzi w E3–E6.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
$data    = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : hajlajty_get_match_data( $post_id );
?>
<main class="watch container">
	<!-- E2: routing dotarł do wariantu ZAKOŃCZONY. Render FT dochodzi w E3. -->
	<p style="padding: var(--space-xl) 0; color: var(--text-muted);">
		Wariant ZAKOŃCZONY — render skrótu meczu „<?php echo esc_html( get_the_title( $post_id ) ); ?>" dochodzi w E3.
	</p>
</main>
