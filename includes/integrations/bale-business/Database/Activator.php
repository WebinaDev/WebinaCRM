<?php

namespace WebinaBaleBusiness\Database;

class Activator {

	public static function activate(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_sessions  = $wpdb->prefix . 'wbb_sessions';
		$table_events    = $wpdb->prefix . 'wbb_events';
		$table_logs      = $wpdb->prefix . 'wbb_logs';
		$table_business  = $wpdb->prefix . 'wbb_businesses';
		$table_profiles  = $wpdb->prefix . 'wbb_profiles';
		$table_leads     = $wpdb->prefix . 'wbb_leads';
		$table_timeline  = $wpdb->prefix . 'wbb_timeline';
		$table_campaigns = $wpdb->prefix . 'wbb_campaigns';
		$table_delivery  = $wpdb->prefix . 'wbb_campaign_deliveries';
		$table_queue     = $wpdb->prefix . 'wbb_automation_queue';

		require_once \ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_sessions = "CREATE TABLE {$table_sessions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			chat_id VARCHAR(64) NOT NULL,
			session_key VARCHAR(191) NOT NULL,
			session_value LONGTEXT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY chat_session (chat_id, session_key)
		) {$charset_collate};";

		$sql_events = "CREATE TABLE {$table_events} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			chat_id VARCHAR(64) NOT NULL,
			event_type VARCHAR(100) NOT NULL,
			payload LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY chat_idx (chat_id),
			KEY event_idx (event_type)
		) {$charset_collate};";

		$sql_logs = "CREATE TABLE {$table_logs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			level VARCHAR(20) NOT NULL,
			log_type VARCHAR(100) NOT NULL,
			context LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY level_idx (level),
			KEY type_idx (log_type)
		) {$charset_collate};";

		$sql_businesses = "CREATE TABLE {$table_business} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			chat_id VARCHAR(64) NOT NULL,
			owner_name VARCHAR(191) NOT NULL,
			business_type VARCHAR(191) NOT NULL,
			business_field VARCHAR(191) NOT NULL,
			has_website TINYINT(1) NOT NULL DEFAULT 0,
			website_url VARCHAR(255) NOT NULL DEFAULT '',
			website_name VARCHAR(191) NOT NULL DEFAULT '',
			bot_name VARCHAR(191) NOT NULL DEFAULT '',
			bot_short_desc TEXT NULL,
			bot_welcome_text TEXT NULL,
			bot_profile_image_file_id VARCHAR(191) NOT NULL DEFAULT '',
			status VARCHAR(50) NOT NULL DEFAULT 'draft',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY chat_idx (chat_id),
			KEY status_idx (status)
		) {$charset_collate};";

		$sql_profiles = "CREATE TABLE {$table_profiles} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			chat_id VARCHAR(64) NOT NULL,
			first_name VARCHAR(191) NOT NULL DEFAULT '',
			last_name VARCHAR(191) NOT NULL DEFAULT '',
			national_card_file_id VARCHAR(191) NOT NULL DEFAULT '',
			mobile VARCHAR(32) NOT NULL DEFAULT '',
			is_complete TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY chat_unique (chat_id)
		) {$charset_collate};";

		$sql_leads = "CREATE TABLE {$table_leads} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			chat_id VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			lead_post_id BIGINT UNSIGNED DEFAULT NULL,
			score INT NOT NULL DEFAULT 0,
			funnel_stage VARCHAR(32) NOT NULL DEFAULT 'new',
			assigned_to BIGINT UNSIGNED DEFAULT NULL,
			last_event_at DATETIME NULL,
			converted_customer_id BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY chat_unique (chat_id),
			KEY stage_idx (funnel_stage),
			KEY score_idx (score),
			KEY assigned_idx (assigned_to)
		) {$charset_collate};";

		$sql_timeline = "CREATE TABLE {$table_timeline} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			chat_id VARCHAR(64) NOT NULL,
			entity_type VARCHAR(32) NOT NULL,
			entity_id BIGINT UNSIGNED DEFAULT NULL,
			event_type VARCHAR(100) NOT NULL,
			payload LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY chat_idx (chat_id),
			KEY entity_idx (entity_type, entity_id),
			KEY event_idx (event_type)
		) {$charset_collate};";

		$sql_campaigns = "CREATE TABLE {$table_campaigns} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			segment_key VARCHAR(64) NOT NULL,
			variant VARCHAR(8) NOT NULL DEFAULT 'A',
			message_template TEXT NOT NULL,
			cta_text VARCHAR(191) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			scheduled_for DATETIME NULL,
			created_by BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY segment_idx (segment_key),
			KEY status_idx (status)
		) {$charset_collate};";

		$sql_delivery = "CREATE TABLE {$table_delivery} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT UNSIGNED NOT NULL,
			chat_id VARCHAR(64) NOT NULL,
			variant VARCHAR(8) NOT NULL DEFAULT 'A',
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			delivered_at DATETIME NULL,
			clicked_at DATETIME NULL,
			replied_at DATETIME NULL,
			converted_at DATETIME NULL,
			response_payload LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY campaign_idx (campaign_id),
			KEY chat_idx (chat_id),
			KEY status_idx (status)
		) {$charset_collate};";

		$sql_queue = "CREATE TABLE {$table_queue} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			chat_id VARCHAR(64) NOT NULL,
			trigger_key VARCHAR(64) NOT NULL,
			step_key VARCHAR(64) NOT NULL,
			scheduled_for DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			payload LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY chat_idx (chat_id),
			KEY schedule_idx (scheduled_for),
			KEY status_idx (status)
		) {$charset_collate};";

		\dbDelta( $sql_sessions );
		\dbDelta( $sql_events );
		\dbDelta( $sql_logs );
		\dbDelta( $sql_businesses );
		\dbDelta( $sql_profiles );
		\dbDelta( $sql_leads );
		\dbDelta( $sql_timeline );
		\dbDelta( $sql_campaigns );
		\dbDelta( $sql_delivery );
		\dbDelta( $sql_queue );

		self::seed_marketing_content();
		self::seed_business_catalog();
	}

	private static function seed_marketing_content(): void {
		if ( ! function_exists( 'post_type_exists' ) || ! post_type_exists( 'wbb_plan' ) ) {
			return;
		}

		$plan_count  = wp_count_posts( 'wbb_plan' );
		$faq_count   = wp_count_posts( 'wbb_faq' );
		$block_count = wp_count_posts( 'wbb_sales_block' );

		if ( $plan_count && (int) $plan_count->publish === 0 ) {
			wp_insert_post(
				array(
					'post_type'    => 'wbb_plan',
					'post_status'  => 'publish',
					'post_title'   => 'پلن شروع سریع 🚀',
					'post_content' => 'مناسب کسب وکارهایی که می خواهند سریع وارد فروش در بله شوند.',
				)
			);
			wp_insert_post(
				array(
					'post_type'    => 'wbb_plan',
					'post_status'  => 'publish',
					'post_title'   => 'پلن حرفه ای 💎',
					'post_content' => 'مناسب کسب وکارهایی که مسیر فروش و اتوماسیون کامل تری نیاز دارند.',
				)
			);
		}

		if ( $faq_count && (int) $faq_count->publish === 0 ) {
			wp_insert_post(
				array(
					'post_type'    => 'wbb_faq',
					'post_status'  => 'publish',
					'post_title'   => 'چطور با بله فروشم را بیشتر کنم؟',
					'post_content' => 'با ربات بله می توانید تخفیف ها، محصولات جدید و پیام های تبلیغاتی را هدفمند ارسال کنید.',
				)
			);
			wp_insert_post(
				array(
					'post_type'    => 'wbb_faq',
					'post_status'  => 'publish',
					'post_title'   => 'چرا چند کانال فروش لازم است؟',
					'post_content' => 'تا در صورت اختلال یک کانال، کانال های دیگر جریان فروش شما را حفظ کنند.',
				)
			);
		}

		if ( $block_count && (int) $block_count->publish === 0 ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'wbb_sales_block',
					'post_status'  => 'publish',
					'post_title'   => 'دایرکت فروش در بله 🟣',
					'post_content' => 'با ربات بله وبینا ارتباط ۲۴ ساعته با مشتریان داشته باشید و لیدها را به فروش تبدیل کنید.',
				)
			);
			if ( $id ) {
				update_post_meta( $id, '_wbb_block_cta', 'درخواست ساخت ربات' );
			}
		}
	}

	private static function seed_business_catalog(): void {
		$types = \get_terms(
			array(
				'taxonomy'   => 'wbb_business_type_tax',
				'hide_empty' => false,
				'fields'     => 'id=>slug',
			)
		);
		$activity_terms = \get_terms(
			array(
				'taxonomy'   => 'wbb_business_activity_tax',
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		$has_types      = \is_array( $types ) && ! empty( $types );
		$has_activities = \is_array( $activity_terms ) && ! empty( $activity_terms );

		$default_types = array(
			'store'           => 'فروشگاهی',
			'service'         => 'خدماتی',
			'education'       => 'آموزشی',
			'restaurant'      => 'رستوران و کافه',
			'health-beauty'   => 'سلامت و زیبایی',
			'finance-legal'   => 'مالی و حقوقی',
			'technology'      => 'فناوری و نرم افزار',
			'art-media'       => 'هنری و رسانه',
			'travel-stay'     => 'گردشگری و اقامت',
			'building-decor'  => 'ساختمان و دکوراسیون',
			'auto-transport'  => 'خودرو و حمل و نقل',
			'kids-family'     => 'کودک و خانواده',
		);

		$type_map = array();
		foreach ( $default_types as $slug => $name ) {
			$term = \get_term_by( 'slug', $slug, 'wbb_business_type_tax' );
			if ( ! $term || ! \is_object( $term ) ) {
				$insert = \wp_insert_term(
					$name,
					'wbb_business_type_tax',
					array(
						'slug' => $slug,
					)
				);
				if ( ! \is_wp_error( $insert ) && isset( $insert['term_id'] ) ) {
					$type_map[ $slug ] = (int) $insert['term_id'];
				}
				continue;
			}
			$type_map[ $slug ] = (int) $term->term_id;
		}

		if ( $has_types && $has_activities ) {
			return;
		}

		$activity_map = array(
			'store' => array( 'پوشاک و مد', 'کیف و کفش', 'لوازم دیجیتال', 'موبایل و اکسسوری', 'لوازم خانگی', 'سوپرمارکت و مواد غذایی', 'آرایشی و بهداشتی', 'عطر و ادکلن', 'کتاب و نوشت افزار', 'اسباب بازی و سرگرمی', 'لوازم ورزشی', 'گل و هدیه' ),
			'service' => array( 'مشاوره کسب و کار', 'دیجیتال مارکتینگ', 'طراحی سایت', 'پشتیبانی فنی', 'خدمات چاپ و تبلیغات', 'نظافت و خدمات منزل', 'تعمیرات تخصصی', 'خدمات مهاجرتی', 'خدمات منابع انسانی', 'خدمات بیمه' ),
			'education' => array( 'آموزش زبان', 'آموزش برنامه نویسی', 'آموزش طراحی', 'آموزش کنکور', 'دوره های مهارتی', 'کوچینگ و منتورینگ', 'آموزش کودک', 'وبینار و کارگاه' ),
			'restaurant' => array( 'فست فود', 'رستوران ایرانی', 'کافه', 'شیرینی و نان', 'سفارش بیرون بر', 'کترینگ' ),
			'health-beauty' => array( 'کلینیک پوست و مو', 'سالن زیبایی', 'باشگاه و تناسب اندام', 'تغذیه و رژیم', 'خدمات دندانپزشکی', 'خدمات روانشناسی' ),
			'finance-legal' => array( 'حسابداری', 'مشاوره مالیاتی', 'خدمات حقوقی', 'دفتر وکالت', 'سرمایه گذاری' ),
			'technology' => array( 'SaaS و نرم افزار ابری', 'اپلیکیشن موبایل', 'هوش مصنوعی', 'امنیت شبکه', 'زیرساخت و سرور' ),
			'art-media' => array( 'تولید محتوا', 'عکاسی و فیلمبرداری', 'طراحی گرافیک', 'موسیقی', 'انتشارات و رسانه' ),
			'travel-stay' => array( 'آژانس مسافرتی', 'هتل و اقامتگاه', 'تور داخلی', 'تور خارجی', 'خدمات ویزا' ),
			'building-decor' => array( 'معماری', 'دکوراسیون داخلی', 'بازسازی', 'کابینت و چوب', 'فروش مصالح' ),
			'auto-transport' => array( 'نمایشگاه خودرو', 'تعمیرگاه خودرو', 'قطعات یدکی', 'کارواش', 'حمل و نقل بار' ),
			'kids-family' => array( 'پوشاک کودک', 'آموزش کودک', 'اسباب بازی کودک', 'خدمات مادر و نوزاد', 'مشاوره خانواده' ),
		);

		foreach ( $activity_map as $type_slug => $activities ) {
			foreach ( $activities as $activity_name ) {
				$activity_slug = \sanitize_title( $activity_name . '-' . $type_slug );
				$term          = \get_term_by( 'slug', $activity_slug, 'wbb_business_activity_tax' );
				if ( ! $term || ! \is_object( $term ) ) {
					$insert = \wp_insert_term(
						$activity_name,
						'wbb_business_activity_tax',
						array(
							'slug' => $activity_slug,
						)
					);
					if ( \is_wp_error( $insert ) || ! isset( $insert['term_id'] ) ) {
						continue;
					}
					$term_id = (int) $insert['term_id'];
				} else {
					$term_id = (int) $term->term_id;
				}

				\update_term_meta( $term_id, 'wbb_parent_type_term_slug', $type_slug );
				if ( isset( $type_map[ $type_slug ] ) ) {
					\update_term_meta( $term_id, 'wbb_parent_type_term_id', (int) $type_map[ $type_slug ] );
				}
			}
		}
	}
}
