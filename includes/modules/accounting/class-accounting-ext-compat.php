<?php
/**
 * DB adapter so Dashboard-ported Moadian/Hesabfa code can use CRM accounting tables.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps short table keys to webinocrm_accounting_* tables.
 */
final class WebinoCRM_Accounting_Db_Compat {

	/**
	 * @param string $key Short key (invoices, moadian_jobs, …).
	 * @return string
	 */
	public static function table( $key ) {
		global $wpdb;
		$map = array(
			'invoices'            => 'webinocrm_accounting_invoices',
			'invoice_lines'       => 'webinocrm_accounting_invoice_lines',
			'persons'             => 'webinocrm_accounting_persons',
			'products'            => 'webinocrm_accounting_products',
			'cash_accounts'       => 'webinocrm_accounting_cash_accounts',
			'receipt_vouchers'    => 'webinocrm_accounting_receipt_vouchers',
			'chart_accounts'      => 'webinocrm_accounting_chart_accounts',
			'warehouses'          => 'webinocrm_accounting_warehouses',
			'warehouse_stock'     => 'webinocrm_accounting_warehouse_stock',
			'warehouse_documents' => 'webinocrm_accounting_warehouse_transactions',
			'moadian_jobs'        => 'webinocrm_accounting_moadian_jobs',
			'moadian_log'         => 'webinocrm_accounting_moadian_log',
			'hesabfa_map'         => 'webinocrm_accounting_hesabfa_map',
			'hesabfa_jobs'        => 'webinocrm_accounting_hesabfa_jobs',
			'hesabfa_log'         => 'webinocrm_accounting_hesabfa_log',
			'bank_transfers'      => 'webinocrm_accounting_bank_transfers',
			'projects'            => 'webinocrm_hrm_projects',
		);
		$suffix = $map[ $key ] ?? ( 'webinocrm_accounting_' . sanitize_key( $key ) );
		return $wpdb->prefix . $suffix;
	}

