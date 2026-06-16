<?php
/**
 * Partial powłoki: sidebar off-canvas + nawigacja. Linki do widoków list
 * (Na żywo / Zapowiedzi / Skróty, Mundial 2026) pokażą realne archiwa w 3d —
 * tu prowadzą do '#' jako świadome placeholdery (routing nie należy do 3b).
 * Strona główna kieruje do home_url. Markup/klasy 1:1 z monolitem.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
		<a class="nav-link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg> Strona główna</a>
		<a class="nav-link" href="#"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M5.6 5.6a9 9 0 0 0 0 12.8M18.4 5.6a9 9 0 0 1 0 12.8"/></svg> Na żywo <span class="badge-live">● LIVE</span></a>
		<a class="nav-link" href="#"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Zapowiedzi</a>
		<a class="nav-link" href="#"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4-2.5z"/></svg> Skróty</a>
	</nav>

	<nav class="sidebar__group">
		<p class="sidebar__label">Mundial 2026</p>
		<a class="nav-link" href="#"><svg viewBox="0 0 24 24"><path d="M6 4h12v3a6 6 0 0 1-12 0z"/><path d="M9 14h6M10 14v4M14 14v4M8 21h8"/></svg> Terminarz turnieju</a>
		<a class="nav-link" href="#"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10"/></svg> Tabele grup</a>
		<a class="nav-link" href="#"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 3a14 14 0 0 0 0 18M12 3a14 14 0 0 1 0 18M3 12h18"/></svg> Reprezentacje</a>
	</nav>

	<nav class="sidebar__group">
		<p class="sidebar__label">Twoje</p>
		<a class="nav-link" href="#"><svg viewBox="0 0 24 24"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg> Obserwowane</a>
		<a class="nav-link" href="#"><svg viewBox="0 0 24 24"><path d="M12 20.5 4.2 12.7a4.7 4.7 0 0 1 6.6-6.6l1.2 1.2 1.2-1.2a4.7 4.7 0 0 1 6.6 6.6z"/></svg> Ulubione</a>
		<a class="nav-link" href="#"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 12a7.4 7.4 0 0 0-.1-1.2l2-1.5-2-3.4-2.3 1a7.3 7.3 0 0 0-2-1.2L16.6 2h-4l-.4 2.5a7.3 7.3 0 0 0-2 1.2l-2.3-1-2 3.4 2 1.5a7.4 7.4 0 0 0 0 2.4l-2 1.5 2 3.4 2.3-1a7.3 7.3 0 0 0 2 1.2l.4 2.5h4l.4-2.5a7.3 7.3 0 0 0 2-1.2l2.3 1 2-3.4-2-1.5c.07-.4.1-.8.1-1.2z"/></svg> Ustawienia</a>
	</nav>
</aside>
