<?php
/**
 * Projects service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Project CRUD and meta operations.
 */
class WebinoCRM_Project_Service {

	/**
	 * @param array<string,mixed> $params Query params.
	 * @return array<string,mixed>
	 */
	public static function list( array $params ) {
		if ( ! function_exists( 'webinocrm_user_can_read_projects' ) || ! webinocrm_user_can_read_projects() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$current_user_id  = get_current_user_id();
		$is_manager       = function_exists( 'webinocrm_user_can_manage_projects' ) && webinocrm_user_can_manage_projects();
		$is_team_member   = function_exists( 'webinocrm_current_user_has_role' ) && webinocrm_current_user_has_role( array( 'team_member' ) );
		$is_client_reader = function_exists( 'webinocrm_current_user_has_role' ) && webinocrm_current_user_has_role( array( 'client', 'customer' ) ) && ! $is_manager;

		$search          = isset( $params['s'] ) ? sanitize_text_field( (string) $params['s'] ) : '';
		$contract_filter = isset( $params['contract_id'] ) ? absint( $params['contract_id'] ) : 0;
		$status_filter   = isset( $params['status_filter'] ) ? absint( $params['status_filter'] ) : 0;
		$paged           = isset( $params['paged'] ) ? max( 1, absint( $params['paged'] ) ) : 1;

		$args = array(
			'post_type'      => 'project',
			'posts_per_page' => 20,
			'paged'          => $paged,
			'post_status'    => 'publish',
		);
		if ( $search ) {
			$args['s'] = $search;
		}
		if ( $contract_filter > 0 ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_contract_id',
					'value' => $contract_filter,
				),
			);
		}
		if ( $status_filter > 0 ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'project_status',
					'field'    => 'term_id',
					'terms'    => $status_filter,
				),
			);
		}
		if ( $is_client_reader ) {
			$args['author'] = $current_user_id;
		} elseif ( $is_team_member && ! $is_manager ) {
			$accessible       = function_exists( 'webinocrm_get_accessible_project_ids_for_user' )
				? webinocrm_get_accessible_project_ids_for_user( $current_user_id )
				: array();
			$args['post__in'] = empty( $accessible ) ? array( 0 ) : $accessible;
		}

		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$contract_id   = get_post_meta( $post->ID, '_contract_id', true );
			$contract      = $contract_id ? get_post( $contract_id ) : null;
			$customer_name = $contract && $contract->post_author ? get_the_author_meta( 'display_name', $contract->post_author ) : '---';
			$status_term   = WebinoCRM_Term_Helper::get_first_term( $post->ID, 'project_status' );
			$can_delete    = $is_manager && current_user_can( 'delete_posts' );
			$logo_url      = get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: '';
			$items[]       = array(
				'id'            => $post->ID,
				'title'         => $post->post_title,
				'contract_id'   => (int) $contract_id,
				'customer_name' => $customer_name,
				'status_name'   => $status_term ? $status_term->name : '---',
				'status_id'     => $status_term ? (int) $status_term->term_id : 0,
				'logo_url'      => $logo_url,
				'delete_nonce'  => $can_delete ? wp_create_nonce( 'webino_delete_project_' . $post->ID ) : '',
			);
		}

		$contracts      = get_posts(
			array(
				'post_type'      => 'contract',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		$contracts_list = array();
		foreach ( $contracts as $c ) {
			$cust               = get_userdata( $c->post_author );
			$contracts_list[] = array(
				'id'            => $c->ID,
				'title'         => $c->post_title,
				'customer_name' => $cust ? $cust->display_name : '---',
			);
		}
		$statuses      = get_terms(
			array(
				'taxonomy'   => 'project_status',
				'hide_empty' => false,
			)
		);
		$statuses_list = array_map(
			static function ( $t ) {
				return array(
					'id'   => $t->term_id,
					'name' => $t->name,
				);
			},
			is_array( $statuses ) ? $statuses : array()
		);

		$managers = array();
		if ( $is_manager || $is_team_member ) {
			$managers_raw = get_users(
				array(
					'role__in' => array( 'system_manager', 'administrator' ),
					'orderby'  => 'display_name',
				)
			);
			$managers = array_map(
				static function ( $u ) {
					return array(
						'id'           => $u->ID,
						'display_name' => $u->display_name,
					);
				},
				$managers_raw
			);
		}

		return WebinoCRM_Service_Base::success(
			array(
				'projects'     => $items,
				'contracts'    => $contracts_list,
				'statuses'     => $statuses_list,
				'managers'     => $managers,
				'total_pages'  => $query->max_num_pages,
				'current_page' => $paged,
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function get( array $params ) {
		if ( ! function_exists( 'webinocrm_user_can_read_projects' ) || ! webinocrm_user_can_read_projects() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$current_user_id = get_current_user_id();
		$user_roles      = (array) wp_get_current_user()->roles;
		$is_manager      = current_user_can( 'manage_options' ) || ( function_exists( 'webinocrm_user_can_manage_projects' ) && webinocrm_user_can_manage_projects() );
		$is_team_member  = in_array( 'team_member', $user_roles, true );
		$is_customer     = in_array( 'customer', $user_roles, true ) || in_array( 'client', $user_roles, true );

		if ( ! $is_manager && ! $is_team_member && ! $is_customer ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$project_id = self::int_param( $params, 'project_id' );
		if ( $project_id <= 0 ) {
			$project_id = self::int_param( $params, 'id' );
		}
		$post = get_post( $project_id );
		if ( ! $post || 'project' !== $post->post_type ) {
			return WebinoCRM_Service_Base::error( __( 'پروژه یافت نشد.', 'webinocrm' ), 404 );
		}

		$has_access = $is_manager;
		if ( ! $has_access && $is_team_member ) {
			$assigned_members = get_post_meta( $project_id, '_assigned_team_members', true );
			if ( is_array( $assigned_members ) && in_array( $current_user_id, $assigned_members, true ) ) {
				$has_access = true;
			}
			if ( ! $has_access ) {
				$user_tasks = get_posts(
					array(
						'post_type'      => 'task',
						'posts_per_page' => 1,
						'meta_query'     => array(
							array(
								'key'   => '_project_id',
								'value' => $project_id,
							),
							array(
								'key'   => '_assigned_to',
								'value' => $current_user_id,
							),
						),
					)
				);
				if ( ! empty( $user_tasks ) ) {
					$has_access = true;
				}
			}
		}
		if ( ! $has_access && $is_customer && (int) $post->post_author === $current_user_id ) {
			$has_access = true;
		}
		if ( ! $has_access ) {
			return WebinoCRM_Service_Base::error( __( 'شما دسترسی لازم برای مشاهده این پروژه را ندارید.', 'webinocrm' ), 403 );
		}

		$status_term          = WebinoCRM_Term_Helper::get_first_term( $project_id, 'project_status' );
		$start_date           = get_post_meta( $project_id, '_project_start_date', true );
		$end_date             = get_post_meta( $project_id, '_project_end_date', true );
		$contract_id          = (int) get_post_meta( $project_id, '_contract_id', true );
		$project_manager_id   = (int) get_post_meta( $project_id, '_project_manager', true );
		$assigned_members     = get_post_meta( $project_id, '_assigned_team_members', true );
		$assigned_members     = is_array( $assigned_members ) ? $assigned_members : array();
		$priority             = get_post_meta( $project_id, '_project_priority', true ) ?: 'medium';
		$contract             = $contract_id ? get_post( $contract_id ) : null;
		$customer_id          = $contract && $contract->post_author ? (int) $contract->post_author : (int) $post->post_author;
		$customer             = $customer_id ? get_userdata( $customer_id ) : null;
		$manager              = $project_manager_id ? get_userdata( $project_manager_id ) : null;

		$project_tasks_args = array(
			'post_type'      => 'task',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_project_id',
					'value' => $project_id,
				),
			),
			'orderby'        => 'post_date',
			'order'          => 'DESC',
		);
		if ( ! $is_manager ) {
			$project_tasks_args['meta_query'][] = array(
				'key'   => '_assigned_to',
				'value' => $current_user_id,
			);
			$project_tasks_args['meta_query']['relation'] = 'AND';
		}
		$project_tasks         = get_posts( $project_tasks_args );
		$total_tasks           = count( $project_tasks );
		$completed_tasks       = 0;
		$tasks_list            = array();
		foreach ( $project_tasks as $task_post ) {
			$status_slug = WebinoCRM_Term_Helper::get_first_term_slug( $task_post->ID, 'task_status' );
			if ( 'done' === $status_slug ) {
				++$completed_tasks;
			}
			$tasks_list[] = array(
				'id'          => $task_post->ID,
				'title'       => $task_post->post_title,
				'status_slug' => $status_slug,
				'status_name' => WebinoCRM_Term_Helper::get_first_term_name( $task_post->ID, 'task_status' ),
			);
		}
		$completion_percentage = $total_tasks > 0 ? (int) round( ( $completed_tasks / $total_tasks ) * 100 ) : 0;

		$managers_raw = get_users(
			array(
				'role__in' => array( 'system_manager', 'administrator' ),
				'orderby'  => 'display_name',
			)
		);
		$contracts_raw = get_posts(
			array(
				'post_type'      => 'contract',
				'posts_per_page' => -1,
			)
		);
		$statuses      = get_terms(
			array(
				'taxonomy'   => 'project_status',
				'hide_empty' => false,
			)
		);
		$assigned_members_data = array();
		foreach ( $assigned_members as $uid ) {
			$u = get_userdata( (int) $uid );
			if ( $u ) {
				$assigned_members_data[] = array(
					'id'           => $u->ID,
					'display_name' => $u->display_name,
				);
			}
		}
		$project_tags  = WebinoCRM_Term_Helper::get_term_field_list( $project_id, 'project_tag', 'names' );
		$logo_url      = get_the_post_thumbnail_url( $project_id, 'medium' ) ?: '';

		return WebinoCRM_Service_Base::success(
			array(
				'project'  => array(
					'id'                    => $post->ID,
					'title'                 => $post->post_title,
					'content'               => $post->post_content,
					'contract_id'           => $contract_id,
					'project_manager'       => $project_manager_id,
					'project_status'        => $status_term ? (int) $status_term->term_id : 0,
					'status_name'           => $status_term ? $status_term->name : '---',
					'start_date'            => $start_date,
					'end_date'              => $end_date,
					'priority'              => $priority,
					'delete_nonce'          => $is_manager ? wp_create_nonce( 'webino_delete_project_' . $project_id ) : '',
					'customer_id'           => $customer_id,
					'customer_name'         => $customer ? $customer->display_name : '---',
					'manager_name'          => $manager ? $manager->display_name : '---',
					'assigned_members'      => $assigned_members_data,
					'project_tags'          => $project_tags,
					'logo_url'              => $logo_url,
					'tasks'                 => $tasks_list,
					'total_tasks'           => $total_tasks,
					'completed_tasks'       => $completed_tasks,
					'completion_percentage' => $completion_percentage,
				),
				'managers' => array_map(
					static function ( $u ) {
						return array(
							'id'           => $u->ID,
							'display_name' => $u->display_name,
						);
					},
					$managers_raw
				),
				'contracts' => array_map(
					static function ( $c ) {
						$cust = get_userdata( $c->post_author );
						return array(
							'id'            => $c->ID,
							'title'         => $c->post_title,
							'customer_name' => $cust ? $cust->display_name : '---',
						);
					},
					$contracts_raw
				),
				'statuses'  => array_map(
					static function ( $t ) {
						return array(
							'id'   => $t->term_id,
							'name' => $t->name,
						);
					},
					is_array( $statuses ) ? $statuses : array()
				),
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function create_or_update( array $params ) {
		if ( ! function_exists( 'webinocrm_user_can_manage_projects' ) || ! webinocrm_user_can_manage_projects() ) {
			return self::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_ACCESS_DENIED );
		}

		$project_id          = self::int_param( $params, 'project_id' );
		$contract_id         = self::int_param( $params, 'contract_id' );
		$project_title       = sanitize_text_field( (string) self::param( $params, 'project_title', '' ) );
		$project_content     = wp_kses_post( (string) self::param( $params, 'project_content', '' ) );
		$project_status_id   = self::int_param( $params, 'project_status' );

		if ( '' === $project_title || $contract_id <= 0 ) {
			return self::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_MISSING_PROJECT_DATA );
		}

		$contract = get_post( $contract_id );
		if ( ! $contract || 'contract' !== $contract->post_type ) {
			return self::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_CONTRACT_INVALID );
		}

		$post_data = array(
			'post_title'   => $project_title,
			'post_content' => $project_content,
			'post_author'  => (int) $contract->post_author,
			'post_status'  => 'publish',
			'post_type'    => 'project',
		);

		if ( $project_id > 0 ) {
			$post_data['ID'] = $project_id;
			$result          = wp_update_post( $post_data, true );
			$message         = 'پروژه با موفقیت به‌روزرسانی شد.';
		} else {
			$result  = wp_insert_post( $post_data, true );
			$message = 'پروژه جدید با موفقیت ایجاد شد.';
		}

		if ( is_wp_error( $result ) ) {
			return self::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_PROJECT_SAVE_FAILED );
		}

		$the_project_id = is_int( $result ) ? $result : $project_id;
		update_post_meta( $the_project_id, '_contract_id', $contract_id );
		if ( $project_status_id > 0 ) {
			wp_set_object_terms( $the_project_id, $project_status_id, 'project_status' );
		}

		$project_manager_id = self::int_param( $params, 'project_manager' );
		if ( array_key_exists( 'project_manager', $params ) || isset( $_POST['project_manager'] ) ) {
			if ( $project_manager_id > 0 ) {
				update_post_meta( $the_project_id, '_project_manager', $project_manager_id );
			} else {
				delete_post_meta( $the_project_id, '_project_manager' );
			}
		}

		$start_raw = (string) self::param( $params, 'project_start_date', '' );
		if ( '' !== $start_raw ) {
			$start_gregorian = self::normalize_date( $start_raw );
			if ( $start_gregorian ) {
				update_post_meta( $the_project_id, '_project_start_date', $start_gregorian );
			}
		}

		$end_raw = (string) self::param( $params, 'project_end_date', '' );
		if ( '' !== $end_raw ) {
			$end_gregorian = self::normalize_date( $end_raw );
			if ( $end_gregorian ) {
				update_post_meta( $the_project_id, '_project_end_date', $end_gregorian );
			}
		}

		$priority = sanitize_key( (string) self::param( $params, 'project_priority', '' ) );
		if ( '' !== $priority ) {
			update_post_meta( $the_project_id, '_project_priority', $priority );
		}

		$assigned_team = self::array_param( $params, 'assigned_team_members' );
		if ( null !== $assigned_team ) {
			$assigned_team = array_values( array_filter( array_map( 'intval', $assigned_team ) ) );
			update_post_meta( $the_project_id, '_assigned_team_members', $assigned_team );
		} elseif ( array_key_exists( 'assigned_team_members', $params ) ) {
			delete_post_meta( $the_project_id, '_assigned_team_members' );
		}

		$tags_string = (string) self::param( $params, 'project_tags', '' );
		if ( '' !== $tags_string ) {
			$tags = preg_split( '/[،,]+/', $tags_string );
			$tags = array_values( array_filter( array_map( 'trim', is_array( $tags ) ? $tags : array() ) ) );
			if ( ! taxonomy_exists( 'project_tag' ) ) {
				register_taxonomy(
					'project_tag',
					'project',
					array(
						'label'        => __( 'برچسب‌های پروژه', 'webinocrm' ),
						'hierarchical' => false,
						'public'       => false,
						'show_ui'      => true,
					)
				);
			}
			wp_set_object_terms( $the_project_id, $tags, 'project_tag' );
		} elseif ( array_key_exists( 'project_tags', $params ) ) {
			wp_set_object_terms( $the_project_id, array(), 'project_tag' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_FILES['project_logo'] ) && (int) $_FILES['project_logo']['error'] === 0 ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attachment_id = media_handle_upload( 'project_logo', $the_project_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $the_project_id, $attachment_id );
			}
		}

		if ( class_exists( 'WebinoCRM_Logger' ) ) {
			WebinoCRM_Logger::add(
				'پروژه مدیریت شد',
				array(
					'action'     => $project_id > 0 ? 'به‌روزرسانی' : 'ایجاد',
					'project_id' => $the_project_id,
				),
				'success'
			);
		}

		return WebinoCRM_Service_Base::success(
			array(
				'message'    => $message,
				'project_id' => $the_project_id,
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function delete( array $params ) {
		if ( ! current_user_can( 'delete_posts' ) ) {
			return self::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_ACCESS_DENIED );
		}

		$project_id = self::int_param( $params, 'project_id' );
		if ( $project_id <= 0 ) {
			$project_id = self::int_param( $params, 'id' );
		}
		$nonce = (string) self::param( $params, 'nonce', '' );
		if ( $project_id <= 0 || '' === $nonce ) {
			return self::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_MISSING_PROJECT_DATA );
		}
		if ( ! wp_verify_nonce( $nonce, 'webino_delete_project_' . $project_id ) ) {
			return self::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_NONCE_FAILED );
		}

		$project = get_post( $project_id );
		if ( ! $project || 'project' !== $project->post_type ) {
			return self::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_PROJECT_NOT_FOUND );
		}

		$related_tasks = get_posts(
			array(
				'post_type'      => 'task',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_project_id',
				'meta_value'     => $project_id,
			)
		);
		foreach ( $related_tasks as $task_id ) {
			wp_delete_post( (int) $task_id, true );
		}

		$project_title = $project->post_title;
		if ( wp_delete_post( $project_id, true ) ) {
			if ( class_exists( 'WebinoCRM_Logger' ) ) {
				WebinoCRM_Logger::add(
					'پروژه حذف شد',
					array(
						'content' => "پروژه '{$project_title}' حذف شد.",
						'type'    => 'log',
					)
				);
			}
			return WebinoCRM_Service_Base::success( array( 'message' => 'پروژه با موفقیت حذف شد.' ) );
		}

		return self::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_PROJECT_DELETE_FAILED );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function assignees( array $params ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$search = sanitize_text_field( (string) self::param( $params, 'search', '' ) );
		$args   = array(
			'role__in' => array( 'system_manager', 'team_member', 'administrator' ),
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		);
		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'user_nicename' );
		}
		$users = get_users( $args );
		$items = array_map(
			static function ( $u ) {
				return array(
					'id'           => $u->ID,
					'display_name' => $u->display_name,
				);
			},
			$users
		);

		return WebinoCRM_Service_Base::success( array( 'users' => $items ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function templates( array $params ) {
		unset( $params );
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$templates = get_posts(
			array(
				'post_type'      => 'project_template',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);
		$list        = array();
		foreach ( $templates as $t ) {
			$list[] = array(
				'id'    => $t->ID,
				'title' => $t->post_title,
			);
		}

		return WebinoCRM_Service_Base::success( array( 'templates' => $list ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function product_preview( array $params ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$product_id = self::int_param( $params, 'product_id' );
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return WebinoCRM_Service_Base::success( array( 'titles' => array() ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return WebinoCRM_Service_Base::success( array( 'titles' => array() ) );
		}

		$child_ids = $product->is_type( 'grouped' ) ? $product->get_children() : array( $product->get_id() );
		$titles    = array();
		foreach ( $child_ids as $cid ) {
			$child = wc_get_product( $cid );
			if ( $child ) {
				$titles[] = $child->get_name();
			}
		}

		return WebinoCRM_Service_Base::success( array( 'titles' => $titles ) );
	}

	/**
	 * Register action map entries.
	 *
	 * @return void
	 */
	public static function register_actions() {
		$map = array(
			'webinocrm_get_projects'                 => array( __CLASS__, 'list' ),
			'webinocrm_get_project'                  => array( __CLASS__, 'get' ),
			'webino_manage_project'                  => array( __CLASS__, 'create_or_update' ),
			'webino_delete_project'                  => array( __CLASS__, 'delete' ),
			'webinocrm_get_project_assignees'        => array( __CLASS__, 'assignees' ),
			'webinocrm_get_project_templates'          => array( __CLASS__, 'templates' ),
			'webinocrm_get_product_projects_preview' => array( __CLASS__, 'product_preview' ),
		);
		foreach ( $map as $action => $callable ) {
			WebinoCRM_Service_Action_Map::register( $action, $callable );
		}
	}

	/**
	 * @param string              $code Error code constant.
	 * @return array<string,mixed>
	 */
	private static function coded_error( $code ) {
		$message = class_exists( 'WebinoCRM_Error_Codes' )
			? WebinoCRM_Error_Codes::get_message( $code )
			: __( 'Request failed.', 'webinocrm' );
		return WebinoCRM_Service_Base::error( $message, 400 );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @param string              $key    Key.
	 * @param mixed               $default Default.
	 * @return mixed
	 */
	private static function param( array $params, $key, $default = '' ) {
		if ( array_key_exists( $key, $params ) ) {
			return $params[ $key ];
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $key ] ) ) {
			return wp_unslash( $_POST[ $key ] );
		}
		return $default;
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @param string              $key    Key.
	 * @return int
	 */
	private static function int_param( array $params, $key ) {
		return absint( self::param( $params, $key, 0 ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @param string              $key    Key.
	 * @return array<int,mixed>|null
	 */
	private static function array_param( array $params, $key ) {
		if ( isset( $params[ $key ] ) && is_array( $params[ $key ] ) ) {
			return $params[ $key ];
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) {
			return wp_unslash( $_POST[ $key ] );
		}
		return null;
	}

	/**
	 * @param string $raw Date string (Gregorian Y-m-d or Jalali).
	 * @return string
	 */
	private static function normalize_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( preg_match( '/^\d{4}-\d{1,2}-\d{1,2}$/', $raw ) ) {
			$parts = explode( '-', $raw );
			return sprintf( '%04d-%02d-%02d', (int) $parts[0], (int) $parts[1], (int) $parts[2] );
		}
		if ( function_exists( 'webino_jalali_to_gregorian' ) ) {
			$converted = webino_jalali_to_gregorian( $raw );
			if ( $converted ) {
				return $converted;
			}
		}
		if ( function_exists( 'jalali_to_gregorian' ) ) {
			$date_parts = explode( '/', $raw );
			if ( count( $date_parts ) === 3 ) {
				$g = jalali_to_gregorian( $date_parts[0], $date_parts[1], $date_parts[2] );
				return sprintf( '%04d-%02d-%02d', $g[0], $g[1], $g[2] );
			}
		}
		return $raw;
	}
}
