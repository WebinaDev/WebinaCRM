<?php
/**
 * Save Elementor documents from compiled trees.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Official Elementor document API wrapper.
 */
final class WebinoCRM_AI_Elementor {

	/**
	 * @return true|WP_Error
	 */
	public static function assert_available() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) || ! class_exists( '\Elementor\Plugin', false ) ) {
			return new WP_Error( 'no_elementor', __( 'Elementor must be active to design pages with AI.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * @param int                 $post_id Post ID.
	 * @param array<int,mixed>    $elements Elementor tree.
	 * @return true|WP_Error
	 */
	public static function save_document( $post_id, $elements ) {
		$ok = self::assert_available();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}

		$post_id  = (int) $post_id;
		$post     = get_post( $post_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return new WP_Error( 'not_page', __( 'Target must be a WordPress page.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$elements = WebinoCRM_AI_Elementor_Sanitize::strip_fonts( is_array( $elements ) ? $elements : array() );

		$documents = \Elementor\Plugin::$instance->documents;
		if ( ! $documents || ! method_exists( $documents, 'get' ) ) {
			return self::save_via_meta( $post_id, $elements );
		}

		$document = $documents->get( $post_id );
		if ( ! $document ) {
			// Ensure Elementor knows this post.
			update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
			update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
			$document = $documents->get( $post_id, false );
		}

		if ( $document && method_exists( $document, 'save' ) ) {
			try {
				$document->save(
					array(
						'elements' => $elements,
						'settings' => array(
							'post_status' => $post->post_status,
						),
					)
				);
			} catch ( Exception $e ) {
				return new WP_Error( 'elementor_save', $e->getMessage(), array( 'status' => 500 ) );
			}
		} else {
			$meta = self::save_via_meta( $post_id, $elements );
			if ( is_wp_error( $meta ) ) {
				return $meta;
			}
		}

		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
		}

		self::clear_cache( $post_id );
		return true;
	}

	/**
	 * @param int              $post_id Post.
	 * @param array<int,mixed> $elements Tree.
	 * @return true|WP_Error
	 */
	private static function save_via_meta( $post_id, $elements ) {
		$json = wp_json_encode( $elements );
		if ( false === $json ) {
			return new WP_Error( 'json', __( 'Failed to encode Elementor data.', 'webinocrm' ) );
		}
		update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
		}
		return true;
	}

	/**
	 * @param int $post_id Post.
	 * @return void
	 */
	public static function clear_cache( $post_id ) {
		$post_id = (int) $post_id;
		if ( class_exists( '\Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			$fm = \Elementor\Plugin::$instance->files_manager;
			if ( method_exists( $fm, 'clear_cache' ) ) {
				$fm->clear_cache();
			}
		}
		delete_post_meta( $post_id, '_elementor_css' );
		if ( class_exists( '\Elementor\Core\Files\CSS\Post', false ) ) {
			try {
				$css = new \Elementor\Core\Files\CSS\Post( $post_id );
				if ( method_exists( $css, 'update' ) ) {
					$css->update();
				}
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}
	}
}
