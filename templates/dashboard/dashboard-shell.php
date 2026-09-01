<?php
/**
 * Minimal document shell for the React CRM dashboard (SSR first paint + hydrate).
 *
 * @package WebinaCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wd_uid         = get_current_user_id();
$wd_ui_theme    = $wd_uid ? (string) get_user_meta( $wd_uid, 'webino_dashboard_theme', true ) : '';
$wd_ui_accent   = $wd_uid ? (string) get_user_meta( $wd_uid, 'webino_dashboard_accent', true ) : '';
$wd_ui_theme    = $wd_ui_theme ? $wd_ui_theme : 'light';
$wd_ui_accent   = $wd_ui_accent ? $wd_ui_accent : 'colorful';
$wd_accents_ok  = array( 'colorful', 'default', 'red', 'rose', 'orange', 'green', 'blue', 'yellow', 'violet' );
if ( 'amber' === $wd_ui_accent ) {
	$wd_ui_accent = 'orange';
}
if ( ! in_array( $wd_ui_accent, $wd_accents_ok, true ) ) {
	$wd_ui_accent = 'colorful';
}
$wd_html_class = ( 'dark' === $wd_ui_theme ) ? 'dark' : '';
$wd_asset_ver  = class_exists( 'WebinoCRM_Dashboard_Assets' )
	? WebinoCRM_Dashboard_Assets::get_deploy_asset_version()
	: WEBINOCRM_VERSION;

$wd_ssr_bootstrap = null;
$wd_ssr_page      = null;
if ( class_exists( 'WebinoCRM_Dashboard_Assets' ) && $wd_uid > 0 && is_user_logged_in() ) {
	$wd_ssr_bootstrap = WebinoCRM_Dashboard_Assets::get_cached_bootstrap_for_user( $wd_uid );
}
if ( class_exists( 'WebinoCRM_Dashboard_SSR' ) ) {
	$wd_ssr_page = WebinoCRM_Dashboard_SSR::build_page_payload();
}

if ( function_exists( 'webino_apply_language_locale' ) ) {
	webino_apply_language_locale();
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="<?php echo esc_attr( $wd_html_class ); ?>" data-accent="<?php echo esc_attr( $wd_ui_accent ); ?>" data-wd-asset-version="<?php echo esc_attr( $wd_asset_ver ); ?>" data-wd-plugin-version="<?php echo esc_attr( WEBINOCRM_VERSION ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="<?php echo esc_attr( get_bloginfo( 'description', 'display' ) ?: __( 'ERP dashboard', 'webinocrm' ) ); ?>">
	<title><?php echo esc_html( get_bloginfo( 'name' ) . ' — ' . __( 'Dashboard', 'webinocrm' ) ); ?></title>
	<style id="webino-dashboard-chrome-guard">
		body.webino-dashboard-body { margin: 0; }
		body.webino-dashboard-body #root { min-height: 100vh; }
		body.webino-dashboard-body #masthead,
		body.webino-dashboard-body .site-header,
		body.webino-dashboard-body header.elementor-location-header,
		body.webino-dashboard-body .elementor-location-header,
		body.webino-dashboard-body .elementor-location-footer,
		body.webino-dashboard-body #colophon { display: none !important; }
	</style>
	<link rel="preconnect" href="<?php echo esc_url( rest_url() ); ?>" crossorigin />
	<script>
	(function () {
		try {
			var stored = localStorage.getItem('wd-theme');
			var theme = stored || <?php echo wp_json_encode( $wd_ui_theme ); ?>;
			var dark =
				theme === 'dark' ||
				(theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
			document.documentElement.classList.toggle('dark', dark);
			document.documentElement.setAttribute('data-accent', <?php echo wp_json_encode( $wd_ui_accent ); ?>);
		} catch (e) {}
	})();
	</script>
	<script>
	(function () {
		var ver = document.documentElement.getAttribute('data-wd-asset-version') || '';
		try {
			var prev = localStorage.getItem('wd_asset_version');
			if (prev && prev !== ver && !/\bwd_cache_bust=1\b/.test(location.search)) {
				localStorage.setItem('wd_asset_version', ver);
				var bust = new URL(location.href);
				bust.searchParams.set('wd_cache_bust', '1');
				location.replace(bust.toString());
				return;
			}
			localStorage.setItem('wd_asset_version', ver);
		} catch (e) {}
		if ('serviceWorker' in navigator) {
			navigator.serviceWorker.getRegistrations().then(function (regs) {
				regs.forEach(function (reg) {
					reg.unregister();
				});
			});
		}
	})();
	</script>
	<?php
	if ( class_exists( 'WebinoCRM_Dashboard_Assets' ) ) {
		$wd_config = WebinoCRM_Dashboard_Assets::build_dashboard_client_config(
			(int) $wd_asset_ver,
			WEBINOCRM_PLUGIN_URL . 'assets/dashboard-build/'
		);
		echo '<script>window.webinoDashboard=' . wp_json_encode( $wd_config ) . ';</script>';
		WebinoCRM_Dashboard_Assets::render_entry_tags();
	}
	?>
</head>
<body class="webino-dashboard-body">
<?php
if ( class_exists( 'WebinoCRM_Dashboard_Assets' ) ) {
	WebinoCRM_Dashboard_Assets::render_build_missing_notice();
}
?>
<div id="root" data-wd-hydrate="1">
<?php
if ( is_user_logged_in() && class_exists( 'WebinoCRM_Dashboard_SSR' ) ) {
	WebinoCRM_Dashboard_SSR::render_first_paint( $wd_ssr_bootstrap, $wd_ssr_page );
} else {
	?>
	<p id="wd-shell-loader" style="box-sizing:border-box;margin:0;min-height:100vh;padding:1.5rem;font-family:system-ui,sans-serif;font-size:0.9375rem;color:#525252;display:flex;align-items:center;justify-content:center;">
		<?php esc_html_e( 'Loading dashboard…', 'webinocrm' ); ?>
	</p>
	<?php
}
?>
</div>
</body>
</html>
