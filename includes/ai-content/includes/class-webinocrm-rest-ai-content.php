<?php
/**
 * REST API for AI Content module.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes under webinocrm/v1/ai-content/*.
 */
final class WebinoCRM_REST_AI_Content {

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
		$edit = array( 'permission_callback' => array( __CLASS__, 'perm_edit' ) );
		$manage = array( 'permission_callback' => array( __CLASS__, 'perm_manage' ) );
		$products = array( 'permission_callback' => array( __CLASS__, 'perm_products' ) );
		$terms = array( 'permission_callback' => array( __CLASS__, 'perm_terms' ) );

		register_rest_route(
			self::NS,
			'/ai-content/overview',
			array_merge( $edit, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'overview' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/settings',
			array(
				array_merge( $manage, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'settings_get' ) ) ),
				array_merge( $manage, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'settings_post' ) ) ),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/design-memory',
			array(
				array_merge( $manage, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'design_memory_get' ) ) ),
				array_merge( $manage, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'design_memory_post' ) ) ),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/design-memory/extract',
			array_merge( $manage, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'design_memory_extract' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/design-memory/reset',
			array_merge( $manage, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'design_memory_reset' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/site-profile',
			array_merge(
				array( 'permission_callback' => array( __CLASS__, 'perm_pages' ) ),
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'site_profile_get' ) )
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/pages',
			array_merge(
				array( 'permission_callback' => array( __CLASS__, 'perm_pages' ) ),
				array(
					'methods'  => 'GET',
					'callback' => array( __CLASS__, 'pages_list' ),
					'args'     => array(
						'page'     => array( 'type' => 'integer', 'default' => 1 ),
						'per_page' => array( 'type' => 'integer', 'default' => 50 ),
						'search'   => array( 'type' => 'string', 'default' => '' ),
					),
				)
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/gapgpt/models',
			array_merge(
				$manage,
				array(
					'methods'  => 'GET',
					'callback' => array( __CLASS__, 'gapgpt_models' ),
					'args'     => array(
						'refresh' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				)
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/cost-estimate',
			array(
				array_merge( $edit, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'cost_estimate' ) ) ),
				array_merge( $edit, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'cost_estimate' ) ) ),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/jobs',
			array(
				array_merge( $edit, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'jobs_list' ) ) ),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/jobs/run-due',
			array_merge(
				$edit,
				array(
					'methods'  => 'POST',
					'callback' => array( __CLASS__, 'jobs_run_due' ),
					'args'     => array(
						'limit' => array(
							'type'    => 'integer',
							'default' => 1,
						),
					),
				)
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/jobs/(?P<id>\d+)',
			array_merge( $edit, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'jobs_get' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/jobs/(?P<id>\d+)/run',
			array_merge( $edit, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'jobs_run_one' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/jobs/cancel-pending',
			array_merge( $edit, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'jobs_cancel_pending' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/queue',
			array(
				array_merge( $edit, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'queue_get' ) ) ),
				array_merge( $edit, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'queue_post' ) ) ),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/jobs/(?P<id>\d+)/retry',
			array_merge( $edit, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'jobs_retry' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/jobs/(?P<id>\d+)/cancel',
			array_merge( $edit, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'jobs_cancel' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/generate',
			array_merge( $edit, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'generate' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/products/incomplete',
			array_merge( $products, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'products_incomplete' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/products/fill-batch',
			array_merge( $products, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'products_fill_batch' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/calendar',
			array(
				array_merge( $edit, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'calendar_list' ) ) ),
				array_merge( $edit, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'calendar_create' ) ) ),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/calendar/bulk',
			array_merge( $edit, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'calendar_bulk' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/calendar/(?P<id>\d+)',
			array(
				array_merge( $edit, array( 'methods' => 'PATCH', 'callback' => array( __CLASS__, 'calendar_patch' ) ) ),
				array_merge( $edit, array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'calendar_delete' ) ) ),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/calendar/run-due',
			array_merge( $manage, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'calendar_run_due' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/attribute-templates',
			array(
				array_merge( $terms, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'attr_list' ) ) ),
				array_merge( $terms, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'attr_confirm' ) ) ),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/attribute-templates/(?P<cat_id>\d+)/suggest',
			array_merge( $terms, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'attr_suggest' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/attribute-templates/(?P<cat_id>\d+)/draft',
			array_merge( $terms, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'attr_draft' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/attribute-templates/(?P<cat_id>\d+)',
			array_merge( $terms, array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'attr_delete' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/suggest-categories',
			array_merge( $edit, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'suggest_categories' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/suggest-categories/(?P<kind>blog|product)',
			array_merge( $edit, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'suggest_categories_get' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/suggest-categories/(?P<kind>blog|product)/apply',
			array_merge( $edit, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'suggest_categories_apply' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/terms/fill-batch',
			array_merge( $terms, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'terms_fill_batch' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/proposals/(?P<kind>title|catalog)',
			array_merge(
				$products,
				array(
					'methods'  => 'GET',
					'callback' => array( __CLASS__, 'proposals_list' ),
					'args'     => array(
						'status' => array(
							'type'    => 'string',
							'default' => 'pending',
						),
						'limit'  => array(
							'type'    => 'integer',
							'default' => 100,
						),
					),
				)
			)
		);

		register_rest_route(
			self::NS,
			'/ai-content/proposals/(?P<kind>title|catalog)/enqueue',
			array_merge( $products, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'proposals_enqueue' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/proposals/(?P<id>\d+)/apply',
			array_merge( $products, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'proposals_apply' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/proposals/(?P<id>\d+)/skip',
			array_merge( $products, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'proposals_skip' ) ) )
		);

		register_rest_route(
			self::NS,
			'/ai-content/proposals/(?P<kind>title|catalog)/product/(?P<product_id>\d+)/requeue',
			array_merge( $products, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'proposals_requeue_one' ) ) )
		);
	}

	/** @return bool */
	public static function perm_edit() {
		return WebinoCRM_REST_Base::can( 'edit_posts' ) || WebinoCRM_REST_Base::can( 'edit_pages' );
	}

	/** @return bool */
	public static function perm_manage() {
		return WebinoCRM_REST_Base::can( 'manage_options' );
	}

	/** @return bool */
	public static function perm_products() {
		return WebinoCRM_REST_Base::can( 'edit_products' );
	}

	/** @return bool */
	public static function perm_terms() {
		return WebinoCRM_REST_Base::can( 'manage_product_terms' );
	}

	/** @return bool */
	public static function perm_pages() {
		return WebinoCRM_REST_Base::can( 'edit_pages' );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function design_memory_get() {
		return new WP_REST_Response( WebinoCRM_AI_Design_Memory::get() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function design_memory_post( $request ) {
		$body  = $request->get_json_params();
		$force = is_array( $body ) && ! empty( $body['force'] );
		$saved = WebinoCRM_AI_Design_Memory::save( is_array( $body ) ? $body : array(), $force );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return new WP_REST_Response( $saved );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function design_memory_extract() {
		$saved = WebinoCRM_AI_Design_Memory::extract_from_elementor_kit();
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return new WP_REST_Response( $saved );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function design_memory_reset() {
		return new WP_REST_Response( WebinoCRM_AI_Design_Memory::reset() );
	}

	/**
	 * Lightweight site description for page editors.
	 *
	 * @return WP_REST_Response
	 */
	public static function site_profile_get() {
		$s = WebinoCRM_AI_Content_Settings::get();
		return new WP_REST_Response(
			array(
				'site_name'        => WebinoCRM_AI_Content_Settings::resolved_site_name( $s ),
				'site_topic'       => (string) ( $s['site_topic'] ?? '' ),
				'site_description' => (string) ( $s['site_description'] ?? '' ),
				'do_page'          => ! empty( $s['do_page'] ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function pages_list( $request ) {
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
			$items[] = array(
				'id'           => (int) $p->ID,
				'title'        => get_the_title( $p ),
				'status'       => $p->post_status,
				'modified'     => $p->post_modified,
				'url'          => get_permalink( $p ),
				'page_prompt'  => (string) get_post_meta( $p->ID, '_webinocrm_ai_page_prompt', true ),
				'has_elementor'=> 'builder' === (string) get_post_meta( $p->ID, '_elementor_edit_mode', true ),
				'elementor_url'=> defined( 'ELEMENTOR_VERSION' )
					? admin_url( 'post.php?post=' . (int) $p->ID . '&action=elementor' )
					: '',
			);
		}

		return new WP_REST_Response(
			array(
				'items'       => $items,
				'page'        => $page,
				'found'       => (int) $q->found_posts,
				'elementor'   => defined( 'ELEMENTOR_VERSION' ),
			)
		);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function overview() {
		return new WP_REST_Response( WebinoCRM_AI_Content::overview() );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function settings_get() {
		return new WP_REST_Response( WebinoCRM_AI_Content_Settings::get_public() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function settings_post( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}
		return new WP_REST_Response( WebinoCRM_AI_Content_Settings::save( is_array( $body ) ? $body : array() ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function gapgpt_models( $request ) {
		$refresh = rest_sanitize_boolean( $request->get_param( 'refresh' ) );
		$models  = WebinoCRM_AI_Providers::list_gapgpt_models( (bool) $refresh );
		if ( is_wp_error( $models ) ) {
			return $models;
		}
		return new WP_REST_Response( array( 'models' => $models ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function cost_estimate( $request ) {
		$draft = $request->get_json_params();
		if ( ! is_array( $draft ) ) {
			$draft = array();
		}
		foreach ( array( 'gapgpt_model', 'default_provider', 'usd_to_toman' ) as $key ) {
			$q = $request->get_param( $key );
			if ( null !== $q && '' !== $q && ! array_key_exists( $key, $draft ) ) {
				$draft[ $key ] = $q;
			}
		}
		return new WP_REST_Response( WebinoCRM_AI_Pricing::estimate( $draft ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function jobs_list( $request ) {
		return new WP_REST_Response(
			WebinoCRM_AI_Queue::list_jobs(
				array(
					'status' => (string) $request->get_param( 'status' ),
					'limit'  => (int) $request->get_param( 'limit' ),
					'offset' => (int) $request->get_param( 'offset' ),
				)
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function jobs_retry( $request ) {
		global $wpdb;
		$id  = (int) $request['id'];
		$job = WebinoCRM_AI_Queue::get_job( $id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		$status = (string) ( $job['status'] ?? '' );
		if ( ! in_array( $status, array( 'failed', 'cancelled' ), true ) ) {
			return new WP_Error( 'ai_job', __( 'Only failed or cancelled jobs can be retried.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$table = WebinoCRM_AI_Content_Db::table( 'jobs' );
		$wpdb->update(
			$table,
			array(
				'status'        => 'pending',
				'error_message' => '',
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( WebinoCRM_AI_Queue::is_paused() ) {
			return new WP_REST_Response( array( 'ok' => true, 'id' => $id, 'queued' => true, 'paused' => true ) );
		}
		$claimed = WebinoCRM_AI_Queue::claim_job( $id );
		if ( ! is_wp_error( $claimed ) ) {
			WebinoCRM_AI_Queue::defer_execute( $id );
			return new WP_REST_Response( array( 'ok' => true, 'id' => $id, 'job' => $claimed ) );
		}
		WebinoCRM_AI_Queue::schedule( $id );
		return new WP_REST_Response( array( 'ok' => true, 'id' => $id ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function jobs_get( $request ) {
		$job = WebinoCRM_AI_Queue::get_job( (int) $request['id'] );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		return new WP_REST_Response( $job );
	}

	/**
	 * Process pending jobs in this request (WP-Cron fallback).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function jobs_run_due( $request ) {
		if ( WebinoCRM_AI_Queue::is_paused() ) {
			return new WP_REST_Response(
				array(
					'processed' => array(),
					'count'     => 0,
					'paused'    => true,
				)
			);
		}
		$limit = (int) $request->get_param( 'limit' );
		if ( $limit < 1 ) {
			$body = $request->get_json_params();
			if ( is_array( $body ) && isset( $body['limit'] ) ) {
				$limit = (int) $body['limit'];
			}
		}
		return new WP_REST_Response( WebinoCRM_AI_Queue::run_due( $limit > 0 ? $limit : 1 ) );
	}

	/**
	 * Claim a job and finish work after JSON is sent.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function jobs_run_one( $request ) {
		$id  = (int) $request['id'];
		$job = WebinoCRM_AI_Queue::get_job( $id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		if ( 'pending' !== ( $job['status'] ?? '' ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'job' => $job, 'accepted' => false ) );
		}
		$claimed = WebinoCRM_AI_Queue::claim_job( $id );
		if ( is_wp_error( $claimed ) ) {
			$current = WebinoCRM_AI_Queue::get_job( $id );
			if ( ! is_wp_error( $current ) ) {
				return new WP_REST_Response( array( 'ok' => true, 'job' => $current, 'accepted' => false ) );
			}
			return $claimed;
		}
		WebinoCRM_AI_Queue::defer_execute( $id );
		return new WP_REST_Response( array( 'ok' => true, 'job' => $claimed, 'accepted' => true ), 202 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function jobs_cancel( $request ) {
		$job = WebinoCRM_AI_Queue::cancel_job( (int) $request['id'] );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		return new WP_REST_Response( array( 'ok' => true, 'job' => $job ) );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function jobs_cancel_pending() {
		return new WP_REST_Response( array_merge( array( 'ok' => true ), WebinoCRM_AI_Queue::cancel_pending() ) );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function queue_get() {
		return new WP_REST_Response( array( 'paused' => WebinoCRM_AI_Queue::is_paused() ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function queue_post( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}
		$paused = ! empty( $body['paused'] );
		WebinoCRM_AI_Queue::set_paused( $paused );
		return new WP_REST_Response( array( 'ok' => true, 'paused' => WebinoCRM_AI_Queue::is_paused() ) );
	}

	/**
	 * Immediate or queued generate for a single entity.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function generate( $request ) {
		$ready = self::assert_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		$type   = sanitize_key( (string) ( $body['type'] ?? '' ) );
		$id     = (int) ( $body['id'] ?? 0 );
		$sync   = ! empty( $body['sync'] );
		$payload = isset( $body['payload'] ) && is_array( $body['payload'] ) ? $body['payload'] : array();

		$map = array(
			'product'       => array( 'product_fill', 'product' ),
			'post'          => array( 'blog_write', 'post' ),
			'product_cat'   => array( 'term_fill', 'product_cat' ),
			'product_brand' => array( 'term_fill', 'product_brand' ),
			'category'      => array( 'term_fill', 'category' ),
			'blog'          => array( 'blog_write', 'calendar' ),
			'page'          => array( 'page_design', 'page' ),
		);

		if ( ! isset( $map[ $type ] ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid generate type.', 'webinocrm' ), array( 'status' => 400 ) );
		}

		$entity = WebinoCRM_AI_Content_Settings::entity_for_type( $type );
		$ok_ent = WebinoCRM_AI_Content_Settings::assert_entity( $entity );
		if ( is_wp_error( $ok_ent ) ) {
			return $ok_ent;
		}

		if ( 'post' === $type ) {
			$payload['post_id'] = $id;
		}
		if ( 'blog' === $type ) {
			$payload = array_merge( $payload, array(
				'topic'         => (string) ( $body['topic'] ?? '' ),
				'focus_keyword' => (string) ( $body['focus_keyword'] ?? '' ),
			) );
		}
		if ( 'page' === $type ) {
			if ( $id <= 0 ) {
				return new WP_Error( 'invalid_id', __( 'Page id required.', 'webinocrm' ), array( 'status' => 400 ) );
			}
			if ( ! empty( $body['page_prompt'] ) ) {
				$payload['page_prompt'] = sanitize_textarea_field( (string) $body['page_prompt'] );
			}
			if ( ! empty( $body['prompt'] ) && empty( $payload['page_prompt'] ) ) {
				$payload['page_prompt'] = sanitize_textarea_field( (string) $body['prompt'] );
			}
			if ( ! empty( $body['focus_keyword'] ) ) {
				$payload['focus_keyword'] = sanitize_text_field( (string) $body['focus_keyword'] );
			}
		}

		$job_type    = $map[ $type ][0];
		$target_type = $map[ $type ][1];
		$job_id      = WebinoCRM_AI_Queue::enqueue( $job_type, $target_type, $id, $payload );
		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}

		$run_now = $sync || ! empty( $body['run_now'] );
		if ( $run_now ) {
			WebinoCRM_AI_Queue::process_job( $job_id, true );
			$job = WebinoCRM_AI_Queue::get_job( $job_id );
			if ( is_wp_error( $job ) ) {
				return $job;
			}
			if ( 'failed' === ( $job['status'] ?? '' ) ) {
				return new WP_Error(
					'ai_job_failed',
					(string) ( $job['error_message'] ?? __( 'Generation failed.', 'webinocrm' ) ),
					array( 'status' => 500, 'job_id' => $job_id )
				);
			}
			return new WP_REST_Response( array( 'ok' => true, 'job_id' => $job_id, 'job' => $job, 'queued' => false ) );
		}

		return new WP_REST_Response( array( 'ok' => true, 'job_id' => $job_id, 'queued' => true ), 202 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function products_incomplete( $request ) {
		$limit = (int) $request->get_param( 'limit' );
		return new WP_REST_Response( WebinoCRM_AI_Content::incomplete_products( $limit > 0 ? $limit : 50 ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function products_fill_batch( $request ) {
		$ready = self::assert_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$ent = WebinoCRM_AI_Content_Settings::assert_entity( 'product' );
		if ( is_wp_error( $ent ) ) {
			return $ent;
		}
		$body = $request->get_json_params();
		$ids  = isset( $body['ids'] ) && is_array( $body['ids'] ) ? array_map( 'intval', $body['ids'] ) : array();
		if ( ! $ids ) {
			$incomplete = WebinoCRM_AI_Content::incomplete_products( (int) ( $body['limit'] ?? 10 ) );
			$ids        = array_map(
				static function ( $row ) {
					return (int) $row['id'];
				},
				$incomplete['items']
			);
		}
		$job_ids = array();
		foreach ( $ids as $pid ) {
			if ( $pid <= 0 ) {
				continue;
			}
			$jid = WebinoCRM_AI_Queue::enqueue( 'product_fill', 'product', $pid, array() );
			if ( is_wp_error( $jid ) ) {
				return $jid;
			}
			$job_ids[] = $jid;
		}
		return new WP_REST_Response( array( 'ok' => true, 'job_ids' => $job_ids, 'count' => count( $job_ids ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function calendar_list( $request ) {
		return new WP_REST_Response(
			WebinoCRM_AI_Calendar::list_slots(
				array(
					'from' => (string) $request->get_param( 'from' ),
					'to'   => (string) $request->get_param( 'to' ),
				)
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function calendar_create( $request ) {
		$body = $request->get_json_params();
		$slot = WebinoCRM_AI_Calendar::create_slot( is_array( $body ) ? $body : array() );
		if ( is_wp_error( $slot ) ) {
			return $slot;
		}
		return new WP_REST_Response( $slot, 201 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function calendar_bulk( $request ) {
		$body = $request->get_json_params();
		return new WP_REST_Response( WebinoCRM_AI_Calendar::bulk_from_topics( is_array( $body ) ? $body : array() ), 201 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function calendar_patch( $request ) {
		$body = $request->get_json_params();
		$slot = WebinoCRM_AI_Calendar::update_slot( (int) $request['id'], is_array( $body ) ? $body : array() );
		if ( is_wp_error( $slot ) ) {
			return $slot;
		}
		return new WP_REST_Response( $slot );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function calendar_delete( $request ) {
		WebinoCRM_AI_Calendar::delete_slot( (int) $request['id'] );
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function calendar_run_due() {
		WebinoCRM_AI_Calendar::run_daily();
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function attr_list() {
		return new WP_REST_Response( WebinoCRM_AI_Attributes::list_templates() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function attr_suggest( $request ) {
		$ready = self::assert_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$cat_id = (int) $request['cat_id'];
		$job_id = WebinoCRM_AI_Queue::enqueue( 'attr_template', 'product_cat', $cat_id, array() );
		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}
		return new WP_REST_Response( array( 'ok' => true, 'job_id' => $job_id ), 202 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function attr_draft( $request ) {
		$cat_id     = (int) $request['cat_id'];
		$draft      = get_transient( 'webinocrm_ai_attr_draft_' . $cat_id );
		$discovered = WebinoCRM_AI_Attributes::discover_in_categories( array( $cat_id ) );
		return new WP_REST_Response(
			array(
				'product_cat_id' => $cat_id,
				'draft'          => is_array( $draft ) ? $draft : null,
				'template'       => WebinoCRM_AI_Attributes::get_template( $cat_id ),
				'discovered'     => $discovered['labels'],
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function attr_confirm( $request ) {
		$body   = $request->get_json_params();
		$cat_id = (int) ( $body['product_cat_id'] ?? 0 );
		if ( isset( $body['attribute_ids'] ) && is_array( $body['attribute_ids'] ) ) {
			$saved = WebinoCRM_AI_Attributes::save_mapping( $cat_id, $body['attribute_ids'] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			return new WP_REST_Response( $saved );
		}
		$draft = isset( $body['draft'] ) && is_array( $body['draft'] ) ? $body['draft'] : get_transient( 'webinocrm_ai_attr_draft_' . $cat_id );
		if ( ! is_array( $draft ) ) {
			return new WP_Error( 'no_draft', __( 'No attribute draft to confirm.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$saved = WebinoCRM_AI_Attributes::confirm_template( $cat_id, $draft );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return new WP_REST_Response( $saved );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function attr_delete( $request ) {
		WebinoCRM_AI_Attributes::delete_template( (int) $request['cat_id'] );
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function suggest_categories( $request ) {
		$ready = self::assert_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$body = $request->get_json_params();
		$kind = sanitize_key( (string) ( $body['kind'] ?? 'blog' ) );
		if ( ! in_array( $kind, array( 'blog', 'product' ), true ) ) {
			$kind = 'blog';
		}
		$job_type = 'blog' === $kind ? 'suggest_blog_categories' : 'suggest_product_categories';
		$payload  = array();
		if ( 'product' === $kind && function_exists( 'wc_get_products' ) ) {
			$ids = wc_get_products( array( 'limit' => 15, 'status' => 'publish', 'return' => 'ids' ) );
			$samples = array();
			foreach ( (array) $ids as $id ) {
				$p = wc_get_product( (int) $id );
				if ( $p ) {
					$samples[] = $p->get_name();
				}
			}
			$payload['product_samples'] = $samples;
		}
		$job_id = WebinoCRM_AI_Queue::enqueue( $job_type, 'suggest', 0, $payload );
		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}
		return new WP_REST_Response( array( 'ok' => true, 'job_id' => $job_id ), 202 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function suggest_categories_get( $request ) {
		$kind = sanitize_key( (string) $request['kind'] );
		$data = get_transient( 'webinocrm_ai_suggest_cats_' . $kind );
		return new WP_REST_Response(
			array(
				'kind'        => $kind,
				'suggestions' => is_array( $data ) ? $data : null,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function suggest_categories_apply( $request ) {
		$kind = sanitize_key( (string) $request['kind'] );
		$body = $request->get_json_params();
		$data = isset( $body['categories'] ) && is_array( $body['categories'] ) ? $body['categories'] : null;
		if ( ! $data ) {
			$stored = get_transient( 'webinocrm_ai_suggest_cats_' . $kind );
			$data   = is_array( $stored ) && isset( $stored['categories'] ) ? $stored['categories'] : array();
		}
		$tax     = 'blog' === $kind ? 'category' : 'product_cat';
		$created = array();
		foreach ( (array) $data as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$parent = 0;
			if ( ! empty( $row['parent'] ) ) {
				$pterm = term_exists( sanitize_text_field( (string) $row['parent'] ), $tax );
				if ( $pterm && ! is_wp_error( $pterm ) ) {
					$parent = (int) ( is_array( $pterm ) ? $pterm['term_id'] : $pterm );
				}
			}
			$exists = term_exists( $name, $tax );
			if ( $exists && ! is_wp_error( $exists ) ) {
				$tid = (int) ( is_array( $exists ) ? $exists['term_id'] : $exists );
			} else {
				$ins = wp_insert_term(
					$name,
					$tax,
					array(
						'slug'        => sanitize_title( (string) ( $row['slug'] ?? $name ) ),
						'description' => sanitize_textarea_field( (string) ( $row['description'] ?? '' ) ),
						'parent'      => $parent,
					)
				);
				if ( is_wp_error( $ins ) ) {
					continue;
				}
				$tid = (int) $ins['term_id'];
			}
			$created[] = $tid;
			if ( ! empty( $row['children'] ) && is_array( $row['children'] ) ) {
				foreach ( $row['children'] as $child ) {
					$cname = is_string( $child ) ? $child : (string) ( $child['name'] ?? '' );
					$cname = sanitize_text_field( $cname );
					if ( '' === $cname || term_exists( $cname, $tax ) ) {
						continue;
					}
					$cins = wp_insert_term( $cname, $tax, array( 'parent' => $tid ) );
					if ( ! is_wp_error( $cins ) ) {
						$created[] = (int) $cins['term_id'];
					}
				}
			}
		}
		return new WP_REST_Response( array( 'ok' => true, 'created_ids' => $created, 'count' => count( $created ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function terms_fill_batch( $request ) {
		$ready = self::assert_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$body     = $request->get_json_params();
		$taxonomy = sanitize_key( (string) ( $body['taxonomy'] ?? 'product_cat' ) );
		if ( ! in_array( $taxonomy, array( 'product_cat', 'product_brand', 'category' ), true ) ) {
			$taxonomy = 'product_cat';
		}
		$ent = WebinoCRM_AI_Content_Settings::assert_entity( WebinoCRM_AI_Content_Settings::entity_for_type( $taxonomy ) );
		if ( is_wp_error( $ent ) ) {
			return $ent;
		}
		$ids = isset( $body['ids'] ) && is_array( $body['ids'] ) ? array_map( 'intval', $body['ids'] ) : array();
		if ( ! $ids ) {
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 30 ) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					$desc = trim( wp_strip_all_tags( $t->description ) );
					$seo  = (string) get_term_meta( (int) $t->term_id, 'rank_math_focus_keyword', true );
					if ( mb_strlen( $desc ) < 80 || '' === $seo ) {
						$ids[] = (int) $t->term_id;
					}
				}
			}
		}
		$job_ids = array();
		foreach ( $ids as $tid ) {
			if ( $tid <= 0 ) {
				continue;
			}
			$jid = WebinoCRM_AI_Queue::enqueue( 'term_fill', $taxonomy, $tid, array() );
			if ( is_wp_error( $jid ) ) {
				return $jid;
			}
			$job_ids[] = $jid;
		}
		return new WP_REST_Response( array( 'ok' => true, 'job_ids' => $job_ids, 'count' => count( $job_ids ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function proposals_list( $request ) {
		$kind   = sanitize_key( (string) $request['kind'] );
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$limit  = (int) $request->get_param( 'limit' );
		return new WP_REST_Response(
			WebinoCRM_AI_Proposals::list_proposals(
				$kind,
				array(
					'status' => $status ? $status : 'pending',
					'limit'  => $limit > 0 ? $limit : 100,
				)
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function proposals_enqueue( $request ) {
		$ready = self::assert_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$kind = sanitize_key( (string) $request['kind'] );
		$body = $request->get_json_params();
		$ids  = isset( $body['ids'] ) && is_array( $body['ids'] ) ? array_map( 'intval', $body['ids'] ) : null;
		$res  = WebinoCRM_AI_Proposals::enqueue_batch( $kind, $ids );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return new WP_REST_Response( $res, 202 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function proposals_apply( $request ) {
		$body     = $request->get_json_params();
		$override = is_array( $body ) && isset( $body['proposed'] ) && is_array( $body['proposed'] ) ? $body['proposed'] : null;
		if ( is_array( $body ) && isset( $body['name'] ) ) {
			$override = is_array( $override ) ? $override : array();
			$override['name'] = (string) $body['name'];
		}
		$res = WebinoCRM_AI_Proposals::apply( (int) $request['id'], $override );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return new WP_REST_Response( $res );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function proposals_skip( $request ) {
		$res = WebinoCRM_AI_Proposals::skip( (int) $request['id'] );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return new WP_REST_Response( $res );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function proposals_requeue_one( $request ) {
		$ready = self::assert_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$kind = sanitize_key( (string) $request['kind'] );
		$pid  = (int) $request['product_id'];
		if ( $pid < 1 ) {
			return new WP_Error( 'ai_proposal', __( 'Invalid product.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		$res = WebinoCRM_AI_Proposals::enqueue_batch( $kind, array( $pid ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return new WP_REST_Response( $res, 202 );
	}

	/**
	 * Module enabled and at least one provider key.
	 *
	 * @return true|WP_Error
	 */
	private static function assert_ready() {
		if ( ! WebinoCRM_AI_Content::is_enabled() ) {
			return new WP_Error( 'ai_disabled', __( 'AI Content is disabled.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		if ( ! WebinoCRM_AI_Content_Settings::has_any_provider_key() ) {
			return new WP_Error( 'ai_no_key', __( 'No AI API key is configured.', 'webinocrm' ), array( 'status' => 400 ) );
		}
		return true;
	}
}
