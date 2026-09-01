<?php
/**
 * Register Webina Elementor widgets + category.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Elementor {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'elementor/loaded', array( __CLASS__, 'register_elementor_hooks' ) );
	}

	/**
	 * Register Elementor hooks only after the plugin is loaded.
	 */
	public static function register_elementor_hooks() {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		add_action( 'elementor/elements/categories/register', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * @param \Elementor\Elements_Manager $elements_manager Manager.
	 */
	public static function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'webina-vip',
			array(
				'title' => __( 'Webina VIP', 'webinocrm' ),
				'icon'  => 'fa fa-star',
			)
		);
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager Manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once WEBINOCRM_SITE_DIR . 'elementor/class-webinocrm-site-widget-base.php';

		$widgets = array(
			'hero-cinematic',
			'bento-grid',
			'stats-strip',
			'timeline',
			'logo-marquee',
			'service-card',
			'pricing-table',
			'cta-fullbleed',
			'faq-accordion',
			'lead-form',
			'portfolio-grid',
			'case-study-hero',
			'testimonial',
			'trust-strip',
			'page-hero',
			'client-marquee',
			'split-service',
			'portfolio-carousel',
			'vision-mission',
			'ceo-quote',
			'team-carousel',
			'contact-cards',
			'careers-form',
			'blog-cards',
			'ai-badges',
		);

		foreach ( $widgets as $slug ) {
			$file = WEBINOCRM_SITE_DIR . 'elementor/widgets/class-webinocrm-site-widget-' . $slug . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}

		$map = array(
			'WebinoCRM_Site_Widget_HeroCinematic'      => 'webina_hero_cinematic',
			'WebinoCRM_Site_Widget_BentoGrid'          => 'webina_bento_grid',
			'WebinoCRM_Site_Widget_StatsStrip'         => 'webina_stats_strip',
			'WebinoCRM_Site_Widget_Timeline'           => 'webina_timeline',
			'WebinoCRM_Site_Widget_LogoMarquee'        => 'webina_logo_marquee',
			'WebinoCRM_Site_Widget_ServiceCard'        => 'webina_service_card',
			'WebinoCRM_Site_Widget_PricingTable'       => 'webina_pricing_table',
			'WebinoCRM_Site_Widget_CtaFullbleed'       => 'webina_cta_fullbleed',
			'WebinoCRM_Site_Widget_FaqAccordion'       => 'webina_faq_accordion',
			'WebinoCRM_Site_Widget_LeadForm'           => 'webina_lead_form',
			'WebinoCRM_Site_Widget_PortfolioGrid'      => 'webina_portfolio_grid',
			'WebinoCRM_Site_Widget_CaseStudyHero'      => 'webina_case_study_hero',
			'WebinoCRM_Site_Widget_Testimonial'        => 'webina_testimonial',
			'WebinoCRM_Site_Widget_TrustStrip'         => 'webina_trust_strip',
			'WebinoCRM_Site_Widget_PageHero'           => 'webina_page_hero',
			'WebinoCRM_Site_Widget_ClientMarquee'      => 'webina_client_marquee',
			'WebinoCRM_Site_Widget_SplitService'       => 'webina_split_service',
			'WebinoCRM_Site_Widget_PortfolioCarousel'  => 'webina_portfolio_carousel',
			'WebinoCRM_Site_Widget_VisionMission'      => 'webina_vision_mission',
			'WebinoCRM_Site_Widget_CeoQuote'           => 'webina_ceo_quote',
			'WebinoCRM_Site_Widget_TeamCarousel'       => 'webina_team_carousel',
			'WebinoCRM_Site_Widget_ContactCards'       => 'webina_contact_cards',
			'WebinoCRM_Site_Widget_CareersForm'        => 'webina_careers_form',
			'WebinoCRM_Site_Widget_BlogCards'          => 'webina_blog_cards',
			'WebinoCRM_Site_Widget_AiBadges'           => 'webina_ai_badges',
		);

		foreach ( $map as $class => $name ) {
			if ( class_exists( $class ) ) {
				$widgets_manager->register( new $class() );
			}
		}
	}
}
