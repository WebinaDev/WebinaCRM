<?php

namespace WebinaBaleBusiness\Automation;

use WebinaBaleBusiness\Database\ProfileRepository;

class Engine {

	/**
	 * @var array<string,int>
	 */
	private static $score_map = array(
		'start'            => 5,
		'reply'            => 3,
		'plan_click'       => 12,
		'profile_complete' => 15,
		'business_submit'  => 20,
		'purchase'         => 30,
	);

	public static function ingest_event( string $chat_id, string $event_type, array $payload = array() ): void {
		global $wpdb;
		$now = \current_time( 'mysql' );
		$timeline_table = $wpdb->prefix . 'wbb_timeline';
		$wpdb->insert(
			$timeline_table,
			array(
				'chat_id'    => $chat_id,
				'entity_type'=> 'bale',
				'entity_id'  => 0,
				'event_type' => \sanitize_key( $event_type ),
				'payload'    => \wp_json_encode( $payload ),
				'created_at' => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		self::upsert_lead_score( $chat_id, $event_type );
		self::schedule_default_sequences( $chat_id, $event_type );
	}

	public static function process_queue_due_items(): array {
		global $wpdb;
		$queue_table = $wpdb->prefix . 'wbb_automation_queue';
		$items = $wpdb->get_results(
			"SELECT * FROM {$queue_table} WHERE status = 'queued' AND scheduled_for <= NOW() ORDER BY id ASC LIMIT 100",
			\ARRAY_A
		);
		$processed = 0;
		$failed = 0;
		$client = new \WebinaBaleBusiness\Bale\Client();
		foreach ( (array) $items as $item ) {
			$chat_id = (string) ( $item['chat_id'] ?? '' );
			$text    = '';
			$payload = \json_decode( (string) ( $item['payload'] ?? '' ), true );
			if ( \is_array( $payload ) ) {
				$text = (string) ( $payload['message'] ?? '' );
			}
			$status = 'done';
			if ( $chat_id === '' || $text === '' ) {
				$status = 'failed';
			} else {
				$res = $client->send_message( array( 'chat_id' => $chat_id, 'text' => $text ) );
				if ( ! \is_array( $res ) || empty( $res['ok'] ) ) {
					$status = 'failed';
				}
			}
			$wpdb->update(
				$queue_table,
				array( 'status' => $status ),
				array( 'id' => (int) $item['id'] ),
				array( '%s' ),
				array( '%d' )
			);
			if ( $status === 'done' ) {
				++$processed;
			} else {
				++$failed;
			}
		}
		return array( 'processed' => $processed, 'failed' => $failed );
	}

	public static function upsert_lead_score( string $chat_id, string $event_type ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wbb_leads';
		$delta = self::$score_map[ $event_type ] ?? 1;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE chat_id = %s LIMIT 1", $chat_id ), \ARRAY_A );
		$now   = \current_time( 'mysql' );
		if ( is_array( $row ) ) {
			$score = (int) ( $row['score'] ?? 0 ) + $delta;
			$stage = self::score_to_stage( $score );
			$wpdb->update(
				$table,
				array(
					'score'         => $score,
					'funnel_stage'  => $stage,
					'last_event_at' => $now,
					'updated_at'    => $now,
				),
				array( 'id' => (int) $row['id'] ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			self::maybe_auto_assign_and_task( $chat_id, $score, $stage );
			return;
		}
		$wpdb->insert(
			$table,
			array(
				'chat_id'       => $chat_id,
				'score'         => $delta,
				'funnel_stage'  => self::score_to_stage( $delta ),
				'last_event_at' => $now,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	public static function sync_identity( string $chat_id, array $profile = array() ): array {
		$mobile = \preg_replace( '/[^0-9+]/', '', (string) ( $profile['mobile'] ?? '' ) );
		$email  = \sanitize_email( (string) ( $profile['email'] ?? '' ) );
		$user_id = 0;

		if ( $mobile !== '' ) {
			$by_mobile = \get_users(
				array(
					'number'     => 1,
					'meta_key'   => 'webino_mobile_phone',
					'meta_value' => $mobile,
					'fields'     => 'ID',
				)
			);
			if ( ! empty( $by_mobile ) ) {
				$user_id = (int) $by_mobile[0];
			}
		}
		if ( $user_id <= 0 && $email !== '' ) {
			$user = \get_user_by( 'email', $email );
			if ( $user ) {
				$user_id = (int) $user->ID;
			}
		}
		if ( $user_id <= 0 ) {
			$login   = 'bale_' . $chat_id;
			$pwd     = \wp_generate_password( 18, true, true );
			$usemail = $email !== '' ? $email : 'bale_' . $chat_id . '@webinocrm.local';
			$user_id = (int) \wp_create_user( \sanitize_user( $login, true ), $pwd, $usemail );
			if ( ! \is_wp_error( $user_id ) ) {
				$user = \get_user_by( 'ID', $user_id );
				if ( $user ) {
					$user->set_role( 'customer' );
				}
			}
		}
		if ( $user_id > 0 ) {
			\update_user_meta( $user_id, 'webinocrm_bale_chat_id', $chat_id );
			if ( $mobile !== '' ) {
				\update_user_meta( $user_id, 'webino_mobile_phone', $mobile );
			}
		}
		return array(
			'user_id' => $user_id,
			'mobile'  => $mobile,
			'email'   => $email,
		);
	}

	public static function ensure_lead_post( string $chat_id, array $data ): int {
		$existing = \get_posts(
			array(
				'post_type'      => 'lead',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => '_webinocrm_bale_chat_id',
				'meta_value'     => $chat_id,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}
		$first_name = \sanitize_text_field( (string) ( $data['first_name'] ?? '' ) );
		$last_name  = \sanitize_text_field( (string) ( $data['last_name'] ?? '' ) );
		$mobile     = \sanitize_text_field( (string) ( $data['mobile'] ?? '' ) );
		if ( $mobile === '' ) {
			return 0;
		}
		$lead_id = \wp_insert_post(
			array(
				'post_type'   => 'lead',
				'post_status' => 'publish',
				'post_title'  => \trim( $first_name . ' ' . $last_name ) !== '' ? \trim( $first_name . ' ' . $last_name ) : 'Lead Bale ' . $chat_id,
			)
		);
		if ( \is_wp_error( $lead_id ) || ! $lead_id ) {
			return 0;
		}
		\update_post_meta( $lead_id, '_webinocrm_bale_chat_id', $chat_id );
		\update_post_meta( $lead_id, '_first_name', $first_name );
		\update_post_meta( $lead_id, '_last_name', $last_name );
		\update_post_meta( $lead_id, '_mobile', $mobile );
		\update_post_meta( $lead_id, '_business_name', \sanitize_text_field( (string) ( $data['business_name'] ?? '' ) ) );
		return (int) $lead_id;
	}

	public static function maybe_auto_convert_customer( string $chat_id ): int {
		$profile_repo = new ProfileRepository();
		$profile      = $profile_repo->get_or_empty( $chat_id );
		if ( empty( $profile['is_complete'] ) ) {
			return 0;
		}
		$lead = self::get_lead_row( $chat_id );
		if ( ! is_array( $lead ) || (int) ( $lead['score'] ?? 0 ) < 60 ) {
			return 0;
		}
		if ( (int) ( $lead['converted_customer_id'] ?? 0 ) > 0 ) {
			return (int) $lead['converted_customer_id'];
		}
		$identity = self::sync_identity(
			$chat_id,
			array(
				'mobile'     => (string) ( $profile['mobile'] ?? '' ),
				'first_name' => (string) ( $profile['first_name'] ?? '' ),
				'last_name'  => (string) ( $profile['last_name'] ?? '' ),
			)
		);
		$user_id = (int) ( $identity['user_id'] ?? 0 );
		if ( $user_id <= 0 ) {
			return 0;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'wbb_leads';
		$wpdb->update(
			$table,
			array(
				'converted_customer_id' => $user_id,
				'funnel_stage'          => 'sales-ready',
				'updated_at'            => \current_time( 'mysql' ),
			),
			array( 'chat_id' => $chat_id ),
			array( '%d', '%s', '%s' ),
			array( '%s' )
		);
		return $user_id;
	}

	public static function campaign_metrics( int $campaign_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wbb_campaign_deliveries';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS cnt FROM {$table} WHERE campaign_id = %d GROUP BY status",
				$campaign_id
			),
			\ARRAY_A
		);
		$data = array(
			'delivered' => 0,
			'clicked'   => 0,
			'replied'   => 0,
			'converted' => 0,
		);
		foreach ( (array) $rows as $row ) {
			$status = (string) ( $row['status'] ?? '' );
			$cnt    = (int) ( $row['cnt'] ?? 0 );
			if ( isset( $data[ $status ] ) ) {
				$data[ $status ] = $cnt;
			}
		}
		return $data;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function get_lead_row( string $chat_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'wbb_leads';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE chat_id = %s LIMIT 1", $chat_id ),
			\ARRAY_A
		);
		return \is_array( $row ) ? $row : null;
	}

	private static function score_to_stage( int $score ): string {
		if ( $score >= 60 ) {
			return 'sales-ready';
		}
		if ( $score >= 40 ) {
			return 'hot';
		}
		if ( $score >= 20 ) {
			return 'warm';
		}
		return 'new';
	}

	private static function maybe_auto_assign_and_task( string $chat_id, int $score, string $stage ): void {
		if ( ! \in_array( $stage, array( 'hot', 'sales-ready' ), true ) ) {
			return;
		}
		$sales_users = \get_users(
			array(
				'role__in' => array( 'sales_consultant', 'system_manager', 'administrator' ),
				'number'   => 1,
				'fields'   => 'ID',
			)
		);
		if ( empty( $sales_users ) ) {
			return;
		}
		$assignee = (int) $sales_users[0];
		global $wpdb;
		$table = $wpdb->prefix . 'wbb_leads';
		$wpdb->update(
			$table,
			array(
				'assigned_to' => $assignee,
				'updated_at'  => \current_time( 'mysql' ),
			),
			array( 'chat_id' => $chat_id ),
			array( '%d', '%s' ),
			array( '%s' )
		);
		$task_title = 'پیگیری لید بله - ' . $chat_id;
		$task_id    = \wp_insert_post(
			array(
				'post_type'   => 'task',
				'post_status' => 'publish',
				'post_title'  => $task_title,
				'post_content'=> 'لید امتیاز بالا از ربات بله. امتیاز: ' . $score,
				'post_author' => $assignee,
			)
		);
		if ( ! \is_wp_error( $task_id ) && $task_id ) {
			\update_post_meta( $task_id, '_webinocrm_bale_chat_id', $chat_id );
			\update_post_meta( $task_id, '_webinocrm_task_priority', 'high' );
			\update_post_meta( $task_id, '_webinocrm_due_date', \gmdate( 'Y-m-d', \strtotime( '+1 day' ) ) );
		}
	}

	private static function schedule_default_sequences( string $chat_id, string $event_type ): void {
		global $wpdb;
		$queue_table = $wpdb->prefix . 'wbb_automation_queue';
		$rules = array();
		if ( $event_type === 'start' ) {
			$rules = array(
				array( 'step' => 'd0_welcome', 'hours' => 0, 'message' => 'خوش اومدی. برای شروع لطفا پروفایل و نیاز کسب‌وکارت رو کامل کن.' ),
				array( 'step' => 'd1_followup', 'hours' => 24, 'message' => 'یادآوری دوستانه: تکمیل پروفایل باعث فعال شدن پیشنهاد اختصاصی می‌شود.' ),
				array( 'step' => 'd3_followup', 'hours' => 72, 'message' => 'هنوز همراهیم؛ اگر آماده‌ای برای شروع، همین پیام رو پاسخ بده.' ),
			);
		} elseif ( $event_type === 'plan_click' ) {
			$rules = array(
				array( 'step' => 'd0_plan', 'hours' => 3, 'message' => 'برای انتخاب بهتر پلن، مشخصات کسب‌وکار را ارسال کن تا دقیق پیشنهاد بدهیم.' ),
				array( 'step' => 'd1_plan', 'hours' => 24, 'message' => 'پیشنهاد ویژه پلن همچنان فعال است. اگر سوالی داری همینجا بپرس.' ),
				array( 'step' => 'd3_plan', 'hours' => 72, 'message' => 'آخرین یادآوری پلن: با فعال‌سازی امروز، onboarding سریع انجام می‌شود.' ),
			);
		} elseif ( $event_type === 'business_submit' ) {
			$rules = array(
				array( 'step' => 'd0_sales', 'hours' => 0, 'message' => 'ثبت کسب‌وکار انجام شد. کارشناس فروش خیلی سریع با شما هماهنگ می‌کند.' ),
				array( 'step' => 'd1_sales', 'hours' => 24, 'message' => 'برای تسریع فرایند، اگر سوالی داری در همین چت ارسال کن.' ),
			);
		}
		if ( empty( $rules ) ) {
			return;
		}
		$now = new \DateTime( \gmdate( 'Y-m-d H:i:s' ) );
		$created_at = \current_time( 'mysql' );
		foreach ( $rules as $rule ) {
			$schedule = clone $now;
			$schedule->modify( '+' . (int) $rule['hours'] . ' hours' );
			$wpdb->insert(
				$queue_table,
				array(
					'chat_id'        => $chat_id,
					'trigger_key'    => \sanitize_key( $event_type ),
					'step_key'       => \sanitize_key( (string) $rule['step'] ),
					'scheduled_for'  => $schedule->format( 'Y-m-d H:i:s' ),
					'status'         => 'queued',
					'payload'        => \wp_json_encode( array( 'message' => (string) $rule['message'] ) ),
					'created_at'     => $created_at,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}
}

