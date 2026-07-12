<?php
/**
 * REST handlers for dashboard media library (list + upload).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content media routes for CRM dashboard SPA.
 */
class WebinoCRM_REST_Content_Media {

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 40 ) );
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'paged'          => $page,
			'posts_per_page' => $per_page,
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$q     = new WP_Query( $args );
		$items = array();
		while ( $q->have_posts() ) {
			$q->the_post();
			$item = self::serialize_attachment( get_the_ID() );
			if ( array() !== $item ) {
				$items[] = $item;
			}
		}
		wp_reset_postdata();

		return new WP_REST_Response(
			array(
				'items' => $items,
				'page'  => $page,
				'total' => (int) $q->found_posts,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function upload( $request ) {
		unset( $request );
		if ( empty( $_FILES['file'] ) ) {
			return new WP_Error(
				'no_file',
				__( 'No file uploaded.', 'webinocrm' ),
				array( 'status' => 400 )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$aid = media_handle_upload( 'file', 0 );
		if ( is_wp_error( $aid ) ) {
			return $aid;
		}

		return new WP_REST_Response(
			array(
				'id'  => (int) $aid,
				'url' => (string) wp_get_attachment_url( (int) $aid ),
			),
			201
		);
	}

	/**
	 * @param int $aid Attachment ID.
	 * @return array<string, mixed>
	 */
	private static function serialize_attachment( $aid ) {
		$post = get_post( $aid );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return array();
		}

		return array(
			'id'          => (int) $aid,
			'title'       => get_the_title( $aid ),
			'slug'        => $post->post_name,
			'url'         => wp_get_attachment_url( $aid ),
			'mime'        => get_post_mime_type( $aid ),
			'caption'     => $post->post_excerpt,
			'description' => $post->post_content,
			'alt_text'    => (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ),
			'categories'  => array(),
			'folders'     => array(),
			'tags'        => array(),
		);
	}
}
