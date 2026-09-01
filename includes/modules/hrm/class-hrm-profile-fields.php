<?php
/**
 * HR profile field definitions (mirrors WebinoCRM_User_Profile).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Profile_Fields {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function sections() {
		return array(
			'identity_info'        => array(
				'title'  => __( 'اطلاعات هویتی', 'webinocrm' ),
				'fields' => array(
					'father_name'     => array( 'label' => __( 'نام پدر', 'webinocrm' ), 'type' => 'text' ),
					'birth_date'      => array( 'label' => __( 'تاریخ تولد', 'webinocrm' ), 'type' => 'date' ),
					'national_id'     => array( 'label' => __( 'کد ملی', 'webinocrm' ), 'type' => 'text' ),
					'id_number'       => array( 'label' => __( 'شماره شناسنامه', 'webinocrm' ), 'type' => 'text' ),
					'id_issue_place'  => array( 'label' => __( 'محل صدور', 'webinocrm' ), 'type' => 'text' ),
					'id_card_attachment_id' => array( 'label' => __( 'تصویر کارت ملی', 'webinocrm' ), 'type' => 'number' ),
					'birth_cert_attachment_id' => array( 'label' => __( 'تصویر شناسنامه', 'webinocrm' ), 'type' => 'number' ),
					'marital_status'  => array(
						'label'   => __( 'وضعیت تأهل', 'webinocrm' ),
						'type'    => 'select',
						'options' => array( '' => '', 'single' => __( 'مجرد', 'webinocrm' ), 'married' => __( 'متاهل', 'webinocrm' ) ),
					),
					'children_count'  => array( 'label' => __( 'تعداد فرزندان', 'webinocrm' ), 'type' => 'number' ),
				),
			),
			'contact_info'         => array(
				'title'  => __( 'اطلاعات تماس و ارتباطی', 'webinocrm' ),
				'fields' => array(
					'mobile_phone'              => array( 'label' => __( 'شماره موبایل', 'webinocrm' ), 'type' => 'tel' ),
					'landline_phone'            => array( 'label' => __( 'تلفن ثابت', 'webinocrm' ), 'type' => 'tel' ),
					'address'                   => array( 'label' => __( 'آدرس محل سکونت', 'webinocrm' ), 'type' => 'textarea' ),
					'emergency_contact_1_name'  => array( 'label' => __( 'نام مخاطب اضطراری ۱', 'webinocrm' ), 'type' => 'text' ),
					'emergency_contact_1_phone' => array( 'label' => __( 'شماره مخاطب اضطراری ۱', 'webinocrm' ), 'type' => 'tel' ),
					'emergency_contact_2_name'  => array( 'label' => __( 'نام مخاطب اضطراری ۲', 'webinocrm' ), 'type' => 'text' ),
					'emergency_contact_2_phone' => array( 'label' => __( 'شماره مخاطب اضطراری ۲', 'webinocrm' ), 'type' => 'tel' ),
				),
			),
			'job_info'             => array(
				'title'  => __( 'اطلاعات شغلی / سازمانی', 'webinocrm' ),
				'fields' => array(
					'personnel_code'  => array( 'label' => __( 'کد پرسنلی', 'webinocrm' ), 'type' => 'text' ),
					'direct_manager'  => array( 'label' => __( 'مدیر مستقیم', 'webinocrm' ), 'type' => 'text' ),
					'hire_date'       => array( 'label' => __( 'تاریخ استخدام', 'webinocrm' ), 'type' => 'date' ),
					'postal_code'     => array( 'label' => __( 'کد پستی', 'webinocrm' ), 'type' => 'text' ),
					'military_status' => array(
						'label'   => __( 'نظام وظیفه', 'webinocrm' ),
						'type'    => 'select',
						'options' => array(
							''           => '',
							'completed'  => __( 'پایان خدمت', 'webinocrm' ),
							'exempt'     => __( 'معاف', 'webinocrm' ),
							'ongoing'    => __( 'در حال خدمت', 'webinocrm' ),
						),
					),
					'education_degree'=> array( 'label' => __( 'مدرک تحصیلی', 'webinocrm' ), 'type' => 'text' ),
					'job_level'       => array( 'label' => __( 'سطح شغلی', 'webinocrm' ), 'type' => 'text' ),
					'tax_exemption'   => array( 'label' => __( 'معافیت مالیاتی', 'webinocrm' ), 'type' => 'text' ),
					'job_description' => array( 'label' => __( 'شرح شغل', 'webinocrm' ), 'type' => 'textarea' ),
					'contract_type'   => array(
						'label'   => __( 'نوع قرارداد', 'webinocrm' ),
						'type'    => 'select',
						'options' => array(
							''            => '',
							'permanent'   => __( 'رسمی', 'webinocrm' ),
							'contractual' => __( 'پیمانی', 'webinocrm' ),
							'project'     => __( 'پروژه‌ای', 'webinocrm' ),
						),
					),
					'job_status'      => array(
						'label'   => __( 'وضعیت شغلی', 'webinocrm' ),
						'type'    => 'select',
						'options' => array(
							''         => '',
							'active'   => __( 'فعال', 'webinocrm' ),
							'on_leave' => __( 'مرخصی', 'webinocrm' ),
							'mission'  => __( 'ماموریت', 'webinocrm' ),
						),
					),
				),
			),
			'financial_info'       => array(
				'title'  => __( 'اطلاعات مالی و حقوقی', 'webinocrm' ),
				'fields' => array(
					'bank_account_number' => array( 'label' => __( 'شماره حساب', 'webinocrm' ), 'type' => 'text' ),
					'bank_name'           => array( 'label' => __( 'نام بانک', 'webinocrm' ), 'type' => 'text' ),
					'iban'                => array( 'label' => __( 'شماره شبا', 'webinocrm' ), 'type' => 'text' ),
					'salary_details'      => array( 'label' => __( 'حقوق و مزایا', 'webinocrm' ), 'type' => 'textarea' ),
					'deductions'          => array( 'label' => __( 'کسورات', 'webinocrm' ), 'type' => 'textarea' ),
				),
			),
			'insurance_legal_info' => array(
				'title'  => __( 'اطلاعات بیمه و قانونی', 'webinocrm' ),
				'fields' => array(
					'insurance_number'  => array( 'label' => __( 'شماره بیمه', 'webinocrm' ), 'type' => 'text' ),
					'tax_file_number'   => array( 'label' => __( 'شماره پرونده مالیاتی', 'webinocrm' ), 'type' => 'text' ),
					'insurance_history' => array( 'label' => __( 'سوابق بیمه‌ای', 'webinocrm' ), 'type' => 'textarea' ),
				),
			),
			'professional_history' => array(
				'title'  => __( 'سوابق حرفه‌ای و آموزشی', 'webinocrm' ),
				'fields' => array(
					'education'           => array( 'label' => __( 'تحصیلات', 'webinocrm' ), 'type' => 'textarea' ),
					'training_courses'    => array( 'label' => __( 'دوره‌ها', 'webinocrm' ), 'type' => 'textarea' ),
					'skills_certificates' => array( 'label' => __( 'مهارت‌ها و گواهینامه‌ها', 'webinocrm' ), 'type' => 'textarea' ),
					'previous_jobs'       => array( 'label' => __( 'سوابق کاری قبلی', 'webinocrm' ), 'type' => 'textarea' ),
				),
			),
		);
	}

	/**
	 * @param int $user_id User id.
	 * @return array<string, mixed>
	 */
	public static function read_profile( $user_id ) {
		$user_id = (int) $user_id;
		$out     = array(
			'user_id'                      => $user_id,
			'first_name'                   => get_user_meta( $user_id, 'first_name', true ),
			'last_name'                    => get_user_meta( $user_id, 'last_name', true ),
			'email'                        => '',
			'role_slug'                    => '',
			'department_id'                => 0,
			'job_title_id'                 => 0,
			'department_manager_dept_ids'  => array(),
			'avatar_url'                   => get_avatar_url( $user_id, array( 'size' => 96 ) ),
			'sections'                     => array(),
		);
		$user = get_userdata( $user_id );
		if ( $user ) {
			$out['email']     = $user->user_email;
			$out['role_slug'] = ! empty( $user->roles ) ? (string) $user->roles[0] : '';
		}
		if ( taxonomy_exists( 'organizational_position' ) ) {
			$positions = wp_get_object_terms( $user_id, 'organizational_position' );
			if ( ! is_wp_error( $positions ) && ! empty( $positions ) ) {
				$pos = $positions[0];
				if ( (int) $pos->parent === 0 ) {
					$out['department_id'] = (int) $pos->term_id;
				} else {
					$out['job_title_id']  = (int) $pos->term_id;
					$out['department_id'] = (int) $pos->parent;
				}
			}
		}
		$dept_mgr = get_user_meta( $user_id, '_department_manager_dept_ids', true );
		$out['department_manager_dept_ids'] = is_array( $dept_mgr ) ? array_map( 'intval', $dept_mgr ) : array();

		foreach ( self::sections() as $section_key => $section ) {
			$fields_out = array();
			foreach ( $section['fields'] as $field_key => $field ) {
				$meta_key = 'webino_' . $field_key;
				$value    = get_user_meta( $user_id, $meta_key, true );
				if ( in_array( $field_key, array( 'birth_date', 'hire_date' ), true ) && $value && function_exists( 'webino_gregorian_to_jalali' ) ) {
					$value = webino_gregorian_to_jalali( $value );
				}
				$fields_out[ $field_key ] = array(
					'value'   => is_scalar( $value ) ? (string) $value : '',
					'label'   => $field['label'],
					'type'    => $field['type'],
					'options' => isset( $field['options'] ) ? $field['options'] : array(),
				);
			}
			$out['sections'][ $section_key ] = array(
				'title'  => $section['title'],
				'fields' => $fields_out,
			);
		}
		return $out;
	}

	/**
	 * @param int                  $user_id User id.
	 * @param array<string, mixed> $data    Payload.
	 * @return true|WP_Error
	 */
	public static function save_profile( $user_id, array $data ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			return new WP_Error( 'invalid_user', __( 'کاربر یافت نشد.', 'webinocrm' ) );
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'دسترسی غیرمجاز.', 'webinocrm' ), array( 'status' => 403 ) );
		}

		if ( isset( $data['sections'] ) && is_array( $data['sections'] ) ) {
			foreach ( self::sections() as $section_key => $section ) {
				if ( ! isset( $data['sections'][ $section_key ] ) || ! is_array( $data['sections'][ $section_key ] ) ) {
					continue;
				}
				$section_data = $data['sections'][ $section_key ];
				foreach ( $section['fields'] as $field_key => $field ) {
					if ( ! array_key_exists( $field_key, $section_data ) ) {
						continue;
					}
					$value = $section_data[ $field_key ];
					if ( 'textarea' === $field['type'] ) {
						$value = sanitize_textarea_field( (string) $value );
					} else {
						$value = sanitize_text_field( (string) $value );
					}
					if ( in_array( $field_key, array( 'birth_date', 'hire_date' ), true ) && function_exists( 'webino_jalali_to_gregorian' ) ) {
						$value = webino_jalali_to_gregorian( $value );
					}
					update_user_meta( $user_id, 'webino_' . $field_key, $value );
				}
			}
		}

		if ( isset( $data['department_id'] ) || isset( $data['job_title_id'] ) ) {
			$job_title_id  = isset( $data['job_title_id'] ) ? (int) $data['job_title_id'] : 0;
			$department_id = isset( $data['department_id'] ) ? (int) $data['department_id'] : 0;
			$term_to_set   = $job_title_id > 0 ? $job_title_id : ( $department_id > 0 ? $department_id : 0 );
			if ( taxonomy_exists( 'organizational_position' ) ) {
				wp_set_object_terms( $user_id, $term_to_set > 0 ? $term_to_set : array(), 'organizational_position', false );
			}
		}

		if ( isset( $data['department_manager_dept_ids'] ) && is_array( $data['department_manager_dept_ids'] ) ) {
			$ids = array_filter( array_map( 'intval', $data['department_manager_dept_ids'] ) );
			update_user_meta( $user_id, '_department_manager_dept_ids', $ids );
		}

		return true;
	}
}
