<?php
/**
 * PSR-4 autoload for WebinaBaleBusiness namespace (embedded in WebinoCRM).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'WebinaBaleBusiness\\';
		$len    = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}
		$relative = substr( $class, $len );
		$file     = WEBINOCRM_BALE_DIR . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
