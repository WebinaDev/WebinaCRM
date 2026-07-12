<?php
/**
 * Search Part Template
 * 
 * Displays the search functionality (inline search bar, not modal)
 *
 * @package WebinoCRM
 * @since 2.1.0
 * @var array $component_data Data from component
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$search_config = isset( $component_data['search_config'] ) ? $component_data['search_config'] : array();
$enabled       = isset( $search_config['enabled'] ) ? $search_config['enabled'] : true;

if ( ! $enabled ) {
	return;
}

$placeholder = isset( $search_config['placeholder'] ) ? $search_config['placeholder'] : __( 'Search...', 'webinocrm' );
?>
<div class="header-element header-search d-md-block d-none my-auto auto-complete-search position-relative">
	<!-- Start::header-link -->
	<input type="text" class="header-search-bar form-control" id="global-search-input" placeholder="<?php echo esc_attr( $placeholder ); ?>" spellcheck="false" autocomplete="off" autocapitalize="off">
	<a href="javascript:void(0);" class="header-search-icon border-0">
		<i class="ri-search-line"></i>
	</a>
	<!-- End::header-link -->
	
	<!-- Search Results Dropdown -->
	<div id="global-search-results" class="global-search-dropdown" style="display: none;">
		<div id="global-search-results-content">
			<!-- Results will be inserted here by JavaScript -->
		</div>
	</div>
</div>
