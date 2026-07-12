<?php
/**
 * Notifications Part Template
 * 
 * Displays notifications dropdown (exact copy from maneli)
 *
 * @package WebinoCRM
 * @since 2.1.0
 * @var array $component_data Data from component
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notifications = isset( $component_data['notifications'] ) ? $component_data['notifications'] : array();
$unread_count  = isset( $component_data['unread_count'] ) ? $component_data['unread_count'] : 0;
?>
<li class="header-element notifications-dropdown d-xl-block d-none dropdown">
	<!-- Start::header-link|dropdown-toggle -->
	<a href="javascript:void(0);" class="header-link dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="messageDropdown" aria-expanded="false">
		<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 header-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
			<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5"></path>
		</svg>
		<span class="header-icon-pulse bg-primary2 rounded pulse pulse-secondary <?php echo ( $unread_count > 0 ) ? '' : 'd-none'; ?>" id="header-notification-count"></span>
	</a>
	<!-- End::header-link|dropdown-toggle -->
	
	<!-- Start::main-header-dropdown -->
	<div class="main-header-dropdown dropdown-menu dropdown-menu-end notifications-dropdown-menu" data-popper-placement="none">
		<div class="p-3">
			<div class="d-flex align-items-center justify-content-between">
				<p class="mb-0 fs-15 fw-medium"><?php esc_html_e( 'Notifications', 'webinocrm' ); ?></p>
				<span class="badge bg-secondary text-fixed-white" id="notifiation-data">
					<?php
					if ( $unread_count > 0 ) {
						printf(
							/* translators: %s: Number of unread */
							esc_html__( '%s Unread', 'webinocrm' ),
							esc_html( number_format_i18n( $unread_count ) )
						);
					} else {
						esc_html_e( '0 Unread', 'webinocrm' );
					}
					?>
				</span>
			</div>
		</div>
		<div class="dropdown-divider"></div>
		<ul class="list-unstyled mb-0" id="header-notification-scroll">
			<?php if ( empty( $notifications ) ) : ?>
				<li class="dropdown-item text-center">
					<div class="text-center">
						<span class="avatar avatar-xl avatar-rounded bg-secondary-transparent">
							<i class="ri-notification-off-line fs-2"></i>
						</span>
						<h6 class="fw-medium mt-3"><?php esc_html_e( 'No notifications available', 'webinocrm' ); ?></h6>
					</div>
				</li>
			<?php else : ?>
				<?php foreach ( $notifications as $notification ) : ?>
					<li class="dropdown-item">
						<div class="d-flex align-items-center">
							<div class="pe-2 lh-1">
								<span class="avatar avatar-md avatar-rounded <?php echo esc_attr( $notification['avatar_class'] ?? 'bg-primary' ); ?>">
									<?php if ( ! empty( $notification['avatar'] ) ) : ?>
										<img src="<?php echo esc_url( $notification['avatar'] ); ?>" alt="user">
									<?php else : ?>
										<i class="<?php echo esc_attr( $notification['icon'] ?? 'ri-notification-3-line' ); ?>"></i>
									<?php endif; ?>
								</span>
							</div>
							<div class="flex-grow-1 d-flex align-items-center justify-content-between">
								<div>
									<p class="mb-0 fw-medium">
										<a href="<?php echo esc_url( $notification['url'] ?? 'javascript:void(0);' ); ?>">
											<?php echo esc_html( $notification['title'] ?? '' ); ?>
										</a>
									</p>
									<div class="text-muted fw-normal fs-12 header-notification-text text-truncate">
										<?php echo esc_html( $notification['message'] ?? '' ); ?>
									</div>
									<div class="fw-normal fs-10 text-muted op-8">
										<?php echo esc_html( $notification['time'] ?? '' ); ?>
									</div>
								</div>
								<div>
									<a href="javascript:void(0);" class="min-w-fit-content dropdown-item-close1">
										<i class="ri-close-line"></i>
									</a>
								</div>
							</div>
						</div>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
		
		<div class="p-3 empty-header-item1 border-top">
			<div class="d-grid">
				<a href="<?php echo esc_url( home_url( '/dashboard/notifications' ) ); ?>" class="btn btn-primary btn-wave">
					<?php esc_html_e( 'View All', 'webinocrm' ); ?>
				</a>
			</div>
		</div>
	</div>
	<!-- End::main-header-dropdown -->
</li>
