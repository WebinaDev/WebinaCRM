<?php
/**
 * Profile Menu Part Template
 * 
 * Displays user profile dropdown menu
 *
 * @package WebinoCRM
 * @since 2.1.0
 * @var array $component_data Data from component
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user         = isset( $component_data['user'] ) ? $component_data['user'] : array();
$profile_menu = isset( $component_data['profile_menu'] ) ? $component_data['profile_menu'] : array();
$user_name    = isset( $user['name'] ) ? $user['name'] : '';
$user_role    = isset( $user['role'] ) ? $user['role'] : '';
$user_avatar  = isset( $user['avatar'] ) ? $user['avatar'] : '';
$user_id      = isset( $user['id'] ) ? $user['id'] : 0;
?>
<li class="header-element dropdown">
	<!-- Start::header-link|dropdown-toggle -->
	<a href="javascript:void(0);" class="header-link dropdown-toggle" id="mainHeaderProfile" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
		<div class="d-flex align-items-center">
			<div>
				<?php 
				if ( $user_id > 0 ) {
					echo get_avatar( $user_id, 32, '', '', array( 'class' => 'avatar avatar-sm' ) );
				} else {
					echo '<span class="avatar avatar-sm avatar-rounded"><i class="ri-user-3-fill"></i></span>';
				}
				?>
			</div>
		</div>
	</a>
	<!-- End::header-link|dropdown-toggle -->
	<ul class="main-header-dropdown dropdown-menu pt-0 overflow-hidden header-profile-dropdown dropdown-menu-end" aria-labelledby="mainHeaderProfile">
		<li>
			<div class="dropdown-item text-center border-bottom">
				<span id="user-profile-name"><?php echo esc_html( $user_name ); ?></span>
				<span class="d-block fs-12 text-muted" id="user-profile-role"><?php echo esc_html( $user_role ); ?></span>
			</div>
		</li>
		<?php if ( ! empty( $profile_menu ) ) : ?>
			<?php foreach ( $profile_menu as $menu_item ) : ?>
				<li>
					<a class="dropdown-item d-flex align-items-center" href="<?php echo esc_url( $menu_item['url'] ); ?>">
						<?php if ( ! empty( $menu_item['icon'] ) ) : ?>
							<i class="<?php echo esc_attr( $menu_item['icon'] ); ?> p-1 rounded-circle bg-primary-transparent me-2 fs-16"></i>
						<?php endif; ?>
						<?php echo esc_html( $menu_item['title'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		<?php endif; ?>
	</ul>
</li>

