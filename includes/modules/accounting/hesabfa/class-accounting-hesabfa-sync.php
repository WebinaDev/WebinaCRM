<?php
/**
 * Hesabfa entity sync: map, push, pull, conflict guards.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bidirectional mappers + job runners for Hesabfa.
 */
final class WebinoCRM_Accounting_Hesabfa_Sync {
	/** @var bool */
	private static $syncing = false;

	/**
	 * @return bool
	 */
	public static function is_syncing() {
		return self::$syncing;
	}

	/**
	 * @param bool $on Flag.
	 * @return void
	 */
	public static function set_syncing( $on ) {
		self::$syncing = (bool) $on;
	}

	/**
	 * @return bool
	 */
	public static function enabled() {
		$cfg = WebinoCRM_Accounting_Moadian_Config::get();
		return ! empty( $cfg['hesabfa_enabled'] ) && '' !== (string) ( $cfg['hesabfa_api_key'] ?? '' );
	}

	/**
	 * @param string $entity Entity type.
	 * @return bool
	 */
	public static function entity_enabled( $entity ) {
		$ents = WebinoCRM_Accounting_Moadian_Config::get()['hesabfa_sync_entities'] ?? array();
		return ! empty( $ents[ $entity ] );
	}

	/**
	 * @param string $entity Entity.
	 * @param int    $local_id Local ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_map( $entity, $local_id ) {
		global $wpdb;
		$table = WebinoCRM_Accounting_Db_Compat::table( 'hesabfa_map' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE entity_type = %s AND local_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				sanitize_key( $entity ),
				absint( $local_id )
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param string     $entity Entity.
	 * @param int|string $remote_id Remote id.
	 * @return array<string,mixed>|null
	 */
	public static function get_map_by_remote( $entity, $remote_id ) {
		global $wpdb;
		$table = WebinoCRM_Accounting_Db_Compat::table( 'hesabfa_map' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE entity_type = %s AND remote_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				sanitize_key( $entity ),
				absint( $remote_id )
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param string     $entity Entity.
	 * @param string     $code Remote code.
	 * @return array<string,mixed>|null
	 */
	public static function get_map_by_code( $entity, $code ) {
		global $wpdb;
		$table = WebinoCRM_Accounting_Db_Compat::table( 'hesabfa_map' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE entity_type = %s AND remote_code = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				sanitize_key( $entity ),
				(string) $code
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Upsert link map row.
	 *
	 * @param string               $entity Entity.
	 * @param int                  $local_id Local.
	 * @param array<string,mixed>  $remote Remote fields.
	 * @param string               $direction push|pull.
	 * @param string               $hash Content hash.
	 * @return void
	 */
	public static function upsert_map( $entity, $local_id, array $remote, $direction, $hash = '' ) {
		global $wpdb;
		$table = WebinoCRM_Accounting_Db_Compat::table( 'hesabfa_map' );
		$existing = self::get_map( $entity, $local_id );
		$row = array(
			'entity_type'       => sanitize_key( $entity ),
			'local_id'          => absint( $local_id ),
			'remote_id'         => isset( $remote['Id'] ) ? absint( $remote['Id'] ) : ( isset( $remote['id'] ) ? absint( $remote['id'] ) : null ),
			'remote_code'       => isset( $remote['Code'] ) ? (string) $remote['Code'] : ( isset( $remote['code'] ) ? (string) $remote['code'] : ( isset( $remote['Number'] ) ? (string) $remote['Number'] : null ) ),
			'content_hash'      => $hash ?: null,
			'remote_updated_at' => isset( $remote['LastUpdateDate'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $remote['LastUpdateDate'] ) ) : ( isset( $remote['UpdateDate'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $remote['UpdateDate'] ) ) : null ),
			'last_synced_at'    => gmdate( 'Y-m-d H:i:s' ),
			'last_direction'    => sanitize_key( $direction ),
		);
		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $existing['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}

	/**
	 * Enqueue a sync job.
	 *
	 * @param string               $action push|pull|delete|migrate_step|pull_changes.
	 * @param string               $entity Entity.
	 * @param int|null             $local_id Local.
	 * @param array<string,mixed>  $payload Extra.
	 * @return int|WP_Error
	 */
	public static function enqueue( $action, $entity = '', $local_id = null, array $payload = array() ) {
		if ( ! self::enabled() && 'migrate_step' !== $action && 'pull_changes' !== $action ) {
			return new WP_Error( 'hesabfa_disabled', __( 'Hesabfa sync is disabled.', 'webinocrm' ) );
		}
		if ( self::$syncing && in_array( $action, array( 'push', 'delete' ), true ) ) {
			return 0;
		}
		return WebinoCRM_Accounting_Db_Compat::insert(
			'hesabfa_jobs',
			array(
				'action'      => sanitize_key( $action ),
				'entity_type' => sanitize_key( $entity ),
				'local_id'    => null !== $local_id ? absint( $local_id ) : null,
				'remote_id'   => isset( $payload['remote_id'] ) ? absint( $payload['remote_id'] ) : null,
				'remote_code' => isset( $payload['remote_code'] ) ? sanitize_text_field( (string) $payload['remote_code'] ) : null,
				'status'      => 'pending',
				'attempts'    => 0,
				'payload'     => wp_json_encode( $payload ),
				'run_after'   => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Process due jobs.
	 *
	 * @param int $limit Max.
	 * @return array{processed:int,errors:array<int,string>}
	 */
	public static function process_jobs( $limit = 15 ) {
		global $wpdb;
		$table = WebinoCRM_Accounting_Db_Compat::table( 'hesabfa_jobs' );
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
	 * @param array<string,mixed> $job Job.
	 * @return true|WP_Error
	 */
	public static function run_job( array $job ) {
		$id       = (int) $job['id'];
		$action   = (string) $job['action'];
		$entity   = (string) $job['entity_type'];
		$local_id = (int) ( $job['local_id'] ?? 0 );
		$attempts = (int) $job['attempts'] + 1;
		$payload  = json_decode( (string) ( $job['payload'] ?? '{}' ), true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		WebinoCRM_Accounting_Db_Compat::update(
			'hesabfa_jobs',
			$id,
			array(
				'status'   => 'running',
				'attempts' => $attempts,
			)
		);

		$result = true;
		try {
			switch ( $action ) {
				case 'push':
					$result = self::push_entity( $entity, $local_id );
					break;
				case 'pull':
					$result = self::pull_entity( $entity, $payload );
					break;
				case 'delete':
					$result = self::delete_remote( $entity, $local_id, $payload );
					break;
				case 'pull_changes':
					$result = WebinoCRM_Accounting_Hesabfa_Webhook::pull_changes();
					break;
				case 'migrate_step':
					$result = WebinoCRM_Accounting_Hesabfa_Migrate::run_step( (string) ( $payload['step'] ?? '' ), $payload );
					break;
				default:
					$result = new WP_Error( 'hesabfa_action', __( 'Unknown Hesabfa job action.', 'webinocrm' ) );
			}
		} catch ( Exception $e ) {
			$result = new WP_Error( 'hesabfa_ex', $e->getMessage() );
		}

		if ( is_wp_error( $result ) ) {
			$retry = $attempts < 5;
			WebinoCRM_Accounting_Db_Compat::update(
				'hesabfa_jobs',
				$id,
				array(
					'status'     => $retry ? 'retry' : 'failed',
					'last_error' => $result->get_error_message(),
					'run_after'  => $retry ? gmdate( 'Y-m-d H:i:s', time() + ( $attempts * 60 ) ) : null,
				)
			);
			return $result;
		}

		WebinoCRM_Accounting_Db_Compat::update(
			'hesabfa_jobs',
			$id,
			array(
				'status'     => 'done',
				'last_error' => null,
			)
		);
		return true;
	}

	/**
	 * Push local entity to Hesabfa.
	 *
	 * @param string $entity Entity.
	 * @param int    $local_id Local.
	 * @return true|WP_Error
	 */
	public static function push_entity( $entity, $local_id ) {
		$local_id = absint( $local_id );
		if ( ! $local_id ) {
			return new WP_Error( 'hesabfa_local', __( 'Missing local id.', 'webinocrm' ) );
		}
		switch ( $entity ) {
			case 'contact':
			case 'contacts':
				return self::push_contact( $local_id );
			case 'item':
			case 'items':
			case 'product':
				return self::push_item( $local_id );
			case 'invoice':
			case 'invoices':
				return self::push_invoice( $local_id );
			case 'receipt':
			case 'receipts':
			case 'voucher':
				return self::push_receipt( $local_id );
			case 'warehouse_document':
			case 'warehouses':
				return self::push_warehouse_doc( $local_id );
			case 'journal':
			case 'journals':
				return self::push_journal( $local_id );
			case 'bank_transfer':
			case 'bank_transfers':
				return self::push_bank_transfer( $local_id );
			default:
				return new WP_Error( 'hesabfa_entity', __( 'Unsupported push entity.', 'webinocrm' ) );
		}
	}

	/**
	 * @param int $person_id Person.
	 * @return true|WP_Error
	 */
	public static function push_contact( $person_id ) {
		if ( ! self::entity_enabled( 'contacts' ) ) {
			return true;
		}
		$row = WebinoCRM_Accounting_Db_Compat::get_row( 'persons', $person_id );
		if ( ! $row ) {
			return new WP_Error( 'hesabfa_nf', __( 'Person not found.', 'webinocrm' ) );
		}
		$map  = self::get_map( 'contact', $person_id );
		$hash = self::hash_row( $row );
		if ( $map && (string) ( $map['content_hash'] ?? '' ) === $hash ) {
			return true;
		}
		$contact = array(
			'Code'         => $map && ! empty( $map['remote_code'] ) ? $map['remote_code'] : '',
			'Name'         => (string) $row['name'],
			'FirstName'    => '',
			'LastName'     => '',
			'ContactType'  => 'legal' === ( $row['person_kind'] ?? '' ) ? 1 : 0,
			'NationalCode' => (string) ( $row['national_id'] ?? '' ),
			'EconomicCode' => (string) ( $row['economic_code'] ?? '' ),
			'Mobile'       => (string) ( $row['mobile'] ?? '' ),
			'Address'      => (string) ( $row['address'] ?? '' ),
			'City'         => (string) ( $row['city'] ?? '' ),
			'State'        => (string) ( $row['province'] ?? '' ),
			'PostalCode'   => (string) ( $row['postal_code'] ?? '' ),
			'Tag'          => (string) ( $row['category'] ?? '' ),
		);
		// Split natural name.
		if ( 'legal' !== ( $row['person_kind'] ?? '' ) ) {
			$parts = preg_split( '/\s+/', trim( (string) $row['name'] ), 2 );
			$contact['FirstName'] = $parts[0] ?? '';
			$contact['LastName']  = $parts[1] ?? '';
			$contact['Name']      = '';
		}
		$saved = WebinoCRM_Accounting_Hesabfa_Client::contact_save( $contact );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		if ( ! is_array( $saved ) ) {
			return new WP_Error( 'hesabfa_save', __( 'Unexpected contact save response.', 'webinocrm' ) );
		}
		self::upsert_map( 'contact', $person_id, $saved, 'push', $hash );
		return true;
	}

	/**
	 * @param int $product_id Product.
	 * @return true|WP_Error
	 */
	public static function push_item( $product_id ) {
		if ( ! self::entity_enabled( 'items' ) ) {
			return true;
		}
		$row = WebinoCRM_Accounting_Db_Compat::get_row( 'products', $product_id );
		if ( ! $row ) {
			return new WP_Error( 'hesabfa_nf', __( 'Product not found.', 'webinocrm' ) );
		}
		$map  = self::get_map( 'item', $product_id );
		$hash = self::hash_row( $row );
		if ( $map && (string) ( $map['content_hash'] ?? '' ) === $hash ) {
			return true;
		}
		$item = array(
			'Code'        => $map && ! empty( $map['remote_code'] ) ? $map['remote_code'] : '',
			'Name'        => (string) $row['name'],
			'Barcode'     => (string) ( $row['barcode'] ?? '' ),
			'Unit'        => (string) ( $row['unit'] ?? '' ),
			'ItemType'    => ! empty( $row['inventory_controlled'] ) ? 0 : 1,
			'SellPrice'   => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) $row['sell_price'] ),
			'BuyPrice'    => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) $row['buy_price'] ),
			'Tag'         => (string) ( $row['category'] ?? '' ),
			'Description' => '',
		);
		$saved = WebinoCRM_Accounting_Hesabfa_Client::item_save( $item );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		if ( ! is_array( $saved ) ) {
			return new WP_Error( 'hesabfa_save', __( 'Unexpected item save response.', 'webinocrm' ) );
		}
		self::upsert_map( 'item', $product_id, $saved, 'push', $hash );
		return true;
	}

	/**
	 * Invoice type map: local → Hesabfa (0 sale, 1 purchase, 2 sale return, 3 purchase return).
	 *
	 * @param string $type Local type.
	 * @return int
	 */
	public static function invoice_type_to_hesabfa( $type ) {
		switch ( sanitize_key( $type ) ) {
			case 'purchase':
				return 1;
			case 'sale_return':
			case 'return':
				return 2;
			case 'purchase_return':
				return 3;
			case 'sale':
			default:
				return 0;
		}
	}

	/**
	 * @param int $hesabfa_type Type.
	 * @return string
	 */
	public static function invoice_type_from_hesabfa( $hesabfa_type ) {
		switch ( (int) $hesabfa_type ) {
			case 1:
				return 'purchase';
			case 2:
				return 'sale_return';
			case 3:
				return 'purchase_return';
			default:
				return 'sale';
		}
	}

	/**
	 * @param int $invoice_id Invoice.
	 * @return true|WP_Error
	 */
	public static function push_invoice( $invoice_id ) {
		if ( ! self::entity_enabled( 'invoices' ) ) {
			return true;
		}
		$inv = Accounting_Invoices::get( $invoice_id );
		if ( is_wp_error( $inv ) ) {
			return $inv;
		}
		if ( 'proforma' === ( $inv['type'] ?? '' ) ) {
			return true;
		}
		$map  = self::get_map( 'invoice', $invoice_id );
		$hash = self::hash_row( $inv );
		if ( $map && (string) ( $map['content_hash'] ?? '' ) === $hash ) {
			return true;
		}

		$person_code = '';
		if ( ! empty( $inv['person_id'] ) ) {
			$pm = self::get_map( 'contact', (int) $inv['person_id'] );
			if ( ! $pm ) {
				$pushed = self::push_contact( (int) $inv['person_id'] );
				if ( is_wp_error( $pushed ) ) {
					return $pushed;
				}
				$pm = self::get_map( 'contact', (int) $inv['person_id'] );
			}
			$person_code = $pm ? (string) $pm['remote_code'] : '';
		}

		$invoice_items = array();
		foreach ( (array) ( $inv['lines'] ?? array() ) as $line ) {
			$item_code = '';
			if ( ! empty( $line['product_id'] ) ) {
				$im = self::get_map( 'item', (int) $line['product_id'] );
				if ( ! $im ) {
					$pushed = self::push_item( (int) $line['product_id'] );
					if ( is_wp_error( $pushed ) ) {
						return $pushed;
					}
					$im = self::get_map( 'item', (int) $line['product_id'] );
				}
				$item_code = $im ? (string) $im['remote_code'] : '';
			}
			$invoice_items[] = array(
				'Description' => (string) ( $line['description'] ?? '' ),
				'ItemCode'    => $item_code,
				'Quantity'    => (float) ( $line['qty'] ?? 1 ),
				'UnitPrice'   => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) ( $line['unit_price'] ?? 0 ) ),
				'Discount'    => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) ( $line['discount'] ?? 0 ) ),
				'Tax'         => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) ( $line['vat_amount'] ?? 0 ) ),
			);
		}

		$invoice = array(
			'Number'       => $map && ! empty( $map['remote_code'] ) ? (int) $map['remote_code'] : 0,
			'ContactCode'  => $person_code,
			'Date'         => (string) ( $inv['document_date'] ?? gmdate( 'Y-m-d' ) ) . ' 00:00:00',
			'DueDate'      => (string) ( $inv['document_date'] ?? gmdate( 'Y-m-d' ) ) . ' 00:00:00',
			'InvoiceType'  => self::invoice_type_to_hesabfa( (string) ( $inv['type'] ?? 'sale' ) ),
			'Status'       => 1,
			'InvoiceItems' => $invoice_items,
			'Description'  => 'Webino #' . $invoice_id . ( ! empty( $inv['wc_order_id'] ) ? ' WC#' . $inv['wc_order_id'] : '' ),
		);

		$wh = (string) ( WebinoCRM_Accounting_Moadian_Config::get()['hesabfa_default_warehouse_code'] ?? '' );
		if ( '' !== $wh ) {
			$invoice['WarehouseCode'] = $wh;
		}

		$saved = WebinoCRM_Accounting_Hesabfa_Client::invoice_save( $invoice );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		if ( ! is_array( $saved ) ) {
			return new WP_Error( 'hesabfa_save', __( 'Unexpected invoice save response.', 'webinocrm' ) );
		}
		self::upsert_map( 'invoice', $invoice_id, $saved, 'push', $hash );

		// Optional warehouse receipt after sale.
		$bank = (string) ( WebinoCRM_Accounting_Moadian_Config::get()['hesabfa_default_bank_code'] ?? '' );
		if ( '' !== $bank && (float) ( $inv['total'] ?? 0 ) > 0 && empty( $map ) ) {
			$num = isset( $saved['Number'] ) ? $saved['Number'] : ( $saved['number'] ?? null );
			if ( $num ) {
				WebinoCRM_Accounting_Hesabfa_Client::invoice_save_payment(
					array(
						'number'            => (int) $num,
						'bankCode'          => (int) $bank,
						'date'              => (string) ( $inv['document_date'] ?? gmdate( 'Y-m-d' ) ) . ' 00:00:00',
						'amount'            => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) $inv['total'] ),
						'transactionNumber' => ! empty( $inv['wc_order_id'] ) ? (string) $inv['wc_order_id'] : (string) $invoice_id,
						'description'       => 'Webino auto payment',
						'transactionFee'    => 0,
					)
				);
			}
		}

