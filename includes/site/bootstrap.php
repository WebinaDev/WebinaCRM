<?php
/**
 * Webina public site module bootstrap.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEBINOCRM_SITE_DIR', WEBINOCRM_PLUGIN_DIR . 'includes/site/' );
define( 'WEBINOCRM_SITE_VERSION', '3.2.0' );

require_once WEBINOCRM_SITE_DIR . 'data/brand.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-ia.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-content.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-chrome.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-home-clone.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-elementor-builder.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-page-stacks.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-theme.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-kit.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-portfolio.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-theme-builder.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-seo.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-performance.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-leads.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-seeder.php';
require_once WEBINOCRM_SITE_DIR . 'class-webinocrm-site-module.php';
require_once WEBINOCRM_SITE_DIR . 'elementor/class-webinocrm-site-elementor.php';
require_once WEBINOCRM_PLUGIN_DIR . 'includes/rest/class-webinocrm-rest-site-portfolio.php';

WebinoCRM_Site_Leads::init();
WebinoCRM_Site_Module::init();
