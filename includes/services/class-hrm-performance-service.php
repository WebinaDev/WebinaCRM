<?php
/**
 * HRM performance (KPI) service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Performance_Service {
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

	/**
	 * @param string $json Raw JSON.
	 * @return array<int,array<string,mixed>>
	 */
	private static function decode_criteria( $json ) {
		$data = json_decode( (string) $json, true );
		return is_array( $data ) ? $data : array();
	}

	public static function kpi_templates_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_hrm();
		if ( $err ) {
			return $err;
		}

		$table = WebinoCRM_Hrm_Service::table( 'kpi_templates' );
		$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY name ASC" );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'          => (int) $row->id,
				'name'        => (string) $row->name,
				'description' => (string) ( $row->description ?? '' ),
				'criteria'    => self::decode_criteria( $row->criteria_json ?? '[]' ),
				'is_active'   => (int) ( $row->is_active ?? 1 ),
			);
		}
		return WebinoCRM_Service_Base::success( array( 'templates' => $items ) );
	}

	public static function kpi_templates_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_hrm();
		if ( $err ) {
			return $err;
		}

		$id          = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$name        = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) );
		$description = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'description', '' ) );
		$criteria    = WebinoCRM_Service_Base::array_param( $params, 'criteria' );
		$is_active   = ! isset( $params['is_active'] ) || ! empty( $params['is_active'] ) ? 1 : 0;

		if ( '' === $name ) {
			return WebinoCRM_Service_Base::error( __( 'نام قالب KPI الزامی است.', 'webinocrm' ) );
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'kpi_templates' );
		$data  = array(
			'name'          => $name,
			'description'   => $description,
			'criteria_json' => wp_json_encode( is_array( $criteria ) ? $criteria : array() ),
			'is_active'     => $is_active,
			'updated_at'    => $now,
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'قالب KPI ذخیره شد.', 'webinocrm' ),
				'id'      => $id,
			)
		);
	}

	public static function cycles_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_hrm();
		if ( $err ) {
			return $err;
		}

		$table = WebinoCRM_Hrm_Service::table( 'review_cycles' );
		$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY year DESC, start_date DESC" );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_cycle( $row );
		}
		return WebinoCRM_Service_Base::success( array( 'cycles' => $items ) );
	}

	/**
	 * @param object $row DB row.
	 * @return array<string,mixed>
	 */
	private static function format_cycle( $row ) {
		return array(
			'id'         => (int) $row->id,
			'name'       => (string) $row->name,
			'year'       => (int) ( $row->year ?? 0 ),
			'start_date' => (string) ( $row->start_date ?? '' ),
			'end_date'   => (string) ( $row->end_date ?? '' ),
			'status'     => (string) ( $row->status ?? 'draft' ),
			'template_id'=> (int) ( $row->template_id ?? 0 ),
		);
	}

	public static function cycles_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();
		$err = self::guard_hrm();
		if ( $err ) {
			return $err;
		}

		$id          = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$name        = sanitize_text_field( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) );
		$year        = WebinoCRM_Service_Base::int_param( $params, 'year' ) ?: (int) current_time( 'Y' );
		$start_date  = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'start_date', '' ) );
		$end_date    = WebinoCRM_Service_Base::normalize_date( (string) WebinoCRM_Service_Base::param( $params, 'end_date', '' ) );
		$status      = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', 'draft' ) );
		$template_id = WebinoCRM_Service_Base::int_param( $params, 'template_id' );

		if ( '' === $name || ! $start_date || ! $end_date ) {
			return WebinoCRM_Service_Base::error( __( 'نام و بازه دوره الزامی است.', 'webinocrm' ) );
		}

		$now   = current_time( 'mysql' );
		$table = WebinoCRM_Hrm_Service::table( 'review_cycles' );
		$data  = array(
			'name'        => $name,
			'year'        => $year,
			'start_date'  => $start_date,
			'end_date'    => $end_date,
			'status'      => $status,
			'template_id' => $template_id,
			'updated_at'  => $now,
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
				'message' => __( 'دوره ارزیابی ذخیره شد.', 'webinocrm' ),
				'cycle'   => $row ? self::format_cycle( $row ) : null,
			)
		);
	}

	public static function reviews_list( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		$cycle_id = WebinoCRM_Service_Base::int_param( $params, 'cycle_id' );
		$user_id  = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$can_hrm  = WebinoCRM_Hrm_Service::can_manage_hrm();

		if ( ! $can_hrm ) {
			$user_id = get_current_user_id();
		}

		$table  = WebinoCRM_Hrm_Service::table( 'reviews' );
		$where  = array( '1=1' );
		$values = array();
		if ( $cycle_id > 0 ) {
			$where[]  = 'cycle_id = %d';
			$values[] = $cycle_id;
		}
		if ( $user_id > 0 ) {
			$where[]  = 'user_id = %d';
			$values[] = $user_id;
		}

		$sql  = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC';
		$rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_results( $sql );

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format_review( $row, false );
		}
		return WebinoCRM_Service_Base::success( array( 'reviews' => $items ) );
	}

	/**
	 * @param object $row DB row.
	 * @param bool   $with_scores Include scores.
	 * @return array<string,mixed>
	 */
	private static function format_review( $row, $with_scores = true ) {
		global $wpdb;
		$user     = get_userdata( (int) $row->user_id );
		$reviewer = get_userdata( (int) ( $row->reviewer_id ?? 0 ) );
		$out      = array(
			'id'            => (int) $row->id,
			'cycle_id'      => (int) ( $row->cycle_id ?? 0 ),
			'user_id'       => (int) ( $row->user_id ?? 0 ),
			'user_name'     => $user ? $user->display_name : '',
			'reviewer_id'   => (int) ( $row->reviewer_id ?? 0 ),
			'reviewer_name' => $reviewer ? $reviewer->display_name : '',
			'status'        => (string) ( $row->status ?? 'draft' ),
			'overall_score' => (float) ( $row->overall_score ?? 0 ),
			'notes'         => (string) ( $row->notes ?? '' ),
			'submitted_at'  => (string) ( $row->submitted_at ?? '' ),
		);
		if ( $with_scores ) {
			$scores_table = WebinoCRM_Hrm_Service::table( 'review_scores' );
			$scores       = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM $scores_table WHERE review_id = %d ORDER BY id ASC", (int) $row->id )
			);
			$out['scores'] = array();
			foreach ( (array) $scores as $score ) {
				$out['scores'][] = array(
					'id'              => (int) $score->id,
					'criterion_key'   => (string) ( $score->criterion_key ?? '' ),
					'criterion_label' => (string) ( $score->criterion_label ?? '' ),
					'weight'          => (float) ( $score->weight ?? 0 ),
					'score'           => (float) ( $score->score ?? 0 ),
					'max_score'       => (float) ( $score->max_score ?? 0 ),
					'notes'           => (string) ( $score->notes ?? '' ),
				);
			}
		}
		return $out;
	}

	public static function reviews_save( array $params ) {
		global $wpdb;
		WebinoCRM_Service_Base::ensure_dependencies();

		$id            = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$cycle_id      = WebinoCRM_Service_Base::int_param( $params, 'cycle_id' );
		$user_id       = WebinoCRM_Service_Base::int_param( $params, 'user_id' );
		$reviewer_id   = WebinoCRM_Service_Base::int_param( $params, 'reviewer_id' );
		$status        = sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', 'draft' ) );
		$notes         = sanitize_textarea_field( (string) WebinoCRM_Service_Base::param( $params, 'notes', '' ) );
		$scores        = WebinoCRM_Service_Base::array_param( $params, 'scores' );

		if ( $cycle_id <= 0 || $user_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'دوره و کارمند الزامی است.', 'webinocrm' ) );
		}

		$can_hrm = WebinoCRM_Hrm_Service::can_manage_hrm();
		if ( ! $can_hrm && ! WebinoCRM_Hrm_Service::can_manage_staff_user( $user_id ) && $user_id !== get_current_user_id() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		if ( $reviewer_id <= 0 ) {
			$reviewer_id = get_current_user_id();
		}

		$now            = current_time( 'mysql' );
		$table          = WebinoCRM_Hrm_Service::table( 'reviews' );
		$overall_score  = 0.0;
		$total_weight   = 0.0;

		if ( is_array( $scores ) ) {
			foreach ( $scores as $score_row ) {
				if ( ! is_array( $score_row ) ) {
					continue;
				}
				$weight = (float) ( $score_row['weight'] ?? 1 );
				$score  = (float) ( $score_row['score'] ?? 0 );
				$max    = (float) ( $score_row['max_score'] ?? 100 );
				if ( $max > 0 ) {
					$overall_score += ( $score / $max ) * 100 * $weight;
					$total_weight  += $weight;
				}
			}
			if ( $total_weight > 0 ) {
				$overall_score = round( $overall_score / $total_weight, 2 );
			}
		}

		$data = array(
			'cycle_id'      => $cycle_id,
			'user_id'       => $user_id,
			'reviewer_id'   => $reviewer_id,
			'status'        => $status,
			'overall_score' => $overall_score,
			'notes'         => $notes,
			'updated_at'    => $now,
		);
		if ( 'submitted' === $status ) {
			$data['submitted_at'] = $now;
		}

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		if ( is_array( $scores ) ) {
			$scores_table = WebinoCRM_Hrm_Service::table( 'review_scores' );
			$wpdb->delete( $scores_table, array( 'review_id' => $id ), array( '%d' ) );
			foreach ( $scores as $score_row ) {
				if ( ! is_array( $score_row ) ) {
					continue;
				}
				$wpdb->insert(
					$scores_table,
					array(
						'review_id'       => $id,
						'criterion_key'   => sanitize_key( (string) ( $score_row['criterion_key'] ?? '' ) ),
						'criterion_label' => sanitize_text_field( (string) ( $score_row['criterion_label'] ?? '' ) ),
						'weight'          => (float) ( $score_row['weight'] ?? 1 ),
						'score'           => (float) ( $score_row['score'] ?? 0 ),
						'max_score'       => (float) ( $score_row['max_score'] ?? 100 ),
						'notes'           => sanitize_textarea_field( (string) ( $score_row['notes'] ?? '' ) ),
						'created_at'      => $now,
						'updated_at'      => $now,
					),
					array( '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s' )
				);
			}
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		return WebinoCRM_Service_Base::success(
			array(
				'message' => __( 'ارزیابی ذخیره شد.', 'webinocrm' ),
				'review'  => $row ? self::format_review( $row, true ) : null,
			)
		);
	}

	public static function register_actions() {}
}
