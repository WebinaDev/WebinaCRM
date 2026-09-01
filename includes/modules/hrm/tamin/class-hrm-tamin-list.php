<?php
/**
 * Build official Tamin list-disk DBF pair from a payroll run.
 *
 * Files: DSKKAR00.DBF (workshop summary) + DSKWOR00.DBF (insured detail).
 * Upload manually on eservices.tamin.ir — no portal login from plugin.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tamin list export.
 */
final class WebinoCRM_Hrm_Tamin_List {

	/**
	 * Field layout for DSKKAR00 (workshop monthly summary).
	 * Names follow common list-disk conventions used with PrjList / eservices upload.
	 *
	 * @return array<int,array{name:string,type:string,len:int,dec?:int}>
	 */
	public static function kar_fields() {
		return array(
			array( 'name' => 'DSK_ID', 'type' => 'C', 'len' => 2 ),
			array( 'name' => 'DSK_YY', 'type' => 'C', 'len' => 2 ),
			array( 'name' => 'DSK_MM', 'type' => 'C', 'len' => 2 ),
			array( 'name' => 'DSK_LISTNO', 'type' => 'C', 'len' => 12 ),
			array( 'name' => 'DSK_DISC', 'type' => 'C', 'len' => 20 ),
			array( 'name' => 'DSK_NUM', 'type' => 'N', 'len' => 8, 'dec' => 0 ),
			array( 'name' => 'DSK_T_DD', 'type' => 'N', 'len' => 8, 'dec' => 0 ),
			array( 'name' => 'DSK_T_ROOZ', 'type' => 'N', 'len' => 8, 'dec' => 0 ),
			array( 'name' => 'DSK_T_MAH', 'type' => 'N', 'len' => 12, 'dec' => 0 ),
			array( 'name' => 'DSK_T_MAZ', 'type' => 'N', 'len' => 12, 'dec' => 0 ),
			array( 'name' => 'DSK_TMASH', 'type' => 'N', 'len' => 14, 'dec' => 0 ),
			array( 'name' => 'DSK_TTOTL', 'type' => 'N', 'len' => 14, 'dec' => 0 ),
			array( 'name' => 'DSK_TBIME', 'type' => 'N', 'len' => 12, 'dec' => 0 ),
			array( 'name' => 'DSK_TKOSO', 'type' => 'N', 'len' => 12, 'dec' => 0 ),
			array( 'name' => 'DSK_BIC', 'type' => 'N', 'len' => 12, 'dec' => 0 ),
			array( 'name' => 'DSK_RATE', 'type' => 'N', 'len' => 6, 'dec' => 2 ),
			array( 'name' => 'DSK_PRATE', 'type' => 'N', 'len' => 6, 'dec' => 2 ),
			array( 'name' => 'DSK_BIMH', 'type' => 'C', 'len' => 10 ),
			array( 'name' => 'DSK_KARGAH', 'type' => 'C', 'len' => 10 ),
			array( 'name' => 'DSK_NAME', 'type' => 'C', 'len' => 50 ),
		);
	}

