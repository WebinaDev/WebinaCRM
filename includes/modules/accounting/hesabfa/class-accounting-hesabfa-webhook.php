<?php
/**
 * Hesabfa change hook + GetChanges polling.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook endpoint and incremental sync from Hesabfa.
 */
final class WebinoCRM_Accounting_Hesabfa_Webhook {

	/**
	 * Register public REST route for Hesabfa SetChangeHook.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'webino-dashboard/v1',
			'/accounting/hesabfa/hook',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'handle_hook' ),
			)
		);
	}

	/**
	 * Public hook URL.
	 *
	 * @return string
	 */
	public static function hook_url() {
		return rest_url( 'webino-dashboard/v1/accounting/hesabfa/hook' );
	}

	/**
	 * Register SetChangeHook with Hesabfa.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function register_hook() {
		$cfg = WebinoCRM_Accounting_Moadian_Config::get();
		$pass = (string) ( $cfg['hesabfa_hook_password'] ?? '' );
		if ( '' === $pass ) {
			$pass = wp_generate_password( 24, false, false );
			WebinoCRM_Accounting_Moadian_Config::save( array( 'hesabfa_hook_password' => $pass ) );
		}
		$res = WebinoCRM_Accounting_Hesabfa_Client::setting_set_change_hook( self::hook_url(), $pass );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array(
			'ok'       => true,
			'hook_url' => self::hook_url(),
		);
	}

	/**
	 * Handle inbound webhook from Hesabfa.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_hook( WP_REST_Request $request ) {
		$cfg  = WebinoCRM_Accounting_Moadian_Config::get();
		$pass = (string) ( $cfg['hesabfa_hook_password'] ?? '' );
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$provided = (string) (
			$request->get_header( 'x-hesabfa-password' )
			?: ( $body['Password'] ?? ( $body['password'] ?? ( $body['hookPassword'] ?? '' ) ) )
		);
		$query_pass = (string) $request->get_param( 'password' );
		if ( '' !== $query_pass ) {
			$provided = $query_pass;
		}

		if ( '' === $pass || ! hash_equals( $pass, $provided ) ) {
			return new WP_Error( 'hesabfa_hook_auth', __( 'Invalid Hesabfa hook password.', 'webinocrm' ), array( 'status' => 403 ) );
		}

		WebinoCRM_Accounting_Hesabfa_Sync::enqueue( 'pull_changes', 'changes', null, array() );
		// Process a small batch immediately for low latency.
		$result = self::pull_changes();
		return new WP_REST_Response(
			array(
				'ok'     => ! is_wp_error( $result ),
				'result' => is_wp_error( $result ) ? $result->get_error_message() : $result,
			)
		);
	}

	/**
	 * Pull GetChanges from last cursor.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function pull_changes() {
		if ( ! WebinoCRM_Accounting_Hesabfa_Sync::enabled() ) {
			return new WP_Error( 'hesabfa_disabled', __( 'Hesabfa sync is disabled.', 'webinocrm' ) );
		}
		$cfg   = WebinoCRM_Accounting_Moadian_Config::get();
		$start = (int) ( $cfg['hesabfa_last_change_id'] ?? 0 );
		$res   = WebinoCRM_Accounting_Hesabfa_Client::setting_get_changes( $start );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$changes = array();
		$last_id = $start;
		if ( is_array( $res ) ) {
			if ( isset( $res['Changes'] ) && is_array( $res['Changes'] ) ) {
				$changes = $res['Changes'];
			} elseif ( isset( $res['List'] ) && is_array( $res['List'] ) ) {
				$changes = $res['List'];
			} elseif ( WebinoCRM_Accounting_Hesabfa_Client::is_list( $res ) ) {
				$changes = $res;
			}
			if ( isset( $res['LastId'] ) ) {
				$last_id = (int) $res['LastId'];
			} elseif ( isset( $res['LastChangeId'] ) ) {
				$last_id = (int) $res['LastChangeId'];
			}
		}

		$pulled = 0;
		$errors = array();
		foreach ( $changes as $change ) {
			if ( ! is_array( $change ) ) {
				continue;
			}
			if ( isset( $change['Id'] ) && (int) $change['Id'] > $last_id ) {
				$last_id = (int) $change['Id'];
			}
			$stat = self::apply_change( $change );
			if ( is_wp_error( $stat ) ) {
				$errors[] = $stat->get_error_message();
			} else {
				++$pulled;
			}
		}

		if ( $last_id > $start ) {
			WebinoCRM_Accounting_Moadian_Config::save( array( 'hesabfa_last_change_id' => $last_id ) );
		}

		return array(
			'pulled'  => $pulled,
			'last_id' => $last_id,
			'errors'  => $errors,
		);
	}

	/**
	 * Apply one change object from GetChanges.
	 *
	 * ObjectType examples from official Woo plugin usage: Contact, Item, Invoice, …
	 *
	 * @param array<string,mixed> $change Change.
	 * @return true|WP_Error
	 */
	public static function apply_change( array $change ) {
		$type = (string) ( $change['ObjectType'] ?? ( $change['Type'] ?? ( $change['objectType'] ?? '' ) ) );
		$id   = isset( $change['ObjectId'] ) ? (int) $change['ObjectId'] : ( isset( $change['Id'] ) ? (int) $change['Id'] : 0 );
		$code = (string) ( $change['Code'] ?? ( $change['ObjectCode'] ?? ( $change['Number'] ?? '' ) ) );
		$action = strtolower( (string) ( $change['Action'] ?? ( $change['action'] ?? 'edit' ) ) );

		$entity = self::map_object_type( $type );
		if ( '' === $entity ) {
			return true;
		}

		if ( in_array( $action, array( 'delete', 'deleted', 'remove' ), true ) ) {
			$map = $id ? WebinoCRM_Accounting_Hesabfa_Sync::get_map_by_remote( $entity, $id ) : null;
			if ( ! $map && '' !== $code ) {
				$map = WebinoCRM_Accounting_Hesabfa_Sync::get_map_by_code( $entity, $code );
			}
			if ( $map ) {
				// Unlink only — do not delete WC-linked products/persons.
				global $wpdb;
				$wpdb->delete( WebinoCRM_Accounting_Db_Compat::table( 'hesabfa_map' ), array( 'id' => (int) $map['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			}
			return true;
		}

		$payload = array(
			'remote_id'   => $id,
			'remote_code' => $code,
		);
		if ( 'invoice' === $entity && isset( $change['InvoiceType'] ) ) {
			$payload['invoice_type'] = (int) $change['InvoiceType'];
		}
		if ( 'receipt' === $entity && isset( $change['ReceiptType'] ) ) {
			$payload['receipt_type'] = (int) $change['ReceiptType'];
		}

		return WebinoCRM_Accounting_Hesabfa_Sync::pull_entity( $entity, $payload );
	}

	/**
	 * @param string $type ObjectType.
	 * @return string Local entity key.
	 */
	private static function map_object_type( $type ) {
		$t = strtolower( preg_replace( '/\s+/', '', (string) $type ) );
		$map = array(
			'contact'           => 'contact',
			'item'              => 'item',
			'invoice'           => 'invoice',
			'receipt'           => 'receipt',
			'receive'           => 'receipt',
			'payment'           => 'receipt',
			'warehouse'         => 'warehouse_document',
			'warehousereceipt'  => 'warehouse_document',
			'document'          => 'journal',
			'accountingdocument'=> 'journal',
			'banktransfer'      => 'bank_transfer',
			'transfer'          => 'bank_transfer',
		);
		return $map[ $t ] ?? '';
	}
}
