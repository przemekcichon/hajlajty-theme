<?php
/**
 * Partial powłoki: otwiera dokument i powłokę aplikacji (topbar, scrim, sidebar,
 * otwarcie .shell/.content). Szablon w roocie woła go przez get_template_part,
 * renderuje swoją treść wewnątrz .content, a domyka footer.php.
 *
 * data-theme="dark" w markupie = fallback bez JS (marka). O motywie przy
 * PIERWSZYM paincie decyduje BLOKUJĄCY skrypt inline na samym początku <head>
 * (patrz niżej): ustawia data-theme ZANIM przeglądarka wyrenderuje <body>, więc
 * nie ma ciemnego błysku, gdy redaktor wybrał motyw jasny (P-g). Skrypt jest
 * synchroniczny i cache-safe (HTML identyczny dla każdego; motyw = stan per-
 * użytkownik czytany po stronie klienta, zgodnie z zasadą cache z planu).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="dark">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<?php /* Anti-FOUC: musi być PIERWSZY i SYNCHRONICZNY (bez defer/async ani
	         wp_enqueue), żeby wykonał się przed pierwszym paintem. Klucz motywu
	         z hajlajty_theme_store_key() — TO SAMO źródło, którego layout.js
	         (toggle) używa przez wp_localize_script. */ ?>
	<script>
		(function () {
			try {
				var saved = localStorage.getItem(<?php echo wp_json_encode( hajlajty_theme_store_key() ); ?>);
				// Ufaj tylko znanym wartościom — inaczej brak pasującego bloku
				// [data-theme=...] w tokens.css i strona maluje się bez kolorów.
				var theme = (saved === "dark" || saved === "light")
					? saved
					: (window.matchMedia && window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark");
				document.documentElement.setAttribute("data-theme", theme);
			} catch (e) {}
		})();
	</script>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

	<?php /* Przywrócenie stanu menu (rail vs pełne) PRZED pierwszym paintem powłoki
	         — analogicznie do anti-FOUC motywu w <head>, ale klasa siedzi na <body>,
	         więc skrypt jest TUŻ po <body> (document.body już istnieje). Klasa
	         nav-collapsed siada zanim parser dojdzie do .shell/.sidebar, więc nie
	         ma błysku pełnego menu, gdy redaktor zostawił szynę. Klucz z
	         hajlajty_nav_store_key() — TO SAMO źródło, którego layout.js (toggle)
	         używa przez wp_localize_script. CSS reaguje tylko ≥1100px na widokach z
	         trwałym menu, więc na mobile/single klasa jest nieszkodliwa. */ ?>
	<script>
		(function () {
			try {
				if (localStorage.getItem(<?php echo wp_json_encode( hajlajty_nav_store_key() ); ?>) === "collapsed") {
					document.body.classList.add("nav-collapsed");
				}
			} catch (e) {}
		})();
	</script>

	<!-- ============================ TOP BAR ============================ -->
	<header class="topbar">
		<div class="topbar__inner container">
			<div class="topbar__left">
				<button class="icon-btn" id="menuBtn" aria-label="Otwórz menu" aria-expanded="false" aria-controls="sidebar">
					<svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
				</button>
				<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Hajlajty — strona główna">
					<span class="logo__text">hajlajt<span class="logo__y">y</span></span>
					<svg class="logo__play" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" d="M7 5.8 18 12 7 18.2Z"/></svg>
				</a>
			</div>

			<?php
			/**
			 * Środkowa kolumna topbara — punkt rozszerzenia powłoki. Slice filters
			 * wstrzykuje tu wyszukiwarkę drużyn na widokach LIST (grid 1fr|search|1fr,
			 * patrz body.hajlajty-has-search). Brak wpięcia = pusta kolumna (single).
			 */
			do_action( 'hajlajty_topbar_center' );
			?>

			<div class="topbar__right">
				<button class="icon-btn theme-toggle" id="themeBtn" aria-label="Przełącz motyw jasny/ciemny">
					<svg class="sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4.2"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M19.1 4.9l-1.8 1.8M6.7 17.3l-1.8 1.8"/></svg>
					<svg class="moon" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/></svg>
				</button>
				<button class="icon-btn" aria-label="Profil">
					<svg viewBox="0 0 24 24"><circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>
				</button>
			</div>
		</div>
	</header>

	<div class="scrim" id="scrim" hidden></div>

	<div class="shell">
		<?php get_template_part( 'features/layout/partials/sidebar' ); ?>
		<div class="content" id="content">
