<?php
/**
 * Logo Part Template
 * 
 * Displays the dashboard logo
 *
 * @package WebinoCRM
 * @since 2.1.0
 * @var array $component_data Data from component
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logo_url      = isset( $component_data['logo_url'] ) ? $component_data['logo_url'] : '';
$dashboard_url = home_url( '/dashboard' );
$assets_url    = WEBINOCRM_PLUGIN_URL . 'assets/';
?>
<a href="<?php echo esc_url( $dashboard_url ); ?>" class="header-logo">
	<?php if ( $logo_url ) : ?>
		<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo', 'webinocrm' ); ?>" class="desktop-logo" style="max-height: 45px;">
		<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo', 'webinocrm' ); ?>" class="toggle-dark" style="max-height: 45px;">
		<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo', 'webinocrm' ); ?>" class="desktop-dark" style="max-height: 45px;">
		<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo', 'webinocrm' ); ?>" class="toggle-logo" style="max-height: 45px;">
		<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo', 'webinocrm' ); ?>" class="toggle-white" style="max-height: 45px;">
		<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo', 'webinocrm' ); ?>" class="desktop-white" style="max-height: 45px;">
	<?php else : ?>
		<img src="<?php echo esc_url( $assets_url . 'images/brand-logos/desktop-logo.png' ); ?>" alt="logo" class="desktop-logo">
		<img src="<?php echo esc_url( $assets_url . 'images/brand-logos/toggle-dark.png' ); ?>" alt="logo" class="toggle-dark">
		<img src="<?php echo esc_url( $assets_url . 'images/brand-logos/desktop-dark.png' ); ?>" alt="logo" class="desktop-dark">
		<img src="<?php echo esc_url( $assets_url . 'images/brand-logos/toggle-logo.png' ); ?>" alt="logo" class="toggle-logo">
		<img src="<?php echo esc_url( $assets_url . 'images/brand-logos/toggle-white.png' ); ?>" alt="logo" class="toggle-white">
		<img src="<?php echo esc_url( $assets_url . 'images/brand-logos/desktop-white.png' ); ?>" alt="logo" class="desktop-white">
	<?php endif; ?>
</a>

