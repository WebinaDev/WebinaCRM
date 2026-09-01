<?php
/**
 * Header/footer/popup/FAB HTML for Elementor Theme Builder.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Chrome {

	/**
	 * English labels for mega columns.
	 *
	 * @return array<string,string>
	 */
	private static function hub_en_labels() {
		return array(
			'tech'     => 'TECH',
			'growth'   => 'GROWTH',
			'branding' => 'BRANDING',
			'strategy' => 'STRATEGY',
			'support'  => 'SUPPORT',
			'retail'   => 'RETAIL',
			'health'   => 'HEALTH',
			'corporate'=> 'CORPORATE',
			'education'=> 'EDUCATION',
			'hospitality' => 'HOSPITALITY',
		);
	}

	/**
	 * Full header with mega menus.
	 *
	 * @return string
	 */
	public static function header_html() {
		ob_start();
		?>
<header class="webina-mega-header" id="webina-mega-header">
	<div class="webina-mega-header__bar">
		<div class="webina-mega-header__inner">
			<div class="webina-mega-header__brand"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">وبینا</a></div>
			<button type="button" class="webina-mega-header__toggle" aria-expanded="false" aria-controls="webina-mega-nav" aria-label="منو">
				<span></span><span></span><span></span>
			</button>
			<nav class="webina-mega-header__nav" id="webina-mega-nav" aria-label="Primary">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a>
				<?php self::render_mega_trigger( 'services', 'خدمات' ); ?>
				<?php self::render_mega_trigger( 'solutions', 'راهکارها' ); ?>
				<a href="<?php echo esc_url( home_url( '/portfolio/' ) ); ?>">نمونه‌کارها</a>
				<a href="<?php echo esc_url( home_url( '/resources/' ) ); ?>">منابع</a>
				<a href="<?php echo esc_url( home_url( '/pages/about/' ) ); ?>">درباره ما</a>
				<a href="<?php echo esc_url( home_url( '/pages/contact/' ) ); ?>">تماس با ما</a>
				<button type="button" class="webina-mega-header__cta" data-webina-popup-open>درخواست مشاوره</button>
			</nav>
		</div>
	</div>
	<?php self::render_mega_panel( 'services', WebinoCRM_Site_IA::mega_menu_tree( 'services' ), home_url( '/services/' ), 'همه خدمات' ); ?>
	<?php self::render_mega_panel( 'solutions', WebinoCRM_Site_IA::mega_menu_tree( 'solutions' ), home_url( '/solutions/' ), 'همه راهکارها' ); ?>
</header>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param string $id Panel id suffix.
	 * @param string $label Label.
	 */
	private static function render_mega_trigger( $id, $label ) {
		echo '<button type="button" class="webina-mega-trigger" data-mega="' . esc_attr( $id ) . '" aria-expanded="false">' . esc_html( $label ) . '<span class="webina-mega-trigger__chev" aria-hidden="true"></span></button>';
	}

	/**
	 * @param string              $id Panel id.
	 * @param array<int,mixed>    $columns Columns.
	 * @param string              $all_url All link.
	 * @param string              $all_label All label.
	 */
	private static function render_mega_panel( $id, $columns, $all_url, $all_label ) {
		if ( empty( $columns ) ) {
			return;
		}
		$en_map = self::hub_en_labels();
		echo '<div class="webina-mega-panel" data-mega-panel="' . esc_attr( $id ) . '" hidden>';
		echo '<div class="webina-mega-panel__inner">';
		echo '<div class="webina-mega-panel__grid">';
		foreach ( $columns as $col ) {
			$url   = (string) ( $col['url'] ?? '#' );
			$title = (string) ( $col['title'] ?? '' );
			$slug  = strtolower( (string) ( $col['slug'] ?? '' ) );
			$en    = $en_map[ $slug ] ?? '';
			if ( '' === $en ) {
				foreach ( $en_map as $key => $label ) {
					if ( false !== stripos( $slug, $key ) || false !== stripos( $title, $key ) ) {
						$en = $label;
						break;
					}
				}
			}
			echo '<div class="webina-mega-panel__col">';
			echo '<a class="webina-mega-panel__hub" href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
			if ( $en ) {
				echo '<span class="webina-mega-panel__en">' . esc_html( $en ) . '</span>';
			}
			if ( ! empty( $col['links'] ) && is_array( $col['links'] ) ) {
				echo '<ul class="webina-mega-panel__links">';
				foreach ( $col['links'] as $link ) {
					echo '<li><a href="' . esc_url( (string) ( $link['url'] ?? '#' ) ) . '">' . esc_html( (string) ( $link['title'] ?? '' ) ) . '</a></li>';
				}
				echo '</ul>';
			}
			echo '</div>';
		}
		echo '</div>';
		echo '<div class="webina-mega-panel__foot">';
		echo '<p class="webina-mega-panel__blurb">با ارائه خدمات حرفه‌ای سئو، طراحی سایت و مشاوره کسب‌وکار کمک می‌کنیم حضور موثری در دنیای دیجیتال داشته باشید.</p>';
		echo '<a class="webina-btn webina-btn--ghost" href="' . esc_url( $all_url ) . '">' . esc_html( $all_label ) . '</a>';
		echo '<button type="button" class="webina-btn webina-btn--accent" data-webina-popup-open>مشاوره رایگان</button>';
		echo '</div></div></div>';
	}

	/**
	 * Consultation popup markup.
	 *
	 * @return string
	 */
	public static function popup_html() {
		$brand    = webinocrm_site_brand();
		$services = (array) ( $brand['services'] ?? array() );
		ob_start();
		?>
<div class="webina-popup" id="webina-moshavere-popup" hidden aria-hidden="true">
	<div class="webina-popup__overlay" data-webina-popup-close></div>
	<div class="webina-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="webina-popup-title">
		<button type="button" class="webina-popup__close" data-webina-popup-close aria-label="بستن">&times;</button>
		<h2 class="webina-popup__title" id="webina-popup-title">درخواست مشاوره رایگان</h2>
		<p class="webina-popup__lead">پس از ارسال پیام همکاران ما با شما تماس خواهند گرفت</p>
		<form class="webina-lead-form" data-webina-lead-form method="post" action="#">
			<select name="webina_service" required>
				<option value="">در چه زمینه‌ای مشاوره لازم دارید؟</option>
				<?php foreach ( $services as $key => $label ) : ?>
					<option value="<?php echo esc_attr( (string) $key ); ?>"><?php echo esc_html( (string) $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" name="webina_name" placeholder="نام و نام خانوادگی" required />
			<input type="tel" name="webina_phone" placeholder="شماره همراه" required dir="ltr" />
			<button type="submit" class="webina-btn webina-btn--accent">ارسال درخواست</button>
			<p class="webina-lead-form__status" role="status" aria-live="polite"></p>
		</form>
	</div>
</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Floating action button.
	 *
	 * @return string
	 */
	public static function fab_html() {
		$brand = webinocrm_site_brand();
		$tel   = (string) ( $brand['phone_tel'] ?? '' );
		$disp  = (string) ( $brand['phone_display'] ?? $brand['phone'] ?? '' );
		ob_start();
		?>
<div class="webina-fab" id="webina-fab">
	<div class="webina-fab__panel" hidden>
		<p class="webina-fab__panel-title">تماس با وبینا</p>
		<a href="<?php echo esc_url( 'tel:' . preg_replace( '/\D+/', '', $tel ) ); ?>">
			<span>تماس با تیم فروش</span>
			<strong dir="ltr"><?php echo esc_html( $disp ); ?></strong>
		</a>
		<a href="#" data-webina-popup-open>
			<span>ثبت درخواست خدمات</span>
			<span>سئو، طراحی سایت و ...</span>
		</a>
	</div>
	<button type="button" class="webina-fab__toggle" aria-expanded="false">نتیجه رو انتخاب کن</button>
</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Rich agency footer.
	 *
	 * @return string
	 */
	public static function footer_html() {
		$brand         = webinocrm_site_brand();
		$service_hubs  = WebinoCRM_Site_IA::mega_menu_tree( 'services' );
		$services      = (array) ( $brand['services'] ?? array() );
		ob_start();
		?>
<footer class="webina-mega-footer">
	<div class="webina-mega-footer__inner">
		<div class="webina-mega-footer__top">
			<div class="webina-mega-footer__brand">
				<strong>وبینا</strong>
				<p>آژانس توسعه کسب‌وکار — با ارائه خدمات حرفه‌ای سئو، طراحی سایت و مشاوره کسب‌وکار کمک می‌کنیم حضور موثری در دنیای دیجیتال داشته باشید.</p>
				<div class="webina-mega-footer__hours"><?php echo esc_html( (string) ( $brand['hours'] ?? '' ) ); ?></div>
				<a class="webina-mega-footer__phone" href="<?php echo esc_url( 'tel:' . preg_replace( '/\D+/', '', (string) ( $brand['phone_tel'] ?? '' ) ) ); ?>" dir="ltr"><?php echo esc_html( (string) ( $brand['phone'] ?? '' ) ); ?></a>
			</div>
			<div class="webina-mega-footer__form-wrap">
				<h3>درخواست مشاوره رایگان</h3>
				<p>پس از ارسال پیام همکاران ما با شما تماس خواهند گرفت</p>
				<form class="webina-lead-form" data-webina-lead-form method="post" action="#">
					<select name="webina_service" required>
						<option value="">نام خدمت</option>
						<?php foreach ( $services as $key => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $key ); ?>"><?php echo esc_html( (string) $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="text" name="webina_name" placeholder="نام و نام خانوادگی" required />
					<input type="tel" name="webina_phone" placeholder="شماره همراه" required dir="ltr" />
					<button type="submit" class="webina-btn webina-btn--accent">ارسال</button>
					<p class="webina-lead-form__status" role="status" aria-live="polite"></p>
				</form>
			</div>
		</div>
		<div class="webina-mega-footer__grid">
			<div>
				<h4>خدمات</h4>
				<?php foreach ( array_slice( $service_hubs, 0, 6 ) as $hub ) : ?>
					<a href="<?php echo esc_url( (string) ( $hub['url'] ?? '#' ) ); ?>"><?php echo esc_html( (string) ( $hub['title'] ?? '' ) ); ?></a><br>
				<?php endforeach; ?>
			</div>
			<div>
				<h4>دسترسی سریع</h4>
				<a href="<?php echo esc_url( home_url( '/pages/team/' ) ); ?>">تیم ما</a><br>
				<a href="<?php echo esc_url( home_url( '/resources/blog/' ) ); ?>">مقالات</a><br>
				<a href="<?php echo esc_url( home_url( '/pages/about/' ) ); ?>">درباره ما</a><br>
				<a href="<?php echo esc_url( home_url( '/pages/contact/' ) ); ?>">تماس با ما</a><br>
				<a href="<?php echo esc_url( home_url( '/portfolio/' ) ); ?>">نمونه‌کارها</a><br>
				<a href="<?php echo esc_url( home_url( '/pages/careers/' ) ); ?>">همکاری با ما</a>
			</div>
			<div>
				<h4>اطلاعات تماس</h4>
				<a href="<?php echo esc_url( home_url( '/pages/contact/' ) ); ?>">آدرس: <?php echo esc_html( (string) ( $brand['address'] ?? '' ) ); ?></a><br>
				<a href="mailto:<?php echo esc_attr( (string) ( $brand['email'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $brand['email'] ?? '' ) ); ?></a><br>
				<a href="<?php echo esc_url( 'tel:' . preg_replace( '/\D+/', '', (string) ( $brand['phone_tel'] ?? '' ) ) ); ?>" dir="ltr"><?php echo esc_html( (string) ( $brand['phone'] ?? '' ) ); ?></a>
			</div>
			<div>
				<h4>شبکه‌های اجتماعی</h4>
				<div class="webina-mega-footer__social">
					<?php foreach ( (array) ( $brand['social'] ?? array() ) as $net => $url ) : ?>
						<a href="<?php echo esc_url( (string) $url ); ?>" aria-label="<?php echo esc_attr( (string) $net ); ?>"><?php echo esc_html( strtoupper( substr( (string) $net, 0, 2 ) ) ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<div class="webina-mega-footer__bar">
			<div class="webina-trust-strip">نماد اعتماد · پشتیبانی تخصصی · KPI شفاف</div>
			<p class="webina-mega-footer__copy">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( (string) ( $brand['name'] ?? 'وبینا' ) ); ?> — کلیه حقوق محفوظ است.</p>
		</div>
	</div>
</footer>
<?php echo self::popup_html(); ?>
<?php echo self::fab_html(); ?>
		<?php
		return (string) ob_get_clean();
	}
}
