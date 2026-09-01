<?php
/**
 * Invoices Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Invoices_Service {
	use WebinoCRM_Domain_Service_Trait;

		public static function list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز.'] );
        }
        $paged = max( 1, (int) WebinoCRM_Service_Base::param( $params, 'paged', 1 ) );
        $query = new WP_Query([
            'post_type' => 'pro_invoice',
            'posts_per_page' => 20,
            'paged' => $paged,
        ]);
        $items = [];
        foreach ($query->posts as $post) {
            $inv_id = $post->ID;
            $customer = get_userdata((int) $post->post_author);
            $project_id = get_post_meta($inv_id, '_project_id', true);
            $items[] = [
                'id' => $inv_id,
                'invoice_number' => get_post_meta($inv_id, '_pro_invoice_number', true),
                'customer_id' => (int) $post->post_author,
                'customer_name' => $customer && $customer->exists() ? $customer->display_name : '---',
                'project_id' => (int) $project_id,
                'project_title' => $project_id ? get_the_title($project_id) : '---',
                'final_total' => (float) get_post_meta($inv_id, '_final_total', true),
                'issue_date' => get_post_meta($inv_id, '_issue_date', true),
                'date_display' => class_exists( 'WebinoCRM_Date_Formatter' )
                    ? WebinoCRM_Date_Formatter::format_date( get_the_date( 'Y-m-d', $post ) )
                    : get_the_date( 'Y/m/d', $post ),
            ];
        }
        $customers = get_users(['role__in' => ['customer', 'subscriber'], 'orderby' => 'display_name']);
        $customers_list = array_map(function ($c) {
            return ['id' => $c->ID, 'display_name' => $c->display_name];
        }, $customers);
        return WebinoCRM_Service_Base::success( [
            'invoices' => $items,
            'customers' => $customers_list,
            'total_pages' => $query->max_num_pages,
            'current_page' => $paged,
        ] );
	}

		public static function get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( ['message' => 'دسترسی غیرمجاز.'] );
        }
        $invoice_id = WebinoCRM_Service_Base::int_param( $params, 'invoice_id' );
        if ( ! $invoice_id && ! empty( $params['id'] ) ) {
            $invoice_id = absint( $params['id'] );
        }
        $post = get_post( $invoice_id );
        if (!$post || $post->post_type !== 'pro_invoice') {
            return WebinoCRM_Service_Base::error( ['message' => 'پیش‌فاکتور یافت نشد.'] );
        }
        $issue_date = get_post_meta($invoice_id, '_issue_date', true);
        $issue_jalali = $issue_date && function_exists('webino_gregorian_to_jalali')
            ? webino_gregorian_to_jalali($issue_date) : $issue_date;
        $items = get_post_meta($invoice_id, '_invoice_items', true);
        if (!is_array($items)) $items = [];
        $customers = get_users(['role__in' => ['customer', 'subscriber'], 'orderby' => 'display_name']);
        $customers_list = array_map(function ($c) {
            return ['id' => $c->ID, 'display_name' => $c->display_name];
        }, $customers);
        $projects = get_posts(['post_type' => 'project', 'author' => $post->post_author, 'posts_per_page' => -1]);
        $projects_list = array_map(function ($p) {
            return ['id' => $p->ID, 'title' => $p->post_title];
        }, $projects);
        return WebinoCRM_Service_Base::success( [
            'invoice' => [
                'id' => $post->ID,
                'customer_id' => (int) $post->post_author,
                'project_id' => (int) get_post_meta($invoice_id, '_project_id', true),
                'invoice_number' => get_post_meta($invoice_id, '_pro_invoice_number', true),
                'issue_date' => $issue_date,
                'issue_date_jalali' => $issue_jalali,
                'items' => $items,
                'payment_method' => get_post_meta($invoice_id, '_payment_method', true),
                'notes' => $post->post_content,
            ],
            'customers' => $customers_list,
            'projects' => $projects_list,
        ] );
	}

	public static function customer_projects( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['customer_id'] ) ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
        $customer_id = intval($_POST['customer_id']);
        $projects_posts = get_posts([
            'post_type'      => 'project',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_customer_id',
                    'value'   => $customer_id,
                    'compare' => '=',
                ],
                [
                    'key'     => '_contract_customer_id',
                    'value'   => $customer_id,
                    'compare' => '=',
                ],
            ],
        ]);
        if ( empty( $projects_posts ) ) {
            $projects_posts = get_posts([
                'post_type'      => 'project',
                'author'         => $customer_id,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
            ]);
        }
        $projects_data = [];
        if ($projects_posts) {
            foreach ($projects_posts as $project) {
                $projects_data[] = ['id' => $project->ID, 'title' => $project->post_title];
            }
        }
		return WebinoCRM_Service_Base::success( $projects_data );
	}

		public static function save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
        if (!current_user_can('manage_options')) {
            return WebinoCRM_Service_Base::error( ['message' => 'شما دسترسی لازم را ندارید.'] );
        }
        $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
        $customer_id = intval($_POST['customer_id']);
        $project_id = intval($_POST['project_id']);
        $issue_date_jalali = sanitize_text_field($_POST['issue_date']);
        $payment_method = sanitize_textarea_field($_POST['payment_method']);
        $notes = wp_kses_post($_POST['notes']);
        if (empty($customer_id) || empty($project_id) || empty($issue_date_jalali)) {
            return WebinoCRM_Service_Base::error( ['message' => 'انتخاب مشتری، پروژه و تاریخ صدور الزامی است.'] );
        }
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', trim($issue_date_jalali))) {
            $issue_date_gregorian = trim($issue_date_jalali);
        } else {
            $issue_date_gregorian = webino_jalali_to_gregorian($issue_date_jalali);
        }
        $invoice_number = '';
        if ( $invoice_id > 0 ) {
            $existing_number = get_post_meta( $invoice_id, '_pro_invoice_number', true );
            $invoice_number  = is_string( $existing_number ) && $existing_number !== ''
                ? $existing_number
                : 'puz-' . jdate( 'ymd', strtotime( $issue_date_gregorian ), '', 'en' ) . '-' . $project_id;
        } else {
            $invoice_number = 'puz-' . jdate( 'ymd', strtotime( $issue_date_gregorian ), '', 'en' ) . '-' . $project_id;
        }
        $items = [];
        $subtotal = 0;
        $total_discount = 0;
        if (isset($_POST['item_title']) && is_array($_POST['item_title'])) {
            for ($i = 0; $i < count($_POST['item_title']); $i++) {
                if (!empty($_POST['item_title'][$i])) {
                    $price = (float) str_replace(',', '', $_POST['item_price'][$i]);
                    $discount = (float) str_replace(',', '', $_POST['item_discount'][$i]);
                    $items[] = [
                        'title' => sanitize_text_field($_POST['item_title'][$i]),
                        'desc' => sanitize_text_field($_POST['item_desc'][$i]),
                        'price' => $price,
                        'discount' => $discount,
                    ];
                    $subtotal += $price;
                    $total_discount += $discount;
                }
            }
        }
        $final_total = $subtotal - $total_discount;
        $post_data = [
            'post_title'    => 'پیش‌فاکتور ' . $invoice_number,
            'post_content'  => $notes,
            'post_author'   => $customer_id,
            'post_status'   => 'publish',
            'post_type'     => 'pro_invoice',
        ];
        if ($invoice_id > 0) {
            $post_data['ID'] = $invoice_id;
            $result = wp_update_post($post_data, true);
            $message = 'پیش‌فاکتور با موفقیت به‌روزرسانی شد.';
        } else {
            $result = wp_insert_post($post_data, true);
            $message = 'پیش‌فاکتور با موفقیت ایجاد شد.';
        }
        if (is_wp_error($result)) {
            return WebinoCRM_Service_Base::error( ['message' => 'خطا در ذخیره پیش‌فاکتور.'] );
        }
        $the_invoice_id = is_int($result) ? $result : $invoice_id;
        update_post_meta($the_invoice_id, '_pro_invoice_number', $invoice_number);
        update_post_meta($the_invoice_id, '_project_id', $project_id);
        update_post_meta($the_invoice_id, '_issue_date', $issue_date_gregorian);
        update_post_meta($the_invoice_id, '_invoice_items', $items);
        update_post_meta($the_invoice_id, '_subtotal', $subtotal);
        update_post_meta($the_invoice_id, '_total_discount', $total_discount);
        update_post_meta($the_invoice_id, '_final_total', $final_total);
        update_post_meta($the_invoice_id, '_payment_method', $payment_method);
        return WebinoCRM_Service_Base::success( ['message' => $message, 'reload' => true] );
	}

	public static function generate_pdf( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! current_user_can( 'edit_posts' ) ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		$invoice_id = WebinoCRM_Service_Base::int_param( $params, 'invoice_id' );
		if ( ! $invoice_id && ! empty( $params['id'] ) ) {
			$invoice_id = absint( $params['id'] );
		}
		if ( ! $invoice_id ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'شناسه پیش‌فاکتور نامعتبر است.' ) );
		}
		$post = get_post( $invoice_id );
		if ( ! $post || 'pro_invoice' !== $post->post_type ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'پیش‌فاکتور یافت نشد.' ) );
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-pdf-generator.php';
		$pdf    = new WebinoCRM_PDF_Generator();
		$result = $pdf->generate_invoice_pdf( $invoice_id );
		if ( $result ) {
			return WebinoCRM_Service_Base::success(
				array(
					'message'  => 'فایل PDF با موفقیت ایجاد شد.',
					'pdf_url'  => $result['url'],
					'filename' => $result['filename'],
				)
			);
		}
		return WebinoCRM_Service_Base::error( array( 'message' => 'خطا در ایجاد فایل PDF.' ) );
	}

	public static function send_email( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		if ( ! current_user_can( 'edit_posts' ) ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		$invoice_id = WebinoCRM_Service_Base::int_param( $params, 'invoice_id' );
		if ( ! $invoice_id && ! empty( $params['id'] ) ) {
			$invoice_id = absint( $params['id'] );
		}
		if ( ! $invoice_id ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'شناسه پیش‌فاکتور نامعتبر است.' ) );
		}
		$email = '';
		if ( ! empty( $params['email'] ) ) {
			$email = sanitize_email( (string) $params['email'] );
		} elseif ( isset( $_POST['email'] ) ) {
			$email = sanitize_email( wp_unslash( (string) $_POST['email'] ) );
		}
		if ( $email && ! is_email( $email ) ) {
			return WebinoCRM_Service_Base::error( array( 'message' => 'ایمیل نامعتبر است.' ) );
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-email-handler.php';
		$sent = WebinoCRM_Email_Handler::send_invoice_email( $invoice_id, $email ? $email : null );
		if ( $sent ) {
			return WebinoCRM_Service_Base::success( array( 'message' => 'پیش‌فاکتور با موفقیت ارسال شد.' ) );
		}
		return WebinoCRM_Service_Base::error( array( 'message' => 'خطا در ارسال ایمیل. لطفاً تنظیمات ایمیل سرور را بررسی کنید.' ) );
	}

	public static function register_actions() {
		self::register_map(
			array(

				'webinocrm_get_invoices' => array( __CLASS__, 'list' ),
				'webinocrm_get_invoice' => array( __CLASS__, 'get' ),
				'webino_get_projects_for_customer' => array( __CLASS__, 'customer_projects' ),
				'webino_manage_pro_invoice' => array( __CLASS__, 'save' ),

			)
		);
	}
}
