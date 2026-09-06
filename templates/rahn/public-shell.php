<?php
/**
 * Public rahn-percent calculator shell.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$token = sanitize_text_field( (string) get_query_var( 'rahn_token' ) );
$rest  = esc_url_raw( rest_url( 'webinocrm/v1/' ) );
$build_dir = WEBINOCRM_PLUGIN_DIR . 'assets/dashboard-build/';
$build_url = WEBINOCRM_PLUGIN_URL . 'assets/dashboard-build/';

$js_rel  = '';
$css_rel = '';
$manifest_path = $build_dir . 'manifest.json';
if ( is_readable( $manifest_path ) ) {
	$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
	if ( is_array( $manifest ) ) {
		$entry = null;
		foreach ( array( 'rahn-public.html', 'src/rahn-public.tsx' ) as $key ) {
			if ( ! empty( $manifest[ $key ] ) && is_array( $manifest[ $key ] ) ) {
				$entry = $manifest[ $key ];
				break;
			}
		}
		if ( is_array( $entry ) ) {
			if ( ! empty( $entry['file'] ) ) {
				$js_rel = ltrim( (string) $entry['file'], '/' );
			}
			if ( ! empty( $entry['css'][0] ) ) {
				$css_rel = ltrim( (string) $entry['css'][0], '/' );
			}
			// CSS may live on imported shared chunks.
			if ( '' === $css_rel && ! empty( $entry['imports'] ) && is_array( $entry['imports'] ) ) {
				foreach ( $entry['imports'] as $import_key ) {
					if ( empty( $manifest[ $import_key ]['css'][0] ) ) {
						continue;
					}
					$css_rel = ltrim( (string) $manifest[ $import_key ]['css'][0], '/' );
					break;
				}
			}
		}
		if ( '' === $css_rel ) {
			foreach ( $manifest as $chunk ) {
				if ( is_array( $chunk ) && ! empty( $chunk['css'][0] ) ) {
					$css_rel = ltrim( (string) $chunk['css'][0], '/' );
					break;
				}
			}
		}
	}
}

// Fallback: use main dashboard CSS if public entry CSS missing.
if ( '' === $css_rel && is_readable( $build_dir . 'build-entry.json' ) ) {
	$entry = json_decode( (string) file_get_contents( $build_dir . 'build-entry.json' ), true );
	if ( is_array( $entry ) && ! empty( $entry['css'] ) ) {
		$css_rel = (string) $entry['css'];
	}
}

$config = array(
	'token'   => $token,
	'restUrl' => $rest,
	'siteName'=> get_bloginfo( 'name' ),
	'homeUrl' => home_url( '/' ),
	'isRtl'   => is_rtl(),
	'locale'  => determine_locale(),
);

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html( get_bloginfo( 'name' ) . ' — ' . __( 'ماشین‌حساب رهن‌درصد', 'webinocrm' ) ); ?></title>
	<?php if ( $css_rel ) : ?>
		<link rel="stylesheet" href="<?php echo esc_url( $build_url . $css_rel ); ?>?ver=<?php echo esc_attr( (string) @filemtime( $build_dir . $css_rel ) ); ?>" />
	<?php endif; ?>
	<style>
		body.webino-rahn-public { margin: 0; min-height: 100vh; background: #f6f7fb; font-family: IRANSans, Tahoma, sans-serif; }
		#rahn-root { min-height: 100vh; }
	</style>
	<script>
		window.webinoRahnPublic = <?php echo wp_json_encode( $config ); ?>;
	</script>
</head>
<body class="webino-rahn-public<?php echo is_rtl() ? ' rtl' : ''; ?>" <?php echo is_rtl() ? 'dir="rtl"' : 'dir="ltr"'; ?>>
	<div id="rahn-root"></div>
	<?php if ( $js_rel && is_readable( $build_dir . $js_rel ) ) : ?>
		<script type="module" src="<?php echo esc_url( $build_url . $js_rel ); ?>?ver=<?php echo esc_attr( (string) filemtime( $build_dir . $js_rel ) ); ?>"></script>
	<?php else : ?>
		<script>
		(function () {
			var cfg = window.webinoRahnPublic || {};
			var root = document.getElementById('rahn-root');
			root.innerHTML = '<div style="max-width:640px;margin:40px auto;padding:24px;background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.06)">' +
				'<h1 style="margin-top:0"><?php echo esc_js( __( 'ماشین‌حساب رهن‌درصد', 'webinocrm' ) ); ?></h1>' +
				'<p><?php echo esc_js( __( 'در حال بارگذاری… اگر این پیام ماند، بیلد فرانت عمومی هنوز ساخته نشده است.', 'webinocrm' ) ); ?></p>' +
				'<p dir="ltr" style="opacity:.6;font-size:12px">token: ' + (cfg.token || '') + '</p></div>';
			fetch(cfg.restUrl + 'rahn/public/' + encodeURIComponent(cfg.token))
				.then(function (r) { return r.json(); })
				.then(function (json) {
					var d = (json && json.data) ? json.data : json;
					if (!d || !d.public) {
						root.querySelector('p').textContent = (json && json.message) || '<?php echo esc_js( __( 'پیش‌نویس یافت نشد.', 'webinocrm' ) ); ?>';
						return;
					}
					var p = d.public;
					var html = '<div style="max-width:720px;margin:40px auto;padding:28px;background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.07)">' +
						'<h1 style="margin:0 0 8px">' + (d.title || '<?php echo esc_js( __( 'پیشنهاد رهن‌درصد', 'webinocrm' ) ); ?>') + '</h1>' +
						'<p style="color:#666;margin:0 0 20px">' + (d.site_name || cfg.siteName || '') + '</p>' +
						'<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">' +
						'<div style="padding:16px;border-radius:12px;background:#f3f0ff"><div style="font-size:12px;color:#666"><?php echo esc_js( __( 'ثابت ماهانه', 'webinocrm' ) ); ?></div><div style="font-size:22px;font-weight:700">' + Math.round(p.F).toLocaleString('fa-IR') + '</div></div>' +
						'<div style="padding:16px;border-radius:12px;background:#eef9f1"><div style="font-size:12px;color:#666"><?php echo esc_js( __( 'درصد از فروش', 'webinocrm' ) ); ?></div><div style="font-size:22px;font-weight:700">' + (p.p_percent || 0).toLocaleString('fa-IR') + '٪</div></div>' +
						'</div>' +
						'<p style="padding:14px;background:#fafafa;border-radius:10px;border:1px solid #eee">' + (p.clause || '') + '</p>' +
						'<p style="font-size:13px;color:#888"><?php echo esc_js( __( 'برای تجربه کامل اسلایدر، بیلد SPA را اجرا کنید.', 'webinocrm' ) ); ?></p>' +
						'</div>';
					root.innerHTML = html;
				})
				.catch(function () {
					root.querySelector('p').textContent = '<?php echo esc_js( __( 'خطا در دریافت اطلاعات.', 'webinocrm' ) ); ?>';
				});
		})();
		</script>
	<?php endif; ?>
</body>
</html>
