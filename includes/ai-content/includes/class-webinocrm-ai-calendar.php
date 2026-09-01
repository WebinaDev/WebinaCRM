<?php
/**
 * Content calendar for AI posts/products.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calendar CRUD + daily runner.
 */
final class WebinoCRM_AI_Calendar {

	/**
	 * @param array<string,mixed> $args Filters.
	 * @return array{items:array}
	 */
	public static function list_slots( $args = array() ) {
		global $wpdb;
		$table = WebinoCRM_AI_Content_Db::table( 'calendar' );
		$from  = isset( $args['from'] ) ? sanitize_text_field( (string) $args['from'] ) : gmdate( 'Y-m-d', time() - WEEK_IN_SECONDS );
		$to    = isset( $args['to'] ) ? sanitize_text_field( (string) $args['to'] ) : gmdate( 'Y-m-d', time() + 40 * DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE slot_date BETWEEN %s AND %s ORDER BY slot_date ASC, id ASC",
				$from,
				$to
			),
			ARRAY_A
		);

		return array( 'items' => is_array( $rows ) ? $rows : array() );
	}

	/**
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function create_slot( $input ) {
		global $wpdb;
		$table = WebinoCRM_AI_Content_Db::table( 'calendar' );
		$now   = current_time( 'mysql', true );

		$type = sanitize_key( (string) ( $input['content_type'] ?? 'blog' ) );
		if ( ! in_array( $type, array( 'blog', 'product' ), true ) ) {
			$type = 'blog';
		}

		$date = sanitize_text_field( (string) ( $input['slot_date'] ?? '' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid date.', 'webinocrm' ) );
		}

		$wpdb->insert(
			$table,
			array(
				'slot_date'           => $date,
				'content_type'        => $type,
				'topic'               => sanitize_text_field( (string) ( $input['topic'] ?? '' ) ),
				'focus_keyword'       => sanitize_text_field( (string) ( $input['focus_keyword'] ?? '' ) ),
				'secondary_keywords'  => sanitize_textarea_field( (string) ( $input['secondary_keywords'] ?? '' ) ),
				'category_id'         => (int) ( $input['category_id'] ?? 0 ),
				'product_id'          => (int) ( $input['product_id'] ?? 0 ),
				'status'              => 'planned',
				'notes'               => sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ),
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		$id = (int) $wpdb->insert_id;
		return self::get_slot( $id );
	}

	/**
	 * Bulk create from topic lines.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array{created:int,items:array}
	 */
	public static function bulk_from_topics( $input ) {
		$lines     = preg_split( '/\r\n|\r|\n/', (string) ( $input['topics'] ?? '' ) );
		$start     = sanitize_text_field( (string) ( $input['start_date'] ?? gmdate( 'Y-m-d' ) ) );
		$type      = sanitize_key( (string) ( $input['content_type'] ?? 'blog' ) );
		$keyword   = sanitize_text_field( (string) ( $input['focus_keyword'] ?? '' ) );
		$created   = array();
		$day       = strtotime( $start . ' UTC' );
		if ( ! $day ) {
			$day = time();
		}

		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$focus = $keyword;
			$topic = $line;
			if ( false !== strpos( $line, '|' ) ) {
				$parts = array_map( 'trim', explode( '|', $line, 2 ) );
				$topic = $parts[0];
				$focus = $parts[1] !== '' ? $parts[1] : $keyword;
			}
			$slot = self::create_slot(
				array(
					'slot_date'     => gmdate( 'Y-m-d', $day ),
					'content_type'  => $type,
					'topic'         => $topic,
					'focus_keyword' => $focus !== '' ? $focus : $topic,
					'category_id'   => (int) ( $input['category_id'] ?? 0 ),
				)
			);
			if ( ! is_wp_error( $slot ) ) {
				$created[] = $slot;
			}
			$day += DAY_IN_SECONDS;
		}

		return array(
			'created' => count( $created ),
			'items'   => $created,
		);
	}

	/**
	 * @param int                 $id Slot ID.
	 * @param array<string,mixed> $input Patch.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function update_slot( $id, $input ) {
		global $wpdb;
		$table = WebinoCRM_AI_Content_Db::table( 'calendar' );
		$row   = self::get_slot( (int) $id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$fields = array();
		$format = array();
		$map    = array(
			'slot_date'          => '%s',
			'content_type'       => '%s',
			'topic'              => '%s',
			'focus_keyword'      => '%s',
			'secondary_keywords' => '%s',
			'category_id'        => '%d',
			'product_id'         => '%d',
			'status'             => '%s',
			'notes'              => '%s',
		);
		foreach ( $map as $key => $fmt ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			if ( '%d' === $fmt ) {
				$fields[ $key ] = (int) $input[ $key ];
			} else {
				$fields[ $key ] = sanitize_text_field( (string) $input[ $key ] );
			}
			$format[] = $fmt;
		}
		$fields['updated_at'] = current_time( 'mysql', true );
		$format[]             = '%s';

		$wpdb->update( $table, $fields, array( 'id' => (int) $id ), $format, array( '%d' ) );
		return self::get_slot( (int) $id );
	}

	/**
	 * @param int $id Slot.
	 * @return true|WP_Error
	 */
	public static function delete_slot( $id ) {
		global $wpdb;
		$table = WebinoCRM_AI_Content_Db::table( 'calendar' );
		$wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
		return true;
	}

	/**
	 * @param int $id Slot.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get_slot( $id ) {
		global $wpdb;
		$table = WebinoCRM_AI_Content_Db::table( 'calendar' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Calendar slot not found.', 'webinocrm' ) );
		}
		return $row;
	}

	/**
	 * @param int $calendar_id Slot.
	 * @param int $entity_id Created entity.
	 * @return void
	 */
	public static function mark_done( $calendar_id, $entity_id = 0 ) {
		global $wpdb;
		$table = WebinoCRM_AI_Content_Db::table( 'calendar' );
		$wpdb->update(
			$table,
			array(
				'status'     => 'done',
				'product_id' => (int) $entity_id,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $calendar_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Daily cron: enqueue due planned slots up to quotas.
	 *
	 * @return void
	 */
	public static function run_daily() {
		if ( class_exists( 'WebinoCRM_AI_Queue', false ) && WebinoCRM_AI_Queue::is_paused() ) {
			return;
		}
		$settings = WebinoCRM_AI_Content_Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		global $wpdb;
		$table = WebinoCRM_AI_Content_Db::table( 'calendar' );
		$today = current_time( 'Y-m-d' );

		$blog_quota    = (int) $settings['daily_blog_quota'];
		$product_quota = (int) $settings['daily_product_quota'];

		self::enqueue_due( $table, 'blog', $today, $blog_quota );
		self::enqueue_due( $table, 'product', $today, $product_quota );
	}

	/**
	 * @param string $table Table.
	 * @param string $type blog|product.
	 * @param string $today Date.
	 * @param int    $quota Quota.
	 * @return void
	 */
	private static function enqueue_due( $table, $type, $today, $quota ) {
		global $wpdb;
		if ( $quota <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE content_type = %s AND status = 'planned' AND slot_date <= %s ORDER BY slot_date ASC, id ASC LIMIT %d",
				$type,
				$today,
				$quota
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			if ( 'blog' === $type ) {
				$job_id = WebinoCRM_AI_Queue::enqueue(
					'blog_write',
					'calendar',
					(int) $row['id'],
					array(
						'calendar_id'         => (int) $row['id'],
						'topic'               => $row['topic'],
						'focus_keyword'       => $row['focus_keyword'],
						'secondary_keywords'  => array_filter( array_map( 'trim', explode( ',', (string) $row['secondary_keywords'] ) ) ),
						'category_id'         => (int) $row['category_id'],
					)
				);
			} else {
				$pid = (int) $row['product_id'];
				if ( $pid <= 0 ) {
					$pid = self::next_incomplete_product();
				}
				if ( $pid <= 0 ) {
					continue;
				}
				$job_id = WebinoCRM_AI_Queue::enqueue(
					'product_fill',
					'product',
					$pid,
					array(
						'calendar_id'   => (int) $row['id'],
						'focus_keyword' => $row['focus_keyword'],
					)
				);
			}

			if ( is_wp_error( $job_id ) ) {
				continue;
			}
			WebinoCRM_AI_Queue::schedule( (int) $job_id );

			$wpdb->update(
				$table,
				array(
					'status'     => 'queued',
					'job_id'     => (int) $job_id,
					'updated_at' => current_time( 'mysql', true ),
				),
				array( 'id' => (int) $row['id'] ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * @return int
	 */
	public static function next_incomplete_product() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return 0;
		}
		$ids = wc_get_products(
			array(
				'limit'  => 40,
				'status' => array( 'publish', 'draft', 'pending' ),
				'return' => 'ids',
				'orderby'=> 'date',
				'order'  => 'DESC',
			)
		);
		foreach ( (array) $ids as $id ) {
			$p = wc_get_product( (int) $id );
			if ( ! $p ) {
				continue;
			}
			$desc = trim( wp_strip_all_tags( $p->get_description() ) );
			$seo  = (string) get_post_meta( (int) $id, 'rank_math_focus_keyword', true );
			if ( '' === $desc || mb_strlen( $desc ) < 120 || '' === $seo ) {
				return (int) $id;
			}
		}
		return 0;
	}
}
