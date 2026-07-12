<?php
/**
 * Language Switcher Part Template
 * 
 * Displays language selection dropdown (exact copy from maneli)
 *
 * @package WebinoCRM
 * @since 2.1.0
 * @var array $component_data Data from component
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$language_options = isset( $component_data['language_options'] ) ? $component_data['language_options'] : array();
$assets_url       = WEBINOCRM_PLUGIN_URL . 'assets/';
?>
<li class="header-element country-selector dropdown">
	<!-- Start::header-link|dropdown-toggle -->
	<a href="javascript:void(0);" class="header-link dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown">
		<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 header-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
			<path stroke-linecap="round" stroke-linejoin="round" d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 0 1-3.827-5.802"></path>
		</svg>
	</a>
	<!-- End::header-link|dropdown-toggle -->
	<ul class="main-header-dropdown dropdown-menu dropdown-menu-end" data-popper-placement="none">
		<li>
			<a class="dropdown-item d-flex align-items-center" href="javascript:void(0);" onclick="changeLanguage('fa')">
				<div class="d-flex align-items-center justify-content-between">
					<div class="d-flex align-items-center">
						<span class="avatar avatar-rounded avatar-xs lh-1 me-2 bg-success-transparent">
							<span class="fs-10 fw-bold">FA</span>
						</span>
						<?php esc_html_e( 'Persian', 'webinocrm' ); ?>
					</div>
				</div>
			</a>
		</li>
		<li>
			<a class="dropdown-item d-flex align-items-center" href="javascript:void(0);" onclick="changeLanguage('en')">
				<div class="d-flex align-items-center justify-content-between">
					<div class="d-flex align-items-center">
						<span class="avatar avatar-rounded avatar-xs lh-1 me-2">
							<img src="<?php echo esc_url( $assets_url . 'images/flags/us_flag.jpg' ); ?>" alt="<?php esc_attr_e( 'English', 'webinocrm' ); ?>">
						</span>
						<?php esc_html_e( 'English', 'webinocrm' ); ?>
					</div>
				</div>
			</a>
		</li>
	</ul>
</li>
