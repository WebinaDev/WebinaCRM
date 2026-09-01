<?php
/**
 * Theme header — always renders Webina DMROOM-style chrome.
 *
 * @package Webina
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'webina-site' ); ?>>
<?php wp_body_open(); ?>
<?php
if ( class_exists( 'WebinoCRM_Site_Chrome' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Chrome returns escaped HTML.
	echo WebinoCRM_Site_Chrome::header_html();
} else {
	?>
<header class="webina-mega-header" role="banner">
	<div class="webina-mega-header__bar">
		<div class="webina-mega-header__inner">
			<div class="webina-mega-header__brand"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a></div>
		</div>
	</div>
</header>
	<?php
}
