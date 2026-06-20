<?php
/**
 * Boks-teaser „wkrótce" — zastępuje sidebarową grupę „Twoje" (Obserwowane /
 * Ulubione / Ustawienia) do czasu wtyczki hajlajty-user (MVP-a, trim launchowy).
 *
 * Te trzy linki były martwymi placeholderami `href="#"` — zamiast wyglądać na
 * zepsute, jeden miękki boks informuje I buduje oczekiwanie (spójne z
 * charakterem projektu). Wraca jako realne linki, gdy ruszy hajlajty-user;
 * wtedy ten partial znika z sidebar.php. Markup statyczny, zero danych/JS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sidebar__group">
	<div class="coming-soon">
		<span class="coming-soon__badge">Już wkrótce</span>
		<p class="coming-soon__title">Twoje Hajlajty</p>
		<p class="coming-soon__text">Ulubione mecze, obserwowane drużyny i konto — budujemy to teraz!</p>
	</div>
</div>
