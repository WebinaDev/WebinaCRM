<?php
/**
 * Moadian business logic: build invoices, queue, process.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * High-level Moadian operations.
 */
final class WebinoCRM_Accounting_Moadian {

	/**
	 * @param int    $invoice_id Invoice.
	 * @param string $action     send|cancel|correct.
	 * @return int|WP_Error Job ID.
	 */
	public static function enqueue_send( $invoice_id, $action = 'send' ) {
		$inv = Accounting_Invoices::get( $invoice_id );
		if ( is_wp_error( $inv ) ) {
			return $inv;
		}
		if ( 'proforma' === $inv['type'] ) {
			return new WP_Error( 'acc_proforma', __( 'Proforma cannot be sent to Moadian.', 'webinocrm' ) );
		}
		return WebinoCRM_Accounting_Db_Compat::insert(
			'moadian_jobs',
			array(
				'invoice_id' => absint( $invoice_id ),
				'action'     => sanitize_key( $action ),
				'status'     => 'pending',
				'attempts'   => 0,
				'run_after'  => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Process due jobs.
	 *
	 * @param int $limit Max jobs.
	 * @return array{processed:int,errors:array<int,string>}
	 */
	public static function process_jobs( $limit = 10 ) {
		global $wpdb;
		$table = WebinoCRM_Accounting_Db_Compat::table( 'moadian_jobs' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status IN ('pending','retry') AND (run_after IS NULL OR run_after <= %s) ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s' ),
				max( 1, min( 50, (int) $limit ) )
			),
			ARRAY_A
		);
		$processed = 0;
		$errors    = array();
		foreach ( (array) $rows as $job ) {
			$res = self::run_job( $job );
			++$processed;
			if ( is_wp_error( $res ) ) {
				$errors[] = $res->get_error_message();
			}
		}
		return array( 'processed' => $processed, 'errors' => $errors );
	}

	/**
	 * @param array<string,mixed> $job Job row.
	 * @return true|WP_Error
	 */
	public static function run_job( array $job ) {
		$invoice_id = (int) $job['invoice_id'];
		$action     = (string) $job['action'];
		$attempts   = (int) $job['attempts'] + 1;

		$built = self::build_invoice_payload( $invoice_id, $action );
		if ( is_wp_error( $built ) ) {
			WebinoCRM_Accounting_Db_Compat::update(
				'moadian_jobs',
				(int) $job['id'],
				array(
					'status'     => $attempts >= 5 ? 'failed' : 'retry',
					'attempts'   => $attempts,
					'last_error' => $built->get_error_message(),
					'run_after'  => gmdate( 'Y-m-d H:i:s', time() + 300 * $attempts ),
				)
			);
			WebinoCRM_Accounting_Db_Compat::update( 'invoices', $invoice_id, array( 'moadian_status' => 'failed' ) );
			return $built;
		}

		$uid    = wp_generate_uuid4();
		$packet = WebinoCRM_Accounting_Moadian_Client::wrap_packet( $built, $uid );
		if ( is_wp_error( $packet ) ) {
			WebinoCRM_Accounting_Db_Compat::update(
				'moadian_jobs',
				(int) $job['id'],
				array(
					'status'     => 'failed',
					'attempts'   => $attempts,
					'last_error' => $packet->get_error_message(),
				)
			);
			return $packet;
		}

		$res = WebinoCRM_Accounting_Moadian_Client::send_invoices( array( $packet ) );
		if ( is_wp_error( $res ) ) {
			WebinoCRM_Accounting_Db_Compat::update(
				'moadian_jobs',
				(int) $job['id'],
				array(
					'status'     => $attempts >= 5 ? 'failed' : 'retry',
					'attempts'   => $attempts,
					'last_error' => $res->get_error_message(),
					'run_after'  => gmdate( 'Y-m-d H:i:s', time() + 300 * $attempts ),
				)
			);
			WebinoCRM_Accounting_Db_Compat::update( 'invoices', $invoice_id, array( 'moadian_status' => 'failed' ) );
			return $res;
		}

		$ref = (string) ( $res['result'][0]['referenceNumber'] ?? $res['referenceNumber'] ?? '' );
		WebinoCRM_Accounting_Db_Compat::update(
			'moadian_jobs',
			(int) $job['id'],
			array(
				'status'            => 'sent',
				'attempts'          => $attempts,
				'uid'               => $uid,
				'reference_number'  => $ref,
				'last_error'        => null,
			)
		);
		WebinoCRM_Accounting_Db_Compat::update(
			'invoices',
			$invoice_id,
			array(
				'moadian_status' => 'sent',
				'reference_uid'  => $uid,
				'taxid'          => (string) ( $built['header']['taxid'] ?? '' ),
			)
		);
		return true;
	}

	/**
	 * Build Moadian invoice JSON from accounting invoice.
	 *
	 * @param int    $invoice_id Invoice.
	 * @param string $action     Action.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function build_invoice_payload( $invoice_id, $action = 'send' ) {
		$inv = Accounting_Invoices::get( $invoice_id );
		if ( is_wp_error( $inv ) ) {
			return $inv;
		}
		foreach ( $inv['lines'] as $line ) {
			if ( empty( $line['sstid'] ) ) {
				return new WP_Error( 'acc_moadian_sstid', __( 'All lines need SSTID (goods/services ID) before Moadian send.', 'webinocrm' ) );
			}
		}

		$inty = (int) ( $inv['inty'] ?: 0 );
		if ( $inty <= 0 ) {
			$buyer_probe = json_decode( (string) ( $inv['buyer_json'] ?? '' ), true );
			if ( ! is_array( $buyer_probe ) ) {
				$buyer_probe = array();
			}
			$person_probe = $inv['person_id'] ? WebinoCRM_Accounting_Db_Compat::get_row( 'persons', (int) $inv['person_id'] ) : null;
			$nid_probe    = (string) ( $buyer_probe['national_id'] ?? ( $person_probe['national_id'] ?? '' ) );
			$econ_probe   = (string) ( $buyer_probe['economic_code'] ?? ( $person_probe['economic_code'] ?? '' ) );
			$inty         = ( '' !== $nid_probe || '' !== $econ_probe ) ? 1 : (int) WebinoCRM_Accounting_Moadian_Config::get()['default_invoice_type'];
		}
		$ins  = 1; // original
		if ( 'cancel' === $action ) {
			$ins = 3;
		} elseif ( 'correct' === $action || 'return' === $action ) {
			$ins = 2;
		}
		if ( 'return' === $action ) {
			$ins = 4; // return from sale subject when supported; keep header.ins=4 style.
		}

		$serial = preg_replace( '/\D/', '', (string) $inv['number'] );
		if ( '' === $serial ) {
			$serial = (string) $invoice_id;
		}
		$ts    = strtotime( (string) $inv['document_date'] . ' 12:00:00' ) ?: time();
		$taxid = (string) ( $inv['taxid'] ?: WebinoCRM_Accounting_Moadian_Client::make_taxid( $serial, $ts ) );

		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'IRT';
		$body_rows = array();
		$i = 1;
		foreach ( $inv['lines'] as $line ) {
			$am = WebinoCRM_Accounting_Moadian_Config::amount_to_rial( (float) $line['unit_price'], $currency );
			$dis = WebinoCRM_Accounting_Moadian_Config::amount_to_rial( (float) $line['discount'], $currency );
			$vat = WebinoCRM_Accounting_Moadian_Config::amount_to_rial( (float) $line['vat_amount'], $currency );
			$tsamam = WebinoCRM_Accounting_Moadian_Config::amount_to_rial( (float) $line['line_total'], $currency );
			$body_rows[] = array(
				'sstid'  => (string) $line['sstid'],
				'sstt'   => (string) $line['description'],
				'am'     => $am,
				'fee'    => $am,
				'prdis'  => $am * (float) $line['qty'],
				'dis'    => $dis,
				'adis'   => max( 0, $am * (float) $line['qty'] - $dis ),
				'vra'    => (float) $line['vat_rate'],
				'vam'    => $vat,
				'tsstam' => $tsamam,
				'cui'    => null,
			);
			++$i;
		}

		$header = array(
			'taxid'   => $taxid,
			'indatim' => $ts * 1000,
			'indati2m'=> $ts * 1000,
			'inty'    => $inty,
			'inno'    => str_pad( substr( $serial, -10 ), 10, '0', STR_PAD_LEFT ),
			'irtaxid' => null,
			'inp'     => (int) ( $inv['inp'] ?: 1 ),
			'ins'     => $ins,
			'tins'    => (string) WebinoCRM_Accounting_Moadian_Config::get()['economic_code'],
			'tob'     => 1,
			'bid'     => null,
			'tinb'    => null,
			'sbc'     => null,
			'bpc'     => null,
			'bbc'     => null,
			'ft'      => null,
			'bpn'     => null,
			'scln'    => null,
			'scc'     => null,
			'cdcn'    => null,
			'cdcd'    => null,
			'crn'     => null,
			'setm'    => 1,
			'cap'     => WebinoCRM_Accounting_Moadian_Config::amount_to_rial( (float) $inv['total'], $currency ),
			'insp'    => 0,
			'tvam'    => WebinoCRM_Accounting_Moadian_Config::amount_to_rial( (float) $inv['tax'], $currency ),
			'todis'   => 0,
			'tbill'   => WebinoCRM_Accounting_Moadian_Config::amount_to_rial( (float) $inv['total'], $currency ),
		);

		if ( 1 === $inty || 3 === $inty ) {
			$buyer = json_decode( (string) ( $inv['buyer_json'] ?? '' ), true );
			if ( ! is_array( $buyer ) ) {
				$buyer = array();
			}
			$person = $inv['person_id'] ? WebinoCRM_Accounting_Db_Compat::get_row( 'persons', (int) $inv['person_id'] ) : null;
			$nid    = (string) ( $buyer['national_id'] ?? ( $person['national_id'] ?? '' ) );
			$econ   = (string) ( $buyer['economic_code'] ?? ( $person['economic_code'] ?? '' ) );
			$kind   = (string) ( $person['person_kind'] ?? 'natural' );
			$header['tob'] = 'legal' === $kind ? 2 : 1;
			if ( 'legal' === $kind ) {
				$header['tinb'] = $econ ?: $nid;
			} else {
				$header['bid'] = $nid;
			}
			if ( empty( $header['tinb'] ) && empty( $header['bid'] ) && 1 === $inty ) {
				return new WP_Error( 'acc_moadian_buyer', __( 'Type 1 invoices require buyer national ID or economic code.', 'webinocrm' ) );
			}
		}

		if ( in_array( $ins, array( 2, 3 ), true ) && ! empty( $inv['correction_of_id'] ) ) {
			$orig = WebinoCRM_Accounting_Db_Compat::get_row( 'invoices', (int) $inv['correction_of_id'] );
			if ( $orig && ! empty( $orig['taxid'] ) ) {
				$header['irtaxid'] = $orig['taxid'];
			}
		} elseif ( 'cancel' === $action && ! empty( $inv['taxid'] ) ) {
			$header['irtaxid'] = $inv['taxid'];
		}

		return array(
			'header'   => $header,
			'body'     => $body_rows,
			'payments' => array(),
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public static function test_connection() {
		$info = WebinoCRM_Accounting_Moadian_Client::get_fiscal_information();
		if ( is_wp_error( $info ) ) {
			// Fall back to server info if fiscal requires full TSP enrollment.
			$srv = WebinoCRM_Accounting_Moadian_Client::get_server_information();
			if ( is_wp_error( $srv ) ) {
				return $info;
			}
			return true;
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $args Args.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function list_jobs( array $args = array() ) {
		return WebinoCRM_Accounting_Db_Compat::list_rows( 'moadian_jobs', $args );
	}

	/**
	 * Inquiry job / invoice status.
	 *
	 * @param int $invoice_id Invoice.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function inquiry( $invoice_id ) {
		$inv = Accounting_Invoices::get( $invoice_id );
		if ( is_wp_error( $inv ) ) {
			return $inv;
		}
		if ( ! empty( $inv['reference_uid'] ) ) {
			return WebinoCRM_Accounting_Moadian_Client::inquiry_by_uid( (string) $inv['reference_uid'] );
		}
		global $wpdb;
		$jt  = WebinoCRM_Accounting_Db_Compat::table( 'moadian_jobs' );
		$ref = $wpdb->get_var( $wpdb->prepare( "SELECT reference_number FROM {$jt} WHERE invoice_id = %d AND reference_number IS NOT NULL ORDER BY id DESC LIMIT 1", absint( $invoice_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $ref ) {
			return WebinoCRM_Accounting_Moadian_Client::inquiry_by_reference( (string) $ref );
		}
		return new WP_Error( 'acc_moadian_inquiry', __( 'No Moadian reference to inquire.', 'webinocrm' ) );
	}
}
