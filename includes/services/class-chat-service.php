<?php
/**
 * Team chat service layer (REST / SPA).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Chat_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @return array<string,mixed>|null Error array or null if allowed.
	 */
	private static function guard() {
		if ( ! WebinoCRM_Service_Base::verify_crm_request() ) {
			return WebinoCRM_Service_Base::error( __( 'درخواست نامعتبر.', 'webinocrm' ) );
		}
		if ( ! is_user_logged_in() ) {
			return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
		}
		if ( current_user_can( 'manage_options' ) ) {
			return null;
		}
		if ( function_exists( 'webinocrm_current_user_has_role' ) && webinocrm_current_user_has_role( array( 'system_manager', 'team_member' ) ) ) {
			return null;
		}
		return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @param string|array<int,string> $keys Keys.
	 * @param int                    $default Default.
	 * @return int
	 */
	private static function int_param( array $params, $keys, $default = 0 ) {
		foreach ( (array) $keys as $key ) {
			if ( isset( $params[ $key ] ) ) {
				return (int) $params[ $key ];
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ $key ] ) ) {
				return (int) $_POST[ $key ];
			}
		}
		return $default;
	}

	/**
	 * @param array<string,mixed> $params Params.
	 * @param string              $key Key.
	 * @param string              $default Default.
	 * @return string
	 */
	private static function str_param( array $params, $key, $default = '' ) {
		if ( isset( $params[ $key ] ) ) {
			return (string) $params[ $key ];
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ $key ] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );
		}
		return $default;
	}

	public static function list_channels( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Team_Chat' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول چت در دسترس نیست.', 'webinocrm' ) );
		}
		$user_id  = self::int_param( $params, 'user_id', get_current_user_id() );
		$channels = WebinoCRM_Team_Chat::get_channels( $user_id );
		return WebinoCRM_Service_Base::success( array( 'channels' => $channels ) );
	}

	public static function create_channel( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Team_Chat' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول چت در دسترس نیست.', 'webinocrm' ) );
		}
		$members = array();
		if ( ! empty( $params['members'] ) ) {
			$members = is_array( $params['members'] ) ? array_map( 'intval', $params['members'] ) : array_map( 'intval', explode( ',', (string) $params['members'] ) );
		}
		$result = WebinoCRM_Team_Chat::create_channel(
			array(
				'name'        => self::str_param( $params, 'name' ),
				'description' => isset( $params['description'] ) ? sanitize_textarea_field( (string) $params['description'] ) : '',
				'type'        => sanitize_key( self::str_param( $params, 'type', 'public' ) ),
				'members'     => $members,
			)
		);
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'message'    => __( 'کانال ایجاد شد.', 'webinocrm' ),
				'channel_id' => (int) $result,
			)
		);
	}

	public static function list_direct( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Team_Chat' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول چت در دسترس نیست.', 'webinocrm' ) );
		}
		$user_id = self::int_param( $params, 'user_id', get_current_user_id() );
		$chats   = WebinoCRM_Team_Chat::get_direct_chats( $user_id );
		return WebinoCRM_Service_Base::success( array( 'chats' => $chats ) );
	}

	public static function list_messages( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Team_Chat' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول چت در دسترس نیست.', 'webinocrm' ) );
		}
		$since = self::str_param( $params, 'since' );
		$messages = WebinoCRM_Team_Chat::get_messages(
			array(
				'channel_id'   => self::int_param( $params, array( 'channel_id' ) ),
				'recipient_id' => self::int_param( $params, array( 'recipient_id' ) ),
				'since'        => $since ? $since : null,
				'limit'        => self::int_param( $params, 'limit', 50 ),
				'offset'       => self::int_param( $params, 'offset', 0 ),
			)
		);
		return WebinoCRM_Service_Base::success( array( 'messages' => $messages ) );
	}

	public static function send_message( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Team_Chat' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول چت در دسترس نیست.', 'webinocrm' ) );
		}
		$message = isset( $params['message'] ) ? wp_kses_post( (string) $params['message'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $message && isset( $_POST['message'] ) ) {
			$message = wp_kses_post( wp_unslash( (string) $_POST['message'] ) );
		}
		$result = WebinoCRM_Team_Chat::send_message(
			array(
				'channel_id'   => self::int_param( $params, array( 'channel_id' ) ),
				'recipient_id' => self::int_param( $params, array( 'recipient_id' ) ),
				'message'      => $message,
				'message_type' => sanitize_key( self::str_param( $params, 'message_type', 'text' ) ),
				'parent_id'    => self::int_param( $params, array( 'parent_id' ) ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		$message_data = WebinoCRM_Team_Chat::get_message( (int) $result );
		return WebinoCRM_Service_Base::success(
			array(
				'message'    => __( 'پیام ارسال شد.', 'webinocrm' ),
				'message_id' => (int) $result,
				'item'       => $message_data,
			)
		);
	}

	public static function mark_read( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Team_Chat' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول چت در دسترس نیست.', 'webinocrm' ) );
		}
		$channel_id = self::int_param( $params, array( 'channel_id' ) );
		$message_id = self::int_param( $params, array( 'message_id', 'id' ) );
		if ( $channel_id > 0 ) {
			WebinoCRM_Team_Chat::mark_channel_as_read( $channel_id );
		} elseif ( $message_id > 0 ) {
			WebinoCRM_Team_Chat::mark_as_read( $message_id );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'خوانده شد.', 'webinocrm' ) ) );
	}

	public static function unread_count( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Team_Chat' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول چت در دسترس نیست.', 'webinocrm' ) );
		}
		$user_id    = self::int_param( $params, 'user_id', get_current_user_id() );
		$channel_id = self::int_param( $params, array( 'channel_id' ) );
		$sender_id  = self::int_param( $params, array( 'sender_id', 'recipient_id' ) );
		$count      = WebinoCRM_Team_Chat::get_unread_count( $user_id, $channel_id, $sender_id );
		return WebinoCRM_Service_Base::success( array( 'count' => (int) $count ) );
	}

	public static function search( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Team_Chat' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول چت در دسترس نیست.', 'webinocrm' ) );
		}
		$query      = self::str_param( $params, 'query' );
		$channel_id = self::int_param( $params, array( 'channel_id' ) );
		$messages   = WebinoCRM_Team_Chat::search_messages( $query, $channel_id );
		return WebinoCRM_Service_Base::success( array( 'messages' => $messages ) );
	}

	public static function delete_message( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Team_Chat' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول چت در دسترس نیست.', 'webinocrm' ) );
		}
		$message_id = self::int_param( $params, array( 'id', 'message_id' ) );
		if ( $message_id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه پیام نامعتبر است.', 'webinocrm' ) );
		}
		$result = WebinoCRM_Team_Chat::delete_message( $message_id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'پیام حذف شد.', 'webinocrm' ) ) );
	}

	public static function register_actions() {
		// REST-only; no legacy ajax map entries.
	}
}
