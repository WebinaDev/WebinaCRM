<?php
/**
 * Page Wrapper Helper Functions
 * 
 * Helper functions for page wrapper component
 *
 * @package WebinoCRM
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get breadcrumb items for current page
 * 
 * @param string $current_page Current dashboard page slug
 * @return array Array of breadcrumb items with 'title' and 'url'
 */
function webinocrm_get_breadcrumb( $current_page = '' ) {
	$breadcrumb = array();
	$routes = WebinoCRM_Dashboard_Router::get_routes();
	
	// Always start with dashboard
	$breadcrumb[] = array(
		'title' => 'داشبورد',
		'url' => home_url( '/dashboard' ),
	);
	
	// If we have a current page, add it to breadcrumb
	if ( ! empty( $current_page ) && isset( $routes[ $current_page ] ) ) {
		$breadcrumb[] = array(
			'title' => $routes[ $current_page ]['title'],
			'url' => home_url( '/dashboard/' . $current_page ),
		);
	} elseif ( empty( $current_page ) ) {
		// If no current page, we're on dashboard home
		// Remove the last item since we're already there
		array_pop( $breadcrumb );
	}
	
	/**
	 * Filter breadcrumb items
	 * 
	 * @param array $breadcrumb Breadcrumb items
	 * @param string $current_page Current page slug
	 */
	return apply_filters( 'webinocrm_breadcrumb_items', $breadcrumb, $current_page );
}

/**
 * Get page title for current page
 * 
 * @param string $current_page Current dashboard page slug
 * @param string $user_role User role
 * @return string Page title
 */
function webinocrm_get_page_title( $current_page = '', $user_role = '' ) {
	$routes = WebinoCRM_Dashboard_Router::get_routes();
	
	if ( ! empty( $current_page ) && isset( $routes[ $current_page ] ) ) {
		return $routes[ $current_page ]['title'];
	}
	
	// Default dashboard titles based on role
	switch ( $user_role ) {
		case 'system_manager':
			return 'داشبورد مدیر سیستم';
		case 'finance_manager':
			return 'داشبورد مدیر مالی';
		case 'team_member':
			return 'داشبورد عضو تیم';
		case 'customer':
			return 'داشبورد مشتری';
		default:
			return 'داشبورد';
	}
}

