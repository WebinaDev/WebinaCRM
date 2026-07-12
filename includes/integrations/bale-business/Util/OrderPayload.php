<?php

namespace WebinaBaleBusiness\Util;

class OrderPayload {

	public static function build( int $order_id ): string {
		$mac = hash_hmac( 'sha256', (string) $order_id, wp_salt(), false );
		return $order_id . ':' . substr( $mac, 0, 16 );
	}

	public static function verify( string $payload ): int {
		$parts = explode( ':', $payload );
		if ( count( $parts ) !== 2 ) {
			return 0;
		}
		$order_id = (int) $parts[0];
		$expected = self::build( $order_id );
		return hash_equals( $expected, $payload ) ? $order_id : 0;
	}
}
