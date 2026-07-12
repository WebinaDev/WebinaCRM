<?php
/**
 * Import / export service for REST.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Import_Export_Service {

	/**
	 * @param array $params Params.
	 * @return array
	 */
	public static function export_leads( array $params ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::error( [ 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ] );
		}
		$leads = get_posts( [
			'post_type'      => 'lead',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		] );
		$csv_data   = [ [ 'ID', 'نام', 'ایمیل', 'تلفن', 'وضعیت', 'منبع', 'تاریخ' ] ];
		foreach ( $leads as $lead ) {
			$csv_data[]   = [
				$lead->ID,
				$lead->post_title,
				get_post_meta( $lead->ID, '_email', true ) ?: get_post_meta( $lead->ID, '_lead_email', true ),
				get_post_meta( $lead->ID, '_mobile', true ) ?: get_post_meta( $lead->ID, '_lead_phone', true ),
				WebinoCRM_Term_Helper::get_first_term_name( $lead->ID, 'lead_status' ),
				WebinoCRM_Term_Helper::get_first_term_name( $lead->ID, 'lead_source' ),
				get_the_date( 'Y-m-d', $lead ),
			];
		}
		return self::write_csv( $csv_data, 'leads-export-' . gmdate( 'Y-m-d' ) . '.csv', count( $leads ) );
	}

	/**
	 * @param array $params Params.
	 * @return array
	 */
	public static function export_customers( array $params ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::error( [ 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ] );
		}
		$users    = get_users( [ 'role' => 'customer', 'orderby' => 'display_name' ] );
		$csv_data = [ [ 'ID', 'نام', 'ایمیل', 'موبایل', 'تاریخ ثبت' ] ];
		foreach ( $users as $user ) {
			$csv_data[] = [
				$user->ID,
				$user->display_name,
				$user->user_email,
				get_user_meta( $user->ID, 'webino_mobile_phone', true ),
				$user->user_registered,
			];
		}
		return self::write_csv( $csv_data, 'customers-export-' . gmdate( 'Y-m-d' ) . '.csv', count( $users ) );
	}

	/**
	 * Import leads from uploaded CSV (export-compatible columns).
	 *
	 * @param array $params Params.
	 * @return array
	 */
	public static function import_leads( array $params ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::error( [ 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ] );
		}

		$parsed = self::parse_csv_upload();
		if ( isset( $parsed['error'] ) ) {
			return WebinoCRM_Service_Base::error( [ 'message' => $parsed['error'] ] );
		}

		$imported = 0;
		$errors   = [];
		$row_num  = 1;

		foreach ( $parsed['rows'] as $row ) {
			$row_num++;
			$mapped = self::map_row_by_header(
				$parsed['header'],
				$row,
				[
					'name'   => [ 'نام', 'name' ],
					'email'  => [ 'ایمیل', 'email' ],
					'phone'  => [ 'تلفن', 'phone', 'موبایل', 'mobile' ],
					'status' => [ 'وضعیت', 'status' ],
					'source' => [ 'منبع', 'source' ],
				]
			);

			$name  = trim( (string) ( $mapped['name'] ?? '' ) );
			$email = sanitize_email( (string) ( $mapped['email'] ?? '' ) );
			$phone = sanitize_text_field( (string) ( $mapped['phone'] ?? '' ) );

			if ( '' === $name && '' === $phone ) {
				continue;
			}

			$parts      = self::split_display_name( $name );
			$first_name = $parts['first_name'];
			$last_name  = $parts['last_name'];

			if ( '' === $last_name && '' === $phone ) {
				$errors[] = sprintf( __( 'ردیف %d: نام یا موبایل الزامی است.', 'webinocrm' ), $row_num );
				continue;
			}

			if ( '' === $last_name ) {
				$last_name  = $first_name ?: __( 'بدون نام', 'webinocrm' );
				$first_name = '';
			}

			$lead_id = wp_insert_post( [
				'post_type'   => 'lead',
				'post_title'  => trim( $first_name . ' ' . $last_name ),
				'post_status' => 'publish',
			] );

			if ( is_wp_error( $lead_id ) ) {
				$errors[] = sprintf( __( 'ردیف %d: %s', 'webinocrm' ), $row_num, $lead_id->get_error_message() );
				continue;
			}

			update_post_meta( $lead_id, '_first_name', $first_name );
			update_post_meta( $lead_id, '_last_name', $last_name );
			update_post_meta( $lead_id, '_mobile', $phone );
			update_post_meta( $lead_id, '_email', $email );

			$source = trim( (string) ( $mapped['source'] ?? '' ) );
			if ( '' !== $source ) {
				self::assign_term_by_label( $lead_id, $source, 'lead_source' );
			}

			$status = trim( (string) ( $mapped['status'] ?? '' ) );
			if ( '' !== $status ) {
				self::assign_term_by_label( $lead_id, $status, 'lead_status' );
			} else {
				self::apply_default_lead_status( $lead_id );
			}

			$imported++;
		}

		return WebinoCRM_Service_Base::success( [
			'message'  => sprintf( __( '%d سرنخ وارد شد.', 'webinocrm' ), $imported ),
			'imported' => $imported,
			'errors'   => $errors,
		] );
	}

	/**
	 * Import customers from uploaded CSV (export-compatible columns).
	 *
	 * @param array $params Params.
	 * @return array
	 */
	public static function import_customers( array $params ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return WebinoCRM_Service_Base::error( [ 'message' => __( 'دسترسی غیرمجاز.', 'webinocrm' ) ] );
		}

		$parsed = self::parse_csv_upload();
		if ( isset( $parsed['error'] ) ) {
			return WebinoCRM_Service_Base::error( [ 'message' => $parsed['error'] ] );
		}

		$imported = 0;
		$errors   = [];
		$row_num  = 1;

		foreach ( $parsed['rows'] as $row ) {
			$row_num++;
			$mapped = self::map_row_by_header(
				$parsed['header'],
				$row,
				[
					'name'   => [ 'نام', 'name' ],
					'email'  => [ 'ایمیل', 'email' ],
					'mobile' => [ 'موبایل', 'mobile', 'تلفن', 'phone' ],
				]
			);

			$name  = trim( (string) ( $mapped['name'] ?? '' ) );
			$email = sanitize_email( (string) ( $mapped['email'] ?? '' ) );

			if ( ! is_email( $email ) ) {
				$errors[] = sprintf( __( 'ردیف %d: ایمیل نامعتبر است.', 'webinocrm' ), $row_num );
				continue;
			}

			if ( email_exists( $email ) ) {
				$errors[] = sprintf( __( 'ردیف %d: ایمیل تکراری است (%s).', 'webinocrm' ), $row_num, $email );
				continue;
			}

			$parts      = self::split_display_name( $name );
			$first_name = $parts['first_name'];
			$last_name  = $parts['last_name'];
			if ( '' === $last_name ) {
				$last_name  = $first_name ?: $email;
				$first_name = '';
			}

			$mobile = sanitize_text_field( (string) ( $mapped['mobile'] ?? '' ) );

			$user_id = wp_insert_user( [
				'user_login'   => $email,
				'user_email'   => $email,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ) ?: $email,
				'user_pass'    => wp_generate_password( 16, true ),
				'role'         => 'customer',
			] );

			if ( is_wp_error( $user_id ) ) {
				$errors[] = sprintf( __( 'ردیف %d: %s', 'webinocrm' ), $row_num, $user_id->get_error_message() );
				continue;
			}

			if ( '' !== $mobile ) {
				update_user_meta( $user_id, 'webino_mobile_phone', $mobile );
			}

			$imported++;
		}

		return WebinoCRM_Service_Base::success( [
			'message'  => sprintf( __( '%d مشتری وارد شد.', 'webinocrm' ), $imported ),
			'imported' => $imported,
			'errors'   => $errors,
		] );
	}

	/**
	 * @return array{header: string[], rows: array<int, string[]>}|array{error: string}
	 */
	private static function parse_csv_upload() {
		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			return [ 'error' => __( 'فایلی انتخاب نشده است.', 'webinocrm' ) ];
		}

		$file = $_FILES['import_file']['tmp_name'];
		if ( ! is_uploaded_file( $file ) && ! file_exists( $file ) ) {
			return [ 'error' => __( 'فایل یافت نشد.', 'webinocrm' ) ];
		}

		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			return [ 'error' => __( 'خطا در خواندن فایل.', 'webinocrm' ) ];
		}

		$header = fgetcsv( $handle );
		if ( false === $header || empty( $header ) ) {
			fclose( $handle );
			return [ 'error' => __( 'فایل CSV خالی است.', 'webinocrm' ) ];
		}

		$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
		$header    = array_map( static function ( $col ) {
			return trim( (string) $col );
		}, $header );

		$rows = [];
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( ! is_array( $row ) || self::is_empty_row( $row ) ) {
				continue;
			}
			$rows[] = $row;
		}
		fclose( $handle );

		return [
			'header' => $header,
			'rows'   => $rows,
		];
	}

	/**
	 * @param string[]               $header Header row.
	 * @param string[]               $row    Data row.
	 * @param array<string, string[]> $aliases Column aliases.
	 * @return array<string, string>
	 */
	private static function map_row_by_header( array $header, array $row, array $aliases ) {
		$out = [];
		foreach ( $aliases as $key => $labels ) {
			$out[ $key ] = '';
			foreach ( $header as $idx => $col ) {
				$col_lower = mb_strtolower( trim( $col ) );
				foreach ( $labels as $label ) {
					if ( $col_lower === mb_strtolower( $label ) || $col === $label ) {
						$out[ $key ] = isset( $row[ $idx ] ) ? trim( (string) $row[ $idx ] ) : '';
						break 2;
					}
				}
				if ( 'id' === $col_lower && 'name' === $key && '' === $out[ $key ] ) {
					continue;
				}
			}
		}

		// Legacy 4-column format without header match: name, email, phone, source.
		if ( '' === $out['name'] && count( $row ) >= 3 && ! self::header_looks_like_export( $header ) ) {
			$out['name']   = trim( (string) ( $row[0] ?? '' ) );
			$out['email']  = trim( (string) ( $row[1] ?? '' ) );
			$out['phone']  = trim( (string) ( $row[2] ?? '' ) );
			$out['mobile'] = $out['phone'];
			$out['source'] = trim( (string) ( $row[3] ?? '' ) );
		}

		return $out;
	}

	/**
	 * @param string[] $header Header.
	 * @return bool
	 */
	private static function header_looks_like_export( array $header ) {
		$joined = implode( ' ', $header );
		return false !== mb_strpos( $joined, 'نام' ) || false !== mb_strpos( $joined, 'ایمیل' );
	}

	/**
	 * @param string[] $row Row.
	 * @return bool
	 */
	private static function is_empty_row( array $row ) {
		foreach ( $row as $cell ) {
			if ( '' !== trim( (string) $cell ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param string $name Full name.
	 * @return array{first_name: string, last_name: string}
	 */
	private static function split_display_name( $name ) {
		$name = trim( $name );
		if ( '' === $name ) {
			return [ 'first_name' => '', 'last_name' => '' ];
		}
		$parts = preg_split( '/\s+/u', $name, 2 );
		if ( count( $parts ) === 1 ) {
			return [ 'first_name' => '', 'last_name' => $parts[0] ];
		}
		return [
			'first_name' => $parts[0],
			'last_name'  => $parts[1],
		];
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $label   Term name or slug.
	 * @param string $taxonomy Taxonomy.
	 */
	private static function assign_term_by_label( $post_id, $label, $taxonomy ) {
		$term = get_term_by( 'slug', sanitize_title( $label ), $taxonomy );
		if ( ! $term ) {
			$term = get_term_by( 'name', $label, $taxonomy );
		}
		if ( $term && ! is_wp_error( $term ) ) {
			wp_set_object_terms( $post_id, [ $term->term_id ], $taxonomy );
		}
	}

	/**
	 * @param int $lead_id Lead post ID.
	 */
	private static function apply_default_lead_status( $lead_id ) {
		if ( class_exists( 'WebinoCRM_Settings_Handler' ) ) {
			$settings       = WebinoCRM_Settings_Handler::get_all_settings();
			$default_status = ! empty( $settings['lead_default_status'] ) ? $settings['lead_default_status'] : null;
			if ( $default_status ) {
				wp_set_object_terms( $lead_id, $default_status, 'lead_status' );
				return;
			}
		}
		$first_status = get_terms( [
			'taxonomy'   => 'lead_status',
			'hide_empty' => false,
			'number'     => 1,
			'orderby'    => 'term_id',
			'order'      => 'ASC',
		] );
		if ( ! empty( $first_status ) && ! is_wp_error( $first_status ) ) {
			wp_set_object_terms( $lead_id, $first_status[0]->slug, 'lead_status' );
		}
	}

	/**
	 * @param array  $rows CSV rows.
	 * @param string $filename Filename.
	 * @param int    $count Item count.
	 * @return array
	 */
	private static function write_csv( array $rows, $filename, $count ) {
		$upload_dir = wp_upload_dir();
		$file_path  = trailingslashit( $upload_dir['path'] ) . $filename;
		$fp         = fopen( $file_path, 'w' );
		if ( ! $fp ) {
			return WebinoCRM_Service_Base::error( [ 'message' => __( 'خطا در ایجاد فایل.', 'webinocrm' ) ] );
		}
		fprintf( $fp, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		foreach ( $rows as $row ) {
			fputcsv( $fp, $row );
		}
		fclose( $fp );
		return WebinoCRM_Service_Base::success( [
			'message'  => sprintf( __( '%d مورد صادر شد.', 'webinocrm' ), $count ),
			'file_url' => trailingslashit( $upload_dir['url'] ) . $filename,
			'filename' => $filename,
		] );
	}
}
