<?php
/**
 * Header Template
 * 
 * Main header template for dashboard (copied structure from maneli-car-inquiry)
 *
 * @package WebinoCRM
 * @since 2.1.0
 * @var array $component_data Data from component
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Start::app-header -->
<header class="app-header">

	<!-- Start::main-header-container -->
	<div class="main-header-container container-fluid">

		<!-- Start::header-content-left (RTL: will be on right) -->
		<div class="header-content-left">

			<!-- Start::header-element -->
			<div class="header-element">
				<div class="horizontal-logo">
					<?php $this->load_template( 'parts/logo.php' ); ?>
				</div>
			</div>
			<!-- End::header-element -->

			<!-- Start::header-element -->
			<div class="header-element mx-lg-0 mx-2">
				<a aria-label="<?php esc_attr_e( 'Hide Sidebar', 'webinocrm' ); ?>" class="sidemenu-toggle header-link animated-arrow hor-toggle horizontal-navtoggle" data-bs-toggle="sidebar" href="javascript:void(0);">
					<span></span>
				</a>
			</div>
			<!-- End::header-element -->

			<!-- Start::header-element -->
			<?php $this->load_template( 'parts/search.php' ); ?>
			<!-- End::header-element -->

		</div>
		<!-- End::header-content-left -->

		<!-- Start::header-content-right (RTL: will be on left) -->
		<ul class="header-content-right">

			<!-- Start::header-element (mobile search) -->
			<li class="header-element d-md-none d-block">
				<a href="javascript:void(0);" class="header-link" data-bs-toggle="modal" data-bs-target="#header-responsive-search">
					<i class="bi bi-search header-link-icon d-flex"></i>
				</a>
			</li>
			<!-- End::header-element -->

			<!-- Start::header-element (view site) -->
			<li class="header-element header-view-site">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="header-link header-view-site-link" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'View Site', 'webinocrm' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5h13.5M5.25 12h13.5m-13.5 4.5H12"></path>
						<path stroke-linecap="round" stroke-linejoin="round" d="M21 3v7.5a2.25 2.25 0 0 1-2.25 2.25H17.5L21 21 6.75 12.75H5.25A2.25 2.25 0 0 1 3 10.5V3A2.25 2.25 0 0 1 5.25.75h13.5A2.25 2.25 0 0 1 21 3Z"></path>
					</svg>
					<span class="header-view-site-text"><?php esc_html_e( 'View Site', 'webinocrm' ); ?></span>
				</a>
			</li>
			<!-- End::header-element -->

			<!-- Start::header-element (language switcher) -->
			<?php $this->load_template( 'parts/language-switcher.php' ); ?>
			<!-- End::header-element -->

			<!-- Start::header-element (notifications) -->
			<?php $this->load_template( 'parts/notifications.php' ); ?>
			<!-- End::header-element -->

			<!-- Start::header-element (fullscreen) -->
			<li class="header-element header-fullscreen">
				<a onclick="toggleFullscreen();" href="javascript:void(0);" class="header-link">
					<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 full-screen-open header-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"></path>
					</svg>
					<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 full-screen-close header-link-icon d-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25"></path>
					</svg>
				</a>
			</li>
			<!-- End::header-element -->

			<!-- Start::header-element (dark mode) -->
			<li class="header-element header-theme-mode">
				<a href="javascript:void(0);" class="header-link layout-setting">
					<span class="light-layout">
						<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 header-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"></path>
						</svg>
					</span>
					<span class="dark-layout">
						<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 header-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"></path>
						</svg>
					</span>
				</a>
			</li>
			<!-- End::header-element -->

			<!-- Start::header-element (profile) -->
			<?php $this->load_template( 'parts/profile-menu.php' ); ?>
			<!-- End::header-element -->

		</ul>
		<!-- End::header-content-right -->

	</div>
	<!-- End::main-header-container -->

</header>
<!-- End::app-header -->

<!-- Responsive Search Modal -->
<div class="modal fade" id="header-responsive-search" tabindex="-1" aria-labelledby="header-responsive-search" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-body">
				<div class="input-group">
					<input type="text" class="form-control border-end-0" placeholder="<?php esc_attr_e( 'Search...', 'webinocrm' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'webinocrm' ); ?>">
					<button class="btn btn-outline-light bg-transparent" type="button">
						<i class="ri-search-line"></i>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- End Responsive Search Modal -->
