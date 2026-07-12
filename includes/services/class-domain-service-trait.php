<?php
/**
 * Trait for domain services that delegate unextracted actions to legacy handlers.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers wp_ajax action → service method pairs.
 */
trait WebinoCRM_Domain_Service_Trait {

	/**
	 * @param array<string,callable> $map Action => callable.
	 * @return void
	 */
	protected static function register_map( array $map ) {
		foreach ( $map as $action => $callable ) {
			WebinoCRM_Service_Action_Map::register( $action, $callable );
		}
	}

	/**
	 * @param string              $action Action name.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	protected static function legacy( $action, array $params ) {
		return WebinoCRM_Service_Base::delegate_legacy( $action, $params );
	}
}
