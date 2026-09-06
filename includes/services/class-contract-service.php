<?php
/**
 * Contract service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-project-template-helper.php';

/**
 * Contract CRUD and related operations.
 */
class WebinoCRM_Contract_Service {

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! function_exists( 'webinocrm_user_can_read_contracts' ) || ! webinocrm_user_can_read_contracts() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$search                = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 's', '' ) );
		$customer_filter       = WebinoCRM_Service_Base::int_param( $params, 'customer_filter' );
		$payment_status_filter = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'payment_status', '' ) );
		$paged                 = max( 1, WebinoCRM_Service_Base::int_param( $params, 'paged' ) ?: 1 );
		$current_user_id       = get_current_user_id();
		$is_client_reader      = function_exists( 'webinocrm_current_user_has_role' )
			&& webinocrm_current_user_has_role( array( 'client', 'customer' ) )
			&& ( ! function_exists( 'webinocrm_user_can_manage_contracts' ) || ! webinocrm_user_can_manage_contracts() );

		$args = array(
			'post_type'      => 'contract',
			'posts_per_page' => 20,
			'paged'          => $paged,
		);
		if ( '' !== $search ) {
			$args['s']          = $search;
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => '_contract_number',
					'value'   => $search,
					'compare' => 'LIKE',
				),
			);
		}
		if ( $is_client_reader ) {
			$args['author'] = $current_user_id;
		} elseif ( $customer_filter > 0 ) {
			$args['author'] = $customer_filter;
		}

		$query          = new WP_Query( $args );
		$customers      = get_users( array( 'role__in' => array( 'customer', 'subscriber', 'client' ), 'orderby' => 'display_name' ) );
		$customers_list = array();
		foreach ( $customers as $c ) {
			$customers_list[] = array(
				'id'           => $c->ID,
				'display_name' => $c->display_name,
			);
		}

		$items = array();
		foreach ( $query->posts as $post ) {
			$contract_id   = $post->ID;
			$author_id     = (int) $post->post_author;
			$customer      = $author_id ? get_userdata( $author_id ) : null;
			$customer_name = ( $customer && $customer->exists() ) ? $customer->display_name : 'مشتری حذف شده';
			$customer_email = ( $customer && $customer->exists() ) ? $customer->user_email : '';

			$installments = get_post_meta( $contract_id, '_installments', true );
			if ( ! is_array( $installments ) ) {
				$installments = array();
			}

			$total_amount     = get_post_meta( $contract_id, '_total_amount', true );
			$total_amount_int = (int) preg_replace( '/[^\d]/', '', (string) $total_amount );
			$paid_amount      = 0;
			$pending_amount   = 0;
			$paid_count       = 0;
			$pending_count    = 0;
			foreach ( $installments as $inst ) {
				$inst_amount = (int) preg_replace( '/[^\d]/', '', (string) ( $inst['amount'] ?? 0 ) );
				$inst_status = $inst['status'] ?? 'pending';
				if ( 'paid' === $inst_status ) {
					$paid_amount += $inst_amount;
					++$paid_count;
				} elseif ( 'cancelled' !== $inst_status ) {
					$pending_amount += $inst_amount;
					++$pending_count;
				}
			}
			if ( empty( $total_amount_int ) && count( $installments ) > 0 ) {
				$total_amount_int = $paid_amount + $pending_amount;
			}

			$contract_status = get_post_meta( $contract_id, '_contract_status', true );
			$status_class    = 'pending';
			if ( 'cancelled' === $contract_status ) {
				$status_text  = 'لغو شده';
				$status_class = 'cancelled';
			} elseif ( $total_amount_int > 0 && $paid_amount >= $total_amount_int ) {
				$status_text  = 'تکمیل پرداخت';
				$status_class = 'paid';
			} elseif ( $paid_amount > 0 ) {
				$status_text  = 'در حال پرداخت';
				$status_class = 'pending';
			} else {
				$status_text  = 'در انتظار پرداخت';
				$status_class = 'pending';
			}

			if ( 'paid' === $payment_status_filter && 'paid' !== $status_class ) {
				continue;
			}
			if ( 'pending' === $payment_status_filter && ( 'paid' === $status_class || 'cancelled' === $status_class ) ) {
				continue;
			}

			$start_date          = get_post_meta( $contract_id, '_project_start_date', true );
			$start_date_jalali   = $start_date && function_exists( 'webino_gregorian_to_jalali' )
				? webino_gregorian_to_jalali( $start_date ) : '-';
			$payment_percentage  = $total_amount_int > 0 ? (int) round( ( $paid_amount / $total_amount_int ) * 100 ) : 0;
			$contract_number     = get_post_meta( $contract_id, '_contract_number', true ) ?: (string) $contract_id;

			$items[] = array(
				'id'                  => $contract_id,
				'contract_number'     => $contract_number,
				'title'               => $post->post_title,
				'customer_id'         => $author_id,
				'customer_name'       => $customer_name,
				'customer_email'      => $customer_email,
				'total_amount'        => $total_amount_int,
				'paid_amount'         => $paid_amount,
				'pending_amount'      => $pending_amount,
				'installment_count'   => count( $installments ),
				'paid_count'          => $paid_count,
				'pending_count'       => $pending_count,
				'status_class'        => $status_class,
				'status_text'         => $status_text,
				'start_date'          => $start_date,
				'start_date_jalali'   => $start_date_jalali,
				'payment_percentage'  => $payment_percentage,
				'is_cancelled'        => 'cancelled' === $contract_status,
				'delete_nonce'        => wp_create_nonce( 'webino_delete_contract_' . $contract_id ),
			);
		}

		return WebinoCRM_Service_Base::success(
			array(
				'contracts'    => $items,
				'customers'    => $customers_list,
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
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! function_exists( 'webinocrm_user_can_read_contracts' ) || ! webinocrm_user_can_read_contracts() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}

		$contract_id = WebinoCRM_Service_Base::int_param( $params, 'contract_id' );
		if ( $contract_id <= 0 ) {
			$contract_id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		}
		$post = get_post( $contract_id );
		if ( ! $post || 'contract' !== $post->post_type ) {
			return WebinoCRM_Service_Base::error( __( 'قرارداد یافت نشد.', 'webinocrm' ), 404 );
		}
		if ( function_exists( 'webinocrm_current_user_has_role' )
			&& webinocrm_current_user_has_role( array( 'client', 'customer' ) )
			&& ( ! function_exists( 'webinocrm_user_can_manage_contracts' ) || ! webinocrm_user_can_manage_contracts() ) ) {
			if ( (int) $post->post_author !== get_current_user_id() ) {
				return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
			}
		}

		$installments = get_post_meta( $contract_id, '_installments', true );
		if ( ! is_array( $installments ) ) {
			$installments = array();
		}
		$start_date   = get_post_meta( $contract_id, '_project_start_date', true );
		$start_jalali = $start_date && function_exists( 'webino_gregorian_to_jalali' )
			? webino_gregorian_to_jalali( $start_date ) : '';

		$inst_data = array();
		foreach ( $installments as $i ) {
			$due          = $i['due_date'] ?? '';
			$due_jalali   = $due && function_exists( 'webino_gregorian_to_jalali' )
				? webino_gregorian_to_jalali( $due ) : $due;
			$inst_data[]  = array(
				'amount'             => $i['amount'] ?? '',
				'due_date'           => $due_jalali,
				'due_date_gregorian' => $due,
				'status'             => $i['status'] ?? 'pending',
			);
		}

		$related_projects = get_posts(
			array(
				'post_type'      => 'project',
				'posts_per_page' => -1,
				'meta_key'       => '_contract_id',
				'meta_value'     => $contract_id,
			)
		);

		$customers      = get_users( array( 'role__in' => array( 'customer', 'subscriber' ), 'orderby' => 'display_name' ) );
		$customers_list = array();
		foreach ( $customers as $c ) {
			$customers_list[] = array(
				'id'           => $c->ID,
				'display_name' => $c->display_name,
			);
		}

		return WebinoCRM_Service_Base::success(
			array(
				'contract'         => array(
					'id'                   => $post->ID,
					'title'                => $post->post_title,
					'customer_id'          => (int) $post->post_author,
					'contract_number'      => get_post_meta( $contract_id, '_contract_number', true ),
					'start_date'           => $start_date,
					'start_date_jalali'    => $start_jalali,
					'total_amount'         => get_post_meta( $contract_id, '_total_amount', true ),
					'total_installments'   => get_post_meta( $contract_id, '_total_installments', true ) ?: 1,
					'duration'             => get_post_meta( $contract_id, '_project_contract_duration', true ) ?: '1-month',
					'subscription_model'   => get_post_meta( $contract_id, '_project_subscription_model', true ) ?: 'onetime',
					'installments'         => $inst_data,
					'is_cancelled'         => 'cancelled' === get_post_meta( $contract_id, '_contract_status', true ),
					'delete_nonce'         => wp_create_nonce( 'webino_delete_contract_' . $contract_id ),
					'rahn'                 => ( 'rahn_percent' === get_post_meta( $contract_id, '_project_subscription_model', true ) )
						? array(
							'F'         => (float) get_post_meta( $contract_id, '_rahn_fixed', true ),
							'p'         => (float) get_post_meta( $contract_id, '_rahn_percent', true ),
							'p_percent' => (float) get_post_meta( $contract_id, '_rahn_percent', true ) * 100.0,
							'clause'    => (string) get_post_meta( $contract_id, '_rahn_clause', true ),
							's_hat'     => (float) get_post_meta( $contract_id, '_rahn_s_hat', true ),
							'duration'  => (int) get_post_meta( $contract_id, '_rahn_duration', true ),
							'locked_at' => (string) get_post_meta( $contract_id, '_rahn_locked_at', true ),
							'quote_id'  => (int) get_post_meta( $contract_id, '_rahn_quote_id', true ),
						)
						: null,
				),
				'related_projects' => array_map(
					static function ( $p ) {
						return array(
							'id'    => $p->ID,
							'title' => $p->post_title,
						);
					},
					$related_projects
				),
				'customers'        => $customers_list,
				'durations'        => array(
					array( 'value' => '1-month', 'label' => 'یک ماهه' ),
					array( 'value' => '3-months', 'label' => 'سه ماهه' ),
					array( 'value' => '6-months', 'label' => 'شش ماهه' ),
					array( 'value' => '12-months', 'label' => 'یک ساله' ),
				),
				'models'           => array(
					array( 'value' => 'onetime', 'label' => 'یکبار پرداخت' ),
					array( 'value' => 'subscription', 'label' => 'اشتراکی' ),
					array( 'value' => 'rahn_percent', 'label' => 'رهن‌درصد' ),
				),
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function create_or_update( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! function_exists( 'webinocrm_user_can_manage_contracts' ) || ! webinocrm_user_can_manage_contracts() ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_ACCESS_DENIED );
		}

		$contract_id        = WebinoCRM_Service_Base::int_param( $params, 'contract_id' );
		$customer_id        = WebinoCRM_Service_Base::int_param( $params, 'customer_id' );
		$lead_id            = WebinoCRM_Service_Base::int_param( $params, 'lead_id' );
		$start_date_jalali  = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, '_project_start_date', '' ) );
		$contract_title     = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'contract_title', '' ) );
		$total_amount       = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'total_amount', '' ) );
		$total_installments = WebinoCRM_Service_Base::int_param( $params, 'total_installments' ) ?: 1;

		if ( $customer_id <= 0 || '' === $start_date_jalali ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_MISSING_CONTRACT_DATA );
		}

		$customer_data = get_userdata( $customer_id );
		if ( ! $customer_data ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_INVALID_CUSTOMER );
		}

		if ( '' === $contract_title ) {
			$contract_title = 'قرارداد برای ' . ( $customer_data->display_name ?? 'مشتری نامشخص' );
		}

		$start_date_gregorian = WebinoCRM_Service_Base::normalize_date( $start_date_jalali );
		$start_timestamp      = strtotime( $start_date_gregorian );
		if ( '' === $start_date_gregorian || false === $start_timestamp ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_INVALID_START_DATE );
		}

		$post_data = array(
			'post_title'  => $contract_title,
			'post_author' => $customer_id,
			'post_status' => 'publish',
			'post_type'   => 'contract',
		);

		if ( $contract_id > 0 ) {
			$post_data['ID'] = $contract_id;
			$result          = wp_update_post( $post_data, true );
			$message         = 'قرارداد با موفقیت به‌روزرسانی شد.';
		} else {
			$result  = wp_insert_post( $post_data, true );
			$message = 'قرارداد جدید با موفقیت ایجاد شد.';
		}

		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_CONTRACT_SAVE_FAILED );
		}

		$the_contract_id = is_int( $result ) ? $result : $contract_id;

		update_post_meta( $the_contract_id, '_customer_id', $customer_id );
		update_post_meta( $the_contract_id, '_total_amount', preg_replace( '/[^\d]/', '', $total_amount ) );
		update_post_meta( $the_contract_id, '_total_installments', $total_installments );

		$existing_contract_number = get_post_meta( $the_contract_id, '_contract_number', true );
		if ( 0 === $contract_id ) {
			$contract_number = self::generate_contract_number( $start_timestamp, $customer_id, $start_date_gregorian );
			update_post_meta( $the_contract_id, '_contract_number', $contract_number );
		} elseif ( empty( $existing_contract_number ) ) {
			update_post_meta( $the_contract_id, '_contract_number', self::generate_contract_number( $start_timestamp, $customer_id, $start_date_gregorian ) );
		}
		$new_number = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'contract_number', '' ) );
		if ( '' !== $new_number ) {
			update_post_meta( $the_contract_id, '_contract_number', $new_number );
		}

		update_post_meta( $the_contract_id, '_project_start_date', $start_date_gregorian );
		$duration = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, '_project_contract_duration', '1-month' ) );
		update_post_meta( $the_contract_id, '_project_contract_duration', $duration );
		$end_date = gmdate( 'Y-m-d', strtotime( $start_date_gregorian . ' +' . str_replace( '-', ' ', $duration ) ) );
		update_post_meta( $the_contract_id, '_project_end_date', $end_date );
		update_post_meta( $the_contract_id, '_project_subscription_model', sanitize_key( (string) WebinoCRM_Service_Base::param( $params, '_project_subscription_model', 'onetime' ) ) );

		$installment_result = self::process_installments( $params, $contract_id );
		if ( ! $installment_result['success'] ) {
			return $installment_result['response'];
		}
		$installments = $installment_result['installments'];
		update_post_meta( $the_contract_id, '_installments', $installments );

		if ( ! empty( $installments ) ) {
			$calculated_total = 0;
			foreach ( $installments as $inst ) {
				$calculated_total += (int) $inst['amount'];
			}
			$stored_total = (int) preg_replace( '/[^\d]/', '', $total_amount );
			if ( $calculated_total > 0 && abs( $calculated_total - $stored_total ) > 100 ) {
				update_post_meta( $the_contract_id, '_total_amount', $calculated_total );
			}
		}

		if ( class_exists( 'WebinoCRM_Logger' ) ) {
			WebinoCRM_Logger::add(
				'قرارداد مدیریت شد',
				array(
					'action'             => $contract_id > 0 ? 'به‌روزرسانی' : 'ایجاد',
					'contract_id'        => $the_contract_id,
					'installments_count' => count( $installments ),
				),
				'success'
			);
		}

		if ( 0 === $contract_id ) {
			self::maybe_create_projects_for_new_contract( $the_contract_id, $params );
		}

		if ( $lead_id > 0 && 0 === $contract_id ) {
			update_post_meta( $lead_id, '_contract_id', $the_contract_id );
		}

		return WebinoCRM_Service_Base::success(
			array(
				'message'         => $message,
				'contract_id'     => $the_contract_id,
				'contract_number' => get_post_meta( $the_contract_id, '_contract_number', true ),
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! function_exists( 'webinocrm_user_can_manage_contracts' ) || ! webinocrm_user_can_manage_contracts() ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_ACCESS_DENIED );
		}

		$contract_id = WebinoCRM_Service_Base::int_param( $params, 'contract_id' );
		if ( $contract_id <= 0 ) {
			$contract_id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		}
		$nonce = (string) WebinoCRM_Service_Base::param( $params, 'nonce', '' );
		if ( $contract_id <= 0 || '' === $nonce ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_MISSING_CONTRACT_DATA );
		}
		if ( ! wp_verify_nonce( $nonce, 'webino_delete_contract_' . $contract_id ) ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_NONCE_FAILED );
		}

		$contract = get_post( $contract_id );
		if ( ! $contract || 'contract' !== $contract->post_type ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_CONTRACT_NOT_FOUND );
		}

		$related_items_query = new WP_Query(
			array(
				'post_type'      => array( 'project', 'pro_invoice' ),
				'posts_per_page' => -1,
				'meta_key'       => '_contract_id',
				'meta_value'     => $contract_id,
			)
		);
		if ( $related_items_query->have_posts() ) {
			foreach ( $related_items_query->posts as $related_post ) {
				wp_delete_post( $related_post->ID, true );
			}
		}

		if ( wp_delete_post( $contract_id, true ) ) {
			if ( class_exists( 'WebinoCRM_Logger' ) ) {
				WebinoCRM_Logger::add(
					'قرارداد حذف شد',
					array(
						'content' => "قرارداد '{$contract->post_title}' حذف شد.",
						'type'    => 'log',
					)
				);
			}
			return WebinoCRM_Service_Base::success(
				array(
					'message' => 'قرارداد و تمام داده‌های مرتبط با آن با موفقیت حذف شدند.',
					'reload'  => true,
				)
			);
		}

		return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_CONTRACT_DELETE_FAILED );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function cancel( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_ACCESS_DENIED );
		}

		$contract_id = WebinoCRM_Service_Base::int_param( $params, 'contract_id' );
		if ( $contract_id <= 0 ) {
			$contract_id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		}
		$reason = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'reason', 'دلیلی ذکر نشده است.' ) );

		$post = get_post( $contract_id );
		if ( $contract_id <= 0 || ! $post || 'contract' !== $post->post_type ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_CANCEL_CONTRACT_FAILED );
		}

		update_post_meta( $contract_id, '_contract_status', 'cancelled' );
		update_post_meta( $contract_id, '_cancellation_reason', $reason );
		update_post_meta( $contract_id, '_cancellation_date', current_time( 'mysql' ) );

		if ( class_exists( 'WebinoCRM_Logger' ) ) {
			WebinoCRM_Logger::add(
				'قرارداد لغو شد',
				array(
					'contract_id' => $contract_id,
					'reason'      => $reason,
				),
				'warning'
			);
		}

		return WebinoCRM_Service_Base::success(
			array(
				'message' => 'قرارداد با موفقیت لغو شد.',
				'reload'  => true,
			)
		);
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	public static function add_project( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_ACCESS_DENIED );
		}

		$contract_id   = WebinoCRM_Service_Base::int_param( $params, 'contract_id' );
		if ( $contract_id <= 0 ) {
			$contract_id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		}
		$project_title = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'project_title', '' ) );

		if ( $contract_id <= 0 || '' === $project_title ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_MISSING_PROJECT_DATA );
		}

		$contract = get_post( $contract_id );
		if ( ! $contract || 'contract' !== $contract->post_type ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_CONTRACT_INVALID );
		}

		$project_id = wp_insert_post(
			array(
				'post_title'  => $project_title,
				'post_author' => $contract->post_author,
				'post_status' => 'publish',
				'post_type'   => 'project',
			),
			true
		);

		if ( is_wp_error( $project_id ) ) {
			return WebinoCRM_Service_Base::coded_error( WebinoCRM_Error_Codes::PRJ_ERR_PROJECT_SAVE_FAILED );
		}

		update_post_meta( $project_id, '_contract_id', $contract_id );
		$active_status = get_term_by( 'slug', 'active', 'project_status' );
		if ( $active_status ) {
			wp_set_object_terms( $project_id, $active_status->term_id, 'project_status' );
		}

		if ( class_exists( 'WebinoCRM_Logger' ) ) {
			WebinoCRM_Logger::add(
				'پروژه به قرارداد اضافه شد',
				array(
					'project_id'  => $project_id,
					'contract_id' => $contract_id,
				),
				'success'
			);
		}

		return WebinoCRM_Service_Base::success(
			array(
				'message' => 'پروژه جدید با موفقیت به قرارداد اضافه شد.',
				'reload'  => true,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function register_actions() {
		$map = array(
			'webinocrm_get_contracts'         => array( __CLASS__, 'list' ),
			'webinocrm_get_contract'          => array( __CLASS__, 'get' ),
			'webino_manage_contract'          => array( __CLASS__, 'create_or_update' ),
			'webino_delete_contract'          => array( __CLASS__, 'delete' ),
			'webino_cancel_contract'          => array( __CLASS__, 'cancel' ),
			'webino_add_project_to_contract' => array( __CLASS__, 'add_project' ),
		);
		foreach ( $map as $action => $callable ) {
			WebinoCRM_Service_Action_Map::register( $action, $callable );
		}
	}

	/**
	 * @param int    $start_timestamp   Unix timestamp.
	 * @param int    $customer_id       Customer user ID.
	 * @param string $start_date_gregorian Y-m-d.
	 * @return string
	 */
	private static function generate_contract_number( $start_timestamp, $customer_id, $start_date_gregorian ) {
		if ( function_exists( 'jdate' ) ) {
			return 'puz-' . jdate( 'ymd', $start_timestamp, '', 'en' ) . '-' . $customer_id;
		}
		$date_parts = explode( '-', $start_date_gregorian );
		return 'puz-' . substr( $date_parts[0], 2 ) . $date_parts[1] . $date_parts[2] . '-' . $customer_id;
	}

	/**
	 * @param array<string,mixed> $params      Request params.
	 * @param int                 $contract_id Existing contract ID (0 = new).
	 * @return array{success:bool,installments:array<int,array<string,string>>,response?:array<string,mixed>}
	 */
	private static function process_installments( array $params, $contract_id ) {
		$payment_amounts  = WebinoCRM_Service_Base::array_param( $params, 'payment_amount' );
		$payment_due_dates = WebinoCRM_Service_Base::array_param( $params, 'payment_due_date' );
		$payment_statuses  = WebinoCRM_Service_Base::array_param( $params, 'payment_status' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null === $payment_amounts && isset( $_POST['payment_amount'] ) && is_array( $_POST['payment_amount'] ) ) {
			$payment_amounts = wp_unslash( $_POST['payment_amount'] );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null === $payment_due_dates && isset( $_POST['payment_due_date'] ) && is_array( $_POST['payment_due_date'] ) ) {
			$payment_due_dates = wp_unslash( $_POST['payment_due_date'] );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null === $payment_statuses && isset( $_POST['payment_status'] ) && is_array( $_POST['payment_status'] ) ) {
			$payment_statuses = wp_unslash( $_POST['payment_status'] );
		}

		$installments        = array();
		$installment_errors  = array();

		if ( ! is_array( $payment_amounts ) ) {
			return array(
				'success'      => true,
				'installments' => array(),
			);
		}

		$installment_count = count( $payment_amounts );
		for ( $i = 0; $i < $installment_count; $i++ ) {
			$amount_raw  = isset( $payment_amounts[ $i ] ) ? trim( (string) $payment_amounts[ $i ] ) : '';
			$jalali_date = is_array( $payment_due_dates ) && isset( $payment_due_dates[ $i ] )
				? trim( (string) $payment_due_dates[ $i ] ) : '';
			$status      = is_array( $payment_statuses ) && isset( $payment_statuses[ $i ] )
				? trim( (string) $payment_statuses[ $i ] ) : 'pending';

			if ( '' === $amount_raw && '' === $jalali_date ) {
				continue;
			}
			if ( '' === $amount_raw ) {
				$installment_errors[] = 'قسط شماره ' . ( $i + 1 ) . ': مبلغ وارد نشده است.';
				continue;
			}
			$amount_clean = preg_replace( '/[^\d]/', '', $amount_raw );
			if ( '' === $amount_clean || (int) $amount_clean <= 0 ) {
				$installment_errors[] = 'قسط شماره ' . ( $i + 1 ) . ': مبلغ نامعتبر است.';
				continue;
			}
			if ( '' === $jalali_date ) {
				$installment_errors[] = 'قسط شماره ' . ( $i + 1 ) . ': تاریخ سررسید وارد نشده است.';
				continue;
			}
			$due_date_gregorian = WebinoCRM_Service_Base::normalize_date( $jalali_date );
			if ( '' === $due_date_gregorian || false === strtotime( $due_date_gregorian ) ) {
				$installment_errors[] = 'قسط شماره ' . ( $i + 1 ) . ': تاریخ سررسید نامعتبر است (' . sanitize_text_field( $jalali_date ) . ').';
				continue;
			}
			$valid_statuses = array( 'pending', 'paid', 'cancelled' );
			if ( ! in_array( $status, $valid_statuses, true ) ) {
				$status = 'pending';
			}
			$installments[] = array(
				'amount'   => $amount_clean,
				'due_date' => $due_date_gregorian,
				'status'   => $status,
			);
		}

		if ( ! empty( $installment_errors ) ) {
			$error_message = 'خطا در ثبت اقساط:\n' . implode( '\n', array_slice( $installment_errors, 0, 5 ) );
			if ( count( $installment_errors ) > 5 ) {
				$error_message .= '\nو ' . ( count( $installment_errors ) - 5 ) . ' خطای دیگر...';
			}
			return array(
				'success'      => false,
				'installments' => array(),
				'response'     => WebinoCRM_Service_Base::coded_error(
					WebinoCRM_Error_Codes::PRJ_ERR_MISSING_CONTRACT_DATA,
					array( 'message' => $error_message )
				),
			);
		}

		return array(
			'success'      => true,
			'installments' => $installments,
		);
	}

	/**
	 * @param int                 $the_contract_id Contract ID.
	 * @param array<string,mixed> $params          Params.
	 * @return void
	 */
	private static function maybe_create_projects_for_new_contract( $the_contract_id, array $params ) {
		$product_id = WebinoCRM_Service_Base::int_param( $params, 'product_id' );
		$project_assignments = WebinoCRM_Service_Base::array_param( $params, 'project_assignments' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null === $project_assignments && isset( $_POST['project_assignments'] ) && is_array( $_POST['project_assignments'] ) ) {
			$project_assignments = array_map( 'intval', wp_unslash( $_POST['project_assignments'] ) );
		}
		if ( ! is_array( $project_assignments ) ) {
			$project_assignments = array();
		}

		if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
			update_post_meta( $the_contract_id, '_wc_product_id', $product_id );
			$product       = wc_get_product( $product_id );
			$active_status = get_term_by( 'slug', 'active', 'project_status' );
			if ( $product ) {
				$child_ids = $product->is_type( 'grouped' ) ? $product->get_children() : array( $product->get_id() );
				$idx       = 0;
				foreach ( $child_ids as $child_id ) {
					$child = wc_get_product( $child_id );
					if ( ! $child ) {
						continue;
					}
					$contract_post = get_post( $the_contract_id );
					$project_id    = wp_insert_post(
						array(
							'post_title'  => $child->get_name(),
							'post_author' => $contract_post ? (int) $contract_post->post_author : 0,
							'post_status' => 'publish',
							'post_type'   => 'project',
						),
						true
					);
					if ( ! is_wp_error( $project_id ) ) {
						update_post_meta( $project_id, '_contract_id', $the_contract_id );
						$dept_id = (int) get_post_meta( $child_id, '_default_department_id', true );
						if ( $dept_id > 0 ) {
							update_post_meta( $project_id, '_department_id', $dept_id );
						}
						$assigned = isset( $project_assignments[ $idx ] ) ? (int) $project_assignments[ $idx ] : 0;
						if ( $assigned > 0 ) {
							update_post_meta( $project_id, '_assigned_to', $assigned );
							update_post_meta( $project_id, '_project_manager', $assigned );
						}
						if ( $active_status ) {
							wp_set_object_terms( $project_id, $active_status->term_id, 'project_status' );
						}
						++$idx;
					}
				}
			}
			return;
		}

		$project_template_id = WebinoCRM_Service_Base::int_param( $params, 'project_template_id' );
		if ( $project_template_id > 0 ) {
			$project_id = WebinoCRM_Project_Template_Helper::create_project_from_template_for_contract( $project_template_id, $the_contract_id );
			if ( is_int( $project_id ) ) {
				WebinoCRM_Project_Template_Helper::create_tasks_from_template_for_project( $project_id, $project_template_id );
			}
		}
	}
}
