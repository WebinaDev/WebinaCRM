<?php
/**
 * Elementor widget: Careers Form
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_CareersForm extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_careers_form';
	}

	public function get_title() {
		return __( 'Careers Form', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'webinocrm' ) ) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'درخواست همکاری با وبینا', 'webinocrm' ),
		) );
		$this->add_control( 'description', array(
			'label'   => __( 'Description', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXTAREA,
			'default' => __( 'شما هم می‌توانید عضوی از این تیم باشید.', 'webinocrm' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="webina-widget webina-widget--careers-form">';
		if ( ! empty( $s['heading'] ) ) {
			echo '<h2 class="webina-widget__heading">' . esc_html( (string) $s['heading'] ) . '</h2>';
		}
		if ( ! empty( $s['description'] ) ) {
			echo '<p class="webina-widget__desc">' . esc_html( (string) $s['description'] ) . '</p>';
		}
		?>
		<form class="webina-careers-form" data-webina-career-form method="post" action="#">
			<div>
				<label>نام و نام خانوادگی *</label>
				<input type="text" name="webina_name" required />
			</div>
			<div>
				<label>شماره همراه *</label>
				<input type="tel" name="webina_phone" required dir="ltr" />
			</div>
			<div>
				<label>شهر سکونت *</label>
				<input type="text" name="webina_city" required />
			</div>
			<div>
				<label>نوع همکاری *</label>
				<select name="webina_work_type" required>
					<option value="حضوری">حضوری</option>
					<option value="آنلاین">آنلاین</option>
					<option value="هیبریدی">هیبریدی</option>
				</select>
			</div>
			<div>
				<label>زمینه فعالیت *</label>
				<select name="webina_field" required>
					<option value="سئو">سئو</option>
					<option value="طراحی وب سایت">طراحی وب سایت</option>
					<option value="محتوا">محتوا</option>
					<option value="گرافیک">گرافیک</option>
					<option value="تکنیکال">تکنیکال</option>
					<option value="مارکتینگ">مارکتینگ</option>
					<option value="فروش">فروش</option>
					<option value="پشتیبانی">پشتیبانی</option>
				</select>
			</div>
			<div>
				<label>سابقه کار *</label>
				<select name="webina_experience" required>
					<option value="زیر یک سال">زیر یک سال</option>
					<option value="بین 1 تا 2 سال">بین ۱ تا ۲ سال</option>
					<option value="بین 2 تا 3 سال">بین ۲ تا ۳ سال</option>
					<option value="بیش از 3 سال">بیش از ۳ سال</option>
				</select>
			</div>
			<div>
				<label>سطح پوزیشن *</label>
				<select name="webina_level" required>
					<option value="کارآموز">کارآموز</option>
					<option value="کارشناس">کارشناس</option>
					<option value="کارشناس ارشد">کارشناس ارشد</option>
					<option value="مدیر">مدیر</option>
				</select>
			</div>
			<div>
				<label>لینک رزومه (اختیاری)</label>
				<input type="url" name="webina_resume_link" dir="ltr" placeholder="https://" />
			</div>
			<div class="full">
				<button type="submit" class="webina-btn webina-btn--accent">ارسال درخواست همکاری</button>
				<p class="webina-lead-form__status" role="status" aria-live="polite"></p>
			</div>
		</form>
		<?php
		echo '</div>';
	}
}
