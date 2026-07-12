<?php
/**
 * Sidebar Menu Part Template
 * 
 * Displays the navigation menu with categories
 *
 * @package WebinoCRM
 * @since 2.1.0
 * @var array $component_data Data from component
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$menu_items   = isset( $component_data['menu_items'] ) ? $component_data['menu_items'] : array();
$current_page = isset( $component_data['current_page'] ) ? $component_data['current_page'] : '';

if ( empty( $menu_items ) ) {
	return;
}
?>

<?php foreach ( $menu_items as $menu_item ) : ?>
	<?php
	// Check if this is a category
	if ( isset( $menu_item['type'] ) && $menu_item['type'] === 'category' ) {
		?>
		<!-- Start::slide__category -->
		<li class="slide__category"><span class="category-name"><?php echo esc_html( $menu_item['title'] ); ?></span></li>
		<!-- End::slide__category -->
		<?php
	} else {
		// Load menu item template
		$this->load_template(
			'parts/menu-item.php',
			array(
				'menu_item'    => $menu_item,
				'current_page' => $current_page,
			)
		);
	}
	?>
<?php endforeach; ?>

