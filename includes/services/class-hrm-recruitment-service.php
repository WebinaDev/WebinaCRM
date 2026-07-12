<?php
/**
 * HRM recruitment service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Recruitment_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @return array<string,mixed>|null
	 */
	private static function guard_hrm() {
		if ( ! WebinoCRM_Hrm_Service::can_manage_hrm() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		return null;
	}

	public static function job_postings_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_hrm();
		if ( $err ) {
			return $err;
		}

		$table = WebinoCRM_Hrm_Service::table( 'job_postings' );
		$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC" );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_job_posting( $row );
		}
		return WebinoCRM_Service_Base::success( array( 'postings' => $items ) );
	}

	/**
	 * @param object $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_job_posting( $row ) {
		return array(
			'id'             => (int) $row->id,
			'title'          => (string) $row->title,
			'department_id'  => (int) ( $row->department_id ?? 0 ),
			'description'    => (string) ( $row->description ?? '' ),
			'requirements'   => (string) ( $row->requirements ?? '' ),
			'status'         => (string) ( $row->status ?? 'draft' ),
			'posted_at'      => (string) ( $row->posted_at ?? '' ),
		);
	}

	public static function job_postings_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_hrm();
		if ( $err ) {
			return $err;
		}

		$id            = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$title         = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'title', '' ) );
		$department_id = WebinoCRM_Service_Base::int_param( $params, 'department_id' );
		$description   = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'description', '' ) );
		$requirements  = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'requirements', '' ) );
		$status        = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', 'draft' ) );

		if ( '' === $title ) {
			return WebinoCRM_Service_Base::error( __( 'عنوان آگهی الزامی است.', 'webinocrm' ) );
		}
		if ( ! in_array( $status, array( 'draft', 'open', 'closed' ), true ) ) {
			$status = 'draft';
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'job_postings' );
		$data  = array(
			'title'         => $title,
			'department_id' => $department_id,
			'description'   => $description,
			'requirements'  => $requirements,
			'status'        => $status,
			'updated_at'    => $now,
		);
		if ( 'open' === $status ) {
			$data['posted_at'] = $now;
		}

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'آگهی ذخیره شد.', 'webinocrm' ),
				'posting' => $row ? self::format_job_posting( $row ) : null,
			)
		);
	}

	public static function applicants_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_hrm();
		if ( $err ) {
			return $err;
		}

		$job_posting_id = WebinoCRM_Service_Base::int_param( $params, 'job_posting_id' );
		$status         = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', '' ) );
		$table          = WebinoCRM_Hrm_Service::table( 'applicants' );
		$where          = array( '1=1' );
		$values         = array();

		if ( $job_posting_id > 0 ) {
			$where[]  = 'job_posting_id = %d';
			$values[] = $job_posting_id;
		}
		if ( $status ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}

		$sql  = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC';
		$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_results( $sql );

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_applicant( $row );
		}
		return WebinoCRM_Service_Base::success( array( 'applicants' => $items ) );
	}

	/**
	 * @param object $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_applicant( $row ) {
		return array(
			'id'             => (int) $row->id,
			'job_posting_id' => (int) ( $row->job_posting_id ?? 0 ),
			'first_name'     => (string) ( $row->first_name ?? '' ),
			'last_name'      => (string) ( $row->last_name ?? '' ),
			'email'          => (string) ( $row->email ?? '' ),
			'phone'          => (string) ( $row->phone ?? '' ),
			'status'         => (string) ( $row->status ?? 'new' ),
			'resume_url'     => (string) ( $row->resume_url ?? '' ),
			'cover_letter'   => (string) ( $row->cover_letter ?? '' ),
			'source'         => (string) ( $row->source ?? '' ),
			'hired_user_id'  => (int) ( $row->hired_user_id ?? 0 ),
		);
	}

	public static function applicants_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_hrm();
		if ( $err ) {
			return $err;
		}

		$id             = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$job_posting_id = WebinoCRM_Service_Base::int_param( $params, 'job_posting_id' );
		$first_name     = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'first_name', '' ) );
		$last_name      = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'last_name', '' ) );
		$email          = sanitize_email( (string) WebinoCRM_Service_Base::param( $params, 'email', '' ) );
		$phone          = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'phone', '' ) );
		$status         = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', 'new' ) );
		$resume_url     = esc_url_raw( (string) WebinoCRM_Service_Base::param( $params, 'resume_url', '' ) );
		$cover_letter   = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'cover_letter', '' ) );
		$source         = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'source', '' ) );

		if ( $job_posting_id <= 0 || '' === $last_name || ! is_email( $email ) ) {
			return WebinoCRM_Service_Base::error( __( 'آگهی، نام خانوادگی و ایمیل الزامی است.', 'webinocrm' ) );
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'applicants' );
		$data  = array(
			'job_posting_id' => $job_posting_id,
			'first_name'     => $first_name,
			'last_name'      => $last_name,
			'email'          => $email,
			'phone'          => $phone,
			'status'         => $status,
			'resume_url'     => $resume_url,
			'cover_letter'   => $cover_letter,
			'source'         => $source,
			'updated_at'     => $now,
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message'   => __( 'متقاضی ذخیره شد.', 'webinocrm' ),
				'applicant' => $row ? self::format_applicant( $row ) : null,
			)
		);
	}

	public static function applicants_delete( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_hrm();
		if ( $err ) {
			return $err;
		}

		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه نامعتبر است.', 'webinocrm' ) );
		}

		$table = WebinoCRM_Hrm_Service::table( 'applicants' );
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		$wpdb->delete( WebinoCRM_Hrm_Service::table( 'interviews' ), array( 'applicant_id' => $id ), array( '%d' ) );
		$wpdb->delete( WebinoCRM_Hrm_Service::table( 'onboarding_tasks' ), array( 'applicant_id' => $id ), array( '%d' ) );

		return WebinoCRM_Service_Base::success( array( 'message' => __( 'متقاضی حذف شد.', 'webinocrm' ) ) );
	}

	public static function applicant_hire( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_hrm();
		if ( $err ) {
			return $err;
		}

		$id       = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$password = (string) WebinoCRM_Service_Base::param( $params, 'password', wp_generate_password( 12, true ) );

		$table     = WebinoCRM_Hrm_Service::table( 'applicants' );
		$applicant = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		if ( ! $applicant ) {
			return WebinoCRM_Service_Base::error( __( 'متقاضی یافت نشد.', 'webinocrm' ), 404 );
		}
		if ( (int) ( $applicant->hired_user_id ?? 0 ) > 0 ) {
			return WebinoCRM_Service_Base::error( __( 'این متقاضی قبلاً استخدام شده است.', 'webinocrm' ) );
		}

		$email = sanitize_email( (string) $applicant->email );
		if ( ! is_email( $email ) ) {
			return WebinoCRM_Service_Base::error( __( 'ایمیل متقاضی نامعتبر است.', 'webinocrm' ) );
		}

		$user_id = email_exists( $email );
		if ( $user_id ) {
			$user = new WP_User( (int) $user_id );
			$user->set_role( 'team_member' );
		} else {
			$user_id = wp_insert_user(
				array(
					'user_login'   => $email,
					'user_email'   => $email,
					'user_pass'    => $password,
					'first_name'   => (string) $applicant->first_name,
					'last_name'    => (string) $applicant->last_name,
					'display_name' => trim( (string) $applicant->first_name . ' ' . (string) $applicant->last_name ),
					'role'         => 'team_member',
				)
			);
			if ( is_wp_error( $user_id ) ) {
				return WebinoCRM_Service_Base::error( $user_id->get_error_message() );
			}
		}

		$user_id = (int) $user_id;
		if ( ! empty( $applicant->phone ) ) {
			update_user_meta( $user_id, 'webino_mobile_phone', sanitize_text_field( (string) $applicant->phone ) );
		}
		update_user_meta( $user_id, 'webino_hire_date', current_time( 'Y-m-d' ) );
		update_user_meta( $user_id, 'webino_job_status', 'active' );
		update_user_meta( $user_id, 'webino_contract_type', 'contractual' );

		$posting = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . WebinoCRM_Hrm_Service::table( 'job_postings' ) . ' WHERE id = %d',
				(int) $applicant->job_posting_id
			)
		);
		if ( $posting && (int) $posting->department_id > 0 && taxonomy_exists( 'organizational_position' ) ) {
			wp_set_object_terms( $user_id, (int) $posting->department_id, 'organizational_position', false );
		}

		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-profile-fields.php';
		WebinoCRM_Hrm_Profile_Fields::save_profile(
			$user_id,
			array(
				'sections' => array(
					'contact_info' => array(
						'mobile_phone' => (string) ( $applicant->phone ?? '' ),
					),
					'job_info'     => array(
						'hire_date'     => current_time( 'Y-m-d' ),
						'job_status'    => 'active',
						'contract_type' => 'contractual',
					),
				),
			)
		);

		$now = current_time( 'mysql' );
		$wpdb->update(
			$table,
			array(
				'status'        => 'hired',
				'hired_user_id' => $user_id,
				'updated_at'    => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		$wpdb->update(
			WebinoCRM_Hrm_Service::table( 'onboarding_tasks' ),
			array( 'user_id' => $user_id, 'updated_at' => $now ),
			array( 'applicant_id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return WebinoCRM_Service_Base::success(
			array(
				'message'  => __( 'متقاضی با موفقیت استخدام شد.', 'webinocrm' ),
				'user_id'  => $user_id,
				'profile'  => WebinoCRM_Hrm_Profile_Fields::read_profile( $user_id ),
			)
		);
	}

	public static function interviews_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_hrm();
		if ( $err ) {
			return $err;
		}

		$applicant_id = WebinoCRM_Service_Base::int_param( $params, 'applicant_id' );
		$table        = WebinoCRM_Hrm_Service::table( 'interviews' );
		if ( $applicant_id > 0 ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE applicant_id = %d ORDER BY scheduled_at DESC", $applicant_id ) );
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY scheduled_at DESC" );
		}

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_interview( $row );
		}
		return WebinoCRM_Service_Base::success( array( 'interviews' => $items ) );
	}

	/**
	 * @param object $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_interview( $row ) {
		$interviewer = get_userdata( (int) ( $row->interviewer_id ?? 0 ) );
		return array(
			'id'              => (int) $row->id,
			'applicant_id'    => (int) ( $row->applicant_id ?? 0 ),
			'scheduled_at'    => (string) ( $row->scheduled_at ?? '' ),
			'interviewer_id'  => (int) ( $row->interviewer_id ?? 0 ),
			'interviewer_name'=> $interviewer ? $interviewer->display_name : '',
			'location'        => (string) ( $row->location ?? '' ),
			'status'          => (string) ( $row->status ?? 'scheduled' ),
			'score'           => (float) ( $row->score ?? 0 ),
			'notes'           => (string) ( $row->notes ?? '' ),
			'feedback'        => (string) ( $row->feedback ?? '' ),
		);
	}

	public static function interviews_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_hrm();
		if ( $err ) {
			return $err;
		}

		$id             = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$applicant_id   = WebinoCRM_Service_Base::int_param( $params, 'applicant_id' );
		$scheduled_at   = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'scheduled_at', '' ) );
		$interviewer_id = WebinoCRM_Service_Base::int_param( $params, 'interviewer_id' );
		$location       = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'location', '' ) );
		$status         = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', 'scheduled' ) );
		$score          = (float) WebinoCRM_Service_Base::param( $params, 'score', 0 );
		$notes          = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'notes', '' ) );
		$feedback       = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'feedback', '' ) );

		if ( $applicant_id <= 0 || '' === $scheduled_at ) {
			return WebinoCRM_Service_Base::error( __( 'متقاضی و زمان مصاحبه الزامی است.', 'webinocrm' ) );
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'interviews' );
		$data  = array(
			'applicant_id'   => $applicant_id,
			'scheduled_at'   => $scheduled_at,
			'interviewer_id' => $interviewer_id,
			'location'       => $location,
			'status'         => $status,
			'score'          => $score,
			'notes'          => $notes,
			'feedback'       => $feedback,
			'updated_at'     => $now,
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message'   => __( 'مصاحبه ذخیره شد.', 'webinocrm' ),
				'interview' => $row ? self::format_interview( $row ) : null,
			)
		);
	}

	public static function register_actions() {}
}
