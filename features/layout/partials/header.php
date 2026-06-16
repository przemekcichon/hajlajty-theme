<?php
/**
 * Partial powłoki: otwiera dokument i powłokę aplikacji (topbar, scrim, sidebar,
 * otwarcie .shell/.content). Szablon w roocie woła go przez get_template_part,
 * renderuje swoją treść wewnątrz .content, a domyka footer.php.
 *
 * data-theme="dark" domyślnie; layout.js nadpisuje zapisanym motywem z
 * localStorage tuż po starcie (akceptowalny mikro-FOUC — jak w monolicie).
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
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

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
