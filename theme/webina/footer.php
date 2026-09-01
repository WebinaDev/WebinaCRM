<?php
/**
 * Theme footer — always renders Webina chrome (footer + popup + FAB).
 *
 * @package Webina
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WebinoCRM_Site_Chrome' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Chrome returns escaped HTML.
	echo WebinoCRM_Site_Chrome::footer_html();
} else {
	?>
<footer class="webina-mega-footer" role="contentinfo">
	<div class="webina-mega-footer__inner">
		<p class="webina-mega-footer__copy">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>
	<?php
}
wp_footer();
?>
</body>
</html>
