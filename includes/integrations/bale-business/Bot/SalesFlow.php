<?php

namespace WebinaBaleBusiness\Bot;

use WebinaBaleBusiness\Admin\BusinessAdminSync;
use WebinaBaleBusiness\Analytics\EventLogger;
use WebinaBaleBusiness\Bale\Client;
use WebinaBaleBusiness\Content\Repository as ContentRepository;
use WebinaBaleBusiness\Core\Plugin;
use WebinaBaleBusiness\Database\BusinessRepository;
use WebinaBaleBusiness\Database\ProfileRepository;
use WebinaBaleBusiness\Database\SessionRepository;
use WebinaBaleBusiness\Woo\OrderService;
use WebinaBaleBusiness\Woo\PaymentService;
use WebinaBaleBusiness\Support\Logger;
use WebinaBaleBusiness\Automation\Engine as AutomationEngine;

class SalesFlow {

	private Client $client;
	private SessionRepository $sessions;
	private BusinessRepository $businesses;
	private ProfileRepository $profiles;
	private OrderService $orders;
	private PaymentService $payments;

	public function __construct() {
		$this->client   = new Client();
		$this->sessions = new SessionRepository();
		$this->businesses = new BusinessRepository();
		$this->profiles = new ProfileRepository();
		$this->orders   = new OrderService();
		$this->payments = new PaymentService( $this->client );
	}

	public function start( string $chat_id ): void {
		$s = Plugin::get_settings();
		EventLogger::log( $chat_id, 'conversation_started' );
		AutomationEngine::ingest_event( $chat_id, 'start' );
		$this->maybe_register_wp_user_from_bale( $chat_id );
		if ( $this->membership_required() && ! $this->is_member( $chat_id, $chat_id ) ) {
			$this->show_membership_gate( $chat_id );
			return;
		}
		$this->send_reply_menu(
			$chat_id,
			(string) $s['welcome_text'] . "\n\n" . (string) ( $s['start_hint_text'] ?? '' ),
			$this->main_reply_keyboard()
		);
	}

	public function handle_text_action( string $chat_id, string $text ): bool {
		$map = array(
			'🏢 کسب و کارهای من'     => 'wbb:businesses',
			'🚀 چرا بله برای فروش؟'  => 'wbb:why',
			'🤖 ربات کسب و کار چیه؟'  => 'wbb:features',
			'💳 پلن ها و قیمت'       => 'wbb:plans',
			'❓ سوالات پرتکرار'      => 'wbb:faq',
			'🆘 تماس با پشتیبانی'    => 'wbb:support',
			'👤 اطلاعات من'          => 'wbb:profile',
			'❌ انصراف'             => 'wbb:request:cancel',
		);
		if ( ! isset( $map[ $text ] ) ) {
			return false;
		}
		if ( $map[ $text ] === 'wbb:plans' ) {
			AutomationEngine::ingest_event( $chat_id, 'plan_click' );
		}
		$this->handle_callback( $chat_id, $map[ $text ] );
		return true;
	}

	public function handle_callback( string $chat_id, string $data, string $callback_query_id = '' ): void {
		if ( $callback_query_id !== '' ) {
			$this->client->answer_callback_query( array( 'callback_query_id' => $callback_query_id ) );
		}

		if ( $this->membership_required() && ! in_array( $data, array( 'wbb:membership:join', 'wbb:membership:verify' ), true ) ) {
			if ( ! $this->is_member( $chat_id, $chat_id ) ) {
				$this->show_membership_gate( $chat_id );
				return;
			}
		}

		if ( $data === 'wbb:membership:join' ) {
			$this->show_membership_gate( $chat_id );
			return;
		}
		if ( $data === 'wbb:membership:verify' ) {
			if ( $this->is_member( $chat_id, $chat_id ) ) {
				$this->start( $chat_id );
				return;
			}
			$s = Plugin::get_settings();
			$this->send_inline_menu(
				$chat_id,
				(string) ( $s['membership_error_text'] ?? 'عضویت شما تایید نشد.' ),
				$this->membership_buttons()
			);
			return;
		}

		if ( $data === 'wbb:features' ) {
			$this->show_features( $chat_id );
			return;
		}
		if ( $data === 'wbb:businesses' ) {
			$this->show_businesses( $chat_id );
			return;
		}
		if ( $data === 'wbb:profile' ) {
			$this->show_profile( $chat_id );
			return;
		}
		if ( strpos( $data, 'wbb:profile:edit:' ) === 0 ) {
			$this->start_profile_edit( $chat_id, substr( $data, strlen( 'wbb:profile:edit:' ) ) );
			return;
		}
		if ( $data === 'wbb:profile:mobile:request' ) {
			$this->prompt_mobile_contact( $chat_id );
			return;
		}
		if ( $data === 'wbb:business:add' ) {
			$this->start_business_wizard( $chat_id, 0 );
			return;
		}
		if ( strpos( $data, 'wbb:business:view:' ) === 0 ) {
			$this->open_business( $chat_id, (int) substr( $data, strlen( 'wbb:business:view:' ) ) );
			return;
		}
		if ( strpos( $data, 'wbb:business:edit:' ) === 0 ) {
			$this->start_business_wizard( $chat_id, (int) substr( $data, strlen( 'wbb:business:edit:' ) ) );
			return;
		}
		if ( strpos( $data, 'wbb:business:delete:' ) === 0 ) {
			$this->delete_business( $chat_id, (int) substr( $data, strlen( 'wbb:business:delete:' ) ) );
			return;
		}
		if ( strpos( $data, 'wbb:business:complete:' ) === 0 ) {
			$this->complete_business( $chat_id, (int) substr( $data, strlen( 'wbb:business:complete:' ) ) );
			return;
		}
		if ( strpos( $data, 'wbb:business:website:' ) === 0 ) {
			$this->set_business_website_flag( $chat_id, substr( $data, strlen( 'wbb:business:website:' ) ) );
			return;
		}
		if ( strpos( $data, 'wbb:business:type:' ) === 0 ) {
			$this->set_business_type( $chat_id, substr( $data, strlen( 'wbb:business:type:' ) ) );
			return;
		}
		if ( strpos( $data, 'wbb:business:activity:' ) === 0 ) {
			$this->set_business_activity( $chat_id, substr( $data, strlen( 'wbb:business:activity:' ) ) );
			return;
		}
		if ( strpos( $data, 'wbb:feature:' ) === 0 ) {
			$this->show_feature_detail( $chat_id, substr( $data, strlen( 'wbb:feature:' ) ) );
			return;
		}
		if ( $data === 'wbb:faq' ) {
			$this->show_faq( $chat_id );
			return;
		}
		if ( $data === 'wbb:why' ) {
			$this->show_sales_blocks( $chat_id );
			return;
		}
		if ( $data === 'wbb:plans' ) {
			$this->show_plans( $chat_id );
			return;
		}
		if ( $data === 'wbb:sales' ) {
			$this->show_sales_contact( $chat_id );
			return;
		}
		if ( $data === 'wbb:support' ) {
			$this->show_support( $chat_id );
			return;
		}
		if ( strpos( $data, 'wbb:support:item:' ) === 0 ) {
			$this->handle_support_item( $chat_id, substr( $data, strlen( 'wbb:support:item:' ) ) );
			return;
		}
		if ( $data === 'wbb:order' ) {
			$this->show_plan_selection( $chat_id );
			return;
		}
		if ( $data === 'wbb:request:start' ) {
			$this->start_request_flow( $chat_id );
			return;
		}
		if ( $data === 'wbb:request:cancel' ) {
			$this->sessions->set( $chat_id, 'request_stage', '' );
			$this->start( $chat_id );
			return;
		}
		if ( strpos( $data, 'wbb:order_plan:' ) === 0 ) {
			$plan_key = substr( $data, strlen( 'wbb:order_plan:' ) );
			$this->register_order( $chat_id, \sanitize_key( $plan_key ) );
			return;
		}
		if ( $data === 'wbb:pay_wallet' ) {
			$this->pay_with_wallet( $chat_id );
			return;
		}
	}

