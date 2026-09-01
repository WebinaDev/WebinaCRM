<?php
/**
 * Elementor widget: Contact Cards
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Site_Widget_ContactCards extends WebinoCRM_Site_Widget_Base {

	public function get_name() {
		return 'webina_contact_cards';
	}

	public function get_title() {
		return __( 'Contact Cards', 'webinocrm' );
	}

	public function get_icon() {
		return 'eicon-tel-field';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'webinocrm' ) ) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'webinocrm' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => '',
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$brand = function_exists( 'webinocrm_site_brand' ) ? webinocrm_site_brand() : array();
		$phone = (string) ( $brand['phone'] ?? '' );
		$tel   = preg_replace( '/\D+/', '', (string) ( $brand['phone_tel'] ?? '' ) );
		$email = (string) ( $brand['email'] ?? '' );
		$addr  = (string) ( $brand['address'] ?? '' );

		echo '<div class="webina-widget webina-widget--contact-cards"><div class="webina-contact-cards">';
		echo '<a class="webina-contact-card" href="' . esc_url( 'tel:' . $tel ) . '"><span class="webina-contact-card__icon">ت</span><strong>تلفن تماس</strong><span dir="ltr">' . esc_html( $phone ) . '</span></a>';
		echo '<a class="webina-contact-card" href="mailto:' . esc_attr( $email ) . '"><span class="webina-contact-card__icon">@</span><strong>ایمیل پشتیبانی</strong><span>' . esc_html( $email ) . '</span></a>';
		echo '<a class="webina-contact-card" href="' . esc_url( home_url( '/pages/contact/' ) ) . '"><span class="webina-contact-card__icon">م</span><strong>آدرس</strong><span>' . esc_html( $addr ) . '</span></a>';
		echo '</div></div>';
	}
}
