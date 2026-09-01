<?php
/**
 * Storefront related-products block from AI upsells.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce product-page related grid.
 */
final class WebinoCRM_AI_Related_Storefront {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'register_hooks' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * @return void
	 */
	public static function register_hooks() {
		if ( is_admin() ) {
			return;
		}
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
		add_action( 'woocommerce_after_single_product_summary', array( __CLASS__, 'render' ), 16 );
	}

	/**
	 * @return void
	 */
	public static function enqueue() {
		if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$css = dirname( __DIR__ ) . '/public/related-products.css';
		if ( ! is_readable( $css ) ) {
			return;
		}
		$ver = (string) filemtime( $css );
		$url = defined( 'WEBINOCRM_PLUGIN_FILE' )
			? plugins_url( 'includes/ai-content/public/related-products.css', WEBINOCRM_PLUGIN_FILE )
			: plugins_url( 'public/related-products.css', dirname( __DIR__ ) . '/bootstrap.php' );
		wp_enqueue_style( 'webino-ai-related-products', $url, array(), $ver );
	}

	/**
	 * @return void
	 */
	public static function render() {
		if ( ! function_exists( 'wc_get_product' ) || ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_the_ID() );
		if ( ! $product ) {
			return;
		}
		$ids = array_values( array_filter( array_map( 'intval', (array) $product->get_upsell_ids() ) ) );
		if ( ! $ids ) {
			return;
		}

		$items = array();
		foreach ( $ids as $id ) {
			$rel = wc_get_product( $id );
			if ( ! $rel || 'publish' !== $rel->get_status() ) {
				continue;
			}
			$items[] = $rel;
		}
		if ( ! $items ) {
			return;
		}

		$title = 0 === strpos( (string) get_locale(), 'fa' )
			? 'محصولات مرتبط'
			: __( 'Related products', 'webinocrm' );

		echo '<section class="webino-ai-related" aria-labelledby="webino-ai-related-title">';
		echo '<h2 id="webino-ai-related-title" class="webino-ai-related__title">' . esc_html( $title ) . '</h2>';
		echo '<ul class="webino-ai-related__list">';
		foreach ( $items as $rel ) {
			$url   = get_permalink( $rel->get_id() );
			$img   = $rel->get_image( 'woocommerce_thumbnail' );
			$price = $rel->get_price_html();
			echo '<li class="webino-ai-related__item">';
			echo '<a class="webino-ai-related__link" href="' . esc_url( $url ) . '">';
			if ( $img ) {
				echo '<span class="webino-ai-related__image">' . $img . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '<span class="webino-ai-related__name">' . esc_html( $rel->get_name() ) . '</span>';
			if ( $price ) {
				echo '<span class="webino-ai-related__price">' . $price . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</a>';
			echo '</li>';
		}
		echo '</ul>';
		echo '</section>';
	}
}
