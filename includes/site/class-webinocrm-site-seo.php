<?php
/**
 * Rank Math / basic SEO meta for seeded pages.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Site_Seo {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'meta_description_fallback' ), 1 );
	}

	/**
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $copy Copy data.
	 */
	public static function apply( $post_id, $copy ) {
		$title = sanitize_text_field( (string) ( $copy['seo_title'] ?? '' ) );
		$desc  = sanitize_text_field( (string) ( $copy['seo_desc'] ?? '' ) );
		$kw    = sanitize_text_field( (string) ( $copy['focus_kw'] ?? '' ) );

		update_post_meta( $post_id, '_webina_seo_title', $title );
		update_post_meta( $post_id, '_webina_seo_description', $desc );

		// Rank Math compatibility.
		if ( $title ) {
			update_post_meta( $post_id, 'rank_math_title', $title );
		}
		if ( $desc ) {
			update_post_meta( $post_id, 'rank_math_description', $desc );
		}
		if ( $kw ) {
			update_post_meta( $post_id, 'rank_math_focus_keyword', $kw );
		}
	}

	/**
	 * Fallback meta description when Rank Math absent.
	 */
	public static function meta_description_fallback() {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_queried_object_id();
		$desc    = (string) get_post_meta( $post_id, '_webina_seo_description', true );
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
	}
}
