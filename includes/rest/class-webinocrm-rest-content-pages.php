<?php
/**
 * WordPress pages CRUD for CRM AI / Elementor workflows.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST under webinocrm/v1/content/pages.
 */
final class WebinoCRM_REST_Content_Pages {

	const NS = 'webinocrm/v1';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		$pages = array(
			'permission_callback' => static function () {
				return WebinoCRM_REST_Base::can( 'edit_pages' );
			},
		);

		register_rest_route(
			self::NS,
			'/content/pages',
			array(
				array_merge(
					$pages,
					array(
						'methods'  => 'GET',
						'callback' => array( __CLASS__, 'list_pages' ),
						'args'     => array(
							'page'     => array( 'type' => 'integer', 'default' => 1 ),
							'per_page' => array( 'type' => 'integer', 'default' => 20 ),
							'search'   => array( 'type' => 'string', 'default' => '' ),
						),
					)
				),
				array_merge( $pages, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create' ) ) ),
			)
		);

		register_rest_route(
			self::NS,
			'/content/pages/(?P<id>\d+)',
			array(
				array_merge( $pages, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get' ) ) ),
				array_merge( $pages, array( 'methods' => 'PATCH', 'callback' => array( __CLASS__, 'patch' ) ) ),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'delete' ),
					'permission_callback' => static function () {
						return WebinoCRM_REST_Base::can( 'delete_pages' );
					},
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list_pages( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );

		$q = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				's'              => $search,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$items = array();
		foreach ( $q->posts as $p ) {
			$items[] = self::serialize_list_item( $p );
		}

		return new WP_REST_Response(
			array(
				'items' => $items,
				'page'  => $page,
				'found' => (int) $q->found_posts,
				'stats' => array(),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get( $request ) {
		$p = get_post( (int) $request['id'] );
		if ( ! $p || 'page' !== $p->post_type ) {
			return new WP_Error( 'not_found', __( 'Page not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( self::serialize( $p ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		$id = wp_insert_post(
			array(
				'post_title'   => sanitize_text_field( (string) ( $body['title'] ?? '' ) ),
				'post_content' => (string) ( $body['content'] ?? '' ),
				'post_excerpt' => sanitize_textarea_field( (string) ( $body['excerpt'] ?? '' ) ),
				'post_status'  => sanitize_key( (string) ( $body['status'] ?? 'draft' ) ) ?: 'draft',
				'post_type'    => 'page',
				'post_parent'  => max( 0, (int) ( $body['parent'] ?? 0 ) ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		self::apply_meta( (int) $id, $body );
		return new WP_REST_Response( self::serialize( get_post( $id ) ), 201 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function patch( $request ) {
		$id = (int) $request['id'];
		$p  = get_post( $id );
		if ( ! $p || 'page' !== $p->post_type ) {
			return new WP_Error( 'not_found', __( 'Page not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		$args = array( 'ID' => $id );
		if ( array_key_exists( 'title', $body ) ) {
			$args['post_title'] = sanitize_text_field( (string) $body['title'] );
		}
		if ( array_key_exists( 'content', $body ) ) {
			$args['post_content'] = (string) $body['content'];
		}
		if ( array_key_exists( 'excerpt', $body ) ) {
			$args['post_excerpt'] = sanitize_textarea_field( (string) $body['excerpt'] );
		}
		if ( array_key_exists( 'status', $body ) ) {
			$args['post_status'] = sanitize_key( (string) $body['status'] );
		}
		if ( array_key_exists( 'parent', $body ) ) {
			$args['post_parent'] = max( 0, (int) $body['parent'] );
		}
		$r = wp_update_post( $args, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		self::apply_meta( $id, $body );
		return new WP_REST_Response( self::serialize( get_post( $id ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete( $request ) {
		$id = (int) $request['id'];
		$p  = get_post( $id );
		if ( ! $p || 'page' !== $p->post_type ) {
			return new WP_Error( 'not_found', __( 'Page not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		$deleted = wp_delete_post( $id, true );
		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Could not delete page.', 'webinocrm' ), array( 'status' => 500 ) );
		}
		return new WP_REST_Response( array( 'ok' => true, 'id' => $id ) );
	}

	/**
	 * @param int                 $id Page ID.
	 * @param array<string,mixed> $body Body.
	 * @return void
	 */
	private static function apply_meta( $id, $body ) {
		if ( array_key_exists( 'featured_image_id', $body ) ) {
			$thumb = (int) $body['featured_image_id'];
			if ( $thumb > 0 ) {
				set_post_thumbnail( $id, $thumb );
			} else {
				delete_post_thumbnail( $id );
			}
		}
		if ( array_key_exists( 'ai_page_prompt', $body ) ) {
			update_post_meta( $id, '_webinocrm_ai_page_prompt', sanitize_textarea_field( (string) $body['ai_page_prompt'] ) );
		}
		if ( ! empty( $body['seo'] ) && is_array( $body['seo'] ) && class_exists( 'WebinoCRM_AI_Writer', false ) ) {
			WebinoCRM_AI_Writer::apply_post_seo( $id, $body['seo'] );
		}
	}

	/**
	 * @param WP_Post $p Post.
	 * @return array<string,mixed>
	 */
	private static function serialize_list_item( $p ) {
		$urls = self::urls( (int) $p->ID );
		return array(
			'id'            => (int) $p->ID,
			'title'         => $p->post_title,
			'status'        => $p->post_status,
			'date'          => mysql2date( 'c', $p->post_date, false ),
			'excerpt'       => $p->post_excerpt,
			'parent'        => (int) $p->post_parent,
			'url'           => $urls['url'],
			'elementor_url' => $urls['elementor_url'],
			'trash_url'     => $urls['trash_url'],
		);
	}

	/**
	 * @param WP_Post $p Post.
	 * @return array<string,mixed>
	 */
	private static function serialize( $p ) {
		$thumb = (int) get_post_thumbnail_id( $p->ID );
		$urls  = self::urls( (int) $p->ID );
		$seo   = class_exists( 'WebinoCRM_AI_Writer', false )
			? WebinoCRM_AI_Writer::map_post_seo( (int) $p->ID )
			: array();
		return array(
			'id'                 => (int) $p->ID,
			'title'              => $p->post_title,
			'content'            => $p->post_content,
			'excerpt'            => $p->post_excerpt,
			'status'             => $p->post_status,
			'parent'             => (int) $p->post_parent,
			'featured_image_id'  => $thumb,
			'featured_image_url' => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'medium' ) : '',
			'comment_status'     => $p->comment_status,
			'visibility'         => 'private' === $p->post_status ? 'private' : ( $p->post_password ? 'password' : 'public' ),
			'password'           => '',
			'date'               => mysql2date( 'c', $p->post_date, false ),
			'url'                => $urls['url'],
			'elementor_url'      => $urls['elementor_url'],
			'trash_url'          => $urls['trash_url'],
			'seo'                => $seo,
			'rank_math_available'=> class_exists( 'RankMath', false ) || defined( 'RANK_MATH_VERSION' ),
			'ai_page_prompt'     => (string) get_post_meta( (int) $p->ID, '_webinocrm_ai_page_prompt', true ),
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array{url:string,elementor_url:string,trash_url:string}
	 */
	private static function urls( $post_id ) {
		$urls = array(
			'url'           => (string) get_permalink( $post_id ),
			'elementor_url' => '',
			'trash_url'     => (string) get_delete_post_link( $post_id, '', false ),
		);
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$urls['elementor_url'] = admin_url( 'post.php?post=' . (int) $post_id . '&action=elementor' );
		}
		return $urls;
	}
}
