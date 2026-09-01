<?php
/**
 * REST API for portfolio management from CRM dashboard.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_REST_Site_Portfolio {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'webinocrm/v1',
			'/site/portfolio',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_items' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_item' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			'webinocrm/v1',
			'/site/portfolio/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_item' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( __CLASS__, 'update_item' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list_items( $request ) {
		$q = new WP_Query(
			array(
				'post_type'      => WebinoCRM_Site_Portfolio::POST_TYPE,
				'posts_per_page' => 100,
				'post_status'    => 'any',
			)
		);
		$items = array();
		foreach ( $q->posts as $post ) {
			$items[] = self::format_post( $post );
		}
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_item( $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || WebinoCRM_Site_Portfolio::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Portfolio item not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::format_post( $post ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_item( $request ) {
		$params = $request->get_json_params();
		$id     = wp_insert_post(
			array(
				'post_type'    => WebinoCRM_Site_Portfolio::POST_TYPE,
				'post_title'   => sanitize_text_field( (string) ( $params['title'] ?? '' ) ),
				'post_excerpt' => sanitize_textarea_field( (string) ( $params['excerpt'] ?? '' ) ),
				'post_content' => wp_kses_post( (string) ( $params['content'] ?? '' ) ),
				'post_status'  => 'publish',
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		self::save_meta( (int) $id, $params );
		return rest_ensure_response( self::format_post( get_post( $id ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_item( $request ) {
		$id = (int) $request['id'];
		$params = $request->get_json_params();
		wp_update_post(
			array(
				'ID'           => $id,
				'post_title'   => sanitize_text_field( (string) ( $params['title'] ?? get_the_title( $id ) ) ),
				'post_excerpt' => sanitize_textarea_field( (string) ( $params['excerpt'] ?? '' ) ),
				'post_content' => wp_kses_post( (string) ( $params['content'] ?? '' ) ),
			)
		);
		self::save_meta( $id, $params );
		return rest_ensure_response( self::format_post( get_post( $id ) ) );
	}

	/**
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $params Params.
	 */
	private static function save_meta( $post_id, $params ) {
		$fields = array( 'summary', 'challenge', 'solution', 'live_url', 'video_url' );
		foreach ( $fields as $field ) {
			if ( isset( $params[ $field ] ) ) {
				update_post_meta( $post_id, '_webina_' . $field, sanitize_text_field( (string) $params[ $field ] ) );
			}
		}
		if ( isset( $params['results'] ) && is_array( $params['results'] ) ) {
			update_post_meta( $post_id, '_webina_results', wp_json_encode( $params['results'] ) );
		}
		if ( isset( $params['featured'] ) ) {
			update_post_meta( $post_id, '_webina_featured', $params['featured'] ? '1' : '0' );
		}
	}

	/**
	 * @param WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	private static function format_post( $post ) {
		return array(
			'id'        => (int) $post->ID,
			'title'     => $post->post_title,
			'excerpt'   => $post->post_excerpt,
			'content'   => $post->post_content,
			'status'    => $post->post_status,
			'permalink' => get_permalink( $post ),
			'meta'      => array(
				'summary'   => get_post_meta( $post->ID, '_webina_summary', true ),
				'challenge' => get_post_meta( $post->ID, '_webina_challenge', true ),
				'solution'  => get_post_meta( $post->ID, '_webina_solution', true ),
				'results'   => json_decode( (string) get_post_meta( $post->ID, '_webina_results', true ), true ),
				'featured'  => '1' === get_post_meta( $post->ID, '_webina_featured', true ),
				'live_url'  => get_post_meta( $post->ID, '_webina_live_url', true ),
			),
		);
	}
}
