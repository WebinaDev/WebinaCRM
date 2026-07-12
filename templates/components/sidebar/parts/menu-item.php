<?php
/**
 * Sidebar Menu Item Part Template
 * 
 * Displays a single menu item
 *
 * @package WebinoCRM
 * @since 2.1.0
 * @var array $menu_item Menu item data
 * @var string $current_page Current page slug
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $menu_item ) ) {
	return;
}

$item_id    = isset( $menu_item['id'] ) ? $menu_item['id'] : '';
$item_title = isset( $menu_item['title'] ) ? $menu_item['title'] : '';
$item_url   = isset( $menu_item['url'] ) ? $menu_item['url'] : '#';
$item_icon  = isset( $menu_item['icon'] ) ? $menu_item['icon'] : '';

// Check if current item is active
$is_active    = ( $item_id === $current_page ) || ( empty( $current_page ) && $item_id === 'dashboard' );
$active_class = $is_active ? ' active' : '';
?>
<li class="slide<?php echo esc_attr( $active_class ); ?>">
	<a href="<?php echo esc_url( $item_url ); ?>" class="side-menu__item">
		<?php if ( $item_icon ) : ?>
			<i class="<?php echo esc_attr( $item_icon ); ?> side-menu__icon"></i>
		<?php endif; ?>
		<span class="side-menu__label"><?php echo esc_html( $item_title ); ?></span>
	</a>
</li>

