<?php
/**
 * Maps legacy AJAX action names to service callables.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central action → service dispatcher.
 */
class WebinoCRM_Service_Action_Map {

	/**
	 * @var array<string,callable>
	 */
	private static $map = array();

	/**
	 * @param string   $action   wp_ajax action suffix.
	 * @param callable $callable function( array $params ): array.
	 * @return void
	 */
	public static function register( $action, $callable ) {
		self::$map[ sanitize_key( (string) $action ) ] = $callable;
	}

	/**
	 * @param string              $action Action name.
	 * @param array<string,mixed> $params Parameters.
	 * @return array<string,mixed>|null
	 */
	public static function dispatch( $action, array $params = array() ) {
		$action = sanitize_key( (string) $action );
		if ( isset( self::$map[ $action ] ) ) {
			return call_user_func( self::$map[ $action ], $params );
		}
		return WebinoCRM_REST_Legacy_Invoker::invoke_raw( $action, $params );
	}

	/**
	 * @return bool
	 */
	public static function has( $action ) {
		return isset( self::$map[ sanitize_key( (string) $action ) ] );
	}
}