	/**
	 * Field layout for DSKWOR00 (insured workers).
	 *
	 * @return array<int,array{name:string,type:string,len:int,dec?:int}>
	 */
	public static function wor_fields() {
		return array(
			array( 'name' => 'DSW_ID', 'type' => 'C', 'len' => 2 ),
			array( 'name' => 'DSW_YY', 'type' => 'C', 'len' => 2 ),
			array( 'name' => 'DSW_MM', 'type' => 'C', 'len' => 2 ),
			array( 'name' => 'DSW_LISTNO', 'type' => 'C', 'len' => 12 ),
			array( 'name' => 'DSW_ID1', 'type' => 'C', 'len' => 10 ),
			array( 'name' => 'DSW_FNAME', 'type' => 'C', 'len' => 40 ),
			array( 'name' => 'DSW_LNAME', 'type' => 'C', 'len' => 40 ),
			array( 'name' => 'DSW_DNAME', 'type' => 'C', 'len' => 40 ),
			array( 'name' => 'DSW_IDNO', 'type' => 'C', 'len' => 15 ),
			array( 'name' => 'DSW_IDPLC', 'type' => 'C', 'len' => 30 ),
			array( 'name' => 'DSW_IDATE', 'type' => 'C', 'len' => 8 ),
			array( 'name' => 'DSW_BDATE', 'type' => 'C', 'len' => 8 ),
			array( 'name' => 'DSW_SEX', 'type' => 'C', 'len' => 1 ),
			array( 'name' => 'DSW_NAT', 'type' => 'C', 'len' => 10 ),
			array( 'name' => 'DSW_OCP', 'type' => 'C', 'len' => 6 ),
			array( 'name' => 'DSW_SDATE', 'type' => 'C', 'len' => 8 ),
			array( 'name' => 'DSW_EDATE', 'type' => 'C', 'len' => 8 ),
			array( 'name' => 'DSW_DD', 'type' => 'N', 'len' => 6, 'dec' => 0 ),
			array( 'name' => 'DSW_ROOZ', 'type' => 'N', 'len' => 8, 'dec' => 0 ),
			array( 'name' => 'DSW_MAH', 'type' => 'N', 'len' => 12, 'dec' => 0 ),
			array( 'name' => 'DSW_MAZ', 'type' => 'N', 'len' => 12, 'dec' => 0 ),
			array( 'name' => 'DSW_MASH', 'type' => 'N', 'len' => 14, 'dec' => 0 ),
			array( 'name' => 'DSW_TOTL', 'type' => 'N', 'len' => 14, 'dec' => 0 ),
			array( 'name' => 'DSW_BIME', 'type' => 'N', 'len' => 12, 'dec' => 0 ),
			array( 'name' => 'DSW_PRATE', 'type' => 'N', 'len' => 6, 'dec' => 2 ),
			array( 'name' => 'DSW_JOB', 'type' => 'C', 'len' => 50 ),
			array( 'name' => 'DSW_PERNO', 'type' => 'C', 'len' => 12 ),
			array( 'name' => 'DSW_SPOSE', 'type' => 'C', 'len' => 1 ),
			array( 'name' => 'DSW_MARRIAGE', 'type' => 'N', 'len' => 12, 'dec' => 0 ),
			array( 'name' => 'DSW_SENIOR', 'type' => 'N', 'len' => 12, 'dec' => 0 ),
		);
	}