	private function show_sales_blocks( string $chat_id ): void {
		$settings = Plugin::get_settings();
		$blocks   = ContentRepository::get_sales_blocks();
		$lines    = array( 'چرا الان ربات کسب و کار وبینا می تونه برگ برنده‌ت باشه؟', '' );
		if ( empty( $blocks ) ) {
			$lines[] = (string) ( $settings['sales_trust_text'] ?? '' );
		} else {
			foreach ( $blocks as $block ) {
				$lines[] = '• ' . (string) ( $block['title'] ?? '' );
				$lines[] = (string) ( $block['description'] ?? '' );
				if ( ! empty( $block['cta'] ) ) {
					$lines[] = 'اقدام پیشنهادی: ' . (string) $block['cta'];
				}
				$lines[] = '';
			}
		}
		$this->send_context_message( $chat_id, trim( implode( "\n", $lines ) ), '🆘 پشتیبانی', 'wbb:support' );
	}

	private function show_features( string $chat_id ): void {
		$s     = Plugin::get_settings();
		$items = ContentRepository::get_feature_docs();
		$lines = array( (string) $s['features_intro_text'], '' );
		$rows  = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$key   = (string) ( $item['key'] ?? '' );
			$title = (string) ( $item['title'] ?? '' );
			$short = (string) ( $item['short'] ?? '' );
			$lines[] = '• ' . $title . ': ' . $short;
			if ( $key !== '' ) {
				$rows[] = array(
					array(
					'text'          => '📘 جزئیات: ' . $title,
						'callback_data' => 'wbb:feature:' . $key,
					),
				);
			}
		}
		$rows = array_merge( $rows, $this->build_global_inline_rows( '💳 پلن ها و قیمت', 'wbb:plans' ) );
		EventLogger::log( $chat_id, 'features_opened' );
		$this->send_inline_menu( $chat_id, implode( "\n", $lines ), $rows );
	}

	private function show_feature_detail( string $chat_id, string $key ): void {
		$doc = ContentRepository::get_feature_doc( $key );
		if ( ! is_array( $doc ) ) {
			return;
		}
		$lines = array(
			'📌 ' . (string) ( $doc['title'] ?? '' ),
			'',
			(string) ( $doc['short'] ?? '' ),
			'',
			(string) ( $doc['details'] ?? '' ),
		);
		$steps = isset( $doc['steps'] ) && is_array( $doc['steps'] ) ? $doc['steps'] : array();
		if ( ! empty( $steps ) ) {
			$lines[] = '';
			$lines[] = 'چطور اجرا میشه؟';
			foreach ( $steps as $step ) {
				$lines[] = '- ' . (string) $step;
			}
		}
		$limitations = isset( $doc['limitations'] ) && is_array( $doc['limitations'] ) ? $doc['limitations'] : array();
		if ( ! empty( $limitations ) ) {
			$lines[] = '';
			$lines[] = 'نکته مهم:';
			foreach ( $limitations as $limitation ) {
				$lines[] = '- ' . (string) $limitation;
			}
		}
		$related = isset( $doc['related_actions'] ) && is_array( $doc['related_actions'] ) ? $doc['related_actions'] : array();
		if ( ! empty( $related ) ) {
			$lines[] = '';
			$lines[] = 'اقدام پیشنهادی: ' . implode( ' | ', array_map( 'strval', $related ) );
		}
		$tags = isset( $doc['tags'] ) && is_array( $doc['tags'] ) ? $doc['tags'] : array();
		if ( ! empty( $tags ) ) {
			$lines[] = 'برچسب ها: #' . implode( ' #', array_map( 'strval', $tags ) );
		}

		$text   = trim( implode( "\n", $lines ) );
		$chunks = str_split( $text, 2800 );
		foreach ( $chunks as $index => $chunk ) {
			if ( $index === count( $chunks ) - 1 ) {
				$this->send_context_message( $chat_id, $chunk, '✨ بازگشت به امکانات', 'wbb:features' );
				continue;
			}
			$this->client->send_message(
				array(
					'chat_id' => $chat_id,
					'text'    => $chunk,
				)
			);
		}
	}

	private function show_businesses( string $chat_id ): void {
		$items = $this->businesses->list_by_chat( $chat_id );
		$rows  = array();
		foreach ( $items as $item ) {
			$label = BusinessAdminSync::get_status_label( (string) ( $item['status'] ?? 'pending' ) );
			$rows[] = array(
				array(
					'text'          => '🏢 ' . (string) ( $item['bot_name'] ?: $item['owner_name'] ) . ' (' . $label . ')',
					'callback_data' => 'wbb:business:view:' . (int) $item['id'],
				),
			);
		}
		$rows[] = array(
			array(
				'text'          => '➕ افزودن کسب و کار جدید',
				'callback_data' => 'wbb:business:add',
			),
		);
		$rows[] = array( array( 'text' => '👤 اطلاعات من', 'callback_data' => 'wbb:profile' ) );
		$this->send_inline_menu( $chat_id, 'لیست کسب و کارهای تو 👇', $rows );
	}

	private function open_business( string $chat_id, int $business_id ): void {
		$item = $this->businesses->get( $business_id, $chat_id );
		if ( ! is_array( $item ) ) {
			return;
		}
		$review  = BusinessAdminSync::get_review_for_business( $business_id );
		$status  = (string) ( $item['status'] ?? 'pending' );
		$label   = BusinessAdminSync::get_status_label( $status );
		$text = '🏢 ' . (string) ( $item['bot_name'] ?: $item['owner_name'] ) . "\n";
		$text .= 'نوع کسب و کار: ' . (string) ( $item['business_type'] ?? '-' ) . "\n";
		$text .= 'حوزه فعالیت: ' . (string) ( $item['business_field'] ?? '-' ) . "\n";
		$text .= 'وضعیت: ' . $label;
		if ( $status === 'rejected' && $review['note'] !== '' ) {
			$text .= "\n📝 دلیل رد: " . $review['note'];
		}
		$this->send_inline_menu(
			$chat_id,
			$text,
			array(
				array( array( 'text' => '✏️ ویرایش کسب و کار', 'callback_data' => 'wbb:business:edit:' . $business_id ) ),
				array(
					array( 'text' => '🗑 حذف', 'callback_data' => 'wbb:business:delete:' . $business_id ),
					array( 'text' => '✅ تکمیل', 'callback_data' => 'wbb:business:complete:' . $business_id ),
				),
				array( array( 'text' => '⬅️ بازگشت', 'callback_data' => 'wbb:businesses' ) ),
			)
		);
	}

	private function delete_business( string $chat_id, int $business_id ): void {
		$this->businesses->delete( $business_id, $chat_id );
		$this->show_businesses( $chat_id );
	}

	private function complete_business( string $chat_id, int $business_id ): void {
		if ( ! $this->profiles->is_complete( $chat_id ) ) {
			$this->send_inline_menu(
				$chat_id,
				'پروفایل شما کامل نیست. برای ادامه، اول اطلاعات من را کامل کن 👇',
				array(
					array( array( 'text' => '👤 تکمیل اطلاعات من', 'callback_data' => 'wbb:profile' ) ),
					array( array( 'text' => '⬅️ بازگشت', 'callback_data' => 'wbb:business:view:' . $business_id ) ),
				)
			);
			return;
		}
		$this->businesses->set_status( $business_id, $chat_id, 'pending' );
		BusinessAdminSync::sync_business_submission( $business_id );
		$this->sessions->set( $chat_id, 'selected_business_id', $business_id );
		$this->show_plan_selection( $chat_id );
	}

	private function show_profile( string $chat_id ): void {
		$profile = $this->profiles->get_or_empty( $chat_id );
		$check = static function ( string $val ): string {
			return $val !== '' ? '✅' : '❌';
		};
		$text = "👤 اطلاعات من\n\n";
		$text .= $check( (string) ( $profile['first_name'] ?? '' ) ) . " نام\n";
		$text .= $check( (string) ( $profile['last_name'] ?? '' ) ) . " نام خانوادگی\n";
		$text .= $check( (string) ( $profile['national_card_file_id'] ?? '' ) ) . " تصویر کارت ملی\n";
		$text .= $check( (string) ( $profile['mobile'] ?? '' ) ) . " شماره تلفن همراه";
		$this->send_inline_menu(
			$chat_id,
			$text,
			array(
				array(
					array( 'text' => '✏️ نام', 'callback_data' => 'wbb:profile:edit:first_name' ),
					array( 'text' => '✏️ نام خانوادگی', 'callback_data' => 'wbb:profile:edit:last_name' ),
				),
				array(
					array( 'text' => '🪪 تصویر کارت ملی', 'callback_data' => 'wbb:profile:edit:national_card' ),
					array( 'text' => '📱 ثبت شماره همراه', 'callback_data' => 'wbb:profile:mobile:request' ),
				),
				array( array( 'text' => '⬅️ بازگشت', 'callback_data' => 'wbb:membership:verify' ) ),
			)
		);
	}

	private function start_profile_edit( string $chat_id, string $field ): void {
		if ( $field === 'first_name' ) {
			$this->sessions->set( $chat_id, 'profile_stage', 'first_name' );
			$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'نام را وارد کن:' ) );
			return;
		}
		if ( $field === 'last_name' ) {
			$this->sessions->set( $chat_id, 'profile_stage', 'last_name' );
			$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'نام خانوادگی را وارد کن:' ) );
			return;
		}
		if ( $field === 'national_card' ) {
			$this->sessions->set( $chat_id, 'profile_stage', 'national_card' );
			$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'تصویر کارت ملی را ارسال کن.' ) );
		}
	}

	private function prompt_mobile_contact( string $chat_id ): void {
		$this->sessions->set( $chat_id, 'profile_stage', 'mobile' );
		$this->client->send_message(
			array(
				'chat_id'      => $chat_id,
				'text'         => 'برای ثبت شماره همراه، روی دکمه ارسال شماره بزن 👇',
				'reply_markup' => array(
					'keyboard'        => array(
						array(
							array(
								'text'            => '📱 ارسال شماره همراه',
								'request_contact' => true,
							),
						),
					),
					'resize_keyboard' => true,
				),
			)
		);
	}

	private function start_business_wizard( string $chat_id, int $business_id ): void {
		$source = array();
		if ( $business_id > 0 ) {
			$source = (array) $this->businesses->get( $business_id, $chat_id );
		}
		$this->sessions->set( $chat_id, 'business_editing_id', $business_id );
		$this->sessions->set( $chat_id, 'business_data', $source );
		$this->sessions->set( $chat_id, 'business_stage', 'owner_name' );
		$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => '🧩 بیایم کسب و کار را مرحله ای تکمیل کنیم. اول نام صاحب کسب و کار:' ) );
	}

	private function set_business_website_flag( string $chat_id, string $flag ): void {
		$data = (array) $this->sessions->get( $chat_id, 'business_data', array() );
		$data['has_website'] = $flag === '1' ? 1 : 0;
		$this->sessions->set( $chat_id, 'business_data', $data );
		if ( $flag === '1' ) {
			$this->sessions->set( $chat_id, 'business_stage', 'website_url' );
			$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'آدرس سایت را بفرست (مثال: https://example.com)' ) );
			return;
		}
		$this->sessions->set( $chat_id, 'business_stage', 'website_name' );
		$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'اگر سایت نداری، اسم سایت پیشنهادی را بفرست (برای هماهنگی با ادمین):' ) );
	}

	private function prompt_business_type_selection( string $chat_id ): void {
		$types = ContentRepository::get_business_types();
		$rows  = array();
		foreach ( $types as $type ) {
			$key = (string) ( $type['key'] ?? '' );
			if ( $key === '' ) {
				continue;
			}
			$rows[] = array(
				array(
					'text'          => '🏷 ' . (string) ( $type['title'] ?? $key ),
					'callback_data' => 'wbb:business:type:' . $key,
				),
			);
		}
		$this->send_inline_menu( $chat_id, '🏷 نوع کسب و کار را انتخاب کن:', $rows );
	}

	private function set_business_type( string $chat_id, string $type_key ): void {
		$data = (array) $this->sessions->get( $chat_id, 'business_data', array() );
		$data['business_type'] = \sanitize_text_field( $type_key );
		$this->sessions->set( $chat_id, 'business_data', $data );
		$this->sessions->set( $chat_id, 'business_stage', 'business_field' );
		$this->prompt_business_activity_selection( $chat_id, $type_key );
	}

	private function prompt_business_activity_selection( string $chat_id, string $type_key ): void {
		$activities = ContentRepository::get_business_activities_by_type( $type_key );
		$rows       = array();
		foreach ( $activities as $activity ) {
			$key = (string) ( $activity['key'] ?? '' );
			if ( $key === '' ) {
				continue;
			}
			$rows[] = array(
				array(
					'text'          => '🧭 ' . (string) ( $activity['title'] ?? $key ),
					'callback_data' => 'wbb:business:activity:' . $key,
				),
			);
		}
		$this->send_inline_menu( $chat_id, '🧭 حوزه فعالیت را انتخاب کن:', $rows );
	}

	private function set_business_activity( string $chat_id, string $activity_key ): void {
		$data = (array) $this->sessions->get( $chat_id, 'business_data', array() );
		$data['business_field'] = \sanitize_text_field( $activity_key );
		$this->sessions->set( $chat_id, 'business_data', $data );
		$this->sessions->set( $chat_id, 'business_stage', 'has_website' );
		$this->send_inline_menu(
			$chat_id,
			'🌐 برای کسب و کارت سایت داری؟',
			array(
				array(
					array( 'text' => '✅ دارم', 'callback_data' => 'wbb:business:website:1' ),
					array( 'text' => '❌ ندارم', 'callback_data' => 'wbb:business:website:0' ),
				),
			)
		);
	}

	private function show_faq( string $chat_id ): void {
		$s     = Plugin::get_settings();
		$items = ContentRepository::get_faq_items();
		$lines = array( (string) $s['faq_intro_text'], '' );
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$lines[] = 'Q: ' . (string) ( $item['question'] ?? '' );
			$lines[] = 'A: ' . (string) ( $item['answer'] ?? '' );
			$lines[] = '';
		}
		EventLogger::log( $chat_id, 'faq_opened' );
		$this->send_context_message( $chat_id, trim( implode( "\n", $lines ) ), '🆘 پشتیبانی', 'wbb:support' );
	}

	private function show_plans( string $chat_id ): void {
		$items = ContentRepository::get_plan_items();
		$lines = array( 'پلن ها و سناریوهای فروش وبینا', 'ظرفیت اجرای روزانه محدوده؛ اگه آماده ای سریع انتخاب کن 👇', '' );
		if ( empty( $items ) ) {
			$lines[] = 'برای تعریف پلن ها از منوی وردپرس، بخش "پلن های فروش ربات" را تکمیل کنید.';
		} else {
			foreach ( $items as $item ) {
				$lines[] = '• ' . (string) ( $item['title'] ?? '' );
				$lines[] = (string) ( $item['description'] ?? '' );
				$lines[] = '';
			}
		}
		EventLogger::log( $chat_id, 'plans_opened' );
		$this->send_context_message( $chat_id, trim( implode( "\n", $lines ) ), '📝 شروع ثبت درخواست', 'wbb:request:start' );
	}

	private function show_sales_contact( string $chat_id ): void {
		$s = Plugin::get_settings();
		EventLogger::log( $chat_id, 'sales_contact_opened' );
		$this->send_context_message( $chat_id, (string) $s['contact_sales_text'], '🆘 پشتیبانی', 'wbb:support' );
	}

	private function register_order( string $chat_id, string $plan_key = '' ): void {
		$business_id = (int) $this->sessions->get( $chat_id, 'selected_business_id', 0 );
		$order_data = $this->orders->create_order_for_chat( $chat_id, $plan_key, $business_id );
		if ( ! $order_data ) {
			$this->client->send_message(
				array(
					'chat_id' => $chat_id,
					'text'    => 'ثبت سفارش انجام نشد. لطفا تنظیمات محصول فروش را بررسی کنید.',
				)
			);
			return;
		}
		$this->sessions->set( $chat_id, 'last_order_id', (int) $order_data['order_id'] );
		EventLogger::log( $chat_id, 'order_registered', $order_data );
		$s    = Plugin::get_settings();
		$text = (string) $s['order_success_text'] . "\n\n";
		$text .= str_replace( '{payment_url}', (string) $order_data['payment_url'], (string) $s['manual_payment_link_template'] );
		$this->send_inline_menu(
			$chat_id,
			$text,
			array(
				array( array( 'text' => '💼 پرداخت با کیف پول بله', 'callback_data' => 'wbb:pay_wallet' ) ),
				array( array( 'text' => '🌐 پرداخت از سایت', 'url' => (string) $order_data['payment_url'] ) ),
				array( array( 'text' => '🛠 درخواست ساخت ربات', 'callback_data' => 'wbb:request:start' ) ),
			)
		);
	}

	private function show_plan_selection( string $chat_id ): void {
		$plans = ContentRepository::get_plan_items();
		if ( empty( $plans ) ) {
			$this->register_order( $chat_id, '' );
			return;
		}
		$rows = array();
		foreach ( $plans as $index => $plan ) {
			$key    = \sanitize_key( (string) ( $plan['title'] ?? ( 'plan_' . $index ) ) );
			$rows[] = array(
				array(
					'text'          => (string) ( $plan['title'] ?? 'پلن' ),
					'callback_data' => 'wbb:order_plan:' . $key,
				),
			);
		}
		$rows[] = array( array( 'text' => '❌ انصراف', 'callback_data' => 'wbb:request:cancel' ) );
		$rows[] = array( array( 'text' => '🛠 درخواست ساخت ربات', 'callback_data' => 'wbb:request:start' ) );
		$this->send_inline_menu( $chat_id, '💳 لطفا پلن موردنظر را انتخاب کنید:', $rows );
	}

	private function pay_with_wallet( string $chat_id ): void {
		$order_id = (int) $this->sessions->get( $chat_id, 'last_order_id', 0 );
		if ( $order_id <= 0 ) {
			return;
		}
		$ok = $this->payments->send_invoice( $chat_id, $order_id );
		if ( $ok ) {
			EventLogger::log( $chat_id, 'wallet_invoice_sent', array( 'order_id' => $order_id ) );
		}
	}

	public function on_payment_success( string $chat_id, int $order_id ): void {
		$settings = Plugin::get_settings();
		$this->client->send_message(
			array(
				'chat_id' => $chat_id,
				'text'    => '✅ ' . (string) $settings['payment_success_text'] . "\n" . 'شماره سفارش: #' . $order_id,
			)
		);
		$this->start( $chat_id );
	}

	public function handle_user_message( string $chat_id, array $message ): void {
		$profile_stage = (string) $this->sessions->get( $chat_id, 'profile_stage', '' );
		if ( $profile_stage !== '' ) {
			$this->handle_profile_message( $chat_id, $message, $profile_stage );
			return;
		}
		$business_stage = (string) $this->sessions->get( $chat_id, 'business_stage', '' );
		if ( $business_stage !== '' ) {
			$this->handle_business_message( $chat_id, $message, $business_stage );
			return;
		}

		$stage = (string) $this->sessions->get( $chat_id, 'request_stage', '' );
		if ( $stage === '' ) {
			return;
		}

		$text = trim( (string) ( $message['text'] ?? '' ) );
		$photo = isset( $message['photo'] ) && is_array( $message['photo'] );
		$data = (array) $this->sessions->get( $chat_id, 'request_data', array() );

		if ( $stage === 'owner_name' ) {
			if ( $text === '' ) {
				return;
			}
			$data['owner_name'] = $text;
			$this->sessions->set( $chat_id, 'request_data', $data );
			$this->sessions->set( $chat_id, 'request_stage', 'business_name' );
			$this->prompt_stage( $chat_id, 'business_name' );
			return;
		}
		if ( $stage === 'business_name' ) {
			if ( $text === '' ) {
				return;
			}
			$data['business_name'] = $text;
			$this->sessions->set( $chat_id, 'request_data', $data );
			$this->sessions->set( $chat_id, 'request_stage', 'intro_text' );
			$this->prompt_stage( $chat_id, 'intro_text' );
			return;
		}
		if ( $stage === 'intro_text' ) {
			if ( $text === '' ) {
				return;
			}
			$data['intro_text'] = $text;
			$this->sessions->set( $chat_id, 'request_data', $data );
			$this->sessions->set( $chat_id, 'request_stage', 'profile_image' );
			$this->prompt_stage( $chat_id, 'profile_image' );
			return;
		}
		if ( $stage === 'profile_image' ) {
			if ( ! $photo ) {
				return;
			}
			$data['profile_image'] = 'uploaded';
			$this->sessions->set( $chat_id, 'request_data', $data );
			$this->sessions->set( $chat_id, 'request_stage', 'license_image' );
			$this->prompt_stage( $chat_id, 'license_image' );
			return;
		}
		if ( $stage === 'license_image' ) {
			if ( ! $photo ) {
				return;
			}
			$data['license_image'] = 'uploaded';
			$this->sessions->set( $chat_id, 'request_data', $data );
			$this->sessions->set( $chat_id, 'request_stage', '' );
			EventLogger::log( $chat_id, 'lead_form_submitted', $data );
			AutomationEngine::ingest_event( $chat_id, 'reply', array( 'source' => 'lead_form_submitted' ) );
			Logger::log( 'info', 'lead_form_submitted', array( 'chat_id' => $chat_id, 'data' => $data ) );
			$this->send_context_message( $chat_id, "✅ اطلاعاتت ثبت شد.\nتیم وبینا خیلی زود برای هماهنگی ادامه مسیر باهات در ارتباطه.", '💳 مشاهده پلن ها', 'wbb:plans' );
		}
	}

	/**
	 * @param array<string,mixed> $message
	 */
	private function handle_profile_message( string $chat_id, array $message, string $stage ): void {
		$text  = trim( (string) ( $message['text'] ?? '' ) );
		$photo = isset( $message['photo'] ) && is_array( $message['photo'] );
		if ( $stage === 'first_name' && $text !== '' ) {
			$this->profiles->upsert( $chat_id, array( 'first_name' => $text ) );
		} elseif ( $stage === 'last_name' && $text !== '' ) {
			$this->profiles->upsert( $chat_id, array( 'last_name' => $text ) );
		} elseif ( $stage === 'national_card' && $photo ) {
			$last = end( $message['photo'] );
			$file_id = is_array( $last ) ? (string) ( $last['file_id'] ?? '' ) : '';
			$this->profiles->upsert( $chat_id, array( 'national_card_file_id' => $file_id ) );
		} elseif ( $stage === 'mobile' && isset( $message['contact']['phone_number'] ) ) {
			$this->profiles->upsert( $chat_id, array( 'mobile' => (string) $message['contact']['phone_number'] ) );
		} else {
			return;
		}
		$this->sessions->set( $chat_id, 'profile_stage', '' );
		if ( $this->profiles->is_complete( $chat_id ) ) {
			AutomationEngine::ingest_event( $chat_id, 'profile_complete' );
			AutomationEngine::maybe_auto_convert_customer( $chat_id );
		}
		$this->send_reply_menu( $chat_id, 'اطلاعاتت ثبت شد ✅', $this->main_reply_keyboard() );
		$this->show_profile( $chat_id );
	}

	/**
	 * @param array<string,mixed> $message
	 */
	private function handle_business_message( string $chat_id, array $message, string $stage ): void {
		$text  = trim( (string) ( $message['text'] ?? '' ) );
		$photo = isset( $message['photo'] ) && is_array( $message['photo'] );
		$data  = (array) $this->sessions->get( $chat_id, 'business_data', array() );

		if ( $stage === 'owner_name' && $text !== '' ) {
			$data['owner_name'] = $text;
			$this->sessions->set( $chat_id, 'business_data', $data );
			$this->sessions->set( $chat_id, 'business_stage', 'business_type' );
			$this->prompt_business_type_selection( $chat_id );
			return;
		}
		if ( $stage === 'business_type' || $stage === 'business_field' ) {
			return;
		}
		if ( $stage === 'website_url' && $text !== '' ) {
			$data['website_url'] = $text;
			$this->sessions->set( $chat_id, 'business_data', $data );
			$this->sessions->set( $chat_id, 'business_stage', 'bot_name' );
			$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'نام بازو (ربات) را وارد کن:' ) );
			return;
		}
		if ( $stage === 'website_name' && $text !== '' ) {
			$data['website_name'] = $text;
			$this->sessions->set( $chat_id, 'business_data', $data );
			$this->sessions->set( $chat_id, 'business_stage', 'bot_name' );
			$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'اسم سایت پیشنهادی ثبت شد. حالا نام بازو را وارد کن:' ) );
			return;
		}
		if ( $stage === 'bot_name' && $text !== '' ) {
			$data['bot_name'] = $text;
			$this->sessions->set( $chat_id, 'business_data', $data );
			$this->sessions->set( $chat_id, 'business_stage', 'bot_short_desc' );
			$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'تعریف کوتاه برای بازو بنویس (مثال: فروشگاه آنلاین با ارسال سریع):' ) );
			return;
		}
		if ( $stage === 'bot_short_desc' && $text !== '' ) {
			$data['bot_short_desc'] = $text;
			$this->sessions->set( $chat_id, 'business_data', $data );
			$this->sessions->set( $chat_id, 'business_stage', 'bot_welcome_text' );
			$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'پیام خوش آمد بازو را بنویس (مثال: سلام، خوش اومدی 👋 از منو گزینه موردنظرتو انتخاب کن):' ) );
			return;
		}
		if ( $stage === 'bot_welcome_text' && $text !== '' ) {
			$data['bot_welcome_text'] = $text;
			$this->sessions->set( $chat_id, 'business_data', $data );
			$this->sessions->set( $chat_id, 'business_stage', 'bot_profile_image' );
			$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'تصویر پروفایل بازو را ارسال کن. راهنما: مربع، واضح، لوگو یا برند خوانا، پس‌زمینه ساده.' ) );
			return;
		}
		if ( $stage === 'bot_profile_image' && $photo ) {
			$last = end( $message['photo'] );
			$file_id = is_array( $last ) ? (string) ( $last['file_id'] ?? '' ) : '';
			if ( $file_id === '' ) {
				$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'تصویر قابل پردازش نبود. لطفا یک تصویر واضح دوباره ارسال کن.' ) );
				return;
			}
			$data['bot_profile_image_file_id'] = $file_id;
			$data['status'] = 'pending';
			Logger::log( 'info', 'business_wizard_profile_image_received', array( 'chat_id' => $chat_id ) );
			$editing_id = (int) $this->sessions->get( $chat_id, 'business_editing_id', 0 );
			if ( $editing_id > 0 ) {
				$updated = $this->businesses->update( $editing_id, $chat_id, $data );
				if ( ! $updated ) {
					$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'ذخیره اطلاعات انجام نشد. لطفا دوباره تصویر را ارسال کن.' ) );
					return;
				}
				$business_id = $editing_id;
			} else {
				$business_id = $this->businesses->create( $chat_id, $data );
				if ( $business_id <= 0 ) {
					$this->client->send_message( array( 'chat_id' => $chat_id, 'text' => 'ثبت کسب و کار انجام نشد. لطفا دوباره تلاش کن.' ) );
					return;
				}
			}
			BusinessAdminSync::sync_business_submission( $business_id );
			$this->maybe_sync_business_to_crm_lead( $chat_id, $business_id, $data );
			AutomationEngine::ingest_event( $chat_id, 'business_submit', array( 'business_id' => $business_id ) );
			AutomationEngine::maybe_auto_convert_customer( $chat_id );
			$this->sessions->set( $chat_id, 'business_stage', '' );
			$this->sessions->set( $chat_id, 'business_data', array() );
			$this->sessions->set( $chat_id, 'business_editing_id', 0 );
			$this->send_reply_menu( $chat_id, '✅ تصویر ثبت شد و کسب و کارت ذخیره شد.', $this->main_reply_keyboard() );
			$this->show_businesses( $chat_id );
			$this->open_business( $chat_id, $business_id );
		}
	}

	private function start_request_flow( string $chat_id ): void {
		$this->sessions->set( $chat_id, 'request_data', array() );
		$this->sessions->set( $chat_id, 'request_stage', 'owner_name' );
		$this->prompt_stage( $chat_id, 'owner_name' );
	}

	private function prompt_stage( string $chat_id, string $stage ): void {
		$map = array(
			'owner_name'    => '👤 عالیه! اول نام و نام خانوادگی صاحب کسب‌وکار رو بفرست.',
			'business_name' => "🏪 حالا اسم کسب‌وکارت رو بفرست.\nبا همین اسم رباتت تنظیم میشه.",
			'intro_text'    => '📝 یه متن کوتاه معرفی کسب‌وکارت بنویس تا پیام خوش‌آمدگویی حرفه‌ای بچینیم.',
			'profile_image' => '🖼 لطفا تصویر پروفایل ربات رو ارسال کن.',
			'license_image' => '🪪 برای تکمیل سریع درخواست، تصویر کارت ملی یا مجوز رو هم بفرست.',
		);
		$this->send_reply_menu(
			$chat_id,
			(string) ( $map[ $stage ] ?? 'مرحله بعدی' ),
			array(
				array( '❌ انصراف' ),
			)
		);
	}

	private function show_support( string $chat_id ): void {
		$s = Plugin::get_settings();
		$items = ContentRepository::get_support_items();
		$text = (string) ( $s['support_intro_text'] ?? '' ) . "\n\n";
		foreach ( $items as $idx => $item ) {
			$text .= (string) ( $item['emoji'] ?? '•' ) . ' ' . (string) ( $item['title'] ?? '' ) . "\n";
			$text .= (string) ( $item['description'] ?? '' ) . "\n\n";
		}
		$rows = array();
		foreach ( $items as $idx => $item ) {
			$rows[] = array(
				array(
					'text' => (string) ( ( $item['emoji'] ?? '•' ) . ' ' . ( $item['title'] ?? ( 'آیتم ' . $idx ) ) ),
					'callback_data' => 'wbb:support:item:' . $idx,
				),
			);
		}
		$rows = array_merge( $rows, $this->build_global_inline_rows( '🏠 منوی اصلی', 'wbb:membership:verify' ) );
		EventLogger::log( $chat_id, 'support_opened' );
		$this->send_inline_menu( $chat_id, trim( $text ), $rows );
	}

	private function handle_support_item( string $chat_id, string $index ): void {
		$items = ContentRepository::get_support_items();
		$i = \absint( $index );
		if ( ! isset( $items[ $i ] ) || ! is_array( $items[ $i ] ) ) {
			return;
		}
		$item = $items[ $i ];
		$type = (string) ( $item['action_type'] ?? 'copy_text' );
		$value = (string) ( $item['action_value'] ?? '' );
		EventLogger::log( $chat_id, 'support_item_clicked', array( 'index' => $i, 'type' => $type ) );
		if ( $type === 'url' ) {
			$this->send_inline_menu(
				$chat_id,
				'برای ادامه سریع، روی دکمه زیر بزن 👇',
				array(
					array( array( 'text' => '🔗 باز کردن لینک', 'url' => $value ) ),
					array( array( 'text' => '🛠 درخواست ساخت ربات', 'callback_data' => 'wbb:request:start' ) ),
				)
			);
			return;
		}
		$prefix = 'ℹ️';
		if ( $type === 'phone' ) {
			$prefix = '📞';
		} elseif ( $type === 'username' ) {
			$prefix = '👤';
		}
		$this->send_context_message( $chat_id, $prefix . ' اطلاعات قابل کپی:' . "\n" . $value, '🆘 بازگشت به پشتیبانی', 'wbb:support' );
	}

	private function membership_required(): bool {
		$s = Plugin::get_settings();
		return (string) ( $s['membership_required'] ?? '1' ) === '1';
	}

	private function show_membership_gate( string $chat_id ): void {
		$s = Plugin::get_settings();
		EventLogger::log( $chat_id, 'membership_gate_shown' );
		$this->send_inline_menu( $chat_id, (string) ( $s['membership_gate_text'] ?? '' ), $this->membership_buttons() );
	}

	/**
	 * @return array<int,array<int,array<string,string>>>
	 */
	private function membership_buttons(): array {
		$s = Plugin::get_settings();
		$join_url = (string) ( $s['channel_join_url'] ?? '' );
		return array(
			array(
				array(
					'text' => '📢 عضویت در کانال',
					'url'  => $join_url !== '' ? $join_url : 'https://ble.ir',
				),
			),
			array(
				array(
					'text'          => '✅ بررسی عضویت',
					'callback_data' => 'wbb:membership:verify',
				),
			),
		);
	}

	private function is_member( string $chat_id, string $user_id ): bool {
		$s = Plugin::get_settings();
		$channel_id = trim( (string) ( $s['channel_id'] ?? '' ) );
		if ( $channel_id === '' ) {
			return false;
		}
		$res = $this->client->get_chat_member( $channel_id, $user_id );
		if ( ! is_array( $res ) || empty( $res['ok'] ) || empty( $res['result']['status'] ) ) {
			Logger::log( 'warning', 'membership_check_failed', array( 'chat_id' => $chat_id, 'channel_id' => $channel_id ) );
			return false;
		}
		$status = (string) $res['result']['status'];
		$ok = in_array( $status, array( 'member', 'administrator', 'creator' ), true );
		EventLogger::log( $chat_id, 'membership_checked', array( 'status' => $status, 'ok' => $ok ? 1 : 0 ) );
		return $ok;
	}

	/**
	 * @param array<int,array<int,array<string,string>>> $keyboard_rows
	 */
	private function send_inline_menu( string $chat_id, string $text, array $keyboard_rows ): void {
		$this->client->send_message(
			array(
				'chat_id'      => $chat_id,
				'text'         => $text,
				'reply_markup' => array( 'inline_keyboard' => $keyboard_rows ),
			)
		);
	}

	private function send_context_message( string $chat_id, string $text, string $context_label, string $context_callback ): void {
		$this->send_inline_menu( $chat_id, $text, $this->build_global_inline_rows( $context_label, $context_callback ) );
	}

	/**
	 * @return array<int,array<int,array<string,string>>>
	 */
	private function build_global_inline_rows( string $context_label, string $context_callback ): array {
		return array(
			array(
				array(
					'text' => '🛠 درخواست ساخت ربات',
					'callback_data' => 'wbb:request:start',
				),
			),
			array(
				array(
					'text' => $context_label,
					'callback_data' => $context_callback,
				),
			),
		);
	}

	/**
	 * @param array<int,array<int,string>> $rows
	 */
	private function send_reply_menu( string $chat_id, string $text, array $rows ): void {
		$keyboard = array();
		foreach ( $rows as $row ) {
			$kRow = array();
			foreach ( $row as $label ) {
				$kRow[] = array( 'text' => $label );
			}
			$keyboard[] = $kRow;
		}
		$this->client->send_message(
			array(
				'chat_id'      => $chat_id,
				'text'         => $text,
				'reply_markup' => array(
					'keyboard'          => $keyboard,
					'resize_keyboard'   => true,
					'one_time_keyboard' => false,
				),
			)
		);
	}

	/**
	 * @return array<int,array<int,string>>
	 */
	private function main_reply_keyboard(): array {
		$s     = Plugin::get_settings();
		$rows  = array();
		$line1 = array();
		if ( (string) ( $s['enable_menu_businesses'] ?? '1' ) === '1' ) {
			$line1[] = '🏢 کسب و کارهای من';
		}
		if ( ! empty( $line1 ) ) {
			$rows[] = $line1;
		}
		$line2 = array();
		if ( (string) ( $s['enable_menu_why_bale'] ?? '1' ) === '1' ) {
			$line2[] = '🚀 چرا بله برای فروش؟';
		}
		if ( (string) ( $s['enable_menu_features'] ?? '1' ) === '1' ) {
			$line2[] = '🤖 ربات کسب و کار چیه؟';
		}
		if ( ! empty( $line2 ) ) {
			$rows[] = $line2;
		}
		$line3 = array();
		if ( (string) ( $s['enable_menu_pricing'] ?? '1' ) === '1' ) {
			$line3[] = '💳 پلن ها و قیمت';
		}
		if ( (string) ( $s['enable_menu_faq'] ?? '1' ) === '1' ) {
			$line3[] = '❓ سوالات پرتکرار';
		}
		if ( ! empty( $line3 ) ) {
			$rows[] = $line3;
		}
		$line4 = array();
		if ( (string) ( $s['enable_menu_support'] ?? '1' ) === '1' ) {
			$line4[] = '🆘 تماس با پشتیبانی';
		}
		if ( (string) ( $s['enable_menu_profile'] ?? '1' ) === '1' ) {
			$line4[] = '👤 اطلاعات من';
		}
		if ( ! empty( $line4 ) ) {
			$rows[] = $line4;
		}
		return ! empty( $rows ) ? $rows : array( array( '🏢 کسب و کارهای من' ) );
	}

	/**
	 * @return array<int,array<int,string>>
	 */
	private function secondary_reply_keyboard(): array {
		return array(
			array( '💳 پلن ها و قیمت', '❓ سوالات پرتکرار' ),
			array( '🆘 تماس با پشتیبانی', '👤 اطلاعات من' ),
		);
	}

	private function maybe_register_wp_user_from_bale( string $chat_id ): void {
		$s = Plugin::get_settings();
		if ( (string) ( $s['enable_auto_register_user'] ?? '1' ) !== '1' ) {
			return;
		}
		$profile = $this->profiles->get_or_empty( $chat_id );
		$mobile  = preg_replace( '/[^0-9+]/', '', (string) ( $profile['mobile'] ?? '' ) );
		if ( $mobile === '' ) {
			return;
		}
		$existing = \get_users(
			array(
				'number'     => 1,
				'meta_key'   => 'webino_mobile_phone',
				'meta_value' => $mobile,
			)
		);
		if ( ! empty( $existing ) ) {
			$user_id = (int) $existing[0]->ID;
		} else {
			$first_name = \sanitize_text_field( (string) ( $profile['first_name'] ?? '' ) );
			$last_name  = \sanitize_text_field( (string) ( $profile['last_name'] ?? '' ) );
			$full_name  = trim( $first_name . ' ' . $last_name );
			$login      = 'bale_' . preg_replace( '/[^0-9]/', '', $mobile );
			$email      = $login . '@webinocrm.local';
			$user_id    = \wp_create_user( $login, \wp_generate_password( 18, true, true ), $email );
			if ( \is_wp_error( $user_id ) ) {
				return;
			}
			\wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $full_name !== '' ? $full_name : $login,
					'first_name'   => $first_name,
					'last_name'    => $last_name,
				)
			);
			$user = \get_user_by( 'ID', $user_id );
			if ( $user ) {
				$user->set_role( 'customer' );
			}
			\update_user_meta( $user_id, 'webino_mobile_phone', $mobile );
		}
		\update_user_meta( $user_id, 'webinocrm_bale_chat_id', $chat_id );
		AutomationEngine::sync_identity(
			$chat_id,
			array(
				'mobile'     => $mobile,
				'first_name' => (string) ( $profile['first_name'] ?? '' ),
				'last_name'  => (string) ( $profile['last_name'] ?? '' ),
			)
		);
	}

	/**
	 * @param array<string,mixed> $business_data
	 */
	private function maybe_sync_business_to_crm_lead( string $chat_id, int $business_id, array $business_data ): void {
		$s = Plugin::get_settings();
		if ( (string) ( $s['enable_auto_lead_from_business'] ?? '1' ) !== '1' ) {
			return;
		}
		$meta_key = '_webinocrm_bale_business_id';
		$exists   = \get_posts(
			array(
				'post_type'      => 'lead',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => $meta_key,
				'meta_value'     => (string) $business_id,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $exists ) ) {
			return;
		}
		$profile    = $this->profiles->get_or_empty( $chat_id );
		$first_name = \sanitize_text_field( (string) ( $profile['first_name'] ?? '' ) );
		$last_name  = \sanitize_text_field( (string) ( $profile['last_name'] ?? '' ) );
		$mobile     = \sanitize_text_field( (string) ( $profile['mobile'] ?? '' ) );
		if ( $first_name === '' ) {
			$first_name = \sanitize_text_field( (string) ( $business_data['owner_name'] ?? '' ) );
		}
		$lead_id = \wp_insert_post(
			array(
				'post_type'    => 'lead',
				'post_status'  => 'publish',
				'post_title'   => trim( $first_name . ' ' . $last_name ) !== '' ? trim( $first_name . ' ' . $last_name ) : \__( 'سرنخ بله', 'webinocrm' ),
				'post_content' => \sanitize_textarea_field( (string) ( $business_data['bot_short_desc'] ?? '' ) ),
			)
		);
		if ( \is_wp_error( $lead_id ) || ! $lead_id ) {
			return;
		}
		\update_post_meta( $lead_id, '_first_name', $first_name );
		\update_post_meta( $lead_id, '_last_name', $last_name );
		\update_post_meta( $lead_id, '_mobile', $mobile );
		\update_post_meta( $lead_id, '_business_name', \sanitize_text_field( (string) ( $business_data['bot_name'] ?? '' ) ) );
		\update_post_meta( $lead_id, $meta_key, (string) $business_id );
		\update_post_meta( $lead_id, '_webinocrm_bale_chat_id', $chat_id );
		\wp_set_object_terms( $lead_id, 'bale-bot', 'lead_source' );
		AutomationEngine::ensure_lead_post(
			$chat_id,
			array(
				'first_name'    => $first_name,
				'last_name'     => $last_name,
				'mobile'        => $mobile,
				'business_name' => (string) ( $business_data['bot_name'] ?? '' ),
			)
		);
	}
}
