<?php
/**
 * Partial powłoki: stopka + domknięcie powłoki (.content/.shell) i dokumentu.
 * Stopka leży WEWNĄTRZ .content (jak w monolicie), więc tu zamykamy oba
 * kontenery i wołamy wp_footer() przed </body>. Markup/klasy 1:1 z monolitem.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
		<footer class="footer">
			<div class="container">
				<div class="footer__grid">
					<div class="footer__brand">
						<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Hajlajty">
							<span class="logo__text">hajlajt<span class="logo__y">y</span></span>
							<svg class="logo__play" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" d="M7 5.8 18 12 7 18.2Z"/></svg>
						</a>
						<p class="footer__tag">Portal Młodego Kibica. Wszystkie skróty reprezentacji w jednym miejscu — prosto z oficjalnych kanałów.</p>
					</div>
					<div class="footer__col">
						<h4>Serwis</h4>
						<a href="<?php echo esc_url( home_url( '/na-zywo/' ) ); ?>">Na żywo</a>
						<a href="<?php echo esc_url( home_url( '/zapowiedzi/' ) ); ?>">Zapowiedzi</a>
						<a href="<?php echo esc_url( home_url( '/skroty/' ) ); ?>">Skróty</a>
						<a href="<?php echo esc_url( home_url( '/reprezentacje/' ) ); ?>">Reprezentacje</a>
					</div>
					<div class="footer__col">
						<h4>Mundial 2026</h4>
						<a href="<?php echo esc_url( home_url( '/terminarz/' ) ); ?>">Terminarz</a>
						<a href="<?php echo esc_url( home_url( '/tabele-grup/' ) ); ?>">Grupy</a>
						<?php // Faza pucharowa i Strzelcy — ukryte do czasu realizacji (display:none, łatwe do przywrócenia). ?>
						<a href="#" style="display:none">Faza pucharowa</a>
						<a href="#" style="display:none">Strzelcy</a>
					</div>
					<div class="footer__col">
						<h4>Hajlajty</h4>
						<a href="#">O nas</a>
						<a href="#">Kontakt</a>
						<a href="#">Polityka prywatności</a>
						<a href="#">Regulamin</a>
					</div>
				</div>
				<div class="footer__bottom">
					<span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Hajlajty.pl — wszystkie prawa zastrzeżone.</span>
					<span>Skróty pochodzą z oficjalnych kanałów YouTube.</span>
				</div>
			</div>
		</footer>

		</div><!-- /.content -->
	</div><!-- /.shell -->

	<?php wp_footer(); ?>
</body>
</html>