		return true;
	}

	/**
	 * @param int $voucher_id Voucher.
	 * @return true|WP_Error
	 */
	public static function push_receipt( $voucher_id ) {
		if ( ! self::entity_enabled( 'receipts' ) ) {
			return true;
		}
		$row = WebinoCRM_Accounting_Db_Compat::get_row( 'receipt_vouchers', $voucher_id );
		if ( ! $row ) {
			return new WP_Error( 'hesabfa_nf', __( 'Voucher not found.', 'webinocrm' ) );
		}
		$map = self::get_map( 'receipt', $voucher_id );
		$hash = self::hash_row( $row );
		if ( $map && (string) ( $map['content_hash'] ?? '' ) === $hash ) {
			return true;
		}

		$contact_code = '';
		if ( ! empty( $row['person_id'] ) ) {
			$pm = self::get_map( 'contact', (int) $row['person_id'] );
			if ( ! $pm ) {
				self::push_contact( (int) $row['person_id'] );
				$pm = self::get_map( 'contact', (int) $row['person_id'] );
			}
			$contact_code = $pm ? (string) $pm['remote_code'] : '';
		}

		$bank_code = '';
		$cash_code = '';
		if ( ! empty( $row['cash_account_id'] ) ) {
			$cm = self::get_map( 'cash_account', (int) $row['cash_account_id'] );
			$cash = WebinoCRM_Accounting_Db_Compat::get_row( 'cash_accounts', (int) $row['cash_account_id'] );
			if ( $cm ) {
				if ( $cash && 'bank' === ( $cash['type'] ?? '' ) ) {
					$bank_code = (string) $cm['remote_code'];
				} else {
					$cash_code = (string) $cm['remote_code'];
				}
			} elseif ( $cash && ! empty( WebinoCRM_Accounting_Moadian_Config::get()['hesabfa_default_bank_code'] ) ) {
				$bank_code = (string) WebinoCRM_Accounting_Moadian_Config::get()['hesabfa_default_bank_code'];
			}
		}

		$type = 'payment' === ( $row['type'] ?? '' ) ? 1 : 0;
		$receipt = array(
			'Number'      => $map && ! empty( $map['remote_code'] ) ? (int) $map['remote_code'] : 0,
			'ContactCode' => $contact_code,
			'Date'        => (string) ( $row['document_date'] ?? gmdate( 'Y-m-d' ) ) . ' 00:00:00',
			'Type'        => $type,
			'Description' => (string) ( $row['notes'] ?? ( $row['description'] ?? '' ) ),
			'Amount'      => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) $row['amount'] ),
			'BankCode'    => $bank_code,
			'CashCode'    => $cash_code,
		);

		$saved = WebinoCRM_Accounting_Hesabfa_Client::receipt_save( $receipt );
		if ( is_wp_error( $saved ) ) {
			// Fallback save2 for alternate payload shapes.
			$saved = WebinoCRM_Accounting_Hesabfa_Client::receipt_save2( $receipt );
		}
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		if ( is_array( $saved ) ) {
			self::upsert_map( 'receipt', $voucher_id, $saved, 'push', $hash );
		}
		return true;
	}

	/**
	 * @param int $doc_id Warehouse document.
	 * @return true|WP_Error
	 */
	public static function push_warehouse_doc( $doc_id ) {
		if ( ! self::entity_enabled( 'warehouses' ) ) {
			return true;
		}
		$row = WebinoCRM_Accounting_Db_Compat::get_row( 'warehouse_documents', $doc_id );
		if ( ! $row ) {
			return new WP_Error( 'hesabfa_nf', __( 'Warehouse document not found.', 'webinocrm' ) );
		}
		$map  = self::get_map( 'warehouse_document', $doc_id );
		$hash = self::hash_row( $row );
		if ( $map && (string) ( $map['content_hash'] ?? '' ) === $hash ) {
			return true;
		}

		$items = json_decode( (string) ( $row['items'] ?? '[]' ), true );
		if ( ! is_array( $items ) ) {
			$items = array();
		}
		$wh_items = array();
		foreach ( $items as $it ) {
			$code = '';
			if ( ! empty( $it['product_id'] ) ) {
				$im = self::get_map( 'item', (int) $it['product_id'] );
				if ( ! $im ) {
					self::push_item( (int) $it['product_id'] );
					$im = self::get_map( 'item', (int) $it['product_id'] );
				}
				$code = $im ? (string) $im['remote_code'] : '';
			}
			$wh_items[] = array(
				'ItemCode' => $code,
				'Quantity' => (float) ( $it['qty'] ?? 0 ),
			);
		}

		$receipt = array(
			'Number'      => $map && ! empty( $map['remote_code'] ) ? (int) $map['remote_code'] : 0,
			'Date'        => (string) ( $row['document_date'] ?? gmdate( 'Y-m-d' ) ) . ' 00:00:00',
			'Description' => (string) ( $row['notes'] ?? '' ),
			'Items'       => $wh_items,
		);
		$saved = WebinoCRM_Accounting_Hesabfa_Client::warehouse_save( $receipt );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		if ( is_array( $saved ) ) {
			self::upsert_map( 'warehouse_document', $doc_id, $saved, 'push', $hash );
		}
		return true;
	}

	/**
	 * @param int $journal_id Journal.
	 * @return true|WP_Error
	 */
	public static function push_journal( $journal_id ) {
		if ( ! self::entity_enabled( 'journals' ) ) {
			return true;
		}
		$entry = Accounting_Journal::get_with_lines( $journal_id );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}
		$map  = self::get_map( 'journal', $journal_id );
		$hash = self::hash_row( $entry );
		if ( $map && (string) ( $map['content_hash'] ?? '' ) === $hash ) {
			return true;
		}

		$transactions = array();
		foreach ( (array) ( $entry['lines'] ?? array() ) as $line ) {
			$acc = ! empty( $line['account_id'] ) ? WebinoCRM_Accounting_Db_Compat::get_row( 'chart_accounts', (int) $line['account_id'] ) : null;
			$code = $acc ? (string) ( $acc['code'] ?? '' ) : '';
			$transactions[] = array(
				'AccountPath' => $code,
				'Description' => (string) ( $line['description'] ?? '' ),
				'Debit'       => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) ( $line['debit'] ?? 0 ) ),
				'Credit'      => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) ( $line['credit'] ?? 0 ) ),
			);
		}

		$document = array(
			'Number'       => $map && ! empty( $map['remote_code'] ) ? (int) $map['remote_code'] : 0,
			'Date'         => (string) ( $entry['document_date'] ?? gmdate( 'Y-m-d' ) ) . ' 00:00:00',
			'Description'  => (string) ( $entry['description'] ?? ( $entry['memo'] ?? '' ) ),
			'Transactions' => $transactions,
		);
		$saved = WebinoCRM_Accounting_Hesabfa_Client::document_save( $document );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		if ( is_array( $saved ) ) {
			self::upsert_map( 'journal', $journal_id, $saved, 'push', $hash );
		}
		return true;
	}

	/**
	 * @param int $transfer_id Transfer.
	 * @return true|WP_Error
	 */
	public static function push_bank_transfer( $transfer_id ) {
		if ( ! self::entity_enabled( 'bank_transfers' ) ) {
			return true;
		}
		$row = WebinoCRM_Accounting_Db_Compat::get_row( 'bank_transfers', $transfer_id );
		if ( ! $row ) {
			return new WP_Error( 'hesabfa_nf', __( 'Bank transfer not found.', 'webinocrm' ) );
		}
		$map  = self::get_map( 'bank_transfer', $transfer_id );
		$hash = self::hash_row( $row );
		if ( $map && (string) ( $map['content_hash'] ?? '' ) === $hash ) {
			return true;
		}

		$from_bank = '';
		$to_bank   = '';
		if ( ! empty( $row['from_cash_account_id'] ) ) {
			$m = self::get_map( 'cash_account', (int) $row['from_cash_account_id'] );
			$from_bank = $m ? (string) $m['remote_code'] : '';
		}
		if ( ! empty( $row['to_cash_account_id'] ) ) {
			$m = self::get_map( 'cash_account', (int) $row['to_cash_account_id'] );
			$to_bank = $m ? (string) $m['remote_code'] : '';
		}

		$transfer = array(
			'number'              => $map && ! empty( $map['remote_code'] ) ? (int) $map['remote_code'] : 0,
			'description'         => (string) ( $row['description'] ?? '' ),
			'date'                => (string) ( $row['document_date'] ?? gmdate( 'Y-m-d' ) ) . 'T00:00:00',
			'fromAmount'          => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) $row['from_amount'] ),
			'toAmount'            => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) $row['to_amount'] ),
			'fromBank'            => $from_bank,
			'toBank'              => $to_bank,
			'fromTransactionFee'  => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) $row['from_fee'] ),
			'toTransactionFee'    => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount( (float) $row['to_fee'] ),
			'fromCurrencyRate'    => 1,
			'toCurrencyRate'      => 1,
		);
		$saved = WebinoCRM_Accounting_Hesabfa_Client::bank_transfer_save( $transfer );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		if ( is_array( $saved ) ) {
			self::upsert_map( 'bank_transfer', $transfer_id, $saved, 'push', $hash );
		}
		return true;
	}

	/**
	 * Pull a single remote entity into local.
	 *
	 * @param string              $entity Entity.
	 * @param array<string,mixed> $payload Payload with remote_id/code/type.
	 * @return true|WP_Error
	 */
	public static function pull_entity( $entity, array $payload ) {
		self::$syncing = true;
		try {
			switch ( $entity ) {
				case 'contact':
				case 'contacts':
					return self::pull_contact( $payload );
				case 'item':
				case 'items':
					return self::pull_item( $payload );
				case 'invoice':
				case 'invoices':
					return self::pull_invoice( $payload );
				case 'receipt':
				case 'receipts':
					return self::pull_receipt( $payload );
				case 'warehouse_document':
					return self::pull_warehouse_doc( $payload );
				case 'journal':
				case 'journals':
					return self::pull_journal( $payload );
				case 'bank_transfer':
					return self::pull_bank_transfer( $payload );
				default:
					return new WP_Error( 'hesabfa_entity', __( 'Unsupported pull entity.', 'webinocrm' ) );
			}
		} finally {
			self::$syncing = false;
		}
	}

	/**
	 * @param array<string,mixed> $payload Payload.
	 * @return true|WP_Error
	 */
	public static function pull_contact( array $payload ) {
		$remote = null;
		if ( ! empty( $payload['remote_code'] ) ) {
			$remote = WebinoCRM_Accounting_Hesabfa_Client::contact_get( $payload['remote_code'] );
		} elseif ( ! empty( $payload['remote_id'] ) ) {
			$list = WebinoCRM_Accounting_Hesabfa_Client::contact_get_by_id( array( (int) $payload['remote_id'] ) );
			if ( is_array( $list ) && isset( $list[0] ) ) {
				$remote = $list[0];
			} elseif ( is_array( $list ) && ! isset( $list[0] ) && isset( $list['Code'] ) ) {
				$remote = $list;
			}
		}
		if ( is_wp_error( $remote ) ) {
			return $remote;
		}
		if ( ! is_array( $remote ) ) {
			return new WP_Error( 'hesabfa_nf', __( 'Remote contact not found.', 'webinocrm' ) );
		}
		return self::upsert_contact_from_remote( $remote );
	}

	/**
	 * @param array<string,mixed> $remote Remote contact.
	 * @return int|WP_Error Local id.
	 */
	public static function upsert_contact_from_remote( array $remote ) {
		$remote_id = isset( $remote['Id'] ) ? (int) $remote['Id'] : 0;
		$code      = isset( $remote['Code'] ) ? (string) $remote['Code'] : '';
		$map       = $remote_id ? self::get_map_by_remote( 'contact', $remote_id ) : null;
		if ( ! $map && '' !== $code ) {
			$map = self::get_map_by_code( 'contact', $code );
		}

		$name = (string) ( $remote['Name'] ?? '' );
		if ( '' === $name ) {
			$name = trim( (string) ( $remote['FirstName'] ?? '' ) . ' ' . (string) ( $remote['LastName'] ?? '' ) );
		}
		if ( '' === $name ) {
			$name = 'Contact ' . $code;
		}

		$data = array(
			'name'          => $name,
			'type'          => 'both',
			'person_kind'   => ! empty( $remote['ContactType'] ) ? 'legal' : 'natural',
			'national_id'   => (string) ( $remote['NationalCode'] ?? '' ),
			'economic_code' => (string) ( $remote['EconomicCode'] ?? '' ),
			'mobile'        => (string) ( $remote['Mobile'] ?? '' ),
			'address'       => (string) ( $remote['Address'] ?? '' ),
			'city'          => (string) ( $remote['City'] ?? '' ),
			'province'      => (string) ( $remote['State'] ?? '' ),
			'postal_code'    => (string) ( $remote['PostalCode'] ?? '' ),
			'category'      => (string) ( $remote['Tag'] ?? '' ),
			'sync_source'   => 'hesabfa',
		);

		if ( $map ) {
			$local_id = (int) $map['local_id'];
			$local    = WebinoCRM_Accounting_Db_Compat::get_row( 'persons', $local_id );
			if ( $local && self::remote_wins( $local, $remote, $map ) ) {
				Accounting_Persons::update( $local_id, $data );
			} elseif ( ! $local ) {
				$local_id = Accounting_Persons::create( $data );
				if ( is_wp_error( $local_id ) ) {
					return $local_id;
				}
			}
		} else {
			$local_id = Accounting_Persons::create( $data );
			if ( is_wp_error( $local_id ) ) {
				return $local_id;
			}
		}

		self::upsert_map( 'contact', (int) $local_id, $remote, 'pull', self::hash_row( $data ) );
		return (int) $local_id;
	}

	/**
	 * @param array<string,mixed> $payload Payload.
	 * @return true|WP_Error
	 */
	public static function pull_item( array $payload ) {
		$remote = null;
		if ( ! empty( $payload['remote_code'] ) ) {
			$remote = WebinoCRM_Accounting_Hesabfa_Client::item_get( $payload['remote_code'] );
		} elseif ( ! empty( $payload['remote_id'] ) ) {
			$list = WebinoCRM_Accounting_Hesabfa_Client::item_get_by_id( array( (int) $payload['remote_id'] ) );
			if ( is_array( $list ) && isset( $list[0] ) ) {
				$remote = $list[0];
			} elseif ( is_array( $list ) && isset( $list['Code'] ) ) {
				$remote = $list;
			}
		}
		if ( is_wp_error( $remote ) ) {
			return $remote;
		}
		if ( ! is_array( $remote ) ) {
			return new WP_Error( 'hesabfa_nf', __( 'Remote item not found.', 'webinocrm' ) );
		}
		$id = self::upsert_item_from_remote( $remote );
		return is_wp_error( $id ) ? $id : true;
	}

	/**
	 * @param array<string,mixed> $remote Remote item.
	 * @return int|WP_Error
	 */
	public static function upsert_item_from_remote( array $remote ) {
		$remote_id = isset( $remote['Id'] ) ? (int) $remote['Id'] : 0;
		$code      = isset( $remote['Code'] ) ? (string) $remote['Code'] : '';
		$barcode   = (string) ( $remote['Barcode'] ?? '' );
		$map       = $remote_id ? self::get_map_by_remote( 'item', $remote_id ) : null;
		if ( ! $map && '' !== $code ) {
			$map = self::get_map_by_code( 'item', $code );
		}

		$wc_id = 0;
		$link_only = ! empty( WebinoCRM_Accounting_Moadian_Config::get()['hesabfa_link_wc_only'] );
		if ( '' !== $barcode && function_exists( 'wc_get_product_id_by_sku' ) ) {
			$wc_id = (int) wc_get_product_id_by_sku( $barcode );
		}
		if ( ! $wc_id && '' !== $code && function_exists( 'wc_get_product_id_by_sku' ) ) {
			$wc_id = (int) wc_get_product_id_by_sku( $code );
		}

		$data = array(
			'name'                 => (string) ( $remote['Name'] ?? ( 'Item ' . $code ) ),
			'unit'                 => (string) ( $remote['Unit'] ?? '' ),
			'barcode'              => $barcode,
			'category'             => (string) ( $remote['Tag'] ?? '' ),
			'buy_price'            => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount_to_local( (float) ( $remote['BuyPrice'] ?? 0 ) ),
			'sell_price'           => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount_to_local( (float) ( $remote['SellPrice'] ?? 0 ) ),
			'inventory_controlled' => isset( $remote['ItemType'] ) ? ( 0 === (int) $remote['ItemType'] ? 1 : 0 ) : 1,
			'wc_product_id'        => $wc_id ?: null,
		);

		if ( $map ) {
			$local_id = (int) $map['local_id'];
			$local    = WebinoCRM_Accounting_Db_Compat::get_row( 'products', $local_id );
			if ( $local && self::remote_wins( $local, $remote, $map ) ) {
				if ( $link_only && ! empty( $local['wc_product_id'] ) ) {
					// Keep WC-linked product fields; only refresh map + prices optionally.
					Accounting_Products::update(
						$local_id,
						array(
							'buy_price'  => $data['buy_price'],
							'sell_price' => $data['sell_price'],
							'barcode'    => $data['barcode'] ?: ( $local['barcode'] ?? '' ),
						)
					);
				} else {
					Accounting_Products::update( $local_id, $data );
				}
			}
		} else {
			$local_id = Accounting_Products::create( $data );
			if ( is_wp_error( $local_id ) ) {
				return $local_id;
			}
		}

		self::upsert_map( 'item', (int) $local_id, $remote, 'pull', self::hash_row( $data ) );
		return (int) $local_id;
	}

	/**
	 * @param array<string,mixed> $payload Payload.
	 * @return true|WP_Error
	 */
	public static function pull_invoice( array $payload ) {
		$type = isset( $payload['invoice_type'] ) ? (int) $payload['invoice_type'] : 0;
		$remote = null;
		if ( ! empty( $payload['remote_code'] ) || ! empty( $payload['number'] ) ) {
			$num = $payload['remote_code'] ?? $payload['number'];
			$remote = WebinoCRM_Accounting_Hesabfa_Client::invoice_get( $num, $type );
		} elseif ( ! empty( $payload['remote_id'] ) ) {
			$remote = WebinoCRM_Accounting_Hesabfa_Client::invoice_get_by_id( (int) $payload['remote_id'] );
		}
		if ( is_wp_error( $remote ) ) {
			return $remote;
		}
		if ( ! is_array( $remote ) ) {
			return new WP_Error( 'hesabfa_nf', __( 'Remote invoice not found.', 'webinocrm' ) );
		}
		$id = self::upsert_invoice_from_remote( $remote );
		return is_wp_error( $id ) ? $id : true;
	}

	/**
	 * @param array<string,mixed> $remote Remote invoice.
	 * @return int|WP_Error
	 */
	public static function upsert_invoice_from_remote( array $remote ) {
		$remote_id = isset( $remote['Id'] ) ? (int) $remote['Id'] : 0;
		$number    = isset( $remote['Number'] ) ? (string) $remote['Number'] : '';
		$type      = self::invoice_type_from_hesabfa( (int) ( $remote['InvoiceType'] ?? 0 ) );
		$map       = $remote_id ? self::get_map_by_remote( 'invoice', $remote_id ) : null;
		if ( ! $map && '' !== $number ) {
			$map = self::get_map_by_code( 'invoice', $number );
		}
		if ( $map ) {
			// Already linked — skip overwrite of posted local invoices.
			$existing = Accounting_Invoices::get( (int) $map['local_id'] );
			if ( ! is_wp_error( $existing ) && 'posted' === ( $existing['status'] ?? '' ) ) {
				self::upsert_map( 'invoice', (int) $map['local_id'], $remote, 'pull' );
				return (int) $map['local_id'];
			}
		}

		$person_id = 0;
		$contact_code = (string) ( $remote['ContactCode'] ?? '' );
		if ( '' !== $contact_code ) {
			$cm = self::get_map_by_code( 'contact', $contact_code );
			if ( $cm ) {
				$person_id = (int) $cm['local_id'];
			} else {
				$c = WebinoCRM_Accounting_Hesabfa_Client::contact_get( $contact_code );
				if ( is_array( $c ) ) {
					$person_id = (int) self::upsert_contact_from_remote( $c );
				}
			}
		}

		$lines = array();
		foreach ( (array) ( $remote['InvoiceItems'] ?? array() ) as $it ) {
			$product_id = 0;
			$item_code  = (string) ( $it['ItemCode'] ?? '' );
			if ( '' !== $item_code ) {
				$im = self::get_map_by_code( 'item', $item_code );
				if ( $im ) {
					$product_id = (int) $im['local_id'];
				} else {
					$iremote = WebinoCRM_Accounting_Hesabfa_Client::item_get( $item_code );
					if ( is_array( $iremote ) ) {
						$product_id = (int) self::upsert_item_from_remote( $iremote );
					}
				}
			}
			$lines[] = array(
				'product_id'  => $product_id ?: null,
				'description' => (string) ( $it['Description'] ?? '' ),
				'qty'         => (float) ( $it['Quantity'] ?? 1 ),
				'unit_price'  => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount_to_local( (float) ( $it['UnitPrice'] ?? 0 ) ),
				'discount'    => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount_to_local( (float) ( $it['Discount'] ?? 0 ) ),
				'vat_amount'  => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount_to_local( (float) ( $it['Tax'] ?? 0 ) ),
				'vat_rate'    => 0,
			);
		}

		$header = array(
			'type'          => $type,
			'number'        => $number,
			'person_id'     => $person_id ?: null,
			'document_date' => isset( $remote['Date'] ) ? gmdate( 'Y-m-d', strtotime( (string) $remote['Date'] ) ) : gmdate( 'Y-m-d' ),
			'status'        => 'draft',
		);

		if ( $map ) {
			$local_id = (int) $map['local_id'];
			self::upsert_map( 'invoice', $local_id, $remote, 'pull' );
			return $local_id;
		}

		$local_id = Accounting_Invoices::create( $header, $lines );
		if ( is_wp_error( $local_id ) ) {
			return $local_id;
		}
		self::upsert_map( 'invoice', (int) $local_id, $remote, 'pull' );
		return (int) $local_id;
	}

	/**
	 * @param array<string,mixed> $payload Payload.
	 * @return true|WP_Error
	 */
	public static function pull_receipt( array $payload ) {
		$type = isset( $payload['receipt_type'] ) ? (int) $payload['receipt_type'] : 0;
		$remote = null;
		if ( ! empty( $payload['remote_code'] ) || ! empty( $payload['number'] ) ) {
			$remote = WebinoCRM_Accounting_Hesabfa_Client::receipt_get( $payload['remote_code'] ?? $payload['number'], $type );
		} elseif ( ! empty( $payload['remote_id'] ) ) {
			$remote = WebinoCRM_Accounting_Hesabfa_Client::receipt_get_by_id( (int) $payload['remote_id'] );
		}
		if ( is_wp_error( $remote ) || ! is_array( $remote ) ) {
			return is_wp_error( $remote ) ? $remote : new WP_Error( 'hesabfa_nf', __( 'Remote receipt not found.', 'webinocrm' ) );
		}
		return self::upsert_receipt_from_remote( $remote );
	}

	/**
	 * @param array<string,mixed> $remote Remote.
	 * @return true|WP_Error
	 */
	public static function upsert_receipt_from_remote( array $remote ) {
		$remote_id = isset( $remote['Id'] ) ? (int) $remote['Id'] : 0;
		$number    = isset( $remote['Number'] ) ? (string) $remote['Number'] : '';
		$map       = $remote_id ? self::get_map_by_remote( 'receipt', $remote_id ) : null;
		if ( $map ) {
			self::upsert_map( 'receipt', (int) $map['local_id'], $remote, 'pull' );
			return true;
		}

		$person_id = 0;
		$code = (string) ( $remote['ContactCode'] ?? '' );
		if ( '' !== $code ) {
			$cm = self::get_map_by_code( 'contact', $code );
			$person_id = $cm ? (int) $cm['local_id'] : 0;
		}

		$id = WebinoCRM_Accounting_Db_Compat::insert(
			'receipt_vouchers',
			array(
				'type'            => ! empty( $remote['Type'] ) ? 'payment' : 'receipt',
				'number'          => $number,
				'person_id'       => $person_id ?: null,
				'amount'          => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount_to_local( (float) ( $remote['Amount'] ?? 0 ) ),
				'document_date'   => isset( $remote['Date'] ) ? gmdate( 'Y-m-d', strtotime( (string) $remote['Date'] ) ) : gmdate( 'Y-m-d' ),
				'status'          => 'posted',
				'description'     => (string) ( $remote['Description'] ?? '' ),
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		self::upsert_map( 'receipt', (int) $id, $remote, 'pull' );
		return true;
	}

	/**
	 * @param array<string,mixed> $payload Payload.
	 * @return true|WP_Error
	 */
	public static function pull_warehouse_doc( array $payload ) {
		$remote = null;
		if ( ! empty( $payload['remote_code'] ) || ! empty( $payload['number'] ) ) {
			$remote = WebinoCRM_Accounting_Hesabfa_Client::warehouse_get( $payload['remote_code'] ?? $payload['number'] );
		} elseif ( ! empty( $payload['remote_id'] ) ) {
			$remote = WebinoCRM_Accounting_Hesabfa_Client::warehouse_get_by_id( (int) $payload['remote_id'] );
		}
		if ( is_wp_error( $remote ) || ! is_array( $remote ) ) {
			return is_wp_error( $remote ) ? $remote : new WP_Error( 'hesabfa_nf', __( 'Remote warehouse doc not found.', 'webinocrm' ) );
		}
		$remote_id = isset( $remote['Id'] ) ? (int) $remote['Id'] : 0;
		$map = $remote_id ? self::get_map_by_remote( 'warehouse_document', $remote_id ) : null;
		if ( $map ) {
			self::upsert_map( 'warehouse_document', (int) $map['local_id'], $remote, 'pull' );
			return true;
		}
		$items = array();
		foreach ( (array) ( $remote['Items'] ?? array() ) as $it ) {
			$pid = 0;
			$ic  = (string) ( $it['ItemCode'] ?? '' );
			if ( '' !== $ic ) {
				$im = self::get_map_by_code( 'item', $ic );
				$pid = $im ? (int) $im['local_id'] : 0;
			}
			$items[] = array(
				'product_id' => $pid,
				'qty'        => (float) ( $it['Quantity'] ?? 0 ),
			);
		}
		$wh_id = (int) ( WebinoCRM_Accounting_Moadian_Config::get()['default_warehouse_id'] ?? 0 );
		if ( $wh_id <= 0 ) {
			global $wpdb;
			$wt    = WebinoCRM_Accounting_Db_Compat::table( 'warehouses' );
			$wh_id = (int) $wpdb->get_var( "SELECT id FROM {$wt} ORDER BY is_default DESC, id ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$id = WebinoCRM_Accounting_Db_Compat::insert(
			'warehouse_documents',
			array(
				'type'          => 'receipt',
				'warehouse_id'  => max( 1, $wh_id ),
				'number'        => (string) ( $remote['Number'] ?? '' ),
				'document_date' => isset( $remote['Date'] ) ? gmdate( 'Y-m-d', strtotime( (string) $remote['Date'] ) ) : gmdate( 'Y-m-d' ),
				'notes'         => (string) ( $remote['Description'] ?? '' ),
				'items'         => wp_json_encode( $items ),
				'status'        => 'posted',
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		self::upsert_map( 'warehouse_document', (int) $id, $remote, 'pull' );
		return true;
	}

	/**
	 * @param array<string,mixed> $payload Payload.
	 * @return true|WP_Error
	 */
	public static function pull_journal( array $payload ) {
		$remote = null;
		if ( ! empty( $payload['remote_code'] ) || ! empty( $payload['number'] ) ) {
			$remote = WebinoCRM_Accounting_Hesabfa_Client::document_get( $payload['remote_code'] ?? $payload['number'] );
		}
		if ( is_wp_error( $remote ) || ! is_array( $remote ) ) {
			return is_wp_error( $remote ) ? $remote : new WP_Error( 'hesabfa_nf', __( 'Remote document not found.', 'webinocrm' ) );
		}
		$remote_id = isset( $remote['Id'] ) ? (int) $remote['Id'] : 0;
		$map = $remote_id ? self::get_map_by_remote( 'journal', $remote_id ) : null;
		if ( $map ) {
			self::upsert_map( 'journal', (int) $map['local_id'], $remote, 'pull' );
			return true;
		}

		$lines = array();
		foreach ( (array) ( $remote['Transactions'] ?? array() ) as $tx ) {
			$path = (string) ( $tx['AccountPath'] ?? '' );
			$acc_id = 0;
			if ( '' !== $path ) {
				global $wpdb;
				$t = WebinoCRM_Accounting_Db_Compat::table( 'chart_accounts' );
				$acc_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE code = %s LIMIT 1", $path ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			$lines[] = array(
				'account_id'  => $acc_id ?: null,
				'description' => (string) ( $tx['Description'] ?? '' ),
				'debit'       => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount_to_local( (float) ( $tx['Debit'] ?? 0 ) ),
				'credit'      => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount_to_local( (float) ( $tx['Credit'] ?? 0 ) ),
			);
		}

		$header = array(
			'document_date' => isset( $remote['Date'] ) ? gmdate( 'Y-m-d', strtotime( (string) $remote['Date'] ) ) : gmdate( 'Y-m-d' ),
			'description'   => (string) ( $remote['Description'] ?? '' ),
			'memo'          => 'Hesabfa #' . (string) ( $remote['Number'] ?? '' ),
		);
		$id = Accounting_Journal::create( $header, $lines, false );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		self::upsert_map( 'journal', (int) $id, $remote, 'pull' );
		return true;
	}

	/**
	 * @param array<string,mixed> $payload Payload.
	 * @return true|WP_Error
	 */
	public static function pull_bank_transfer( array $payload ) {
		$remote = null;
		if ( ! empty( $payload['remote_code'] ) || ! empty( $payload['number'] ) ) {
			$remote = WebinoCRM_Accounting_Hesabfa_Client::bank_transfer_get( $payload['remote_code'] ?? $payload['number'] );
		}
		if ( is_wp_error( $remote ) || ! is_array( $remote ) ) {
			return is_wp_error( $remote ) ? $remote : new WP_Error( 'hesabfa_nf', __( 'Remote transfer not found.', 'webinocrm' ) );
		}
		$remote_id = isset( $remote['Id'] ) ? (int) $remote['Id'] : 0;
		$map = $remote_id ? self::get_map_by_remote( 'bank_transfer', $remote_id ) : null;
		if ( $map ) {
			self::upsert_map( 'bank_transfer', (int) $map['local_id'], $remote, 'pull' );
			return true;
		}
		$id = WebinoCRM_Accounting_Db_Compat::insert(
			'bank_transfers',
			array(
				'number'        => (string) ( $remote['Number'] ?? '' ),
				'document_date' => isset( $remote['Date'] ) ? gmdate( 'Y-m-d', strtotime( (string) $remote['Date'] ) ) : gmdate( 'Y-m-d' ),
				'description'   => (string) ( $remote['Description'] ?? '' ),
				'from_amount'   => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount_to_local( (float) ( $remote['FromAmount'] ?? 0 ) ),
				'to_amount'     => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount_to_local( (float) ( $remote['ToAmount'] ?? 0 ) ),
				'from_fee'      => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount_to_local( (float) ( $remote['FromTransactionFee'] ?? 0 ) ),
				'to_fee'        => WebinoCRM_Accounting_Moadian_Config::hesabfa_amount_to_local( (float) ( $remote['ToTransactionFee'] ?? 0 ) ),
				'project'       => (string) ( $remote['Project'] ?? '' ),
				'status'        => 'posted',
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		self::upsert_map( 'bank_transfer', (int) $id, $remote, 'pull' );
		return true;
	}

	/**
	 * Import cash accounts from Hesabfa banks/cashes/petty.
	 *
	 * @return array{imported:int}|WP_Error
	 */
	public static function import_cash_accounts() {
		self::$syncing = true;
		$imported = 0;
		try {
			foreach (
				array(
					array( 'fn' => 'setting_get_banks', 'type' => 'bank' ),
					array( 'fn' => 'setting_get_cashes', 'type' => 'cash' ),
					array( 'fn' => 'setting_get_petty_cashes', 'type' => 'petty' ),
				) as $src
			) {
				$list = call_user_func( array( 'WebinoCRM_Accounting_Hesabfa_Client', $src['fn'] ) );
				if ( is_wp_error( $list ) ) {
					return $list;
				}
				if ( ! is_array( $list ) ) {
					continue;
				}
				foreach ( $list as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$code = (string) ( $row['Code'] ?? ( $row['code'] ?? '' ) );
					$name = (string) ( $row['Name'] ?? ( $row['name'] ?? $code ) );
					if ( '' === $code ) {
						continue;
					}
					$map = self::get_map_by_code( 'cash_account', $code );
					if ( $map ) {
						continue;
					}
					$id = WebinoCRM_Accounting_Db_Compat::insert(
						'cash_accounts',
						array(
							'name'           => $name,
							'type'           => $src['type'],
							'bank_name'      => $name,
							'account_number' => (string) ( $row['AccountNumber'] ?? '' ),
							'sheba'          => (string) ( $row['Sheba'] ?? ( $row['IBAN'] ?? '' ) ),
							'is_active'      => 1,
						)
					);
					if ( ! is_wp_error( $id ) ) {
						self::upsert_map( 'cash_account', (int) $id, array_merge( $row, array( 'Code' => $code ) ), 'pull' );
						++$imported;
					}
				}
			}
		} finally {
			self::$syncing = false;
		}
		return array( 'imported' => $imported );
	}

	/**
	 * Import warehouses list.
	 *
	 * @return array{imported:int}|WP_Error
	 */
	public static function import_warehouses() {
		self::$syncing = true;
		$imported = 0;
		try {
			$list = WebinoCRM_Accounting_Hesabfa_Client::setting_get_warehouses();
			if ( is_wp_error( $list ) ) {
				return $list;
			}
			foreach ( (array) $list as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$code = (string) ( $row['Code'] ?? '' );
				$name = (string) ( $row['Name'] ?? $code );
				if ( '' === $code ) {
					continue;
				}
				$map = self::get_map_by_code( 'warehouse', $code );
				if ( $map ) {
					continue;
				}
				$id = WebinoCRM_Accounting_Db_Compat::insert(
					'warehouses',
					array(
						'name'      => $name . ' (' . $code . ')',
						'address'   => '',
						'is_active' => 1,
					)
				);
				if ( ! is_wp_error( $id ) ) {
					self::upsert_map( 'warehouse', (int) $id, $row, 'pull' );
					++$imported;
				}
			}
		} finally {
			self::$syncing = false;
		}
		return array( 'imported' => $imported );
	}

	/**
	 * Import chart accounts (read-only from Hesabfa).
	 *
	 * @return array{imported:int}|WP_Error
	 */
	public static function import_accounts() {
		if ( ! self::entity_enabled( 'accounts' ) ) {
			return array( 'imported' => 0 );
		}
		self::$syncing = true;
		$imported = 0;
		try {
			$list = WebinoCRM_Accounting_Hesabfa_Client::setting_get_accounts();
			if ( is_wp_error( $list ) ) {
				return $list;
			}
			foreach ( (array) $list as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$code = (string) ( $row['Code'] ?? ( $row['Path'] ?? ( $row['AccountPath'] ?? '' ) ) );
				$name = (string) ( $row['Name'] ?? ( $row['Title'] ?? $code ) );
				if ( '' === $code ) {
					continue;
				}
				global $wpdb;
				$t = WebinoCRM_Accounting_Db_Compat::table( 'chart_accounts' );
				$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE code = %s LIMIT 1", $code ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $existing ) {
					self::upsert_map( 'account', $existing, array_merge( $row, array( 'Code' => $code ) ), 'pull' );
					continue;
				}
				$id = WebinoCRM_Accounting_Db_Compat::insert(
					'chart_accounts',
					array(
						'code'        => $code,
						'name'        => $name,
						'type'        => sanitize_key( (string) ( $row['Type'] ?? 'other' ) ) ?: 'other',
						'is_postable' => 1,
					)
				);
				if ( ! is_wp_error( $id ) ) {
					self::upsert_map( 'account', (int) $id, array_merge( $row, array( 'Code' => $code ) ), 'pull' );
					++$imported;
				}
			}
		} finally {
			self::$syncing = false;
		}
		return array( 'imported' => $imported );
	}

	/**
	 * Delete remote after local delete (unlink WC products instead of deleting).
	 *
	 * @param string              $entity Entity.
	 * @param int                 $local_id Local.
	 * @param array<string,mixed> $payload Payload.
	 * @return true|WP_Error
	 */
	public static function delete_remote( $entity, $local_id, array $payload ) {
		$map = $local_id ? self::get_map( $entity, $local_id ) : null;
		$code = $map ? (string) $map['remote_code'] : (string) ( $payload['remote_code'] ?? '' );
		if ( '' === $code ) {
			return true;
		}
		switch ( $entity ) {
			case 'contact':
				$res = WebinoCRM_Accounting_Hesabfa_Client::contact_delete( $code );
				break;
			case 'item':
				$res = WebinoCRM_Accounting_Hesabfa_Client::item_delete( $code );
				break;
			case 'invoice':
				$type = isset( $payload['invoice_type'] ) ? (int) $payload['invoice_type'] : 0;
				$res  = WebinoCRM_Accounting_Hesabfa_Client::invoice_delete( $code, $type );
				break;
			case 'receipt':
				$type = isset( $payload['receipt_type'] ) ? (int) $payload['receipt_type'] : 0;
				$res  = WebinoCRM_Accounting_Hesabfa_Client::receipt_delete( $code, $type );
				break;
			case 'warehouse_document':
				$res = WebinoCRM_Accounting_Hesabfa_Client::warehouse_delete( $code );
				break;
			case 'journal':
				$res = WebinoCRM_Accounting_Hesabfa_Client::document_delete( $code );
				break;
			case 'bank_transfer':
				$res = WebinoCRM_Accounting_Hesabfa_Client::bank_transfer_delete( $code );
				break;
			default:
				$res = true;
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( $map ) {
			global $wpdb;
			$wpdb->delete( WebinoCRM_Accounting_Db_Compat::table( 'hesabfa_map' ), array( 'id' => (int) $map['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}
		return true;
	}

	/**
	 * Last-write-wins: remote wins if remote LastUpdate newer than local updated_at and hash differs.
	 *
	 * @param array<string,mixed> $local Local row.
	 * @param array<string,mixed> $remote Remote.
	 * @param array<string,mixed> $map Map.
	 * @return bool
	 */
	private static function remote_wins( array $local, array $remote, array $map ) {
		$remote_ts = 0;
		foreach ( array( 'LastUpdateDate', 'UpdateDate', 'Date' ) as $k ) {
			if ( ! empty( $remote[ $k ] ) ) {
				$remote_ts = strtotime( (string) $remote[ $k ] );
				if ( $remote_ts ) {
					break;
				}
			}
		}
		$local_ts = ! empty( $local['updated_at'] ) ? strtotime( (string) $local['updated_at'] ) : 0;
		if ( $remote_ts && $local_ts && $remote_ts < $local_ts ) {
			return false;
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $row Row.
	 * @return string
	 */
	public static function hash_row( array $row ) {
		unset( $row['updated_at'], $row['created_at'], $row['lines'], $row['items'] );
		ksort( $row );
		return hash( 'sha256', (string) wp_json_encode( $row ) );
	}
}
