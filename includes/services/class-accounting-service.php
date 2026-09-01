<?php
/**
 * Accounting Service service layer.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Accounting_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @param array<string,mixed> $params Params.
	 * @return true|array<string,mixed>
	 */
	public static function verify_accounting_access( array $params ) {
		if ( ! function_exists( 'webinocrm_current_user_can_accounting' ) || ! webinocrm_current_user_can_accounting() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ), 403 );
		}
		$nonce = isset( $params['nonce'] ) ? $params['nonce'] : ( $params['security'] ?? ( $params['_wpnonce'] ?? '' ) );
		$nonce = sanitize_text_field( wp_unslash( (string) $nonce ) );
		$valid_nonce = '' !== $nonce && (
			wp_verify_nonce( $nonce, 'webinocrm-ajax-nonce' )
			|| wp_verify_nonce( $nonce, 'wp_rest' )
		);
		if ( ! $valid_nonce ) {
			return WebinoCRM_Service_Base::error( __( 'خطای امنیتی.', 'webinocrm' ), 403 );
		}
		return true;
	}

	public static function fiscal_years( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$list = WebinoCRM_Accounting_Fiscal_Year::get_all();
		return WebinoCRM_Service_Base::success( array( 'items' => $list )  );
	}

	public static function fiscal_year_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$data = array(
			'name'       => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) ) ),
			'start_date' => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'start_date', '' ) ) ),
			'end_date'   => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'end_date', '' ) ) ),
			'is_active'  => (int) ( (bool) WebinoCRM_Service_Base::param( $params, 'is_active', false ) ),
		);
		if ( $id > 0 ) {
			$ok = WebinoCRM_Accounting_Fiscal_Year::update( $id, $data );
			return WebinoCRM_Service_Base::success( array( 'updated' => $ok, 'id' => $id )  );
		}
		$new_id = WebinoCRM_Accounting_Fiscal_Year::create( $data );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ایجاد سال مالی.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function fiscal_year_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Fiscal_Year::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف این سال مالی وجود ندارد (دارای سند است).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function chart_list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$fiscal_year_id = WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' );
		if ( $fiscal_year_id <= 0 ) {
			$active = WebinoCRM_Accounting_Fiscal_Year::get_active();
			$fiscal_year_id = $active ? (int) $active->id : 0;
		}
		if ( $fiscal_year_id <= 0 ) {
			return WebinoCRM_Service_Base::success( array( 'items' => array() )  );
		}
		$items = WebinoCRM_Accounting_Chart_Of_Accounts::get_tree( $fiscal_year_id );
		return WebinoCRM_Service_Base::success( array( 'items' => $items )  );
	}

	public static function chart_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$data = array(
			'code'           => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'code', '' ) ) ),
			'title'          => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'title', '' ) ) ),
			'level'          => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'level', 1 ) ),
			'parent_id'      => WebinoCRM_Service_Base::int_param( $params, 'parent_id' ),
			'account_type'   => sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'account_type', 'asset' ) ),
			'fiscal_year_id' => WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' ),
			'sort_order'     => WebinoCRM_Service_Base::int_param( $params, 'sort_order' ),
		);
		if ( $data['fiscal_year_id'] <= 0 ) {
			$active = WebinoCRM_Accounting_Fiscal_Year::get_active();
			$data['fiscal_year_id'] = $active ? (int) $active->id : 0;
		}
		if ( $id > 0 ) {
			$ok = WebinoCRM_Accounting_Chart_Of_Accounts::update( $id, $data );
			return WebinoCRM_Service_Base::success( array( 'updated' => $ok, 'id' => $id )  );
		}
		$new_id = WebinoCRM_Accounting_Chart_Of_Accounts::create( $data );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ایجاد حساب (احتمالاً کد تکراری است).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function chart_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Chart_Of_Accounts::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف این حساب وجود ندارد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function journal_list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$args = array(
			'fiscal_year_id' => WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' ),
			'status'        => sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'status', '' ) ),
			'date_from'     => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_from', '' ) ) ),
			'date_to'       => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_to', '' ) ) ),
			'per_page'      => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'per_page', 20 ) ),
			'page'          => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'page', 1 ) ),
		);
		$result = WebinoCRM_Accounting_Journal_Entry::list_entries( $args );
		return WebinoCRM_Service_Base::success( $result  );
	}

	public static function journal_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$entry = WebinoCRM_Accounting_Journal_Entry::get( $id );
		if ( ! $entry ) {
		return WebinoCRM_Service_Base::error( __( 'سند یافت نشد.', 'webinocrm' ) );
		}
		$lines = WebinoCRM_Accounting_Journal_Entry::get_lines( $id );
		return WebinoCRM_Service_Base::success( array( 'entry' => $entry, 'lines' => $lines )  );
	}

	public static function journal_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id        = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$lines     = array();
		$raw_lines = WebinoCRM_Service_Base::param( $params, 'lines', null );
		if ( is_array( $raw_lines ) ) {
			$lines = $raw_lines;
		} elseif ( is_string( $raw_lines ) && '' !== $raw_lines ) {
			$decoded = json_decode( sanitize_text_field( wp_unslash( $raw_lines ) ), true );
			$lines   = is_array( $decoded ) ? $decoded : array();
		}
		$lines_processed = array();
		foreach ( $lines as $l ) {
			if ( ! is_array( $l ) ) {
				continue;
			}
			$lines_processed[] = array(
				'account_id'  => isset( $l['account_id'] ) ? (int) $l['account_id'] : 0,
				'debit'       => isset( $l['debit'] ) ? (float) $l['debit'] : 0,
				'credit'      => isset( $l['credit'] ) ? (float) $l['credit'] : 0,
				'description' => isset( $l['description'] ) ? sanitize_textarea_field( wp_unslash( $l['description'] ) ) : '',
			);
		}
		$lines = $lines_processed;
		$data = array(
			'fiscal_year_id'  => WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' ),
			'voucher_date'    => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'voucher_date', '' ) ) ),
			'description'     => sanitize_textarea_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'description', '' ) ) ),
			'reference_type'  => ( sanitize_key( (string) WebinoCRM_Service_Base::param( $params, 'reference_type', '' ) ) ) ?: null,
			'reference_id'    => ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'reference_id' ) ) > 0 ? $_pid : null ),
			'status'         => 'draft',
			'lines'          => $lines,
		);
		if ( $id > 0 ) {
			$data['lines'] = $lines;
			$ok = WebinoCRM_Accounting_Journal_Entry::update( $id, $data );
			return WebinoCRM_Service_Base::success( array( 'updated' => $ok, 'id' => $id )  );
		}
		$new_id = WebinoCRM_Accounting_Journal_Entry::create( $data, get_current_user_id() );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'جمع بدهکار و بستانکار برابر نیست یا خطای دیگر.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function journal_post( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Journal_Entry::post( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان ثبت سند وجود ندارد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function journal_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Journal_Entry::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف سند وجود ندارد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function ledger( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$account_id     = WebinoCRM_Service_Base::int_param( $params, 'account_id' );
		$fiscal_year_id = WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' );
		$date_from      = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_from', '' ) ) );
		$date_to        = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_to', '' ) ) );
		if ( $account_id <= 0 || $fiscal_year_id <= 0 ) {
		return WebinoCRM_Service_Base::error( __( 'حساب و سال مالی الزامی است.', 'webinocrm' ) );
		}
		$result = WebinoCRM_Accounting_Reports::account_turnover( $account_id, $fiscal_year_id, $date_from, $date_to );
		return WebinoCRM_Service_Base::success( $result  );
	}

	public static function report_trial_balance( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$fiscal_year_id = WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' );
		$date_from      = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_from', '' ) ) );
		$date_to        = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_to', '' ) ) );
		$data = WebinoCRM_Accounting_Reports::trial_balance( $fiscal_year_id, $date_from, $date_to );
		return WebinoCRM_Service_Base::success( $data  );
	}

	public static function report_balance_sheet( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$fiscal_year_id = WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' );
		$as_of_date     = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'as_of_date', '' ) ) );
		$data = WebinoCRM_Accounting_Reports::balance_sheet( $fiscal_year_id, $as_of_date );
		return WebinoCRM_Service_Base::success( $data  );
	}

	public static function report_profit_loss( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$fiscal_year_id = WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' );
		$date_from      = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_from', '' ) ) );
		$date_to        = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_to', '' ) ) );
		$data = WebinoCRM_Accounting_Reports::profit_and_loss( $fiscal_year_id, $date_from, $date_to );
		return WebinoCRM_Service_Base::success( $data  );
	}

	public static function report_vat( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$from = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_from', '' ) ) );
		$to   = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_to', '' ) ) );
		return WebinoCRM_Service_Base::success( WebinoCRM_Accounting_Reports::vat_summary( $from, $to ) );
	}

	public static function report_aging( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		return WebinoCRM_Service_Base::success( WebinoCRM_Accounting_Reports::aging() );
	}

	public static function report_margin( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$from = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_from', '' ) ) );
		$to   = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_to', '' ) ) );
		return WebinoCRM_Service_Base::success( WebinoCRM_Accounting_Reports::margin( $from, $to ) );
	}

	public static function report_cash_flow( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$from = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_from', '' ) ) );
		$to   = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_to', '' ) ) );
		return WebinoCRM_Service_Base::success( WebinoCRM_Accounting_Reports::cash_flow( $from, $to ) );
	}

	public static function moadian_settings_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		return WebinoCRM_Service_Base::success( array( 'settings' => WebinoCRM_Accounting_Moadian_Config::get_public() ) );
	}

	public static function moadian_settings_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$saved = WebinoCRM_Accounting_Moadian_Config::save( $params );
		return WebinoCRM_Service_Base::success( array( 'settings' => $saved, 'message' => __( 'Settings saved.', 'webinocrm' ) ) );
	}

	public static function moadian_test( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$res = WebinoCRM_Accounting_Moadian::test_connection();
		return is_wp_error( $res )
			? WebinoCRM_Service_Base::error( $res->get_error_message(), 400 )
			: WebinoCRM_Service_Base::success( array( 'ok' => true ) );
	}

	public static function moadian_jobs( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		return WebinoCRM_Service_Base::success( WebinoCRM_Accounting_Moadian::list_jobs( $params ) );
	}

	public static function moadian_process( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$limit = (int) WebinoCRM_Service_Base::param( $params, 'limit', 10 );
		return WebinoCRM_Service_Base::success( WebinoCRM_Accounting_Moadian::process_jobs( $limit ) );
	}

	public static function moadian_send( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id  = (int) WebinoCRM_Service_Base::param( $params, 'invoice_id', 0 );
		$job = WebinoCRM_Accounting_Moadian::enqueue_send( $id, (string) WebinoCRM_Service_Base::param( $params, 'action', 'send' ) );
		return is_wp_error( $job )
			? WebinoCRM_Service_Base::error( $job->get_error_message(), 400 )
			: WebinoCRM_Service_Base::success( array( 'job_id' => $job ) );
	}

	public static function hesabfa_test( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$res = WebinoCRM_Accounting_Hesabfa_Client::test_connection();
		return is_wp_error( $res )
			? WebinoCRM_Service_Base::error( $res->get_error_message(), 400 )
			: WebinoCRM_Service_Base::success( array( 'ok' => true ) );
	}

	public static function hesabfa_sync_now( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$res = WebinoCRM_Accounting_Hesabfa_Sync::pull_changes();
		return is_wp_error( $res )
			? WebinoCRM_Service_Base::error( $res->get_error_message(), 400 )
			: WebinoCRM_Service_Base::success( is_array( $res ) ? $res : array( 'ok' => true ) );
	}

	public static function settings_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$settings = class_exists( 'WebinoCRM_Settings_Handler' ) ? WebinoCRM_Settings_Handler::get_all_settings() : array();
		$out = array(
			'currency'        => isset( $settings['accounting_currency'] ) ? $settings['accounting_currency'] : 'rial',
			'fiscal_year_id'  => isset( $settings['accounting_default_fiscal_year_id'] ) ? (int) $settings['accounting_default_fiscal_year_id'] : 0,
		);
		return WebinoCRM_Service_Base::success( $out  );
	}

	public static function settings_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$current = class_exists( 'WebinoCRM_Settings_Handler' ) ? WebinoCRM_Settings_Handler::get_all_settings() : array();
		$currency = WebinoCRM_Service_Base::param( $params, 'currency', null );
		if ( null !== $currency && '' !== (string) $currency ) {
			$current['accounting_currency'] = sanitize_text_field( wp_unslash( (string) $currency ) );
		}
		$fy = WebinoCRM_Service_Base::param( $params, 'fiscal_year_id', null );
		if ( null !== $fy && '' !== (string) $fy ) {
			$current['accounting_default_fiscal_year_id'] = WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' );
		}
		WebinoCRM_Settings_Handler::update_settings( $current );
		return WebinoCRM_Service_Base::success(  );
	}

	public static function seed_chart( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$fiscal_year_id = WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' );
		if ( $fiscal_year_id <= 0 ) {
			$active = WebinoCRM_Accounting_Fiscal_Year::get_active();
			$fiscal_year_id = $active ? (int) $active->id : 0;
		}
		if ( $fiscal_year_id <= 0 ) {
		return WebinoCRM_Service_Base::error( __( 'ابتدا یک سال مالی تعریف کنید.', 'webinocrm' ) );
		}
		if ( ! class_exists( 'WebinoCRM_Accounting_Chart_Seeder' ) ) {
		return WebinoCRM_Service_Base::error( __( 'سرویس بارگذاری کدینگ در دسترس نیست.', 'webinocrm' ) );
		}
		$seeder = new WebinoCRM_Accounting_Chart_Seeder();
		$count  = $seeder->seed( $fiscal_year_id );
		return WebinoCRM_Service_Base::success( array( 'count' => $count )  );
	}

	public static function person_categories( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$tree = (bool) WebinoCRM_Service_Base::param( $params, 'tree', false );
		if ( $tree ) {
			$items = WebinoCRM_Accounting_Person_Category::get_tree( 0 );
		} else {
			$items = WebinoCRM_Accounting_Person_Category::get_all_flat();
		}
		return WebinoCRM_Service_Base::success( array( 'items' => $items )  );
	}

	public static function person_category_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$data = array(
			'name'       => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) ) ),
			'parent_id'  => WebinoCRM_Service_Base::int_param( $params, 'parent_id' ),
			'sort_order' => WebinoCRM_Service_Base::int_param( $params, 'sort_order' ),
		);
		if ( $id > 0 ) {
			$ok = WebinoCRM_Accounting_Person_Category::update( $id, $data );
			return WebinoCRM_Service_Base::success( array( 'updated' => $ok, 'id' => $id )  );
		}
		$new_id = WebinoCRM_Accounting_Person_Category::create( $data );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ایجاد دسته.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function person_category_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Person_Category::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف دسته وجود ندارد (دارای زیردسته یا شخص است).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function persons_list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$args = array(
			'category_id'  => WebinoCRM_Service_Base::int_param( $params, 'category_id' ),
			'person_type'  => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'person_type', '' ) ) ),
			'search'       => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'search', '' ) ) ),
			'is_active'    => WebinoCRM_Service_Base::param( $params, 'is_active', '' ) !== '' ? WebinoCRM_Service_Base::int_param( $params, 'is_active' ) : '',
			'per_page'     => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'per_page', 50 ) ),
			'page'         => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'page', 1 ) ),
			'orderby'      => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'orderby', 'id' ) ) ),
			'order'        => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'order', 'DESC' ) ) ),
		);
		if ( empty( $args['category_id'] ) ) {
			unset( $args['category_id'] );
		}
		if ( empty( $args['person_type'] ) ) {
			unset( $args['person_type'] );
		}
		if ( empty( $args['search'] ) ) {
			unset( $args['search'] );
		}
		if ( $args['is_active'] === '' ) {
			unset( $args['is_active'] );
		}
		$result = WebinoCRM_Accounting_Person::get_list( $args );
		return WebinoCRM_Service_Base::success( $result  );
	}

	public static function person_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$person = WebinoCRM_Accounting_Person::get( $id );
		if ( ! $person ) {
		return WebinoCRM_Service_Base::error( __( 'شخص یافت نشد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'person' => $person )  );
	}

	public static function person_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$cat_id = ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'category_id' ) ) > 0 ? $_pid : null );
		$grp_id = ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'group_id' ) ) > 0 ? $_pid : null );
		$pl_id  = ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'default_price_list_id' ) ) > 0 ? $_pid : null );
		$data = array(
			'name'                   => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) ) ),
			'code'                   => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'code', null ) ) ),
			'category_id'            => $cat_id > 0 ? $cat_id : null,
			'credit_limit'           => ( WebinoCRM_Service_Base::param( $params, 'credit_limit' ) !== '' && WebinoCRM_Service_Base::param( $params, 'credit_limit' ) !== null ? floatval( WebinoCRM_Service_Base::param( $params, 'credit_limit' ) ) : null ),
			'national_id'            => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'national_id', null ) ) ),
			'economic_code'          => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'economic_code', null ) ) ),
			'registration_no'        => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'registration_no', null ) ) ),
			'phone'                  => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'phone', null ) ) ),
			'mobile'                 => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'mobile', null ) ) ),
			'extra_phones'           => sanitize_textarea_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'extra_phones', null ) ) ),
			'address'                => sanitize_textarea_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'address', null ) ) ),
			'person_type'            => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'person_type', 'both' ) ) ),
			'group_id'               => $grp_id > 0 ? $grp_id : null,
			'image_url'              => esc_url_raw( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'image_url', '' ) ) ) ?: null,
			'note'                   => sanitize_textarea_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'note', null ) ) ),
			'default_price_list_id'  => $pl_id > 0 ? $pl_id : null,
			'is_active'              => (int) ( (bool) WebinoCRM_Service_Base::param( $params, 'is_active', false ) ),
		);
		if ( $id > 0 ) {
			$ok = WebinoCRM_Accounting_Person::update( $id, $data );
			return WebinoCRM_Service_Base::success( array( 'updated' => $ok, 'id' => $id )  );
		}
		$new_id = WebinoCRM_Accounting_Person::create( $data );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ایجاد شخص.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function person_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Person::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف شخص وجود ندارد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function product_categories( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$tree = (bool) WebinoCRM_Service_Base::param( $params, 'tree', false );
		if ( $tree ) {
			$items = WebinoCRM_Accounting_Product_Category::get_tree( 0 );
		} else {
			$items = WebinoCRM_Accounting_Product_Category::get_all_flat();
		}
		return WebinoCRM_Service_Base::success( array( 'items' => $items )  );
	}

	public static function product_category_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$data = array(
			'name'       => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) ) ),
			'parent_id'  => WebinoCRM_Service_Base::int_param( $params, 'parent_id' ),
			'sort_order' => WebinoCRM_Service_Base::int_param( $params, 'sort_order' ),
		);
		if ( $id > 0 ) {
			$ok = WebinoCRM_Accounting_Product_Category::update( $id, $data );
			return WebinoCRM_Service_Base::success( array( 'updated' => $ok, 'id' => $id )  );
		}
		$new_id = WebinoCRM_Accounting_Product_Category::create( $data );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ایجاد دسته کالا.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function product_category_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Product_Category::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف دسته وجود ندارد (دارای زیردسته یا کالا است).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function units_list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$items = WebinoCRM_Accounting_Unit::get_all();
		return WebinoCRM_Service_Base::success( array( 'items' => $items )  );
	}

	public static function unit_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$data = array(
			'name'          => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) ) ),
			'symbol'        => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'symbol', null ) ) ),
			'is_main'       => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'is_main', 1 ) ),
			'base_unit_id'  => ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'base_unit_id' ) ) > 0 ? $_pid : null ),
			'ratio_to_base' => floatval( WebinoCRM_Service_Base::param( $params, 'ratio_to_base', 1 ) ),
		);
		if ( $id > 0 ) {
			$ok = WebinoCRM_Accounting_Unit::update( $id, $data );
			return WebinoCRM_Service_Base::success( array( 'updated' => $ok, 'id' => $id )  );
		}
		$new_id = WebinoCRM_Accounting_Unit::create( $data );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ایجاد واحد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function unit_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Unit::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف واحد وجود ندارد (در کالاها استفاده شده).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function price_lists( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$items = WebinoCRM_Accounting_Price_List::get_all();
		return WebinoCRM_Service_Base::success( array( 'items' => $items )  );
	}

	public static function price_list_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$list = WebinoCRM_Accounting_Price_List::get( $id );
		if ( ! $list ) {
		return WebinoCRM_Service_Base::error( __( 'لیست قیمت یافت نشد.', 'webinocrm' ) );
		}
		$items = WebinoCRM_Accounting_Price_List::get_items( $id );
		return WebinoCRM_Service_Base::success( array( 'price_list' => $list, 'items' => $items )  );
	}

	public static function price_list_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$data = array(
			'name'        => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) ) ),
			'description' => sanitize_textarea_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'description', null ) ) ),
			'is_default'  => (int) ( (bool) WebinoCRM_Service_Base::param( $params, 'is_default', false ) ),
			'valid_from'  => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'valid_from', null ) ) ),
			'valid_to'    => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'valid_to', null ) ) ),
		);
		if ( $id > 0 ) {
			$ok = WebinoCRM_Accounting_Price_List::update( $id, $data );
			return WebinoCRM_Service_Base::success( array( 'updated' => $ok, 'id' => $id )  );
		}
		$new_id = WebinoCRM_Accounting_Price_List::create( $data );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ایجاد لیست قیمت.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function price_list_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Price_List::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف لیست قیمت وجود ندارد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function price_list_items( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$price_list_id = WebinoCRM_Service_Base::int_param( $params, 'price_list_id' );
		if ( $price_list_id <= 0 ) {
		return WebinoCRM_Service_Base::error( __( 'لیست قیمت نامعتبر است.', 'webinocrm' ) );
		}
		$items = WebinoCRM_Accounting_Price_List::get_items( $price_list_id );
		return WebinoCRM_Service_Base::success( array( 'items' => $items )  );
	}

	public static function price_list_items_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$price_list_id = WebinoCRM_Service_Base::int_param( $params, 'price_list_id' );
		if ( $price_list_id <= 0 ) {
		return WebinoCRM_Service_Base::error( __( 'لیست قیمت نامعتبر است.', 'webinocrm' ) );
		}
		$items = array();
		$raw_items = WebinoCRM_Service_Base::param( $params, 'items', null );
		if ( is_array( $raw_items ) ) {
			$items = $raw_items;
		} elseif ( is_string( $raw_items ) && '' !== $raw_items ) {
			$decoded = json_decode( sanitize_text_field( wp_unslash( $raw_items ) ), true );
			$items   = is_array( $decoded ) ? $decoded : array();
		}
		// Replace semantics: remove existing items then insert submitted ones.
		global $wpdb;
		$items_table = $wpdb->prefix . 'webinocrm_accounting_price_list_items';
		$wpdb->delete( $items_table, array( 'price_list_id' => $price_list_id ), array( '%d' ) );
		foreach ( $items as $row ) {
			$product_id = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
			$price      = isset( $row['price'] ) ? floatval( $row['price'] ) : 0;
			$min_qty    = isset( $row['min_quantity'] ) ? floatval( $row['min_quantity'] ) : 1;
			if ( $product_id > 0 ) {
				WebinoCRM_Accounting_Price_List::set_item( $price_list_id, $product_id, $price, $min_qty );
			}
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function products_list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$args = array(
			'category_id' => WebinoCRM_Service_Base::int_param( $params, 'category_id' ),
			'search'      => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'search', '' ) ) ),
			'is_active'   => WebinoCRM_Service_Base::param( $params, 'is_active', '' ) !== '' ? WebinoCRM_Service_Base::int_param( $params, 'is_active' ) : '',
			'per_page'    => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'per_page', 50 ) ),
			'page'        => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'page', 1 ) ),
			'orderby'     => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'orderby', 'id' ) ) ),
			'order'       => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'order', 'DESC' ) ) ),
		);
		if ( empty( $args['category_id'] ) ) {
			unset( $args['category_id'] );
		}
		if ( empty( $args['search'] ) ) {
			unset( $args['search'] );
		}
		if ( $args['is_active'] === '' ) {
			unset( $args['is_active'] );
		}
		$result = WebinoCRM_Accounting_Product::get_list( $args );
		return WebinoCRM_Service_Base::success( $result  );
	}

	public static function product_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$product = WebinoCRM_Accounting_Product::get( $id );
		if ( ! $product ) {
		return WebinoCRM_Service_Base::error( __( 'کالا یافت نشد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'product' => $product )  );
	}

	public static function product_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$cat_id   = ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'category_id' ) ) > 0 ? $_pid : null );
		$sub_id   = ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'sub_unit_id' ) ) > 0 ? $_pid : null );
		$data = array(
			'code'                  => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'code', '' ) ) ),
			'name'                  => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) ) ),
			'category_id'           => $cat_id > 0 ? $cat_id : null,
			'main_unit_id'          => WebinoCRM_Service_Base::int_param( $params, 'main_unit_id' ),
			'sub_unit_id'           => $sub_id > 0 ? $sub_id : null,
			'sub_unit_ratio'        => floatval( WebinoCRM_Service_Base::param( $params, 'sub_unit_ratio', 1 ) ),
			'purchase_price'        => ( WebinoCRM_Service_Base::param( $params, 'purchase_price' ) !== '' && WebinoCRM_Service_Base::param( $params, 'purchase_price' ) !== null ? floatval( WebinoCRM_Service_Base::param( $params, 'purchase_price' ) ) : null ),
			'barcode'               => sanitize_textarea_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'barcode', null ) ) ),
			'inventory_controlled'  => (int) ( (bool) WebinoCRM_Service_Base::param( $params, 'inventory_controlled', false ) ),
			'reorder_point'         => ( WebinoCRM_Service_Base::param( $params, 'reorder_point' ) !== '' && WebinoCRM_Service_Base::param( $params, 'reorder_point' ) !== null ? floatval( WebinoCRM_Service_Base::param( $params, 'reorder_point' ) ) : null ),
			'tax_rate_sales'        => ( WebinoCRM_Service_Base::param( $params, 'tax_rate_sales' ) !== '' && WebinoCRM_Service_Base::param( $params, 'tax_rate_sales' ) !== null ? floatval( WebinoCRM_Service_Base::param( $params, 'tax_rate_sales' ) ) : null ),
			'tax_rate_purchase'     => ( WebinoCRM_Service_Base::param( $params, 'tax_rate_purchase' ) !== '' && WebinoCRM_Service_Base::param( $params, 'tax_rate_purchase' ) !== null ? floatval( WebinoCRM_Service_Base::param( $params, 'tax_rate_purchase' ) ) : null ),
			'image_url'             => esc_url_raw( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'image_url', '' ) ) ) ?: null,
			'note'                  => sanitize_textarea_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'note', null ) ) ),
			'is_active'             => (int) ( (bool) WebinoCRM_Service_Base::param( $params, 'is_active', false ) ),
		);
		if ( $id > 0 ) {
			$ok = WebinoCRM_Accounting_Product::update( $id, $data );
			return WebinoCRM_Service_Base::success( array( 'updated' => $ok, 'id' => $id )  );
		}
		$new_id = WebinoCRM_Accounting_Product::create( $data );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ایجاد کالا (احتمالاً کد تکراری است).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function product_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Product::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف کالا وجود ندارد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function user_defaults_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$defaults = WebinoCRM_Accounting_User_Defaults::get();
		return WebinoCRM_Service_Base::success( array( 'defaults' => $defaults )  );
	}

	public static function user_defaults_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$data = array(
			'default_invoice_person_id' => ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'default_invoice_person_id' ) ) > 0 ? $_pid : null ),
			'default_price_list_id'    => ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'default_price_list_id' ) ) > 0 ? $_pid : null ),
		);
		$ok = WebinoCRM_Accounting_User_Defaults::save( $data );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ذخیره پیش‌فرض‌ها.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function invoice_list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$args = array(
			'fiscal_year_id' => WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' ),
			'person_id'      => WebinoCRM_Service_Base::int_param( $params, 'person_id' ),
			'invoice_type'   => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'invoice_type', '' ) ) ),
			'status'         => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'status', '' ) ) ),
			'date_from'      => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_from', '' ) ) ),
			'date_to'        => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_to', '' ) ) ),
			'per_page'       => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'per_page', 20 ) ),
			'page'           => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'page', 1 ) ),
		);
		if ( $args['fiscal_year_id'] <= 0 ) {
			$active = WebinoCRM_Accounting_Fiscal_Year::get_active();
			$args['fiscal_year_id'] = $active ? (int) $active->id : 0;
		}
		foreach ( array( 'person_id', 'invoice_type', 'status', 'date_from', 'date_to' ) as $k ) {
			if ( empty( $args[ $k ] ) ) {
				unset( $args[ $k ] );
			}
		}
		$result = WebinoCRM_Accounting_Invoice::get_list( $args );
		return WebinoCRM_Service_Base::success( $result  );
	}

	public static function invoice_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$inv = WebinoCRM_Accounting_Invoice::get( $id );
		if ( ! $inv ) {
		return WebinoCRM_Service_Base::error( __( 'فاکتور یافت نشد.', 'webinocrm' ) );
		}
		$lines = WebinoCRM_Accounting_Invoice_Line::get_lines( $id );
		return WebinoCRM_Service_Base::success( array( 'invoice' => $inv, 'lines' => $lines )  );
	}

	public static function invoice_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$lines = array();
		$raw_lines = WebinoCRM_Service_Base::param( $params, 'lines', null );
		if ( is_array( $raw_lines ) ) {
			$lines = $raw_lines;
		} elseif ( is_string( $raw_lines ) && '' !== $raw_lines ) {
			$decoded = json_decode( sanitize_text_field( wp_unslash( $raw_lines ) ), true );
			$lines   = is_array( $decoded ) ? $decoded : array();
		}
		$lines_processed = array();
		foreach ( $lines as $l ) {
			$lines_processed[] = array(
				'product_id'        => isset( $l['product_id'] ) ? (int) $l['product_id'] : 0,
				'quantity'          => isset( $l['quantity'] ) ? floatval( $l['quantity'] ) : 1,
				'unit_id'           => isset( $l['unit_id'] ) ? (int) $l['unit_id'] : null,
				'unit_price'        => isset( $l['unit_price'] ) ? floatval( $l['unit_price'] ) : 0,
				'discount_percent'  => isset( $l['discount_percent'] ) ? floatval( $l['discount_percent'] ) : null,
				'discount_amount'   => isset( $l['discount_amount'] ) ? floatval( $l['discount_amount'] ) : null,
				'tax_percent'       => isset( $l['tax_percent'] ) ? floatval( $l['tax_percent'] ) : null,
				'tax_amount'        => isset( $l['tax_amount'] ) ? floatval( $l['tax_amount'] ) : null,
				'description'       => isset( $l['description'] ) ? sanitize_textarea_field( wp_unslash( $l['description'] ) ) : null,
			);
		}
		$lines = $lines_processed;
		$data = array(
			'fiscal_year_id'   => WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' ),
			'invoice_no'       => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'invoice_no', '' ) ) ),
			'invoice_type'     => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'invoice_type', 'sales' ) ) ),
			'person_id'        => WebinoCRM_Service_Base::int_param( $params, 'person_id' ),
			'invoice_date'     => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'invoice_date', '' ) ) ),
			'due_date'         => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'due_date', null ) ) ),
			'seller_id'        => ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'seller_id' ) ) > 0 ? $_pid : null ),
			'project_id'       => ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'project_id' ) ) > 0 ? $_pid : null ),
			'shipping_cost'    => ( WebinoCRM_Service_Base::param( $params, 'shipping_cost' ) !== '' && WebinoCRM_Service_Base::param( $params, 'shipping_cost' ) !== null ? floatval( WebinoCRM_Service_Base::param( $params, 'shipping_cost' ) ) : null ),
			'extra_additions'  => ( WebinoCRM_Service_Base::param( $params, 'extra_additions' ) !== '' && WebinoCRM_Service_Base::param( $params, 'extra_additions' ) !== null ? floatval( WebinoCRM_Service_Base::param( $params, 'extra_additions' ) ) : null ),
			'extra_deductions' => ( WebinoCRM_Service_Base::param( $params, 'extra_deductions' ) !== '' && WebinoCRM_Service_Base::param( $params, 'extra_deductions' ) !== null ? floatval( WebinoCRM_Service_Base::param( $params, 'extra_deductions' ) ) : null ),
		);
		if ( $id > 0 ) {
			$ok = WebinoCRM_Accounting_Invoice::update( $id, $data );
			if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان ویرایش فاکتور وجود ندارد (فقط پیش‌نویس).', 'webinocrm' ) );
			}
			// Only replace lines when explicitly sent (avoid wiping lines on header-only update).
			if ( null !== $raw_lines ) {
				WebinoCRM_Accounting_Invoice_Line::save_lines( $id, $lines );
			}
			return WebinoCRM_Service_Base::success( array( 'updated' => true, 'id' => $id )  );
		}
		$new_id = WebinoCRM_Accounting_Invoice::create( $data, get_current_user_id() );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ایجاد فاکتور (سال مالی یا طرف حساب نامعتبر).', 'webinocrm' ) );
		}
		WebinoCRM_Accounting_Invoice_Line::save_lines( $new_id, $lines );
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function invoice_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Invoice::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف فاکتور وجود ندارد (فقط پیش‌نویس).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function invoice_next_number( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$fiscal_year_id = WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' );
		$invoice_type   = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'invoice_type', 'sales' ) ) );
		if ( $fiscal_year_id <= 0 ) {
			$active = WebinoCRM_Accounting_Fiscal_Year::get_active();
			$fiscal_year_id = $active ? (int) $active->id : 0;
		}
		if ( $fiscal_year_id <= 0 ) {
		return WebinoCRM_Service_Base::error( __( 'سال مالی انتخاب نشده.', 'webinocrm' ) );
		}
		$next = WebinoCRM_Accounting_Invoice::get_next_number( $fiscal_year_id, $invoice_type );
		return WebinoCRM_Service_Base::success( array( 'invoice_no' => $next )  );
	}

	public static function invoice_confirm( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Invoice::confirm( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان تأیید فاکتور وجود ندارد (فقط پیش‌نویس قابل تأیید است).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function cash_accounts_list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$type = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'type', '' ) ) );
		$is_active = WebinoCRM_Service_Base::param( $params, 'is_active', '' ) !== '' ? WebinoCRM_Service_Base::int_param( $params, 'is_active' ) : '';
		$args = array();
		if ( $type !== '' ) {
			$args['type'] = $type;
		}
		if ( $is_active !== '' ) {
			$args['is_active'] = $is_active;
		}
		$items = WebinoCRM_Accounting_Cash_Account::get_all( $args );
		return WebinoCRM_Service_Base::success( array( 'items' => $items )  );
	}

	public static function cash_account_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$item = WebinoCRM_Accounting_Cash_Account::get( $id );
		if ( ! $item ) {
		return WebinoCRM_Service_Base::error( __( 'حساب یافت نشد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'cash_account' => $item )  );
	}

	public static function cash_account_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$data = array(
			'name'             => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'name', '' ) ) ),
			'type'             => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'type', 'bank' ) ) ),
			'code'             => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'code', null ) ) ),
			'description'      => sanitize_textarea_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'description', null ) ) ),
			'card_no'          => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'card_no', null ) ) ),
			'sheba'            => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'sheba', null ) ) ),
			'chart_account_id' => ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'chart_account_id' ) ) > 0 ? $_pid : null ),
			'is_active'        => (int) ( (bool) WebinoCRM_Service_Base::param( $params, 'is_active', false ) ),
			'sort_order'       => WebinoCRM_Service_Base::int_param( $params, 'sort_order' ),
		);
		if ( $id > 0 ) {
			$ok = WebinoCRM_Accounting_Cash_Account::update( $id, $data );
			return WebinoCRM_Service_Base::success( array( 'updated' => $ok, 'id' => $id )  );
		}
		$new_id = WebinoCRM_Accounting_Cash_Account::create( $data );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ایجاد حساب.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function cash_account_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Cash_Account::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف حساب وجود ندارد (در رسید/پرداخت استفاده شده).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function receipt_voucher_list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$args = array(
			'fiscal_year_id'   => WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' ),
			'type'             => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'type', '' ) ) ),
			'cash_account_id'  => WebinoCRM_Service_Base::int_param( $params, 'cash_account_id' ),
			'person_id'        => WebinoCRM_Service_Base::int_param( $params, 'person_id' ),
			'status'           => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'status', '' ) ) ),
			'date_from'        => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_from', '' ) ) ),
			'date_to'          => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'date_to', '' ) ) ),
			'per_page'         => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'per_page', 20 ) ),
			'page'             => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'page', 1 ) ),
		);
		if ( $args['fiscal_year_id'] <= 0 ) {
			$active = WebinoCRM_Accounting_Fiscal_Year::get_active();
			$args['fiscal_year_id'] = $active ? (int) $active->id : 0;
		}
		foreach ( array( 'type', 'cash_account_id', 'person_id', 'status', 'date_from', 'date_to' ) as $k ) {
			if ( empty( $args[ $k ] ) ) {
				unset( $args[ $k ] );
			}
		}
		$result = WebinoCRM_Accounting_Receipt_Voucher::get_list( $args );
		return WebinoCRM_Service_Base::success( $result  );
	}

	public static function receipt_voucher_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$voucher = WebinoCRM_Accounting_Receipt_Voucher::get( $id );
		if ( ! $voucher ) {
		return WebinoCRM_Service_Base::error( __( 'رسید/پرداخت یافت نشد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'voucher' => $voucher )  );
	}

	public static function receipt_voucher_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$data = array(
			'fiscal_year_id'              => WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' ),
			'voucher_no'                  => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'voucher_no', '' ) ) ),
			'voucher_date'                => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'voucher_date', '' ) ) ),
			'type'                        => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'type', 'receipt' ) ) ),
			'cash_account_id'             => WebinoCRM_Service_Base::int_param( $params, 'cash_account_id' ),
			'transfer_to_cash_account_id' => ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'transfer_to_cash_account_id' ) ) > 0 ? $_pid : null ),
			'person_id'                   => ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'person_id' ) ) > 0 ? $_pid : null ),
			'amount'                      => floatval( WebinoCRM_Service_Base::param( $params, 'amount', 0 ) ),
			'description'                 => sanitize_textarea_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'description', null ) ) ),
			'invoice_id'                  => ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'invoice_id' ) ) > 0 ? $_pid : null ),
			'project_id'                  => ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'project_id' ) ) > 0 ? $_pid : null ),
			'bank_fee'                    => ( WebinoCRM_Service_Base::param( $params, 'bank_fee' ) !== '' && WebinoCRM_Service_Base::param( $params, 'bank_fee' ) !== null ? floatval( WebinoCRM_Service_Base::param( $params, 'bank_fee' ) ) : null ),
		);
		if ( $id > 0 ) {
			$ok = WebinoCRM_Accounting_Receipt_Voucher::update( $id, $data );
			if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان ویرایش وجود ندارد (فقط پیش‌نویس).', 'webinocrm' ) );
			}
			return WebinoCRM_Service_Base::success( array( 'updated' => true, 'id' => $id )  );
		}
		$new_id = WebinoCRM_Accounting_Receipt_Voucher::create( $data, get_current_user_id() );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ایجاد رسید/پرداخت (حساب یا سال مالی نامعتبر).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function receipt_voucher_post( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Receipt_Voucher::post( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان ثبت رسید/پرداخت وجود ندارد (فقط پیش‌نویس).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function receipt_voucher_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Receipt_Voucher::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف وجود ندارد (فقط پیش‌نویس).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function receipt_voucher_next_number( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$fiscal_year_id = WebinoCRM_Service_Base::int_param( $params, 'fiscal_year_id' );
		if ( $fiscal_year_id <= 0 ) {
			$active = WebinoCRM_Accounting_Fiscal_Year::get_active();
			$fiscal_year_id = $active ? (int) $active->id : 0;
		}
		if ( $fiscal_year_id <= 0 ) {
		return WebinoCRM_Service_Base::error( __( 'سال مالی انتخاب نشده.', 'webinocrm' ) );
		}
		$next = WebinoCRM_Accounting_Receipt_Voucher::get_next_number( $fiscal_year_id );
		return WebinoCRM_Service_Base::success( array( 'voucher_no' => $next )  );
	}

	public static function check_list( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$args = array(
			'type'             => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'type', '' ) ) ),
			'person_id'        => WebinoCRM_Service_Base::int_param( $params, 'person_id' ),
			'cash_account_id'  => WebinoCRM_Service_Base::int_param( $params, 'cash_account_id' ),
			'status'           => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'status', '' ) ) ),
			'due_from'         => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'due_from', '' ) ) ),
			'due_to'           => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'due_to', '' ) ) ),
			'check_no'         => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'check_no', '' ) ) ),
			'per_page'         => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'per_page', 20 ) ),
			'page'             => max( 0, (int) WebinoCRM_Service_Base::param( $params, 'page', 1 ) ),
		);
		foreach ( array( 'type', 'person_id', 'cash_account_id', 'status', 'due_from', 'due_to', 'check_no' ) as $k ) {
			if ( $k === 'person_id' || $k === 'cash_account_id' ) {
				if ( $args[ $k ] <= 0 ) {
					unset( $args[ $k ] );
				}
			} elseif ( empty( $args[ $k ] ) ) {
				unset( $args[ $k ] );
			}
		}
		$result = WebinoCRM_Accounting_Check::get_list( $args );
		return WebinoCRM_Service_Base::success( $result  );
	}

	public static function check_get( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$check = WebinoCRM_Accounting_Check::get( $id );
		if ( ! $check ) {
		return WebinoCRM_Service_Base::error( __( 'چک یافت نشد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'check' => $check )  );
	}

	public static function check_save( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$data = array(
			'type'               => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'type', 'receivable' ) ) ),
			'check_no'           => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'check_no', '' ) ) ),
			'check_date'         => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'check_date', null ) ) ),
			'amount'             => floatval( WebinoCRM_Service_Base::param( $params, 'amount', 0 ) ),
			'cash_account_id'    => WebinoCRM_Service_Base::int_param( $params, 'cash_account_id' ),
			'person_id'          => WebinoCRM_Service_Base::int_param( $params, 'person_id' ),
			'due_date'           => sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'due_date', '' ) ) ),
			'description'        => sanitize_textarea_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'description', null ) ) ),
			'receipt_voucher_id' => ( ( $_pid = WebinoCRM_Service_Base::int_param( $params, 'receipt_voucher_id' ) ) > 0 ? $_pid : null ),
		);
		if ( $id > 0 ) {
			$ok = WebinoCRM_Accounting_Check::update( $id, $data );
			if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ویرایش چک.', 'webinocrm' ) );
			}
			return WebinoCRM_Service_Base::success( array( 'updated' => true, 'id' => $id )  );
		}
		$data['status'] = 'in_safe';
		$new_id = WebinoCRM_Accounting_Check::create( $data );
		if ( $new_id === false ) {
		return WebinoCRM_Service_Base::error( __( 'خطا در ثبت چک (شماره، بانک، طرف حساب و سررسید الزامی است).', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success( array( 'id' => $new_id )  );
	}

	public static function check_delete( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$ok = WebinoCRM_Accounting_Check::delete( $id );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'امکان حذف چک وجود ندارد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	public static function check_set_status( array $params ) {
		WebinoCRM_Service_Base::ensure_dependencies();
		$access = self::verify_accounting_access( $params );
		if ( is_array( $access ) ) {
			return $access;
		}
		$id = WebinoCRM_Service_Base::int_param( $params, 'id' );
		$status = sanitize_text_field( wp_unslash( (string) WebinoCRM_Service_Base::param( $params, 'status', '' ) ) );
		$valid = array( 'in_safe', 'collected', 'returned', 'spent' );
		if ( ! in_array( $status, $valid, true ) ) {
		return WebinoCRM_Service_Base::error( __( 'وضعیت نامعتبر است.', 'webinocrm' ) );
		}
		$ok = WebinoCRM_Accounting_Check::set_status( $id, $status );
		if ( ! $ok ) {
		return WebinoCRM_Service_Base::error( __( 'تغییر وضعیت انجام نشد.', 'webinocrm' ) );
		}
		return WebinoCRM_Service_Base::success(  );
	}

	/**
	 * REST segment dispatcher.
	 *
	 * @param array<string,mixed> $params Must include segment.
	 * @return array<string,mixed>
	 */
	public static function by_segment( array $params ) {
		$segment = isset( $params['segment'] ) ? sanitize_key( str_replace( '-', '_', (string) $params['segment'] ) ) : '';
		if ( '' === $segment || ! method_exists( __CLASS__, $segment ) ) {
			return WebinoCRM_Service_Base::error( __( 'Unknown accounting action.', 'webinocrm' ), 404 );
		}
		return call_user_func( array( __CLASS__, $segment ), $params );
	}

	/**
	 * Alias for products_list (REST /products and scm segments).
	 *
	 * @param array<string,mixed> $params Request params.
	 * @return array<string,mixed>
	 */
	public static function products( array $params ) {
		return self::products_list( $params );
	}

	public static function register_actions() {
		self::register_map( array(
				'webinocrm_accounting_fiscal_years' => array( __CLASS__, 'fiscal_years' ),
				'webinocrm_accounting_fiscal_year_save' => array( __CLASS__, 'fiscal_year_save' ),
				'webinocrm_accounting_fiscal_year_delete' => array( __CLASS__, 'fiscal_year_delete' ),
				'webinocrm_accounting_chart_list' => array( __CLASS__, 'chart_list' ),
				'webinocrm_accounting_chart_save' => array( __CLASS__, 'chart_save' ),
				'webinocrm_accounting_chart_delete' => array( __CLASS__, 'chart_delete' ),
				'webinocrm_accounting_journal_list' => array( __CLASS__, 'journal_list' ),
				'webinocrm_accounting_journal_get' => array( __CLASS__, 'journal_get' ),
				'webinocrm_accounting_journal_save' => array( __CLASS__, 'journal_save' ),
				'webinocrm_accounting_journal_post' => array( __CLASS__, 'journal_post' ),
				'webinocrm_accounting_journal_delete' => array( __CLASS__, 'journal_delete' ),
				'webinocrm_accounting_ledger' => array( __CLASS__, 'ledger' ),
				'webinocrm_accounting_report_trial_balance' => array( __CLASS__, 'report_trial_balance' ),
				'webinocrm_accounting_report_balance_sheet' => array( __CLASS__, 'report_balance_sheet' ),
				'webinocrm_accounting_report_profit_loss' => array( __CLASS__, 'report_profit_loss' ),
				'webinocrm_accounting_settings_get' => array( __CLASS__, 'settings_get' ),
				'webinocrm_accounting_settings_save' => array( __CLASS__, 'settings_save' ),
				'webinocrm_accounting_seed_chart' => array( __CLASS__, 'seed_chart' ),
				'webinocrm_accounting_person_categories' => array( __CLASS__, 'person_categories' ),
				'webinocrm_accounting_person_category_save' => array( __CLASS__, 'person_category_save' ),
				'webinocrm_accounting_person_category_delete' => array( __CLASS__, 'person_category_delete' ),
				'webinocrm_accounting_persons_list' => array( __CLASS__, 'persons_list' ),
				'webinocrm_accounting_person_get' => array( __CLASS__, 'person_get' ),
				'webinocrm_accounting_person_save' => array( __CLASS__, 'person_save' ),
				'webinocrm_accounting_person_delete' => array( __CLASS__, 'person_delete' ),
				'webinocrm_accounting_product_categories' => array( __CLASS__, 'product_categories' ),
				'webinocrm_accounting_product_category_save' => array( __CLASS__, 'product_category_save' ),
				'webinocrm_accounting_product_category_delete' => array( __CLASS__, 'product_category_delete' ),
				'webinocrm_accounting_units_list' => array( __CLASS__, 'units_list' ),
				'webinocrm_accounting_unit_save' => array( __CLASS__, 'unit_save' ),
				'webinocrm_accounting_unit_delete' => array( __CLASS__, 'unit_delete' ),
				'webinocrm_accounting_price_lists' => array( __CLASS__, 'price_lists' ),
				'webinocrm_accounting_price_list_get' => array( __CLASS__, 'price_list_get' ),
				'webinocrm_accounting_price_list_save' => array( __CLASS__, 'price_list_save' ),
				'webinocrm_accounting_price_list_delete' => array( __CLASS__, 'price_list_delete' ),
				'webinocrm_accounting_price_list_items' => array( __CLASS__, 'price_list_items' ),
				'webinocrm_accounting_price_list_items_save' => array( __CLASS__, 'price_list_items_save' ),
				'webinocrm_accounting_products_list' => array( __CLASS__, 'products_list' ),
				'webinocrm_accounting_product_get' => array( __CLASS__, 'product_get' ),
				'webinocrm_accounting_product_save' => array( __CLASS__, 'product_save' ),
				'webinocrm_accounting_product_delete' => array( __CLASS__, 'product_delete' ),
				'webinocrm_accounting_user_defaults_get' => array( __CLASS__, 'user_defaults_get' ),
				'webinocrm_accounting_user_defaults_save' => array( __CLASS__, 'user_defaults_save' ),
				'webinocrm_accounting_invoice_list' => array( __CLASS__, 'invoice_list' ),
				'webinocrm_accounting_invoice_get' => array( __CLASS__, 'invoice_get' ),
				'webinocrm_accounting_invoice_save' => array( __CLASS__, 'invoice_save' ),
				'webinocrm_accounting_invoice_delete' => array( __CLASS__, 'invoice_delete' ),
				'webinocrm_accounting_invoice_next_number' => array( __CLASS__, 'invoice_next_number' ),
				'webinocrm_accounting_invoice_confirm' => array( __CLASS__, 'invoice_confirm' ),
				'webinocrm_accounting_cash_accounts_list' => array( __CLASS__, 'cash_accounts_list' ),
				'webinocrm_accounting_cash_account_get' => array( __CLASS__, 'cash_account_get' ),
				'webinocrm_accounting_cash_account_save' => array( __CLASS__, 'cash_account_save' ),
				'webinocrm_accounting_cash_account_delete' => array( __CLASS__, 'cash_account_delete' ),
				'webinocrm_accounting_receipt_voucher_list' => array( __CLASS__, 'receipt_voucher_list' ),
				'webinocrm_accounting_receipt_voucher_get' => array( __CLASS__, 'receipt_voucher_get' ),
				'webinocrm_accounting_receipt_voucher_save' => array( __CLASS__, 'receipt_voucher_save' ),
				'webinocrm_accounting_receipt_voucher_post' => array( __CLASS__, 'receipt_voucher_post' ),
				'webinocrm_accounting_receipt_voucher_delete' => array( __CLASS__, 'receipt_voucher_delete' ),
				'webinocrm_accounting_receipt_voucher_next_number' => array( __CLASS__, 'receipt_voucher_next_number' ),
				'webinocrm_accounting_check_list' => array( __CLASS__, 'check_list' ),
				'webinocrm_accounting_check_get' => array( __CLASS__, 'check_get' ),
				'webinocrm_accounting_check_save' => array( __CLASS__, 'check_save' ),
				'webinocrm_accounting_check_delete' => array( __CLASS__, 'check_delete' ),
				'webinocrm_accounting_check_set_status' => array( __CLASS__, 'check_set_status' ),
		) );
	}

}