	/**
	 * Build ZIP with both DBF files for a payroll run.
	 *
	 * @param int $run_id Run ID.
	 * @return array{path:string,url:string,filename:string}|WP_Error
	 */
	public static function export_zip( $run_id ) {
		$built = self::build_files( $run_id );
		if ( is_wp_error( $built ) ) {
			return $built;
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'tamin_upload', (string) $upload['error'] );
		}
		$dir = trailingslashit( $upload['basedir'] ) . 'webino-tamin';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'tamin_dir', __( 'Cannot create Tamin export folder.', 'webinocrm' ) );
		}

		$stamp    = gmdate( 'Ymd-His' );
		$filename = 'tamin-list-' . absint( $run_id ) . '-' . $stamp . '.zip';
		$zip_path = trailingslashit( $dir ) . $filename;

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'tamin_zip', __( 'ZipArchive PHP extension is required.', 'webinocrm' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'tamin_zip_open', __( 'Cannot create ZIP.', 'webinocrm' ) );
		}
		$zip->addFile( $built['kar'], 'DSKKAR00.DBF' );
		$zip->addFile( $built['wor'], 'DSKWOR00.DBF' );
		$zip->addFromString( 'README.txt', self::readme_text() );
		$zip->close();

		@unlink( $built['kar'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink( $built['wor'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		self::update_run(
			'payroll_runs',
			$run_id,
			array( 'list_status' => 'exported' )
		);

		$url = trailingslashit( $upload['baseurl'] ) . 'webino-tamin/' . $filename;
		return array(
			'path'     => $zip_path,
			'url'      => $url,
			'filename' => $filename,
		);
	}

	/**
	 * Preview as CSV rows.
	 *
	 * @param int $run_id Run.
	 * @return array{kar:array<string,mixed>,workers:array<int,array<string,mixed>>}|WP_Error
	 */
	public static function preview( $run_id ) {
		$data = self::collect( $run_id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return array(
			'kar'     => $data['kar_row'],
			'workers' => $data['wor_rows'],
			'guide'   => __( 'Download the ZIP and upload DSKKAR00.DBF + DSKWOR00.DBF on eservices.tamin.ir (Employers → Insurance list).', 'webinocrm' ),
		);
	}

	/**
	 * @param int $run_id Run.
	 * @return array{kar:string,wor:string}|WP_Error
	 */
	public static function build_files( $run_id ) {
		$data = self::collect( $run_id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$tmp = trailingslashit( get_temp_dir() ) . 'webino-tamin-' . absint( $run_id ) . '-' . wp_generate_password( 6, false );
		if ( ! wp_mkdir_p( $tmp ) ) {
			return new WP_Error( 'tamin_tmp', __( 'Cannot create temp folder.', 'webinocrm' ) );
		}
		$kar = trailingslashit( $tmp ) . 'DSKKAR00.DBF';
		$wor = trailingslashit( $tmp ) . 'DSKWOR00.DBF';
		$w1  = WebinoCRM_Hrm_Tamin_Dbf::write( $kar, self::kar_fields(), array( $data['kar_row'] ) );
		if ( is_wp_error( $w1 ) ) {
			return $w1;
		}
		$w2 = WebinoCRM_Hrm_Tamin_Dbf::write( $wor, self::wor_fields(), $data['wor_rows'] );
		if ( is_wp_error( $w2 ) ) {
			return $w2;
		}
		return array(
			'kar' => $kar,
			'wor' => $wor,
		);
	}

	/**
	 * @param int $run_id Run.
	 * @return array{kar_row:array<string,mixed>,wor_rows:array<int,array<string,mixed>>}|WP_Error
	 */
	private static function collect( $run_id ) {
		$run = self::get_run( $run_id );
		if ( is_wp_error( $run ) ) {
			return $run;
		}
		if ( empty( $run['payslips'] ) ) {
			return new WP_Error( 'tamin_empty', __( 'Payroll run has no payslips.', 'webinocrm' ) );
		}

		$wh = null;
		if ( ! empty( $run['workshop_id'] ) ) {
			$wh = self::get_row( 'workshops', (int) $run['workshop_id'] );
		}
		if ( ! $wh ) {
			$def = absint( WebinoCRM_Hrm_Payroll_Config::get()['default_workshop_id'] ?? 0 );
			if ( $def ) {
				$wh = self::get_row( 'workshops', $def );
			}
		}
		if ( ! $wh ) {
			return new WP_Error( 'tamin_workshop', __( 'Define a workshop (کارگاه) with Tamin code before exporting the list.', 'webinocrm' ) );
		}

		$jy = (int) ( $run['jalali_year'] ?? 0 );
		$jm = (int) ( $run['jalali_month'] ?? 0 );
		if ( ! $jy || ! $jm ) {
			list( $jy, $jm ) = WebinoCRM_Hrm_Payroll_Calculator::gregorian_to_jalali_public(
				(int) substr( (string) $run['year_month'], 0, 4 ),
				(int) substr( (string) $run['year_month'], 5, 2 ),
				15
			);
		}
		$yy = sprintf( '%02d', $jy % 100 );
		$mm = sprintf( '%02d', $jm );
		$listno = sprintf( '%s%s%s', $wh['code'], $yy, $mm );

		$total_days = 0.0;
		$total_base = 0.0;
		$total_ben  = 0.0;
		$total_mash = 0.0;
		$total_gross = 0.0;
		$total_emp  = 0.0;
		$workers    = array();
		$seq        = 0;

		foreach ( (array) $run['payslips'] as $slip ) {
			++$seq;
			$emp = WebinoCRM_Hrm_Payroll_Calculator::employee_snapshot( (int) $slip['user_id'] );
			if ( empty( $emp['id'] ) ) {
				continue;
			}
			$items = json_decode( (string) ( $slip['items_json'] ?? '{}' ), true );
			if ( ! is_array( $items ) ) {
				$items = array();
			}
			$days = (float) ( $slip['days_worked'] ?? 0 );
			$base = (float) ( $items['base_pay'] ?? 0 );
			$ben  = (float) ( $slip['insurable_wage'] ?? 0 ) - $base;
			if ( $ben < 0 ) {
				$ben = 0;
			}
			$mash = (float) ( $slip['insurable_capped'] ?? 0 );
			$tot  = (float) ( $slip['gross'] ?? 0 );
			$bime = (float) ( $slip['employee_insurance'] ?? 0 );

			$total_days  += $days;
			$total_base  += $base;
			$total_ben   += $ben;
			$total_mash  += $mash;
			$total_gross += $tot;
			$total_emp   += $bime;

			$fname = (string) ( $emp['first_name'] ?? '' );
			$lname = (string) ( $emp['last_name'] ?? '' );
			if ( '' === $fname && '' === $lname ) {
				$parts = preg_split( '/\s+/', trim( (string) $emp['name'] ), 2 );
				$fname = $parts[0] ?? '';
				$lname = $parts[1] ?? '';
			}
			$sex = '1';
			if ( isset( $emp['gender'] ) && in_array( $emp['gender'], array( 'f', 'female', '2' ), true ) ) {
				$sex = '2';
			}
			$married = in_array( (string) ( $emp['marital_status'] ?? '' ), array( 'married', '1', 'yes' ), true ) ? '1' : '0';

			$workers[] = array(
				'DSW_ID'       => '01',
				'DSW_YY'       => $yy,
				'DSW_MM'       => $mm,
				'DSW_LISTNO'   => $listno,
				'DSW_ID1'      => (string) ( $emp['insurance_no'] ?? '' ),
				'DSW_FNAME'    => $fname,
				'DSW_LNAME'    => $lname,
				'DSW_DNAME'    => (string) ( $emp['father_name'] ?? '' ),
				'DSW_IDNO'     => (string) ( $emp['national_id'] ?? '' ),
				'DSW_IDPLC'    => (string) ( $emp['id_issue_place'] ?? '' ),
				'DSW_IDATE'    => self::ymd_to_jalali_digits( (string) ( $emp['insurance_start'] ?? '' ) ),
				'DSW_BDATE'    => self::ymd_to_jalali_digits( (string) ( $emp['birth_date'] ?? '' ) ),
				'DSW_SEX'      => $sex,
				'DSW_NAT'      => (string) ( $emp['national_id'] ?? '' ),
				'DSW_OCP'      => (string) ( $emp['job_code'] ?? '' ),
				'DSW_SDATE'    => self::ymd_to_jalali_digits( (string) ( $emp['insurance_start'] ?? $emp['hire_date'] ?? '' ) ),
				'DSW_EDATE'    => self::ymd_to_jalali_digits( (string) ( $emp['insurance_end'] ?? '' ) ),
				'DSW_DD'       => (int) round( $days ),
				'DSW_ROOZ'     => (int) round( $days > 0 ? $base / $days : 0 ),
				'DSW_MAH'      => (int) round( $base ),
				'DSW_MAZ'      => (int) round( $ben + (float) ( $items['volume'] ?? $slip['volume_pay'] ?? 0 ) ),
				'DSW_MASH'     => (int) round( $mash ),
				'DSW_TOTL'     => (int) round( $tot ),
				'DSW_BIME'     => (int) round( $bime ),
				'DSW_PRATE'    => (float) ( WebinoCRM_Hrm_Payroll_Config::get()['employee_insurance_pct'] ?? 7 ),
				'DSW_JOB'      => (string) ( $emp['job_title'] ?? '' ),
				'DSW_PERNO'    => (string) $seq,
				'DSW_SPOSE'    => $married,
				'DSW_MARRIAGE' => (int) round( (float) ( $items['marriage'] ?? 0 ) ),
				'DSW_SENIOR'   => (int) round( (float) ( $items['seniority'] ?? 0 ) ),
			);
		}

		if ( ! $workers ) {
			return new WP_Error( 'tamin_workers', __( 'No exportable workers in this run.', 'webinocrm' ) );
		}

		$er_pct = (float) ( WebinoCRM_Hrm_Payroll_Config::get()['employer_insurance_pct'] ?? 20 );
		$ue_pct = (float) ( WebinoCRM_Hrm_Payroll_Config::get()['unemployment_insurance_pct'] ?? 3 );
		$tkoso  = (float) ( $run['total_er_ins'] ?? round( $total_mash * $er_pct / 100 ) );
		$bic    = (float) ( $run['total_unemployment'] ?? round( $total_mash * $ue_pct / 100 ) );

		$kar = array(
			'DSK_ID'     => '01',
			'DSK_YY'     => $yy,
			'DSK_MM'     => $mm,
			'DSK_LISTNO' => $listno,
			'DSK_DISC'   => '',
			'DSK_NUM'    => count( $workers ),
			'DSK_T_DD'   => (int) round( $total_days ),
			'DSK_T_ROOZ' => 0,
			'DSK_T_MAH'  => (int) round( $total_base ),
			'DSK_T_MAZ'  => (int) round( $total_ben ),
			'DSK_TMASH'  => (int) round( $total_mash ),
			'DSK_TTOTL'  => (int) round( $total_gross ),
			'DSK_TBIME'  => (int) round( $total_emp ),
			'DSK_TKOSO'  => (int) round( $tkoso ),
			'DSK_BIC'    => (int) round( $bic ),
			'DSK_RATE'   => $er_pct + $ue_pct,
			'DSK_PRATE'  => (float) ( WebinoCRM_Hrm_Payroll_Config::get()['employee_insurance_pct'] ?? 7 ),
			'DSK_BIMH'   => '',
			'DSK_KARGAH' => (string) $wh['code'],
			'DSK_NAME'   => (string) $wh['name'],
		);

		return array(
			'kar_row'  => $kar,
			'wor_rows' => $workers,
		);
	}

	/**
	 * @param int $run_id Run id.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function get_run( $run_id ) {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'payroll_runs' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $run_id ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Payroll run not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		$pt = WebinoCRM_Hrm_Service::table( 'payslips' );
		$slips = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $pt WHERE run_id = %d ORDER BY id ASC", (int) $run_id ), ARRAY_A );
		$row['payslips'] = is_array( $slips ) ? $slips : array();
		return $row;
	}

	/**
	 * @param string $table_suffix Table suffix.
	 * @param int    $id Row id.
	 * @return array<string,mixed>|null
	 */
	private static function get_row( $table_suffix, $id ) {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( $table_suffix );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param int                 $run_id Run id.
	 * @param array<string,mixed> $data Data.
	 */
	private static function update_run( $run_id, array $data ) {
		global $wpdb;
		$table = WebinoCRM_Hrm_Service::table( 'payroll_runs' );
		$wpdb->update( $table, $data, array( 'id' => (int) $run_id ) );
	}

	/**
	 * @param string $ymd Y-m-d.
	 * @return string YYMMDD jalali digits or empty.
	 */
	private static function ymd_to_jalali_digits( $ymd ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m ) ) {
			return '';
		}
		list( $jy, $jm, $jd ) = WebinoCRM_Hrm_Payroll_Calculator::gregorian_to_jalali_public( (int) $m[1], (int) $m[2], (int) $m[3] );
		return sprintf( '%02d%02d%02d', $jy % 100, $jm, $jd );
	}

	/**
	 * @return string
	 */
	private static function readme_text() {
		return "WebinoCRM — Tamin list-disk export\n"
			. "1) Log in to https://eservices.tamin.ir\n"
			. "2) Employers → Insurance list → Upload files\n"
			. "3) Upload DSKKAR00.DBF (workshop) and DSKWOR00.DBF (workers)\n"
			. "4) Review processing errors in the portal, then pay the bill\n"
			. "This plugin does not log into Tamin for you.\n";
	}
}
