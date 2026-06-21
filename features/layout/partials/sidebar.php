<?php
/**
 * Partial powłoki: sidebar off-canvas + nawigacja. Linki list (Na żywo /
 * Zapowiedzi / Skróty) prowadzą do realnych archiwów 3d (slice match-lists).
 * „Terminarz turnieju" (MVP-c) wskazuje na Stronę z szablonem
 * `template-terminarz.php` — URL rozwiązywany DYNAMICZNIE (nie po slug-u);
 * dopóki Strony nie ma, link zostaje '#'. „Tabele grup"/„Reprezentacje" i grupa
 * „Twoje" pozostają placeholderami (Faza MVP-e/g / hajlajty-user). Strona główna
 * kieruje do home_url. Markup/klasy 1:1 z monolitem.
 *
 * Stan aktywny (.is-active) liczony z bieżącego widoku: strona główna albo
 * archiwum „mecz" wg query var `hajlajty_lista` (live/zapowiedzi/skroty).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hajlajty_is_archive = is_post_type_archive( 'mecz' );
$hajlajty_lista      = $hajlajty_is_archive ? (string) get_query_var( 'hajlajty_lista' ) : '';
if ( $hajlajty_is_archive && '' === $hajlajty_lista ) {
	$hajlajty_lista = 'skroty'; // Gołe /mecz/ = skróty (spójnie z pre_get_posts).
}
$hajlajty_nav_active = static function ( $match ) use ( $hajlajty_is_archive, $hajlajty_lista ) {
	$on = ( 'home' === $match )
		? is_front_page()
		: ( $hajlajty_is_archive && $hajlajty_lista === $match );
	return $on ? ' is-active' : '';
};

// URL „Terminarz turnieju" (MVP-c) — Strona z szablonem template-terminarz.php.
// Rozwiązujemy po SZABLONIE (nie po slug-u): link działa niezależnie od tego, jaki
// slug nada redaktor. Brak takiej Strony → '' → link zostaje '#'.
$hajlajty_terminarz_url   = '';
$hajlajty_terminarz_pages = get_pages(
	array(
		'meta_key'    => '_wp_page_template',
		'meta_value'  => 'template-terminarz.php',
		'number'      => 1,
		'post_status' => 'publish',
	)
);
if ( ! empty( $hajlajty_terminarz_pages ) ) {
	$hajlajty_terminarz_url = (string) get_permalink( $hajlajty_terminarz_pages[0]->ID );
}
$hajlajty_terminarz_active = is_page_template( 'template-terminarz.php' ) ? ' is-active' : '';
?>
<aside class="sidebar" id="sidebar" aria-label="Menu główne">
	<div class="sidebar__head">
		<button class="icon-btn" id="sidebarClose" aria-label="Zamknij menu">
			<svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
		</button>
		<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Hajlajty — strona główna">
			<span class="logo__text">hajlajt<span class="logo__y">y</span></span>
			<svg class="logo__play" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" d="M7 5.8 18 12 7 18.2Z"/></svg>
		</a>
	</div>

	<nav class="sidebar__group">
		<a class="nav-link<?php echo esc_attr( $hajlajty_nav_active( 'home' ) ); ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>"><svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg> Strona główna</a>
		<a class="nav-link<?php echo esc_attr( $hajlajty_nav_active( 'live' ) ); ?>" href="<?php echo esc_url( home_url( '/na-zywo/' ) ); ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M5.6 5.6a9 9 0 0 0 0 12.8M18.4 5.6a9 9 0 0 1 0 12.8"/></svg> Na żywo <span class="badge-live">● LIVE</span></a>
		<a class="nav-link<?php echo esc_attr( $hajlajty_nav_active( 'zapowiedzi' ) ); ?>" href="<?php echo esc_url( home_url( '/zapowiedzi/' ) ); ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Zapowiedzi</a>
		<a class="nav-link<?php echo esc_attr( $hajlajty_nav_active( 'skroty' ) ); ?>" href="<?php echo esc_url( home_url( '/skroty/' ) ); ?>"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4-2.5z"/></svg> Skróty</a>
	</nav>

	<nav class="sidebar__group">
		<p class="sidebar__label">Mundial 2026</p>
		<?php if ( '' !== $hajlajty_terminarz_url ) : ?>
			<a class="nav-link<?php echo esc_attr( $hajlajty_terminarz_active ); ?>" href="<?php echo esc_url( $hajlajty_terminarz_url ); ?>"><svg viewBox="0 0 24 24"><path d="M6 4h12v3a6 6 0 0 1-12 0z"/><path d="M9 14h6M10 14v4M14 14v4M8 21h8"/></svg> Terminarz turnieju</a>
		<?php else : ?>
			<a class="nav-link" href="#"><svg viewBox="0 0 24 24"><path d="M6 4h12v3a6 6 0 0 1-12 0z"/><path d="M9 14h6M10 14v4M14 14v4M8 21h8"/></svg> Terminarz turnieju</a>
		<?php endif; ?>
		<a class="nav-link" href="#"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10"/></svg> Tabele grup</a>
		<a class="nav-link" href="#"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 3a14 14 0 0 0 0 18M12 3a14 14 0 0 1 0 18M3 12h18"/></svg> Reprezentacje</a>
	</nav>

	<?php
	// Grupa „Twoje" (Obserwowane/Ulubione/Ustawienia) zastąpiona boksem-teaserem
	// „wkrótce" do czasu hajlajty-user (MVP-a, trim launchowy). Linki wracają, gdy
	// powstanie konto — wtedy ten partial znika.
	get_template_part( 'features/layout/partials/coming-soon' );
	?>
</aside>
