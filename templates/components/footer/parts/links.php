<?php
/**
 * Footer Links Part Template
 * 
 * Displays footer links
 *
 * @package WebinoCRM
 * @since 2.1.0
 * @var array $component_data Data from component
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$footer_links = isset( $component_data['footer_links'] ) ? $component_data['footer_links'] : array();

if ( empty( $footer_links ) ) {
	return;
}
?>
<div class="footer-links mt-2">
	<ul class="list-inline mb-0">
		<?php foreach ( $footer_links as $link ) : ?>
			<?php if ( isset( $link['url'] ) && isset( $link['title'] ) ) : ?>
				<li class="list-inline-item">
					<a href="<?php echo esc_url( $link['url'] ); ?>" 
					   class="text-muted" 
					   <?php echo isset( $link['target'] ) && $link['target'] === '_blank' ? 'target="_blank" rel="noopener"' : ''; ?>>
						<?php echo esc_html( $link['title'] ); ?>
					</a>
				</li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>
</div>

