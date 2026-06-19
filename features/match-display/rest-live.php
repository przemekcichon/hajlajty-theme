<?php
/**
 * REST: `GET /wp-json/hajlajty/v1/mecz/{id}/live` — wyrenderowany FRAGMENT HTML
 * żywych sekcji meczu (telebim + oś + statystyki) dla auto-refreshu frontu
 * (3e-iii, rekomendacja B1, decyzja D3.8). Należy do slice'a match-display: to
 * odświeżanie JEGO renderu, więc rejestrację trzyma slice (vertical slice).
 *
 * PUBLICZNY, READ-ONLY (D-C): `permission_callback => '__return_true'` — to
 * publiczne dane meczu, bez nonce/auth (wzorzec nonce z hajlajty-user dotyczy
 * ZAPISÓW per-user, nie pasuje). Endpoint NIE woła api-football — czyta wyłącznie
 * bieżący `match_data` z bazy (świeży po `wp hajlajty import-live`, 3e-ii).
 * Polling bije w NASZ serwer, zero kosztu budżetu API.
 *
 * Zwraca SUROWY HTML (nie JSON): poller wstrzykuje go wprost w DOM, a sygnał „czy
 * dalej pollować" siedzi w atrybucie `data-live` markupu (B1) — bez osobnej
 * koperty JSON. Fragment generuje TEN SAM partial co single (`live-fragment.php`),
 * więc jest jedno źródło znacznika (headless-friendly: ten sam partial w Next.js).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'hajlajty_live_register_rest' );

/**
 * Rejestruje route fragmentu live. `id` musi być liczbą (regex `\d+` w ścieżce).
 */
function hajlajty_live_register_rest() {
	register_rest_route(
		'hajlajty/v1',
		'/mecz/(?P<id>\d+)/live',
		array(
			'methods'             => WP_REST_Server::READABLE, // GET.
			'callback'            => 'hajlajty_live_rest_fragment',
			'permission_callback' => '__return_true', // Publiczne dane meczu (D-C).
		)
	);
}

/**
 * Renderuje fragment przez WSPÓLNY partial i zwraca go jako string.
 * Jedyne miejsce, które „łapie" output partiala do zmiennej (single woła go wprost).
 *
 * @param int    $post_id ID posta meczu.
 * @param array  $data    Zdekodowane match_data.
 * @param string $part    Sekcja: all|board|timeline|stats.
 * @return string HTML fragmentu (przycięty z białych znaków brzegowych).
 */
function hajlajty_live_render_fragment( $post_id, $data, $part = 'all' ) {
	ob_start();
	get_template_part(
		'features/match-display/partials/live-fragment',
		null,
		array(
			'post_id' => (int) $post_id,
			'data'    => $data,
			'part'    => $part,
		)
	);
	return trim( (string) ob_get_clean() );
}

/**
 * Callback route'a: waliduje post „mecz", renderuje fragment, serwuje surowy HTML.
 *
 * 404 (WP_Error) gdy brak opublikowanego posta „mecz" o tym id — idzie normalną,
 * JSON-ową ścieżką REST. Sukces serwujemy SAMI (header + echo + exit), bo
 * WP_REST_Response zserializowałby string do JSON; D-C wymaga surowego HTML.
 *
 * @param WP_REST_Request $request Żądanie (param `id`).
 * @return WP_Error|void WP_Error przy 404; przy sukcesie kończy żądanie (exit).
 */
function hajlajty_live_rest_fragment( $request ) {
	$id   = (int) $request['id'];
	$post = get_post( $id );

	if ( ! $post || 'mecz' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_Error(
			'hajlajty_live_not_found',
			'Nie znaleziono meczu o tym identyfikatorze.',
			array( 'status' => 404 )
		);
	}

	$html = hajlajty_live_render_fragment( $id, hajlajty_get_match_data( $id ), 'all' );

	if ( ! headers_sent() ) {
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		// Krótki bufor (D-C, opcjonalny): dane zmienia tylko import-live, więc 15 s
		// nie zaszkodzi świeżości, a tłumi nadmiarowe trafienia. Bez server-side cache.
		header( 'Cache-Control: max-age=15' );
		header( 'X-Robots-Tag: noindex' );
	}

	echo $html; // Markup zescape'owany w partialu.
	exit;
}
