<?php
/**
 * Hesabfa submodule facade: hooks, cron, enqueue from local CRUD.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * High-level Hesabfa operations.
 */
final class WebinoCRM_Accounting_Hesabfa {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( 'WebinoCRM_Accounting_Hesabfa_Webhook', 'register_routes' ) );

		add_action( 'webino_accounting_process_hesabfa', array( __CLASS__, 'cron' ) );
		add_filter(
			'cron_schedules',
			static function ( $schedules ) {
				if ( ! isset( $schedules['accounting_five_minutes'] ) ) {
					$schedules['accounting_five_minutes'] = array(
						'interval' => 300,
						'display'  => 'Every five minutes',
					);
				}
				return $schedules;
			}
		);
		if ( ! wp_next_scheduled( 'webino_accounting_process_hesabfa' ) ) {
			wp_schedule_event( time() + 180, 'accounting_five_minutes', 'webino_accounting_process_hesabfa' );
		}

		// Local CRUD → push jobs.
		add_action( 'webino_acc_person_saved', array( __CLASS__, 'on_person_saved' ), 10, 1 );
		add_action( 'webino_acc_product_saved', array( __CLASS__, 'on_product_saved' ), 10, 1 );
		add_action( 'webino_acc_invoice_saved', array( __CLASS__, 'on_invoice_saved' ), 10, 1 );
		add_action( 'webino_acc_voucher_saved', array( __CLASS__, 'on_voucher_saved' ), 10, 1 );
		add_action( 'webino_acc_warehouse_doc_saved', array( __CLASS__, 'on_warehouse_doc_saved' ), 10, 1 );
		add_action( 'webino_acc_journal_saved', array( __CLASS__, 'on_journal_saved' ), 10, 1 );
	}

	/**
	 * Cron: process outbound jobs + poll GetChanges.
	 *
	 * @return void
	 */
	public static function cron() {
		if ( ! WebinoCRM_Accounting_Hesabfa_Sync::enabled() ) {
			return;
		}
		WebinoCRM_Accounting_Hesabfa_Sync::process_jobs( 20 );
		WebinoCRM_Accounting_Hesabfa_Webhook::pull_changes();
	}

	/**
	 * Test connection.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function test_connection() {
		$info = WebinoCRM_Accounting_Hesabfa_Client::setting_get_business_info();
		if ( is_wp_error( $info ) ) {
			// Fallback: fiscal year is enough to prove auth.
			$fy = WebinoCRM_Accounting_Hesabfa_Client::setting_get_fiscal_year();
			if ( is_wp_error( $fy ) ) {
				return $info;
			}
			return array(
				'ok'     => true,
				'fiscal' => $fy,
			);
		}
		return array(
			'ok'       => true,
			'business' => $info,
		);
	}

	/**
	 * Enqueue invoice push after local create/post.
	 *
	 * @param int $invoice_id Invoice.
	 * @return void
	 */
	public static function enqueue_invoice( $invoice_id ) {
		if ( ! WebinoCRM_Accounting_Hesabfa_Sync::enabled() || WebinoCRM_Accounting_Hesabfa_Sync::is_syncing() ) {
			return;
		}
		WebinoCRM_Accounting_Hesabfa_Sync::enqueue( 'push', 'invoice', (int) $invoice_id );
	}

	/**
	 * @param int $id Person.
	 * @return void
	 */
	public static function on_person_saved( $id ) {
		if ( WebinoCRM_Accounting_Hesabfa_Sync::enabled() && ! WebinoCRM_Accounting_Hesabfa_Sync::is_syncing() ) {
			WebinoCRM_Accounting_Hesabfa_Sync::enqueue( 'push', 'contact', (int) $id );
		}
	}

	/**
	 * @param int $id Product.
	 * @return void
	 */
	public static function on_product_saved( $id ) {
		if ( WebinoCRM_Accounting_Hesabfa_Sync::enabled() && ! WebinoCRM_Accounting_Hesabfa_Sync::is_syncing() ) {
			WebinoCRM_Accounting_Hesabfa_Sync::enqueue( 'push', 'item', (int) $id );
		}
	}

	/**
	 * @param int $id Invoice.
	 * @return void
	 */
	public static function on_invoice_saved( $id ) {
		self::enqueue_invoice( $id );
	}

	/**
	 * @param int $id Voucher.
	 * @return void
	 */
	public static function on_voucher_saved( $id ) {
		if ( WebinoCRM_Accounting_Hesabfa_Sync::enabled() && ! WebinoCRM_Accounting_Hesabfa_Sync::is_syncing() ) {
			WebinoCRM_Accounting_Hesabfa_Sync::enqueue( 'push', 'receipt', (int) $id );
		}
	}

	/**
	 * @param int $id Doc.
	 * @return void
	 */
	public static function on_warehouse_doc_saved( $id ) {
		if ( WebinoCRM_Accounting_Hesabfa_Sync::enabled() && ! WebinoCRM_Accounting_Hesabfa_Sync::is_syncing() ) {
			WebinoCRM_Accounting_Hesabfa_Sync::enqueue( 'push', 'warehouse_document', (int) $id );
		}
	}

	/**
	 * @param int $id Journal.
	 * @return void
	 */
	public static function on_journal_saved( $id ) {
		if ( WebinoCRM_Accounting_Hesabfa_Sync::enabled() && ! WebinoCRM_Accounting_Hesabfa_Sync::is_syncing() ) {
			WebinoCRM_Accounting_Hesabfa_Sync::enqueue( 'push', 'journal', (int) $id );
		}
	}
}
