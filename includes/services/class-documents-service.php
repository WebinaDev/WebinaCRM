<?php
/**
 * Document management service layer (REST / SPA).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Documents_Service {
	use WebinoCRM_Domain_Service_Trait;

	/**
	 * @return array<string,mixed>|null
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
	 * @return void
	 */
	private static function ensure_module() {
		if ( ! class_exists( 'WebinoCRM_Document_Management' ) ) {
			return;
		}
		$dm = new WebinoCRM_Document_Management();
		$dm->create_upload_directory();
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

	public static function list( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Document_Management' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول اسناد در دسترس نیست.', 'webinocrm' ) );
		}
		self::ensure_module();
		$folder_id = null;
		if ( array_key_exists( 'folder_id', $params ) ) {
			$folder_id = self::int_param( $params, 'folder_id', 0 );
		}
		$entity_id = self::int_param( $params, 'entity_id', 0 );
		$args      = array(
			'entity_type' => isset( $params['entity_type'] ) ? sanitize_key( (string) $params['entity_type'] ) : null,
			'entity_id'   => $entity_id > 0 ? $entity_id : null,
			'folder_id'   => $folder_id,
			'search'      => isset( $params['search'] ) ? sanitize_text_field( (string) $params['search'] ) : null,
			'limit'       => self::int_param( $params, 'limit', 50 ),
			'offset'      => self::int_param( $params, 'offset', 0 ),
		);
		$documents = WebinoCRM_Document_Management::get_documents( $args );
		return WebinoCRM_Service_Base::success( array( 'documents' => $documents ) );
	}

	public static function upload( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Document_Management' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول اسناد در دسترس نیست.', 'webinocrm' ) );
		}
		self::ensure_module();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_FILES['file'] ) ) {
			return WebinoCRM_Service_Base::error( __( 'فایلی انتخاب نشده است.', 'webinocrm' ) );
		}
		$result = WebinoCRM_Document_Management::upload_document(
			array(
				'file'        => $_FILES['file'],
				'entity_type' => isset( $params['entity_type'] ) ? sanitize_key( (string) $params['entity_type'] ) : '',
				'entity_id'   => self::int_param( $params, 'entity_id', 0 ),
				'folder_id'   => self::int_param( $params, 'folder_id', 0 ),
				'title'       => isset( $params['title'] ) ? sanitize_text_field( (string) $params['title'] ) : '',
				'description' => isset( $params['description'] ) ? sanitize_textarea_field( (string) $params['description'] ) : '',
				'is_private'  => ! empty( $params['is_private'] ) ? 1 : 0,
			)
		);
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return WebinoCRM_Service_Base::success(
			array(
				'message'     => __( 'فایل با موفقیت آپلود شد.', 'webinocrm' ),
				'document_id' => (int) $result,
			)
		);
	}

	/**
	 * Streams file and exits.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|null
	 */
	public static function download( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Document_Management' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول اسناد در دسترس نیست.', 'webinocrm' ) );
		}
		$id = self::int_param( $params, array( 'id' ), 0 );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه سند نامعتبر است.', 'webinocrm' ) );
		}
		$result = WebinoCRM_Document_Management::download_document( $id );
		if ( is_wp_error( $result ) ) {
			return WebinoCRM_Service_Base::error( $result->get_error_message() );
		}
		return null;
	}

	public static function delete( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Document_Management' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول اسناد در دسترس نیست.', 'webinocrm' ) );
		}
		$id        = self::int_param( $params, array( 'id' ), 0 );
		$permanent = ! empty( $params['permanent'] );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه سند نامعتبر است.', 'webinocrm' ) );
		}
		WebinoCRM_Document_Management::delete_document( $id, $permanent );
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'سند حذف شد.', 'webinocrm' ) ) );
	}

	public static function create_folder( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Document_Management' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول اسناد در دسترس نیست.', 'webinocrm' ) );
		}
		self::ensure_module();
		$name      = isset( $params['name'] ) ? sanitize_text_field( (string) $params['name'] ) : '';
		$parent_id = self::int_param( $params, 'parent_id', 0 );
		if ( '' === $name ) {
			return WebinoCRM_Service_Base::error( __( 'نام پوشه الزامی است.', 'webinocrm' ) );
		}
		$folder_id = WebinoCRM_Document_Management::create_folder( $name, $parent_id );
		return WebinoCRM_Service_Base::success(
			array(
				'message'   => __( 'پوشه ایجاد شد.', 'webinocrm' ),
				'folder_id' => (int) $folder_id,
			)
		);
	}

	public static function update( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Document_Management' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول اسناد در دسترس نیست.', 'webinocrm' ) );
		}
		$id = self::int_param( $params, array( 'id' ), 0 );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه سند نامعتبر است.', 'webinocrm' ) );
		}
		if ( isset( $params['title'] ) && '' !== (string) $params['title'] ) {
			WebinoCRM_Document_Management::rename_document( $id, (string) $params['title'] );
		}
		if ( array_key_exists( 'folder_id', $params ) ) {
			WebinoCRM_Document_Management::move_document( $id, self::int_param( $params, 'folder_id', 0 ) );
		}
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'سند به‌روزرسانی شد.', 'webinocrm' ) ) );
	}

	public static function share( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Document_Management' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول اسناد در دسترس نیست.', 'webinocrm' ) );
		}
		$id = self::int_param( $params, array( 'id' ), 0 );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه سند نامعتبر است.', 'webinocrm' ) );
		}
		$user_ids = array();
		if ( ! empty( $params['user_ids'] ) ) {
			$user_ids = is_array( $params['user_ids'] ) ? array_map( 'intval', $params['user_ids'] ) : array_map( 'intval', explode( ',', (string) $params['user_ids'] ) );
		}
		$permissions = isset( $params['permissions'] ) ? sanitize_key( (string) $params['permissions'] ) : 'view';
		WebinoCRM_Document_Management::share_document( $id, $user_ids, $permissions );
		return WebinoCRM_Service_Base::success( array( 'message' => __( 'سند به اشتراک گذاشته شد.', 'webinocrm' ) ) );
	}

	public static function versions( array $params ) {
		$err = self::guard();
		if ( $err ) {
			return $err;
		}
		if ( ! class_exists( 'WebinoCRM_Document_Management' ) ) {
			return WebinoCRM_Service_Base::error( __( 'ماژول اسناد در دسترس نیست.', 'webinocrm' ) );
		}
		$id = self::int_param( $params, array( 'id' ), 0 );
		if ( $id <= 0 ) {
			return WebinoCRM_Service_Base::error( __( 'شناسه سند نامعتبر است.', 'webinocrm' ) );
		}
		$versions = WebinoCRM_Document_Management::get_document_versions( $id );
		return WebinoCRM_Service_Base::success( array( 'versions' => $versions ) );
	}

	public static function register_actions() {
		// REST-only.
	}
}
