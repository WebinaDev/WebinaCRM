<?php
/**
 * Site module orchestrator.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Module {

	const OPTION_SEEDED     = 'webinocrm_site_seeded_version';
	const OPTION_NEEDS_SEED = 'webinocrm_site_needs_seed';
	const SEED_LOCK_KEY     = 'webinocrm_site_seed_lock';

	/**
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Boot site features.
	 */
	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		WebinoCRM_Site_Theme::init();
		WebinoCRM_Site_Kit::init();
		WebinoCRM_Site_Portfolio::init();
		WebinoCRM_Site_Theme_Builder::init();
		WebinoCRM_Site_Seo::init();
		WebinoCRM_Site_Performance::init();
		WebinoCRM_Site_Elementor::init();
		WebinoCRM_REST_Site_Portfolio::init();

		add_action( 'admin_post_webinocrm_seed_site', array( __CLASS__, 'handle_admin_seed' ) );
		add_action( 'init', array( __CLASS__, 'maybe_run_seed' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notices' ) );
		add_action( 'load-themes.php', array( __CLASS__, 'render_themes_import_card' ) );
		add_action( 'admin_head-themes.php', array( __CLASS__, 'print_themes_import_styles' ) );
	}

	/**
	 * Run deferred site seed on init when rewrite API is ready.
	 */
	public static function maybe_run_seed() {
		if ( ! self::should_seed() ) {
			return;
		}
		if ( get_transient( self::SEED_LOCK_KEY ) ) {
			return;
		}
		set_transient( self::SEED_LOCK_KEY, 1, MINUTE_IN_SECONDS );
		// Version mismatch or explicit needs_seed flag always force-overwrite Elementor data.
		$force = true;
		WebinoCRM_Site_Seeder::run( $force );
		delete_transient( self::SEED_LOCK_KEY );
	}

	/**
	 * @return bool
	 */
	public static function should_seed() {
		return self::needs_seed() || get_option( self::OPTION_NEEDS_SEED, '0' ) === '1';
	}

	/**
	 * @return bool
	 */
	public static function needs_seed() {
		return get_option( self::OPTION_SEEDED, '' ) !== WEBINOCRM_SITE_VERSION;
	}

	/**
	 * Force seed from WP admin tools.
	 */
	public static function handle_admin_seed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'webinocrm' ) );
		}
		check_admin_referer( 'webinocrm_seed_site' );
		WebinoCRM_Site_Seeder::run( true );
		wp_safe_redirect( admin_url( 'themes.php?webinocrm_site_seeded=1' ) );
		exit;
	}

	/**
	 * Success / status notices.
	 */
	public static function render_admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_GET['webinocrm_site_seeded'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$home = esc_url( home_url( '/' ) );
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html__( 'محتوای فارسی سایت با موفقیت ایمپورت و بازسازی شد.', 'webinocrm' );
		echo ' <a href="' . $home . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'بازدید صفحهٔ خانه', 'webinocrm' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Print compact styles for the Themes screen import card.
	 */
	public static function print_themes_import_styles() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<style>
.webinocrm-site-import-card{margin:16px 0 24px;padding:20px 24px;border:1px solid #c9a22755;border-radius:12px;background:linear-gradient(135deg,#0a0a0b 0%,#18181b 55%,#1a1510 100%);color:#fafafa;box-shadow:0 8px 28px rgba(0,0,0,.18)}
.webinocrm-site-import-card h2{margin:0 0 8px;color:#c9a227;font-size:1.25rem}
.webinocrm-site-import-card p{margin:0 0 14px;color:#a1a1aa;max-width:52rem;line-height:1.7}
.webinocrm-site-import-card .button-hero{background:#c9a227!important;border-color:#c9a227!important;color:#0a0a0b!important;font-weight:700;text-shadow:none;box-shadow:none}
.webinocrm-site-import-card .button-hero:hover{filter:brightness(1.05)}
.webinocrm-site-import-card__meta{font-size:12px;opacity:.85}
</style>';
	}

	/**
	 * Inject import card above the themes list.
	 */
	public static function render_themes_import_card() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_action( 'admin_notices', array( __CLASS__, 'print_themes_import_card' ), 5 );
	}

	/**
	 * Themes.php import CTA.
	 */
	public static function print_themes_import_card() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'themes' !== $screen->id ) {
			return;
		}
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=webinocrm_seed_site' ),
			'webinocrm_seed_site'
		);
		$seeded = (string) get_option( self::OPTION_SEEDED, '' );
		echo '<div class="webinocrm-site-import-card" dir="rtl">';
		echo '<h2>' . esc_html__( 'ایمپورت / بازسازی محتوای سایت وبینا', 'webinocrm' ) . '</h2>';
		echo '<p>' . esc_html__( 'صفحات فارسی، منوها، صفحهٔ اصلی Elementor، هدر/فوتر Theme Builder و فونت یکان‌بخ را یکجا ایمپورت یا بازنویسی می‌کند. برای رفع خانهٔ خالی یا منوی placeholder از این دکمه استفاده کنید.', 'webinocrm' ) . '</p>';
		echo '<p><a class="button button-primary button-hero" href="' . esc_url( $url ) . '">' . esc_html__( 'ایمپورت کامل محتوا (فارسی)', 'webinocrm' ) . '</a></p>';
		if ( $seeded ) {
			echo '<p class="webinocrm-site-import-card__meta">' . esc_html(
				sprintf(
					/* translators: %s: seeded site content version */
					__( 'آخرین نسخهٔ محتوای seed شده: %s', 'webinocrm' ),
					$seeded
				)
			) . '</p>';
		}
		echo '</div>';
	}
}
