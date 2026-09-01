<?php
/**
 * Official Hesabfa API v1 client (api.hesabfa.com).
 *
 * All requests are POST JSON. Auth: apiKey + (loginToken OR userId+password).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Low-level Hesabfa HTTP client with rate limiting.
 */
final class WebinoCRM_Accounting_Hesabfa_Client {
	const BASE = 'https://api.hesabfa.com/v1/';
	const RATE_OPTION = 'webino_acc_hesabfa_rate';
	const MAX_PER_MINUTE = 200;

	/**
	 * Generic POST to resource/action.
	 *
	 * @param string               $method Path like contact/get.
	 * @param array<string,mixed>  $data   Extra body (auth merged).
	 * @param bool                 $write  Whether to attach requestUniqueId.
	 * @return array<string,mixed>|WP_Error Decoded body with Success/Result or error.
	 */
	public static function request( $method, array $data = array(), $write = false ) {
		$method = ltrim( (string) $method, '/' );
		if ( '' === $method ) {
			return new WP_Error( 'hesabfa_method', __( 'Hesabfa method is required.', 'webinocrm' ) );
		}

		$cfg = WebinoCRM_Accounting_Moadian_Config::get();
		if ( empty( $cfg['hesabfa_enabled'] ) && empty( $cfg['hesabfa_api_key'] ) ) {
			return new WP_Error( 'hesabfa_disabled', __( 'Hesabfa is not configured.', 'webinocrm' ) );
		}

		$api_key = (string) ( $cfg['hesabfa_api_key'] ?? '' );
		if ( '' === $api_key ) {
			return new WP_Error( 'hesabfa_api_key', __( 'Hesabfa API key is missing.', 'webinocrm' ) );
		}

		$body = array_merge(
			array(
				'apiKey' => $api_key,
			),
			$data
		);

		$token = WebinoCRM_Accounting_Moadian_Config::hesabfa_login_token();
		if ( '' !== $token ) {
			$body['loginToken'] = $token;
		} else {
			$user = (string) ( $cfg['hesabfa_user_id'] ?? '' );
			$pass = WebinoCRM_Accounting_Moadian_Config::hesabfa_password();
			if ( '' === $user || '' === $pass ) {
				return new WP_Error( 'hesabfa_auth', __( 'Hesabfa loginToken or userId/password required.', 'webinocrm' ) );
			}
			$body['userId']   = $user;
			$body['password'] = $pass;
		}

		$year = (int) ( $cfg['hesabfa_year_id'] ?? 0 );
		if ( $year > 0 && ! isset( $body['yearId'] ) ) {
			$body['yearId'] = $year;
		}

		if ( $write && empty( $body['requestUniqueId'] ) ) {
			$body['requestUniqueId'] = self::guid();
		}

		$wait = self::throttle();
		if ( $wait > 0 ) {
			usleep( (int) ( $wait * 1000000 ) );
		}

		$url  = self::BASE . $method;
		$args = array(
			'timeout' => 60,
			'headers' => array(
				'Content-Type' => 'application/json; charset=utf-8',
				'Accept'       => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		);

		$res  = wp_remote_post( $url, $args );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$raw  = is_wp_error( $res ) ? $res->get_error_message() : (string) wp_remote_retrieve_body( $res );

		self::log_call( $method, $write ? 'out' : 'in', $code, self::redact( $body ), $raw );

		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'hesabfa_http', $res->get_error_message() );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'hesabfa_json', __( 'Invalid Hesabfa response.', 'webinocrm' ), array( 'status' => $code ?: 502 ) );
		}

		if ( empty( $decoded['Success'] ) ) {
			$err_code = isset( $decoded['ErrorCode'] ) ? (string) $decoded['ErrorCode'] : '';
			$msg      = self::error_message( $err_code, (string) ( $decoded['ErrorMessage'] ?? '' ) );
			if ( '101' === $err_code ) {
				self::mark_rate_limited();
			}
			return new WP_Error(
				'hesabfa_' . ( $err_code ?: 'api' ),
				$msg,
				array(
					'status'    => 400,
					'errorCode' => $err_code,
					'raw'       => $decoded,
				)
			);
		}

		return $decoded;
	}

	/**
	 * Result helper — returns Result or WP_Error.
	 *
	 * @param string              $method Method.
	 * @param array<string,mixed> $data   Data.
	 * @param bool                $write  Write.
	 * @return mixed|WP_Error
	 */
	public static function result( $method, array $data = array(), $write = false ) {
		$res = self::request( $method, $data, $write );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return $res['Result'] ?? null;
	}

	/**
	 * Paginate a list endpoint until TotalCount exhausted.
	 *
	 * @param string               $method List method.
	 * @param array<string,mixed>  $extra  Extra body (type etc.).
	 * @param array<string,mixed>  $query  queryInfo base.
	 * @param int                  $take   Page size.
	 * @return array<int,mixed>|WP_Error
	 */
	public static function list_all( $method, array $extra = array(), array $query = array(), $take = 100 ) {
		$take  = max( 1, min( 200, (int) $take ) );
		$skip  = 0;
		$all   = array();
		$total = null;
		do {
			$qi = array_merge(
				array(
					'take' => $take,
					'skip' => $skip,
				),
				$query
			);
			$res = self::request( $method, array_merge( $extra, array( 'queryInfo' => $qi ) ), false );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$result = $res['Result'] ?? null;
			$list   = array();
			if ( is_array( $result ) ) {
				if ( isset( $result['List'] ) && is_array( $result['List'] ) ) {
					$list  = $result['List'];
					$total = isset( $result['TotalCount'] ) ? (int) $result['TotalCount'] : null;
				} elseif ( self::is_list( $result ) ) {
					$list = $result;
				}
			}
			foreach ( $list as $row ) {
				$all[] = $row;
			}
			$count = count( $list );
			$skip += $take;
			if ( $count < $take ) {
				break;
			}
			if ( null !== $total && count( $all ) >= $total ) {
				break;
			}
		} while ( true );

		return $all;
	}

	// —— Contact ——

	public static function contact_get( $code ) {
		return self::result( 'contact/get', array( 'code' => $code ) );
	}

	public static function contact_get_by_id( array $id_list ) {
		return self::result( 'contact/getById', array( 'idList' => $id_list ) );
	}

	public static function contact_get_contacts( $query_info = null ) {
		return self::result( 'contact/getcontacts', array( 'queryInfo' => $query_info ) );
	}

	public static function contact_save( array $contact ) {
		return self::result( 'contact/save', array( 'contact' => $contact ), true );
	}

	public static function contact_batch_save( array $contacts ) {
		return self::result( 'contact/batchsave', array( 'contacts' => $contacts ), true );
	}

	public static function contact_delete( $code ) {
		return self::result( 'contact/delete', array( 'code' => $code ), true );
	}

	public static function contact_get_link( $code ) {
		return self::result( 'contact/getContactLink', array( 'code' => $code ) );
	}

	// —— Item ——

	public static function item_get( $code ) {
		return self::result( 'item/get', array( 'code' => $code ) );
	}

	public static function item_get_by_barcode( $barcode ) {
		return self::result( 'item/getByBarcode', array( 'barcode' => $barcode ) );
	}

	public static function item_get_by_id( array $id_list ) {
		return self::result( 'item/getById', array( 'idList' => $id_list ) );
	}

	public static function item_get_items( $query_info = null ) {
		return self::result( 'item/getitems', array( 'queryInfo' => $query_info ) );
	}

	public static function item_save( array $item ) {
		return self::result( 'item/save', array( 'item' => $item ), true );
	}

	public static function item_batch_save( array $items ) {
		return self::result( 'item/batchsave', array( 'items' => $items ), true );
	}

	public static function item_delete( $code ) {
		return self::result( 'item/delete', array( 'code' => $code ), true );
	}

	public static function item_get_quantity( $code, $warehouse_code = null ) {
		$data = array( 'code' => $code );
		if ( null !== $warehouse_code && '' !== (string) $warehouse_code ) {
			$data['warehouseCode'] = $warehouse_code;
		}
		return self::result( 'item/GetQuantity', $data );
	}

	public static function item_get_quantity2( array $codes, $warehouse_code = null ) {
		$data = array( 'codes' => $codes );
		if ( null !== $warehouse_code && '' !== (string) $warehouse_code ) {
			$data['warehouseCode'] = $warehouse_code;
		}
		return self::result( 'item/GetQuantity2', $data );
	}

	public static function item_update_opening_quantity( array $items ) {
		return self::result( 'item/UpdateOpeningQuantity', array( 'items' => $items ), true );
	}

	// —— Invoice ——

	public static function invoice_get( $number, $type = 0 ) {
		return self::result( 'invoice/get', array( 'number' => $number, 'type' => (int) $type ) );
	}

	public static function invoice_get_by_id( $id ) {
		return self::result( 'invoice/getById', array( 'id' => $id ) );
	}

	public static function invoice_get_invoices( $query_info = null, $type = 0 ) {
		return self::result(
			'invoice/getinvoices',
			array(
				'type'      => (int) $type,
				'queryInfo' => $query_info,
			)
		);
	}

	public static function invoice_save( array $invoice ) {
		return self::result( 'invoice/save', array( 'invoice' => $invoice ), true );
	}

	public static function invoice_delete( $number, $type = 0 ) {
		return self::result(
			'invoice/delete',
			array(
				'code' => $number,
				'type' => (int) $type,
			),
			true
		);
	}

	public static function invoice_save_payment( array $payment ) {
		return self::result( 'invoice/savepayment', $payment, true );
	}

	public static function invoice_online_url( $number, $type = 0 ) {
		return self::result(
			'invoice/getonlineinvoiceurl',
			array(
				'number' => $number,
				'type'   => (int) $type,
			)
		);
	}

	public static function invoice_save_warehouse_receipt( array $data ) {
		return self::result( 'invoice/SaveWarehouseReceipt', $data, true );
	}

	public static function invoice_change_paid_status( $number, $type, $status ) {
		return self::result(
			'invoice/changePaidStatus',
			array(
				'number' => $number,
				'type'   => (int) $type,
				'status' => $status,
			),
			true
		);
	}

	public static function invoice_change_sent_status( $number, $type, $status ) {
		return self::result(
			'invoice/changeSentStatus',
			array(
				'number' => $number,
				'type'   => (int) $type,
				'status' => $status,
			),
			true
		);
	}

	// —— Receipt / payment ——

	public static function receipt_get( $number, $type = 0 ) {
		return self::result(
			'receipt/get',
			array(
				'number' => $number,
				'type'   => (int) $type,
			)
		);
	}

	public static function receipt_get_by_id( $id ) {
		return self::result( 'receipt/GetById', array( 'id' => $id ) );
	}

	public static function receipt_get_receipts( $query_info = null, $type = 0 ) {
		return self::result(
			'receipt/getReceipts',
			array(
				'type'      => (int) $type,
				'queryInfo' => $query_info,
			)
		);
	}

	public static function receipt_save( array $receipt ) {
		return self::result( 'receipt/save', array( 'receipt' => $receipt ), true );
	}

	public static function receipt_save2( array $receipt ) {
		return self::result( 'receipt/save2', array( 'receipt' => $receipt ), true );
	}

	public static function receipt_delete( $number, $type = 0 ) {
		return self::result(
			'receipt/delete',
			array(
				'number' => $number,
				'type'   => (int) $type,
			),
			true
		);
	}

	// —— Warehouse ——

	public static function warehouse_get( $number ) {
		return self::result( 'warehouse/get', array( 'number' => $number ) );
	}

	public static function warehouse_get_by_id( $id ) {
		return self::result( 'warehouse/GetById', array( 'id' => $id ) );
	}

	public static function warehouse_get_receipts( $query_info = null ) {
		return self::result( 'warehouse/getReceipts', array( 'queryInfo' => $query_info ) );
	}

	public static function warehouse_save( array $receipt ) {
		return self::result( 'warehouse/save', array( 'receipt' => $receipt ), true );
	}

	public static function warehouse_delete( $number ) {
		return self::result( 'warehouse/delete', array( 'number' => $number ), true );
	}

	// —— Document (journal) ——

	public static function document_get( $number ) {
		return self::result( 'document/get', array( 'number' => $number ) );
	}

	public static function document_get_documents( $query_info = null ) {
		return self::result( 'document/getDocuments', array( 'queryInfo' => $query_info ) );
	}

	public static function document_save( array $document ) {
		return self::result( 'document/save', array( 'document' => $document ), true );
	}

	public static function document_delete( $number ) {
		return self::result( 'document/delete', array( 'number' => $number ), true );
	}

	// —— Discount items ——

	public static function discount_get( $code ) {
		return self::result( 'disCountItem/get', array( 'code' => $code ) );
	}

	public static function discount_get_by_id( array $id_list ) {
		return self::result( 'disCountItem/getById', array( 'idList' => $id_list ) );
	}

	public static function discount_get_items( $query_info = null ) {
		return self::result( 'disCountItem/getItems', array( 'queryInfo' => $query_info ) );
	}

	public static function discount_save( array $item ) {
		return self::result( 'disCountItem/save', array( 'item' => $item ), true );
	}

	public static function discount_batch_save( array $items ) {
		return self::result( 'disCountItem/batchSave', array( 'items' => $items ), true );
	}

	public static function discount_delete( $code ) {
		return self::result( 'disCountItem/delete', array( 'code' => $code ), true );
	}

	// —— Bank transfer ——

	public static function bank_transfer_get( $number ) {
		return self::result( 'bankTransfer/get', array( 'number' => $number ) );
	}

	public static function bank_transfer_get_transfers( $query_info = null ) {
		return self::result( 'bankTransfer/getTransfers', array( 'queryInfo' => $query_info ) );
	}

	public static function bank_transfer_save( array $transfer ) {
		return self::result( 'bankTransfer/save', array( 'transfer' => $transfer ), true );
	}

	public static function bank_transfer_delete( $number ) {
		return self::result( 'bankTransfer/delete', array( 'number' => $number ), true );
	}

	// —— Setting ——

	public static function setting_set_change_hook( $url, $hook_password ) {
		return self::result(
			'setting/SetChangeHook',
			array(
				'url'          => $url,
				'hookPassword' => $hook_password,
			),
			true
		);
	}

	public static function setting_get_change_hook() {
		return self::result( 'setting/GetChangeHook' );
	}

	public static function setting_get_changes( $start = 0 ) {
		return self::result( 'setting/GetChanges', array( 'start' => (int) $start ) );
	}

	public static function setting_get_banks() {
		return self::result( 'setting/getBanks' );
	}

	public static function setting_get_cashes() {
		return self::result( 'setting/getCashes' );
	}

	public static function setting_get_petty_cashes() {
		return self::result( 'setting/getPettyCashes' );
	}

	public static function setting_get_currency() {
		return self::result( 'setting/GetCurrency' );
	}

	public static function setting_get_warehouses() {
		return self::result( 'setting/getWarehouses' );
	}

	public static function setting_get_product_categories() {
		return self::result( 'setting/getProductCategories' );
	}

	public static function setting_get_service_categories() {
		return self::result( 'setting/getServiceCategories' );
	}

	public static function setting_get_contact_categories() {
		return self::result( 'setting/getContactCategories' );
	}

	public static function setting_get_fiscal_year() {
		return self::result( 'setting/GetFiscalYear' );
	}

	public static function setting_get_fiscal_years() {
		return self::result( 'setting/getFiscalYears' );
	}

	public static function setting_get_projects() {
		return self::result( 'setting/getProjects' );
	}

	public static function setting_get_salesmen() {
		return self::result( 'setting/getSalesmen' );
	}

	public static function setting_get_currency_table() {
		return self::result( 'setting/getCurrencyTable' );
	}

	public static function setting_set_currency_table( array $table ) {
		return self::result( 'setting/setCurrencyTable', array( 'table' => $table ), true );
	}

	public static function setting_get_accounts() {
		return self::result( 'setting/getAccounts' );
	}

	public static function setting_get_default_price_list() {
		return self::result( 'setting/getDefaultPriceList' );
	}

	public static function setting_get_business_info() {
		return self::result( 'setting/getBusinessInfo' );
	}

	// —— Report ——

	public static function report_balance_sheet( array $params = array() ) {
		return self::result( 'report/balanceSheet', $params );
	}

	public static function report_debtors_creditors( array $params = array() ) {
		return self::result( 'report/debtorsCreditors', $params );
	}

	public static function report_inventory( array $params = array() ) {
		return self::result( 'report/inventory', $params );
	}

	public static function report_pnl( array $params = array() ) {
		return self::result( 'report/profitandlossStatement', $params );
	}

	public static function report_trial_balance( array $params = array() ) {
		return self::result( 'report/trialBalance', $params );
	}

	public static function report_trial_balance_items( array $params = array() ) {
		return self::result( 'report/trialBalanceItems', $params );
	}

	public static function report_bank( array $params = array() ) {
		return self::result( 'report/bank', $params );
	}

	public static function report_cash( array $params = array() ) {
		return self::result( 'report/cash', $params );
	}

	public static function report_petty_cash( array $params = array() ) {
		return self::result( 'report/pettyCash', $params );
	}

	public static function report_journal( array $params = array() ) {
		return self::result( 'report/journal', $params );
	}

	// —— Inquiry ——

	public static function inquiry_credit() {
		return self::result( 'inquiry/credit' );
	}

	public static function inquiry_national_identity( $national_code, $birth_date ) {
		return self::result(
			'inquiry/nationalIdentity',
			array(
				'nationalCode' => $national_code,
				'birthDate'    => $birth_date,
			)
		);
	}

	public static function inquiry_mobile_national( $national_code, $mobile ) {
		return self::result(
			'inquiry/checkMobileAndNationalCode',
			array(
				'nationalCode' => $national_code,
				'mobile'       => $mobile,
			)
		);
	}

	public static function inquiry_card_national( $national_code, $card_number, $birth_date ) {
		return self::result(
			'inquiry/checkCardAndNationalCode',
			array(
				'nationalCode' => $national_code,
				'cardNumber'   => $card_number,
				'birthDate'    => $birth_date,
			)
		);
	}

	public static function inquiry_iban_national( $national_code, $birth_date, $iban ) {
		return self::result(
			'inquiry/checkIbanAndNationalCode',
			array(
				'nationalCode' => $national_code,
				'birthDate'    => $birth_date,
				'iban'         => $iban,
			)
		);
	}

	public static function inquiry_iban( $iban ) {
		return self::result( 'inquiry/iban', array( 'iban' => $iban ) );
	}

	public static function inquiry_card( $card_number ) {
		return self::result( 'inquiry/card', array( 'cardNumber' => $card_number ) );
	}

	public static function inquiry_card_to_iban( $card_number ) {
		return self::result( 'inquiry/cardToIban', array( 'cardNumber' => $card_number ) );
	}

	public static function inquiry_account_to_iban( $account, $bank ) {
		return self::result(
			'inquiry/accountToIBAN',
			array(
				'account' => $account,
				'bank'    => $bank,
			)
		);
	}

	public static function inquiry_postal_code( $postal_code ) {
		return self::result( 'inquiry/postalCode', array( 'postalCode' => $postal_code ) );
	}

	/**
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function is_list( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $value );
		}
		if ( array() === $value ) {
			return true;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * @return string
	 */
	public static function guid() {
		$data = random_bytes( 16 );
		$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
		$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	/**
	 * Simple token-bucket throttle; returns seconds to sleep.
	 *
	 * @return float
	 */
	private static function throttle() {
		$state = get_option( self::RATE_OPTION, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		$now   = microtime( true );
		$until = (float) ( $state['blocked_until'] ?? 0 );
		if ( $until > $now ) {
			return $until - $now;
		}
		$window = (float) ( $state['window_start'] ?? 0 );
		$count  = (int) ( $state['count'] ?? 0 );
		if ( $now - $window >= 60 ) {
			$window = $now;
			$count  = 0;
		}
		++$count;
		update_option(
			self::RATE_OPTION,
			array(
				'window_start'   => $window,
				'count'          => $count,
				'blocked_until'  => (float) ( $state['blocked_until'] ?? 0 ),
			),
			false
		);
		if ( $count > self::MAX_PER_MINUTE ) {
			$sleep = 60 - ( $now - $window );
			return max( 0.25, $sleep );
		}
		return 0.0;
	}

	/**
	 * @return void
	 */
	private static function mark_rate_limited() {
		$state = get_option( self::RATE_OPTION, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		$state['blocked_until'] = microtime( true ) + 15;
		update_option( self::RATE_OPTION, $state, false );
	}

	/**
	 * @param string $code Error code.
	 * @param string $api_msg API message.
	 * @return string
	 */
	private static function error_message( $code, $api_msg ) {
		$map = array(
			'100' => 'InternalServerError',
			'101' => 'TooManyRequests',
			'103' => 'MissingData',
			'104' => 'MissingParameter',
			'105' => 'ApiDisabled',
			'106' => 'UserIsNotOwner',
			'107' => 'BusinessNotFound',
			'108' => 'BusinessExpired',
			'110' => 'IdMustBeZero',
			'111' => 'IdMustNotBeZero',
			'112' => 'ObjectNotFound',
			'113' => 'MissingApiKey',
			'114' => 'ParameterIsOutOfRange',
			'190' => 'ApplicationError',
		);
		$label = $map[ $code ] ?? ( 'Error ' . $code );
		if ( '' !== $api_msg ) {
			return $label . ': ' . $api_msg;
		}
		return $label;
	}

	/**
	 * @param array<string,mixed> $body Body.
	 * @return array<string,mixed>
	 */
	private static function redact( array $body ) {
		foreach ( array( 'apiKey', 'password', 'loginToken', 'hookPassword' ) as $k ) {
			if ( isset( $body[ $k ] ) && is_string( $body[ $k ] ) && '' !== $body[ $k ] ) {
				$body[ $k ] = '••••••••';
			}
		}
		return $body;
	}

	/**
	 * @param string $endpoint Endpoint.
	 * @param string $direction in|out.
	 * @param int    $http_code HTTP.
	 * @param mixed  $payload Payload.
	 * @param mixed  $response Response.
	 * @return void
	 */
	private static function log_call( $endpoint, $direction, $http_code, $payload, $response ) {
		if ( ! class_exists( 'WebinoCRM_Accounting_Db_Compat', false ) ) {
			return;
		}
		global $wpdb;
		$table = WebinoCRM_Accounting_Db_Compat::table( 'hesabfa_log' );
		// Table may not exist yet during first boot.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table !== $exists ) {
			return;
		}
		$pay = is_string( $payload ) ? $payload : wp_json_encode( $payload );
		$res = is_string( $response ) ? $response : wp_json_encode( $response );
		if ( is_string( $pay ) && strlen( $pay ) > 20000 ) {
			$pay = substr( $pay, 0, 20000 ) . '…';
		}
		if ( is_string( $res ) && strlen( $res ) > 20000 ) {
			$res = substr( $res, 0, 20000 ) . '…';
		}
		WebinoCRM_Accounting_Db_Compat::insert(
			'hesabfa_log',
			array(
				'direction'  => sanitize_key( $direction ),
				'endpoint'   => sanitize_text_field( (string) $endpoint ),
				'http_code'  => (int) $http_code,
				'payload'    => $pay,
				'response'   => $res,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}
}
