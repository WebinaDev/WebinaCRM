<?php
/**
 * Crm Services Module Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Crm_Services_Module_Service {
	use WebinoCRM_Domain_Service_Trait;

	public static function subscriptions( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_request();
		if ( is_array( $access ) ) {
			return $access;
		}
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return WebinoCRM_Service_Base::success( [
				'subscriptions' => [],
				'message'       => __( 'ماژول اشتراک‌ها در دسترس نیست.', 'webinocrm' ),
				'wc_subscriptions_active' => false,
			] );
		}
		$subscriptions_raw = wcs_get_subscriptions( [
			'subscriptions_per_page' => -1,
			'subscription_status'    => 'any',
		] );
		$items = [];
		foreach ( $subscriptions_raw as $subscription ) {
			$customer      = $subscription->get_user();
			$customer_name = $customer ? $customer->display_name : __( 'مهمان', 'webinocrm' );
			$customer_id   = $customer ? $customer->ID : 0;
			$status_name   = function_exists( 'wcs_get_subscription_status_name' )
				? wcs_get_subscription_status_name( $subscription->get_status() )
				: $subscription->get_status();
			$already_converted = (bool) get_post_meta( $subscription->get_id(), '_automation_triggered', true );
			$items[] = [
				'id'               => $subscription->get_id(),
				'customer_id'      => $customer_id,
				'customer_name'    => $customer_name,
				'status'           => $subscription->get_status(),
				'status_name'      => $status_name,
				'total_formatted'  => $subscription->get_formatted_order_total(),
				'start_date'       => $subscription->get_date( 'start_date', 'site' ) ?: '',
				'next_payment'     => $subscription->get_date( 'next_payment', 'site' ) ?: '',
				'already_converted' => $already_converted,
			];
		}
		return WebinoCRM_Service_Base::success( [
			'subscriptions'          => $items,
			'wc_subscriptions_active' => true,
		] );
	}

	public static function products( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_request();
		if ( is_array( $access ) ) {
			return $access;
		}
		if ( ! function_exists( 'wc_get_products' ) ) {
			return WebinoCRM_Service_Base::success( [
				'products' => [],
				'message'  => __( 'ماژول فروشگاه در دسترس نیست.', 'webinocrm' ),
				'wc_active' => false,
			] );
		}
		$types = [ 'simple', 'variable', 'grouped' ];
		if ( function_exists( 'wcs_get_subscriptions' ) ) {
			$types[] = 'subscription';
			$types[] = 'subscription_variation';
		}
		$products_raw = wc_get_products( [
			'limit'  => -1,
			'status' => 'publish',
			'type'   => $types,
		] );
		$task_templates = self::get_task_templates_list();
		$items = [];
		foreach ( $products_raw as $product ) {
			$product_id      = $product->get_id();
			$task_template_id = (int) get_post_meta( $product_id, '_task_template_id', true );
			$service_type    = get_post_meta( $product_id, '_service_task_type', true ) ?: 'onetime';
			$items[] = [
				'id'               => $product_id,
				'name'             => $product->get_name(),
				'type'             => $product->get_type(),
				'price'            => $product->get_price(),
				'task_template_id' => $task_template_id ?: null,
				'service_task_type' => $service_type,
				'task_template_title' => $task_template_id ? ( self::get_template_title( $task_template_id ) ?: '' ) : '',
			];
		}
		return WebinoCRM_Service_Base::success( [
			'products'  => $items,
			'task_templates' => $task_templates,
			'wc_active' => true,
		] );
	}

	public static function convert_subscription( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_request();
		if ( is_array( $access ) ) {
			return $access;
		}
		$subscription_id = isset( $_POST['subscription_id'] ) ? intval( $_POST['subscription_id'] ) : 0;
		if ( ! $subscription_id ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه اشتراک نامعتبر است.', 'webinocrm' ) );
		}
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول اشتراک‌ها در دسترس نیست.', 'webinocrm' ) );
		}
		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return WebinoCRM_Service_Base::error( __( 'اشتراک یافت نشد.', 'webinocrm' ) );
		}
		// Prevent duplicate conversion
		if ( get_post_meta( $subscription_id, '_automation_triggered', true ) ) {
			$existing_contract = get_post_meta( $subscription_id, '_contract_id', true );
			return WebinoCRM_Service_Base::error( __( 'این اشتراک قبلاً به قرارداد تبدیل شده است.', 'webinocrm' ) );
		}
		$customer_id = $subscription->get_customer_id();
		if ( ! $customer_id ) {
			return WebinoCRM_Service_Base::error( __( 'اشتراک فاقد مشتری است.', 'webinocrm' ) );
		}
		$customer = get_userdata( $customer_id );
		$contract_title = sprintf(
			/* translators: 1: subscription ID, 2: customer name */
			__( 'قرارداد اشتراک #%1$d - %2$s', 'webinocrm' ),
			$subscription_id,
			$customer ? $customer->display_name : ''
		);
		$contract_id = wp_insert_post( [
			'post_title'  => $contract_title,
			'post_author' => $customer_id,
			'post_status' => 'publish',
			'post_type'   => 'contract',
		] );
		if ( is_wp_error( $contract_id ) ) {
			return WebinoCRM_Service_Base::error( __( 'خطا در ایجاد قرارداد.', 'webinocrm' ) );
		}
		update_post_meta( $contract_id, '_project_subscription_model', 'subscription' );
		update_post_meta( $contract_id, '_project_start_date', $subscription->get_date( 'start_date', 'site' ) ?: '' );
		update_post_meta( $contract_id, '_project_end_date', $subscription->get_date( 'end', 'site' ) ?: '' );
		$installments = [];
		$total        = $subscription->get_total();
		$schedule     = $subscription->get_dates();
		if ( ! empty( $schedule['next_payment'] ) ) {
			$installments[] = [
				'amount'   => $total,
				'due_date' => $schedule['next_payment'],
				'status'   => 'pending',
			];
		}
		update_post_meta( $contract_id, '_installments', $installments );
		update_post_meta( $contract_id, '_total_amount', (string) (int) round( (float) $total ) );
		update_post_meta( $contract_id, '_total_installments', count( $installments ) );
		update_post_meta( $subscription_id, '_automation_triggered', true );
		update_post_meta( $subscription_id, '_contract_id', $contract_id );
		// Create projects for subscription items (same logic as Automation Handler)
		foreach ( $subscription->get_items() as $item ) {
			$product_id = $item->get_product_id();
			if ( ! $product_id ) {
				continue;
			}
			$product  = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			$project_id = wp_insert_post( [
				'post_title'  => $product->get_name(),
				'post_author' => $customer_id,
				'post_status' => 'publish',
				'post_type'   => 'project',
			] );
			if ( ! is_wp_error( $project_id ) ) {
				update_post_meta( $project_id, '_contract_id', $contract_id );
				$active_status = get_term_by( 'slug', 'active', 'project_status' );
				if ( $active_status ) {
					wp_set_object_terms( $project_id, $active_status->term_id, 'project_status' );
				}
				// Create tasks from task template if linked; else fallback to project template
				$task_template_id = get_post_meta( $product_id, '_task_template_id', true );
				if ( $task_template_id ) {
					self::create_tasks_from_task_template( $project_id, (int) $task_template_id );
				} else {
					$project_template_id = get_post_meta( $product_id, '_project_template_id', true );
					if ( $project_template_id ) {
						self::create_tasks_from_project_template( $project_id, (int) $project_template_id );
					}
				}
			}
		}
		if ( class_exists( 'WebinoCRM_Logger' ) ) {
			WebinoCRM_Logger::add( 'اشتراک به قرارداد تبدیل شد', [
				'subscription_id' => $subscription_id,
				'contract_id'     => $contract_id,
			], 'log' );
		}
		return WebinoCRM_Service_Base::success( [
			'message'     => __( 'اشتراک با موفقیت به قرارداد تبدیل شد.', 'webinocrm' ),
			'contract_id' => $contract_id,
		] );
	}

	public static function product_task_template( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_request();
		if ( is_array( $access ) ) {
			return $access;
		}
		$product_id        = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$task_template_id  = isset( $_POST['task_template_id'] ) ? intval( $_POST['task_template_id'] ) : 0;
		$service_task_type = isset( $_POST['service_task_type'] ) ? sanitize_key( $_POST['service_task_type'] ) : 'onetime';
		if ( ! $product_id ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه محصول نامعتبر است.', 'webinocrm' ) );
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول فروشگاه در دسترس نیست.', 'webinocrm' ) );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return WebinoCRM_Service_Base::error( __( 'محصول یافت نشد.', 'webinocrm' ) );
		}
		$valid_types = [ 'onetime', 'daily', 'weekly', 'monthly' ];
		if ( ! in_array( $service_task_type, $valid_types, true ) ) {
			$service_task_type = 'onetime';
		}
		if ( $task_template_id > 0 ) {
			update_post_meta( $product_id, '_task_template_id', $task_template_id );
		} else {
			delete_post_meta( $product_id, '_task_template_id' );
		}
		update_post_meta( $product_id, '_service_task_type', $service_task_type );
		return WebinoCRM_Service_Base::success( [
			'message' => __( 'قالب تسک با موفقیت به محصول متصل شد.', 'webinocrm' ),
		] );
	}

	public static function task_templates( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_request();
		if ( is_array( $access ) ) {
			return $access;
		}
		return WebinoCRM_Service_Base::success( [
			'task_templates' => self::get_task_templates_list(),
		] );
	}


	private static function create_tasks_from_project_template( $project_id, $template_id ) {
$template = get_post( $template_id );
		if ( ! $template || $template->post_type !== 'project_template' ) {
		}
		$default_tasks = get_post_meta( $template_id, '_default_tasks', true );
		if ( empty( $default_tasks ) || ! is_array( $default_tasks ) ) {
		}
		$todo_term = get_term_by( 'slug', 'to-do', 'task_status' );
		if ( ! $todo_term ) {
			$todo_term = get_term_by( 'slug', 'todo', 'task_status' );
		}
		$todo_term_id = $todo_term ? $todo_term->term_id : 0;
		foreach ( $default_tasks as $task_data ) {
			$title = isset( $task_data['title'] ) ? sanitize_text_field( $task_data['title'] ) : '';
			if ( empty( $title ) ) {
				continue;
			}
			$content = isset( $task_data['content'] ) ? wp_kses_post( $task_data['content'] ) : '';
			$task_id = wp_insert_post( [
				'post_title'   => $title,
				'post_content' => $content,
				'post_type'    => 'task',
				'post_status'  => 'publish',
			], true );
			if ( $task_id && ! is_wp_error( $task_id ) ) {
				update_post_meta( $task_id, '_project_id', $project_id );
				if ( $todo_term_id ) {
					wp_set_object_terms( $task_id, $todo_term_id, 'task_status' );
				}
			}
		}
	}

	private static function create_tasks_from_task_template( $project_id, $template_id ) {
$tasks = get_post_meta( $template_id, '_template_tasks', true );
		if ( ! is_array( $tasks ) || empty( $tasks ) ) {
		}
		$start = strtotime( date( 'Y-m-d' ) );
		$todo_term = get_term_by( 'slug', 'to-do', 'task_status' );
		if ( ! $todo_term ) {
			$todo_term = get_term_by( 'slug', 'todo', 'task_status' );
		}
		$todo_term_id = $todo_term ? $todo_term->term_id : 0;
		foreach ( $tasks as $task_data ) {
			$title   = $task_data['title'] ?? '';
			$duration = isset( $task_data['duration'] ) ? floatval( $task_data['duration'] ) : 1;
			if ( empty( $title ) ) {
				continue;
			}
			$due_date = date( 'Y-m-d', strtotime( "+{$duration} days", $start ) );
			$task_id = wp_insert_post( [
				'post_title'   => $title,
				'post_type'    => 'task',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
			] );
			if ( $task_id && ! is_wp_error( $task_id ) ) {
				update_post_meta( $task_id, '_project_id', $project_id );
				update_post_meta( $task_id, '_start_date', date( 'Y-m-d', $start ) );
				update_post_meta( $task_id, '_due_date', $due_date );
				if ( $todo_term_id ) {
					wp_set_object_terms( $task_id, $todo_term_id, 'task_status' );
				}
				$start = strtotime( $due_date );
			}
		}
	}

	private static function get_task_templates_list() {
$templates = get_posts( [
			'post_type'      => 'task_template',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		] );
		$list = [];
		foreach ( $templates as $t ) {
			$is_recurring   = (bool) get_post_meta( $t->ID, '_is_recurring', true );
			$recurring_type = get_post_meta( $t->ID, '_recurring_type', true ) ?: '';
			$list[] = [
				'id'            => $t->ID,
				'title'         => $t->post_title,
				'is_recurring'  => $is_recurring,
				'recurring_type' => $recurring_type,
			];
		}
		return $list;
	}

	private static function get_template_title( $template_id ) {
$post = get_post( $template_id );
		return $post && $post->post_type === 'task_template' ? $post->post_title : '';
	}

	private static function verify_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		return true;
	}

	public static function register_actions() {
		self::register_map(
			array(

				'webinocrm_list_subscriptions' => array( __CLASS__, 'subscriptions' ),
				'webinocrm_list_products' => array( __CLASS__, 'products' ),
				'webinocrm_convert_subscription_to_contract' => array( __CLASS__, 'convert_subscription' ),
				'webinocrm_update_product_task_template' => array( __CLASS__, 'product_task_template' ),
				'webinocrm_get_task_templates' => array( __CLASS__, 'task_templates' ),

			)
		);
	}
}
