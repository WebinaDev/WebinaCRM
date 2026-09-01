<?php
/**
 * AI Content engine bootstrap for WebinoCRM.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dir = dirname( __FILE__ ) . '/includes/';
$files = array(
	'class-webinocrm-ai-content-crypto.php',
	'class-webinocrm-ai-content-db.php',
	'class-webinocrm-ai-content-settings.php',
	'class-webinocrm-ai-design-memory.php',
	'class-webinocrm-ai-providers.php',
	'class-webinocrm-ai-pricing.php',
	'class-webinocrm-ai-seo-gate.php',
	'class-webinocrm-ai-prompts.php',
	'class-webinocrm-ai-elementor-sanitize.php',
	'class-webinocrm-ai-elementor-catalog.php',
	'class-webinocrm-ai-elementor-compiler.php',
	'class-webinocrm-ai-elementor.php',
	'class-webinocrm-ai-writer.php',
	'class-webinocrm-ai-proposals.php',
	'class-webinocrm-ai-queue.php',
	'class-webinocrm-ai-calendar.php',
	'class-webinocrm-ai-attributes.php',
	'class-webinocrm-ai-content.php',
	'class-webinocrm-ai-related-storefront.php',
	'class-webinocrm-rest-ai-content.php',
);

foreach ( $files as $file ) {
	$path = $dir . $file;
	if ( ! is_readable( $path ) ) {
		return;
	}
	require_once $path;
}

WebinoCRM_AI_Content::init();
if ( class_exists( 'WebinoCRM_AI_Related_Storefront', false ) ) {
	WebinoCRM_AI_Related_Storefront::init();
}
WebinoCRM_REST_AI_Content::init();