	/**
	 * @param string $key Table key.
	 * @param int    $id  ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_row( $key, $id ) {
		global $wpdb;
		$table = self::table( $key );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param string               $key  Table.
	 * @param array<string,mixed>  $data Data.
	 * @return int|WP_Error
	 */
	public static function insert( $key, array $data ) {
		global $wpdb;
		$table = self::table( $key );
		$ok    = $wpdb->insert( $table, $data );
		if ( false === $ok ) {
			return new WP_Error( 'acc_db', $wpdb->last_error ?: __( 'Insert failed.', 'webinocrm' ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param string              $key  Table.
	 * @param int                 $id   ID.
	 * @param array<string,mixed> $data Data.
	 * @return true|WP_Error
	 */
	public static function update( $key, $id, array $data ) {
		global $wpdb;
		$table = self::table( $key );
		$ok    = $wpdb->update( $table, $data, array( 'id' => absint( $id ) ) );
		if ( false === $ok ) {
			return new WP_Error( 'acc_db', $wpdb->last_error ?: __( 'Update failed.', 'webinocrm' ) );
		}
		return true;
	}

	/**
	 * @param string              $key  Table.
	 * @param array<string,mixed> $args Args (per_page, where_sql, where_params, order).
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function list_rows( $key, array $args = array() ) {
		global $wpdb;
		$table = self::table( $key );
		$where = (string) ( $args['where_sql'] ?? '' );
		$params = isset( $args['where_params'] ) && is_array( $args['where_params'] ) ? $args['where_params'] : array();
		$order = (string) ( $args['order'] ?? 'id DESC' );
		$limit = min( 500, max( 1, absint( $args['per_page'] ?? 50 ) ) );
		$sql   = "SELECT * FROM {$table} WHERE 1=1 {$where} ORDER BY {$order} LIMIT {$limit}"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $params ) {
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total_sql = "SELECT COUNT(*) FROM {$table} WHERE 1=1 {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total     = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var( $total_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array( 'items' => is_array( $rows ) ? $rows : array(), 'total' => $total );
	}
}

/**
 * Moadian/Hesabfa settings stored in webinocrm_accounting_ext_settings.
 */
final class WebinoCRM_Accounting_Moadian_Config {
	const OPTION = 'webinocrm_accounting_ext_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'company_name'           => '',
			'economic_code'          => '',
			'national_id'            => '',
			'fiscal_id'              => '',
			'certificate_pem'        => '',
			'private_key_enc'        => '',
			'moadian_sandbox'        => false,
			'moadian_proxy'          => '',
			'auto_send_moadian'      => false,
			'default_invoice_type'   => 2,
			'hesabfa_enabled'        => false,
			'hesabfa_api_key'        => '',
			'hesabfa_login_token_enc'=> '',
			'hesabfa_user_id'        => '',
			'hesabfa_password_enc'   => '',
			'hesabfa_year_id'        => 0,
			'hesabfa_currency'       => 'IRT',
			'hesabfa_hook_password'  => '',
			'hesabfa_last_change_id' => 0,
			'hesabfa_sync_entities'  => array(),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	/**
	 * @param array<string,mixed> $data Data.
	 * @return array<string,mixed>
	 */
	public static function save( array $data ) {
		$old = self::get();
		$new = array_merge( $old, $data );
		if ( ! empty( $data['private_key'] ) ) {
			$new['private_key_enc'] = self::encrypt_secret( (string) $data['private_key'] );
		}
		if ( ! empty( $data['hesabfa_login_token'] ) ) {
			$new['hesabfa_login_token_enc'] = self::encrypt_secret( (string) $data['hesabfa_login_token'] );
		}
		if ( ! empty( $data['hesabfa_password'] ) ) {
			$new['hesabfa_password_enc'] = self::encrypt_secret( (string) $data['hesabfa_password'] );
		}
		update_option( self::OPTION, $new, false );
		return self::get_public();
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get_public() {
		$s = self::get();
		$s['has_private_key'] = '' !== (string) ( $s['private_key_enc'] ?? '' );
		$s['private_key_enc'] = '';
		$s['private_key']     = '';
		$s['certificate_pem'] = ! empty( $s['certificate_pem'] ) ? '••••••••' : '';
		$s['hesabfa_login_token'] = '';
		$s['hesabfa_password']    = '';
		return $s;
	}

	/**
	 * @return string
	 */
	public static function private_key_pem() {
		$enc = (string) ( self::get()['private_key_enc'] ?? '' );
		return $enc ? self::decrypt_secret( $enc ) : '';
	}

	/**
	 * @param float  $amount   Amount.
	 * @param string $currency Currency.
	 * @return int
	 */
	public static function amount_to_rial( $amount, $currency = 'IRT' ) {
		$n = (float) $amount;
		if ( 'IRT' === strtoupper( (string) $currency ) || 'TOMAN' === strtoupper( (string) $currency ) ) {
			$n *= 10;
		}
		return (int) round( $n );
	}

	/**
	 * @param string $plain Plain.
	 * @return string
	 */
	public static function encrypt_secret( $plain ) {
		$key = hash( 'sha256', ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'webinocrm' ) . 'acc-ext', true );
		$iv  = substr( hash( 'sha256', 'iv' . ( defined( 'AUTH_SALT' ) ? AUTH_SALT : 'salt' ), true ), 0, 16 );
		$bin = openssl_encrypt( (string) $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return base64_encode( false === $bin ? '' : $bin );
	}

	/**
	 * @param string $enc Encoded.
	 * @return string
	 */
	public static function decrypt_secret( $enc ) {
		$key = hash( 'sha256', ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'webinocrm' ) . 'acc-ext', true );
		$iv  = substr( hash( 'sha256', 'iv' . ( defined( 'AUTH_SALT' ) ? AUTH_SALT : 'salt' ), true ), 0, 16 );
		$bin = base64_decode( (string) $enc, true );
		if ( false === $bin ) {
			return '';
		}
		$plain = openssl_decrypt( $bin, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}
}

/**
 * Adapter: Dashboard Accounting_Invoices API → CRM invoices.
 */
final class Accounting_Invoices {

	/**
	 * @param int $id Invoice id.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get( $id ) {
		$obj = WebinoCRM_Accounting_Invoice::get( $id );
		if ( ! $obj ) {
			return new WP_Error( 'acc_not_found', __( 'Invoice not found.', 'webinocrm' ), array( 'status' => 404 ) );
		}
		$row = (array) $obj;
		// Normalize field names expected by Moadian builder.
		$row['type']       = $row['invoice_type'] ?? ( $row['type'] ?? 'sales' );
		$row['tax']        = $row['tax_amount'] ?? ( $row['vat_amount'] ?? ( $row['tax'] ?? 0 ) );
		$row['total']      = $row['grand_total'] ?? ( $row['total'] ?? 0 );
		$row['currency']   = $row['currency'] ?? 'IRT';
		$row['person_id']  = $row['person_id'] ?? 0;
		$lines_table       = WebinoCRM_Accounting_Db_Compat::table( 'invoice_lines' );
		global $wpdb;
		$lines = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$lines_table} WHERE invoice_id = %d", absint( $id ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		$norm = array();
		foreach ( (array) $lines as $line ) {
			$norm[] = array(
				'unit_price'  => (float) ( $line['unit_price'] ?? 0 ),
				'discount'    => (float) ( $line['discount'] ?? 0 ),
				'vat_amount'  => (float) ( $line['tax_amount'] ?? $line['vat_amount'] ?? 0 ),
				'line_total'  => (float) ( $line['line_total'] ?? $line['total'] ?? 0 ),
				'qty'         => (float) ( $line['qty'] ?? $line['quantity'] ?? 1 ),
				'sstid'       => (string) ( $line['sstid'] ?? $line['product_code'] ?? '' ),
				'description' => (string) ( $line['description'] ?? $line['title'] ?? '' ),
				'vat_rate'    => (float) ( $line['tax_rate'] ?? $line['vat_rate'] ?? 10 ),
			);
		}
		$row['lines'] = $norm;
		return $row;
	}

	/**
	 * Stub create for Hesabfa import — creates minimal sales invoice.
	 *
	 * @param array<string,mixed>       $header Header.
	 * @param array<int,array<string,mixed>> $lines Lines.
	 * @return int|WP_Error
	 */
	public static function create( array $header, array $lines ) {
		return new WP_Error( 'acc_hesabfa_create', __( 'Hesabfa invoice import into CRM requires manual mapping in this version.', 'webinocrm' ) );
	}
}
