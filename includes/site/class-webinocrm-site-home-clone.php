<?php
/**
 * Pixel-structure + motion-ready clone of DMROOM homepage (Webina-branded).
 *
 * Media slots: assets/site/media/home/* or brand['media'] URLs.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Home_Clone {

	/**
	 * Full homepage HTML for a single Elementor HTML widget.
	 *
	 * @param array<string,mixed> $copy Page copy.
	 * @return string
	 */
	public static function render( $copy = array() ) {
		$brand = function_exists( 'webinocrm_site_brand' ) ? webinocrm_site_brand() : array();
		ob_start();
		?>
<div class="wdm-home" id="wdm-home">
	<?php self::section_marquee( $brand ); ?>
	<?php self::section_hero( $copy, $brand ); ?>
	<?php self::section_seo( $brand ); ?>
	<?php self::section_webdesign( $brand ); ?>
	<?php self::section_uiux( $brand ); ?>
	<?php self::section_goals( $brand ); ?>
	<?php self::section_testimonials( $brand ); ?>
	<?php self::section_ceo( $brand ); ?>
	<?php self::section_team( $brand ); ?>
	<?php self::section_lead( $brand ); ?>
	<?php self::section_blog(); ?>
	<?php self::section_bottom_stats( $brand ); ?>
</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Resolve a named media slot: brand URL → plugin file → empty.
	 *
	 * @param string              $key   Slot key or filename without path.
	 * @param array<string,mixed> $brand Brand.
	 * @return string Absolute URL or empty.
	 */
	private static function media_url( $key, $brand = array() ) {
		$media = (array) ( $brand['media'] ?? array() );
		if ( ! empty( $media[ $key ] ) && is_string( $media[ $key ] ) ) {
			return (string) $media[ $key ];
		}
		$file = WEBINOCRM_PLUGIN_DIR . 'assets/site/media/home/' . $key;
		if ( is_readable( $file ) ) {
			return WEBINOCRM_PLUGIN_URL . 'assets/site/media/home/' . $key;
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $brand Brand.
	 * @param string              $key   Array slot in brand media.
	 * @param int                 $index Index.
	 * @param string              $file  Fallback filename.
	 * @return string
	 */
	private static function media_list_url( $brand, $key, $index, $file ) {
		$media = (array) ( $brand['media'] ?? array() );
		$list  = (array) ( $media[ $key ] ?? array() );
		if ( ! empty( $list[ $index ] ) && is_string( $list[ $index ] ) ) {
			return (string) $list[ $index ];
		}
		$path = WEBINOCRM_PLUGIN_DIR . 'assets/site/media/home/' . $file;
		if ( is_readable( $path ) ) {
			return WEBINOCRM_PLUGIN_URL . 'assets/site/media/home/' . $file;
		}
		return '';
	}

	/**
	 * Decorative layered blob.
	 *
	 * @param string $class Extra class.
	 * @return string
	 */
	private static function svg_blob( $class = '' ) {
		$id = 'wdmG' . esc_attr( substr( md5( $class ), 0, 6 ) );
		return '<svg class="wdm-blob ' . esc_attr( $class ) . '" viewBox="0 0 400 400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#CA2C24" stop-opacity=".4"/><stop offset="55%" stop-color="#f3d4d2" stop-opacity=".35"/><stop offset="100%" stop-color="#231110" stop-opacity=".1"/></linearGradient></defs><path fill="url(#' . $id . ')" d="M320 80c50 40 70 110 50 170s-80 100-150 110S70 320 50 250 70 110 140 70s130-30 180 10z"/></svg>';
	}

	/**
	 * Animated UI scene inside a device frame (Webina original).
	 *
	 * @param string $variant hero|seo|port|ui.
	 * @return string
	 */
	private static function svg_scene( $variant = 'hero' ) {
		$uid = esc_attr( substr( md5( (string) $variant ), 0, 8 ) );
		ob_start();
		?>
<svg class="wdm-scene wdm-scene--<?php echo esc_attr( $variant ); ?>" viewBox="0 0 400 320" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
	<defs>
		<linearGradient id="sc<?php echo $uid; ?>" x1="0" y1="0" x2="1" y2="1">
			<stop offset="0%" stop-color="#CA2C24"/>
			<stop offset="100%" stop-color="#231110"/>
		</linearGradient>
	</defs>
	<rect width="400" height="320" rx="18" fill="#F7F5F4"/>
	<rect x="18" y="18" width="364" height="36" rx="10" fill="#fff"/>
	<circle cx="38" cy="36" r="6" fill="#CA2C24"/>
	<circle cx="58" cy="36" r="6" fill="#e8b4b0"/>
	<circle cx="78" cy="36" r="6" fill="#d9d9d9"/>
	<rect x="18" y="68" width="160" height="180" rx="14" fill="#fff" stroke="rgba(35,17,16,.06)"/>
	<rect x="34" y="88" width="100" height="12" rx="6" fill="url(#sc<?php echo $uid; ?>)" opacity=".85">
		<animate attributeName="width" values="70;128;90;70" dur="4.5s" repeatCount="indefinite"/>
	</rect>
	<rect x="34" y="112" width="128" height="8" rx="4" fill="#eee"/>
	<rect x="34" y="128" width="110" height="8" rx="4" fill="#f0f0f0"/>
	<rect x="34" y="160" width="128" height="64" rx="10" fill="#FAF0EF"/>
	<path d="M50 210 L90 170 L120 195 L150 155" fill="none" stroke="#CA2C24" stroke-width="3" stroke-linecap="round">
		<animate attributeName="stroke-dasharray" values="0 200;180 20" dur="2.8s" repeatCount="indefinite"/>
	</path>
	<rect x="198" y="68" width="184" height="84" rx="14" fill="#231110"/>
	<rect x="214" y="88" width="90" height="10" rx="5" fill="#CA2C24"/>
	<rect x="214" y="110" width="140" height="8" rx="4" fill="rgba(255,255,255,.25)"/>
	<rect x="214" y="126" width="110" height="8" rx="4" fill="rgba(255,255,255,.15)"/>
	<rect x="198" y="164" width="88" height="84" rx="14" fill="#fff" stroke="rgba(35,17,16,.06)"/>
	<rect x="294" y="164" width="88" height="84" rx="14" fill="#fff" stroke="rgba(35,17,16,.06)"/>
	<circle cx="242" cy="206" r="18" fill="#FAF0EF" stroke="#CA2C24" stroke-width="3" stroke-dasharray="80" stroke-dashoffset="40">
		<animate attributeName="stroke-dashoffset" values="80;10;80" dur="3.2s" repeatCount="indefinite"/>
	</circle>
	<circle cx="338" cy="206" r="18" fill="#F5F5F5" stroke="#231110" stroke-width="3" stroke-dasharray="80" stroke-dashoffset="55">
		<animate attributeName="stroke-dashoffset" values="90;20;90" dur="3.8s" repeatCount="indefinite"/>
	</circle>
</svg>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Client logo monogram SVG.
	 *
	 * @param string $label Label.
	 * @return string
	 */
	private static function logo_chip( $label ) {
		$letter = mb_substr( (string) $label, 0, 1 );
		return '<span class="wdm-marquee__logo" aria-hidden="true"><svg viewBox="0 0 120 40" xmlns="http://www.w3.org/2000/svg"><rect width="120" height="40" rx="8" fill="#F3F3F3"/><text x="60" y="26" text-anchor="middle" font-size="14" font-family="Tahoma,sans-serif" font-weight="700" fill="#9a9a9a">' . esc_html( $letter . ' · ' . mb_substr( (string) $label, 0, 8 ) ) . '</text></svg></span>';
	}

	/**
	 * Media or scene inside a figure.
	 *
	 * @param string $url     Media URL.
	 * @param string $variant Scene variant.
	 * @param string $alt     Alt text.
	 * @param string $class   Extra class on figure.
	 */
	private static function media_or_scene( $url, $variant, $alt = '', $class = '' ) {
		echo '<figure class="wdm-media ' . esc_attr( $class ) . '">';
		if ( $url ) {
			echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async" />';
		} else {
			echo self::svg_scene( $variant ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</figure>';
	}

	/**
	 * @param array<string,mixed> $brand Brand.
	 */
	private static function section_marquee( $brand ) {
		$logos = (array) ( $brand['client_logos'] ?? array() );
		if ( empty( $logos ) ) {
			$logos = array( 'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta' );
		}
		$custom = (array) ( ( $brand['media']['logos'] ?? array() ) );
		echo '<section class="wdm-marquee" aria-label="برندهای همکار" data-wdm-reveal="up">';
		echo '<div class="wdm-marquee__track">';
		$loop = array_merge( $logos, $logos );
		foreach ( $loop as $i => $logo ) {
			$url = '';
			if ( ! empty( $custom[ $i % max( 1, count( $logos ) ) ] ) ) {
				$url = (string) $custom[ $i % count( $logos ) ];
			}
			echo '<span class="wdm-marquee__item">';
			if ( $url ) {
				echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( (string) $logo ) . '" loading="lazy" />';
			} else {
				echo self::logo_chip( (string) $logo ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</span>';
		}
		echo '</div></section>';
	}

	/**
	 * @param array<string,mixed> $copy  Copy.
	 * @param array<string,mixed> $brand Brand.
	 */
	private static function section_hero( $copy, $brand ) {
		$lead = (string) ( $copy['excerpt'] ?? 'ما نقشه راه کسب‌وکار را برای افزایش فروش اینترنتی ترسیم می‌کنیم.' );
		$hero = self::media_url( 'hero.webp', $brand );
		if ( ! $hero ) {
			$hero = self::media_url( 'hero', $brand );
		}
		?>
<section class="wdm-hero">
	<div class="wdm-hero__bg" data-wdm-parallax="0.12" aria-hidden="true">
		<?php echo self::svg_blob( 'wdm-blob--hero-bg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<div class="wdm-hero__inner">
		<div class="wdm-hero__content" data-wdm-reveal="right">
			<h1 class="wdm-hero__title">وبینا؛ فـــراتر از <em>نتیجه</em>...</h1>
			<p class="wdm-hero__lead"><?php echo esc_html( $lead ); ?></p>
			<button type="button" class="wdm-btn wdm-btn--primary" data-webina-popup-open>درخواست مشاوره تخصصی</button>
		</div>
		<div class="wdm-hero__art" data-wdm-reveal="left" data-wdm-parallax="0.08">
			<div class="wdm-hero__stage">
				<?php self::media_or_scene( $hero, 'hero', 'وبینا', 'wdm-media--hero' ); ?>
				<div class="wdm-hero__float wdm-hero__float--a" aria-hidden="true">
					<span>Growth</span><strong>+۴۰۰</strong>
				</div>
				<div class="wdm-hero__float wdm-hero__float--b" aria-hidden="true">
					<span>SEO · Design</span>
				</div>
			</div>
		</div>
	</div>
</section>
		<?php
	}

	/**
	 * Circular progress counter markup.
	 *
	 * @param array<string,mixed> $st Stat.
	 * @param int                 $i  Index.
	 */
	private static function counter_ring( $st, $i ) {
		$v      = (string) ( $st['v'] ?? '0' );
		$suffix = (string) ( $st['suffix'] ?? '' );
		$label  = (string) ( $st['l'] ?? '' );
		$pct    = max( 0, min( 100, (int) $v ) );
		?>
		<div class="wdm-counter" data-wdm-reveal="up" style="--wdm-delay:<?php echo esc_attr( (string) ( $i * 0.12 ) ); ?>s">
			<div class="wdm-counter__ring" data-wdm-ring="<?php echo esc_attr( (string) $pct ); ?>">
				<svg viewBox="0 0 120 120" aria-hidden="true">
					<circle class="wdm-counter__track" cx="60" cy="60" r="52"/>
					<circle class="wdm-counter__progress" cx="60" cy="60" r="52" pathLength="100"/>
				</svg>
				<div class="wdm-counter__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M12 2l2.4 7.2H22l-6 4.4 2.3 7L12 16.8 5.7 20.6 8 13.6 2 9.2h7.6z"/></svg>
				</div>
				<strong data-count="<?php echo esc_attr( $v ); ?>" data-suffix="<?php echo esc_attr( $suffix ); ?>">0</strong>
			</div>
			<span><?php echo esc_html( $label ); ?></span>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $brand Brand.
	 */
	private static function section_seo( $brand ) {
		$stats = (array) ( $brand['stats'] ?? array() );
		$lg    = self::media_url( 'seo_device_lg', $brand );
		if ( ! $lg ) {
			$lg = self::media_url( 'seo-device-lg.webp', $brand );
		}
		$sm = self::media_url( 'seo_device_sm', $brand );
		if ( ! $sm ) {
			$sm = self::media_url( 'seo-device-sm.webp', $brand );
		}
		?>
<section class="wdm-split wdm-split--seo">
	<div class="wdm-split__decor" data-wdm-parallax="0.15" aria-hidden="true">
		<?php echo self::svg_blob( 'wdm-blob--seo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<div class="wdm-container wdm-split__grid">
		<div class="wdm-split__text" data-wdm-reveal="right">
			<h2>خدمات سئو سایت</h2>
			<p>بهینه‌سازی سایت برای موتورهای جستجو (SEO)، بهترین روش جذب کاربر هدفمند برای کسب‌وکار شماست! سئو می‌تواند با رشد جایگاه کلمات کلیدی درآمدزا در نتایج جستجو، فروش اینترنتی سایت را تا چندین برابر افزایش دهد.</p>
			<a class="wdm-btn wdm-btn--primary" href="<?php echo esc_url( home_url( '/services/growth/' ) ); ?>">سفارش خدمات سئو</a>
			<div class="wdm-counters">
				<?php foreach ( $stats as $i => $st ) : ?>
					<?php self::counter_ring( (array) $st, (int) $i ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="wdm-split__visual" data-wdm-reveal="left">
			<div class="wdm-device-stack">
				<div class="wdm-device wdm-device--lg" data-wdm-parallax="0.06">
					<?php self::media_or_scene( $lg, 'seo', 'سئو وبینا', 'wdm-media--device' ); ?>
				</div>
				<div class="wdm-device wdm-device--sm" data-wdm-parallax="0.12">
					<?php self::media_or_scene( $sm, 'seo', '', 'wdm-media--device-sm' ); ?>
				</div>
			</div>
		</div>
	</div>
</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $brand Brand.
	 */
	private static function section_webdesign( $brand ) {
		$items = array(
			'طراحی فروشگاهی', 'سایت شرکتی', 'لندینگ تبدیل', 'پورتال سازمانی',
			'بازطراحی برند', 'سایت خدماتی', 'مارکت‌پلیس', 'وب‌اپلیکیشن',
		);
		?>
<section class="wdm-split wdm-split--web">
	<div class="wdm-split__decor wdm-split__decor--iso" data-wdm-parallax="0.1" aria-hidden="true"></div>
	<div class="wdm-container wdm-split__grid wdm-split__grid--reverse">
		<div class="wdm-split__text" data-wdm-reveal="left">
			<h2>خدمات طراحی وب‌سایت</h2>
			<p>طراحی سایت حرفه‌ای و کاربرپسند، اولین قدم موفقیت کسب‌وکار شما در دنیای دیجیتال است. ما با جدیدترین تکنیک‌ها وب‌سایت‌هایی می‌سازیم که زیبا، سریع و تبدیل‌محور باشند.</p>
			<ul class="wdm-pills">
				<li>User friendly</li>
				<li>Mobile friendly</li>
				<li>Google friendly</li>
			</ul>
			<a class="wdm-btn wdm-btn--primary" href="<?php echo esc_url( home_url( '/services/tech/' ) ); ?>">سفارش خدمات طراحی سایت</a>
		</div>
		<div class="wdm-split__visual wdm-split__visual--carousel" data-wdm-reveal="right">
			<div class="wdm-carousel" data-wdm-carousel data-wdm-autoplay="4200">
				<div class="wdm-carousel__track">
					<?php foreach ( $items as $i => $title ) : ?>
						<?php
						$file = 'port-0' . ( $i + 1 ) . '.webp';
						$url  = self::media_list_url( $brand, 'ports', $i, $file );
						?>
						<article class="wdm-card-port">
							<div class="wdm-card-port__thumb">
								<?php self::media_or_scene( $url, 'port', $title ); ?>
							</div>
							<strong><?php echo esc_html( $title ); ?></strong>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $brand Brand.
	 */
	private static function section_uiux( $brand ) {
		$items = array( 'App UI Kit', 'Dashboard UX', 'Mobile Flow', 'Landing UI', 'Brand System', 'Prototype', 'Design System' );
		?>
<section class="wdm-split wdm-split--ui">
	<div class="wdm-split__decor wdm-split__decor--wide" data-wdm-parallax="0.08" aria-hidden="true"></div>
	<div class="wdm-container wdm-split__grid">
		<div class="wdm-split__text" data-wdm-reveal="right">
			<h2>خدمات طراحی رابط کاربری</h2>
			<p>طراحی رابط کاربری فراتر از زیبایی ظاهری است. با اصول UI/UX تجربه کاربری می‌سازیم که با هویت برند هم‌خوان باشد و در رقابت آنلاین بدرخشد.</p>
			<ul class="wdm-pills">
				<li>UX design</li>
				<li>Web app design</li>
				<li>Mobile app design</li>
			</ul>
			<a class="wdm-btn wdm-btn--primary" href="<?php echo esc_url( home_url( '/services/branding/' ) ); ?>">سفارش طراحی رابط کاربری</a>
		</div>
		<div class="wdm-split__visual" data-wdm-reveal="left">
			<div class="wdm-carousel wdm-carousel--tall" data-wdm-carousel data-wdm-autoplay="3800">
				<div class="wdm-carousel__track">
					<?php foreach ( $items as $i => $title ) : ?>
						<?php
						$file = 'ui-0' . ( $i + 1 ) . '.webp';
						$url  = self::media_list_url( $brand, 'ui_ports', $i, $file );
						?>
						<article class="wdm-card-port wdm-card-port--tall">
							<div class="wdm-card-port__thumb">
								<?php self::media_or_scene( $url, 'ui', $title ); ?>
							</div>
							<strong><?php echo esc_html( $title ); ?></strong>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $brand Brand.
	 */
	private static function section_goals( $brand ) {
		$team_img = self::media_url( 'goals_team', $brand );
		if ( ! $team_img ) {
			$team_img = self::media_url( 'goals-team.webp', $brand );
		}
		?>
<section class="wdm-goals">
	<div class="wdm-container">
		<div class="wdm-goals__intro" data-wdm-reveal="up">
			<h2>اهداف آژانس وبینا</h2>
			<p>با بهره‌گیری از دانش روز بازاریابی دیجیتال و تخصص تیم، کمک می‌کنیم نمودار فروش آنلاین شما روندی افزایشی داشته باشد.</p>
		</div>
		<div class="wdm-goals__grid">
			<article class="wdm-vm" data-wdm-reveal="right">
				<span class="wdm-vm__en">Vision</span>
				<span class="wdm-vm__icon" aria-hidden="true">
					<svg viewBox="0 0 76 76" width="48" height="48"><circle cx="38" cy="38" r="36" fill="#FAF0EF"/><circle cx="38" cy="38" r="14" fill="none" stroke="#CA2C24" stroke-width="3"/><circle cx="38" cy="38" r="5" fill="#CA2C24"/></svg>
				</span>
				<h3>چشم انداز وبینا</h3>
				<p><?php echo esc_html( (string) ( $brand['vision'] ?? '' ) ); ?></p>
			</article>
			<article class="wdm-vm" data-wdm-reveal="up">
				<span class="wdm-vm__en">Mission</span>
				<span class="wdm-vm__icon" aria-hidden="true">
					<svg viewBox="0 0 76 76" width="48" height="48"><circle cx="38" cy="38" r="36" fill="#F5F5F5"/><path d="M22 48 L38 22 L54 48 Z" fill="none" stroke="#231110" stroke-width="3"/><circle cx="38" cy="40" r="4" fill="#CA2C24"/></svg>
				</span>
				<h3>ماموریت وبینا</h3>
				<p><?php echo esc_html( (string) ( $brand['mission'] ?? '' ) ); ?></p>
			</article>
			<div class="wdm-goals__art" data-wdm-reveal="left" data-wdm-parallax="0.07">
				<?php self::media_or_scene( $team_img, 'hero', 'تیم وبینا', 'wdm-media--goals' ); ?>
			</div>
		</div>
	</div>
</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $brand Brand.
	 */
	private static function section_testimonials( $brand ) {
		$items = (array) ( $brand['testimonials'] ?? array() );
		?>
<section class="wdm-testimonials">
	<div class="wdm-container">
		<div class="wdm-section-head" data-wdm-reveal="up">
			<h2>نظرات مشتریان وبینا</h2>
			<p>ما به شفافیت در اجرای پروژه‌ها اعتقاد داریم. تجربه کسب‌وکارهایی که به ما اعتماد کردند را از زبان خودشان بشنوید.</p>
		</div>
		<div class="wdm-carousel" data-wdm-carousel data-wdm-autoplay="5000" data-wdm-reveal="up">
			<div class="wdm-carousel__track">
				<?php foreach ( $items as $item ) : ?>
					<blockquote class="wdm-testi">
						<div class="wdm-testi__top">
							<span class="wdm-testi__avatar" aria-hidden="true"><?php echo esc_html( mb_substr( (string) ( $item['name'] ?? 'و' ), 0, 1 ) ); ?></span>
							<div>
								<strong><?php echo esc_html( (string) ( $item['role'] ?? '' ) ); ?></strong>
								<span><?php echo esc_html( (string) ( $item['name'] ?? '' ) ); ?></span>
							</div>
						</div>
						<div class="wdm-testi__stars" aria-hidden="true">
							<svg viewBox="0 0 88 11" width="88" height="11"><g fill="#CA2C24"><?php for ( $s = 0; $s < 5; $s++ ) : ?><path transform="translate(<?php echo (int) ( $s * 18 ); ?>,0)" d="M8 0l2 5h5l-4 3 2 5-5-3-5 3 2-5-4-3h5z"/><?php endfor; ?></g></svg>
						</div>
						<p><?php echo esc_html( (string) ( $item['text'] ?? '' ) ); ?></p>
					</blockquote>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $brand Brand.
	 */
	private static function section_ceo( $brand ) {
		$ceo = self::media_url( 'ceo', $brand );
		if ( ! $ceo ) {
			$ceo = self::media_url( 'ceo.webp', $brand );
		}
		?>
<section class="wdm-ceo">
	<div class="wdm-container wdm-ceo__grid">
		<div class="wdm-ceo__photo" data-wdm-reveal="right">
			<div class="wdm-ceo__frame">
				<?php self::media_or_scene( $ceo, 'hero', (string) ( $brand['ceo_name'] ?? '' ), 'wdm-media--ceo' ); ?>
			</div>
		</div>
		<div class="wdm-ceo__body" data-wdm-reveal="left">
			<span class="wdm-en">CEO Message</span>
			<h2>سخن مدیر عامل</h2>
			<blockquote><?php echo esc_html( (string) ( $brand['ceo_quote'] ?? '' ) ); ?></blockquote>
			<strong><?php echo esc_html( (string) ( $brand['ceo_name'] ?? '' ) ); ?></strong>
			<span class="wdm-ceo__role"><?php echo esc_html( (string) ( $brand['ceo_title'] ?? '' ) ); ?></span>
		</div>
	</div>
</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $brand Brand.
	 */
	private static function section_team( $brand ) {
		$team = (array) ( $brand['team'] ?? array() );
		?>
<section class="wdm-team">
	<div class="wdm-container">
		<div class="wdm-section-head wdm-section-head--row" data-wdm-reveal="up">
			<div>
				<span class="wdm-en">OUR TEAM</span>
				<h2>تیم ما</h2>
			</div>
			<a class="wdm-btn wdm-btn--ghost" href="<?php echo esc_url( home_url( '/pages/team/' ) ); ?>">مشاهده همه اعضای تیم</a>
		</div>
		<div class="wdm-carousel" data-wdm-carousel data-wdm-autoplay="4500">
			<div class="wdm-carousel__track">
				<?php foreach ( $team as $i => $member ) : ?>
					<?php
					$file = 'team-0' . ( $i + 1 ) . '.webp';
					$url  = self::media_list_url( $brand, 'team', $i, $file );
					$name = (string) ( $member['name'] ?? '' );
					$role = (string) ( $member['role'] ?? '' );
					?>
					<article class="wdm-team-card" data-wdm-reveal="up" style="--wdm-delay:<?php echo esc_attr( (string) ( $i * 0.08 ) ); ?>s">
						<div class="wdm-team-card__photo">
							<?php self::media_or_scene( $url, 'ui', $name ); ?>
							<div class="wdm-team-card__overlay">
								<span><?php echo esc_html( $role ); ?></span>
								<strong><?php echo esc_html( $name ); ?></strong>
								<a href="<?php echo esc_url( (string) ( $member['linkedin'] ?? '#' ) ); ?>">LinkedIn</a>
							</div>
						</div>
						<div class="wdm-team-card__meta">
							<span><?php echo esc_html( $role ); ?></span>
							<strong><?php echo esc_html( $name ); ?></strong>
						</div>
					</article>
				<?php endforeach; ?>
				<a class="wdm-team-card wdm-team-card--all" href="<?php echo esc_url( home_url( '/pages/team/' ) ); ?>">
					<div class="wdm-team-card__photo wdm-team-card__photo--all">
						<span>All Team Members</span>
						<strong>مشاهده همه اعضای تیم</strong>
					</div>
				</a>
			</div>
		</div>
	</div>
</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $brand Brand.
	 */
	private static function section_lead( $brand ) {
		$services = (array) ( $brand['services'] ?? array() );
		$people   = self::media_url( 'lead_people', $brand );
		if ( ! $people ) {
			$people = self::media_url( 'lead-people.webp', $brand );
		}
		?>
<section class="wdm-lead">
	<div class="wdm-container wdm-lead__box" data-wdm-reveal="up">
		<div class="wdm-lead__visual" aria-hidden="true">
			<?php self::media_or_scene( $people, 'hero', '', 'wdm-media--lead' ); ?>
		</div>
		<div class="wdm-lead__intro">
			<span class="wdm-en">Free Consultation</span>
			<h2>درخواست مشاوره رایگان</h2>
			<p>پس از ارسال پیام همکاران ما با شما تماس خواهند گرفت</p>
		</div>
		<form class="wdm-form webina-lead-form" data-webina-lead-form method="post" action="#">
			<select name="webina_service" required>
				<option value="">در چه زمینه‌ای مشاوره لازم دارید؟</option>
				<?php foreach ( $services as $key => $label ) : ?>
					<option value="<?php echo esc_attr( (string) $key ); ?>"><?php echo esc_html( (string) $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" name="webina_name" placeholder="نام و نام خانوادگی" required />
			<input type="tel" name="webina_phone" placeholder="شماره همراه" required dir="ltr" />
			<button type="submit" class="wdm-btn wdm-btn--primary">ارسال درخواست</button>
			<p class="webina-lead-form__status" role="status" aria-live="polite"></p>
		</form>
	</div>
</section>
		<?php
	}

	private static function section_blog() {
		$posts    = get_posts(
			array(
				'numberposts' => 3,
				'post_status' => 'publish',
			)
		);
		$fallback = array(
			array( 'title' => 'تفاوت سئو و تبلیغات', 'excerpt' => 'انتخاب بین سئو و تبلیغات یکی از چالش‌های مدیران کسب‌وکار است.', 'url' => home_url( '/resources/blog/' ) ),
			array( 'title' => 'هزینه خدمات سئو چقدر است؟', 'excerpt' => 'تعیین قیمت سئو یکی از تصمیمات مهم برای صاحبان کسب‌وکار است.', 'url' => home_url( '/resources/blog/' ) ),
			array( 'title' => 'سئو تضمینی: واقعیت یا ادعا؟', 'excerpt' => 'به‌عنوان صاحب کسب‌وکار طبیعی است که بخواهید در میدان رقابت بمانید.', 'url' => home_url( '/resources/blog/' ) ),
		);
		?>
<section class="wdm-blog">
	<div class="wdm-container">
		<div class="wdm-section-head wdm-section-head--row" data-wdm-reveal="up">
			<h2>مقالات</h2>
			<a class="wdm-btn wdm-btn--ghost" href="<?php echo esc_url( home_url( '/resources/blog/' ) ); ?>">مشاهده همه</a>
		</div>
		<div class="wdm-blog__grid">
			<?php
			if ( $posts ) {
				foreach ( $posts as $i => $post ) {
					$thumb = get_the_post_thumbnail_url( $post, 'medium_large' );
					echo '<a class="wdm-blog-card" href="' . esc_url( get_permalink( $post ) ) . '" data-wdm-reveal="up" style="--wdm-delay:' . esc_attr( (string) ( $i * 0.1 ) ) . 's">';
					echo '<div class="wdm-blog-card__thumb">';
					if ( $thumb ) {
						echo '<img src="' . esc_url( $thumb ) . '" alt="" loading="lazy" />';
					}
					echo '</div><div class="wdm-blog-card__body">';
					echo '<span>' . esc_html( get_the_date( '', $post ) ) . '</span>';
					echo '<h3>' . esc_html( get_the_title( $post ) ) . '</h3>';
					echo '<p>' . esc_html( wp_trim_words( get_the_excerpt( $post ), 18 ) ) . '</p>';
					echo '</div></a>';
				}
			} else {
				foreach ( $fallback as $i => $item ) {
					echo '<a class="wdm-blog-card" href="' . esc_url( $item['url'] ) . '" data-wdm-reveal="up" style="--wdm-delay:' . esc_attr( (string) ( $i * 0.1 ) ) . 's">';
					echo '<div class="wdm-blog-card__thumb"></div><div class="wdm-blog-card__body">';
					echo '<span>مقاله</span><h3>' . esc_html( $item['title'] ) . '</h3>';
					echo '<p>' . esc_html( $item['excerpt'] ) . '</p></div></a>';
				}
			}
			?>
		</div>
	</div>
</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $brand Brand.
	 */
	private static function section_bottom_stats( $brand ) {
		?>
<section class="wdm-bottom-stats">
	<div class="wdm-container wdm-bottom-stats__grid">
		<div class="wdm-bottom-stat" data-wdm-reveal="up">
			<strong data-count="200" data-suffix="+">0</strong>
			<span>میلیارد تومان افزایش درآمد مشتریان</span>
		</div>
		<div class="wdm-bottom-stat" data-wdm-reveal="up" style="--wdm-delay:.1s">
			<strong data-count="400" data-suffix="+">0</strong>
			<span>تعداد پروژه‌های اجرا شده</span>
		</div>
		<div class="wdm-bottom-stat" data-wdm-reveal="up" style="--wdm-delay:.2s">
			<strong data-count="6" data-suffix="+">0</strong>
			<span>سال تجربه تخصصی</span>
		</div>
		<div class="wdm-bottom-stat" data-wdm-reveal="up" style="--wdm-delay:.3s">
			<strong data-count="50" data-suffix="M+">0</strong>
			<span>میزان ترافیک جذب‌شده از محتوا</span>
		</div>
	</div>
	<div class="wdm-container wdm-bottom-stats__note" data-wdm-reveal="up">
		<p><strong>آژانس وبینا</strong> با تیمی متخصص، پروژه‌های دیجیتال مارکتینگ، طراحی و رشد را با تعهد به اخلاق و پایداری به نتیجه می‌رساند.</p>
	</div>
</section>
		<?php
	}
}
