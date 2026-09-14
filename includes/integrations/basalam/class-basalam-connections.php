<?php
/**
 * Persistent registry of merchant sites connected to Basalam via CRM OAuth.
 *
 * @package WebinaCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebinoCRM_Basalam_Connections {

	const OPTION = 'webinocrm_basalam_connections';

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all() {
		$rows = get_option( self::OPTION, array() );
		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * @param string     $site_url  Merchant site URL.
	 * @param int|string $vendor_id Basalam vendor id.
	 * @param string     $status    connected|disconnected.
	 * @return void
	 */
	public static function upsert( $site_url, $vendor_id, $status = 'connected' ) {
		$site_url  = untrailingslashit( esc_url_raw( (string) $site_url ) );
		$vendor_id = absint( $vendor_id );
		$status    = in_array( $status, array( 'connected', 'disconnected' ), true ) ? $status : 'connected';

		if ( '' === $site_url || ! wp_http_validate_url( $site_url ) ) {
			return;
		}
		if ( 'connected' === $status && $vendor_id < 1 ) {
			return;
		}

		$key  = self::key_for( $site_url );
		$rows = self::all_keyed();
		$now  = gmdate( 'c' );
		$prev = isset( $rows[ $key ] ) && is_array( $rows[ $key ] ) ? $rows[ $key ] : array();

		$rows[ $key ] = array(
			'site_url'      => $site_url,
			'vendor_id'     => $vendor_id > 0 ? $vendor_id : (int) ( $prev['vendor_id'] ?? 0 ),
			'status'        => $status,
			'connected_at'  => isset( $prev['connected_at'] ) ? (string) $prev['connected_at'] : ( 'connected' === $status ? $now : '' ),
			'last_seen_at'  => $now,
			'disconnected_at' => 'disconnected' === $status ? $now : (string) ( $prev['disconnected_at'] ?? '' ),
		);

		if ( 'connected' === $status && empty( $rows[ $key ]['connected_at'] ) ) {
			$rows[ $key ]['connected_at'] = $now;
		}

		update_option( self::OPTION, array_values( $rows ), false );
	}

	/**
	 * @param string $site_url Site URL.
	 * @return void
	 */
	public static function mark_disconnected( $site_url ) {
		$site_url = untrailingslashit( esc_url_raw( (string) $site_url ) );
		if ( '' === $site_url ) {
			return;
		}
		$key  = self::key_for( $site_url );
		$rows = self::all_keyed();
		if ( ! isset( $rows[ $key ] ) || ! is_array( $rows[ $key ] ) ) {
			self::upsert( $site_url, 0, 'disconnected' );
			return;
		}
		$vendor = absint( $rows[ $key ]['vendor_id'] ?? 0 );
		self::upsert( $site_url, $vendor, 'disconnected' );
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function all_keyed() {
		$out = array();
		foreach ( self::all() as $row ) {
			if ( ! is_array( $row ) || empty( $row['site_url'] ) ) {
				continue;
			}
			$out[ self::key_for( (string) $row['site_url'] ) ] = $row;
		}
		return $out;
	}

	/**
	 * @param string $site_url Site URL.
	 * @return string
	 */
	private static function key_for( $site_url ) {
		$host = strtolower( (string) wp_parse_url( $site_url, PHP_URL_HOST ) );
		$path = (string) wp_parse_url( $site_url, PHP_URL_PATH );
		$path = untrailingslashit( $path );
		return md5( $host . '|' . $path );
	}
}
