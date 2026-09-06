<?php
/**
 * Rahn-percent settings stored in webinocrm_settings['rahn_percent'].
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defaults, seed catalog, get/save for رهن‌درصد module.
 */
class WebinoCRM_Rahn_Settings {

	const SETTINGS_KEY = 'rahn_percent';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'T'              => 6,
			'm'              => 0.60,
			'k'              => 0.70,
			'p_min'          => 0.05,
			'p_max'          => 0.25,
			'p_default'      => 0.10,
			's_hat_default'  => 100000000,
			'clause_template'=> 'هر ماه {F} تومان ثابت + {p} درصد از فروش کل',
			'review'         => array(
				'enabled'           => false,
				'deviation_percent' => 25,
				'consecutive_months'=> 3,
			),
			'sales_definition' => array(
				'G' => array( 'enabled' => true, 'label' => 'فروش ثبت‌شده سایت' ),
				'R' => array( 'enabled' => true, 'label' => 'لغو و مرجوعی قطعی' ),
				'D' => array( 'enabled' => true, 'label' => 'کارمزد درگاه / پلتفرم / تخفیف کانال' ),
				'X' => array( 'enabled' => true, 'label' => 'سفارش تست و فروش خارج از اسکوپ' ),
			),
			'catalog' => self::seed_catalog(),
		);
	}

	/**
	 * Seed catalog rows (amounts empty / zero — fill in settings).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function seed_catalog() {
		$rows = array(
			array( 'name' => 'بسته‌بندی', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'category' => 'راه‌اندازی', 'default_selected' => true ),
			array( 'name' => 'طراحی سایت', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'category' => 'راه‌اندازی', 'default_selected' => true ),
			array( 'name' => 'اینماد', 'billing' => 'custom', 'period_months' => 24, 'renewable' => true, 'category' => 'مجوز', 'default_selected' => true ),
			array( 'name' => 'سرور', 'billing' => 'yearly', 'period_months' => 12, 'renewable' => true, 'category' => 'زیرساخت', 'default_selected' => true ),
			array( 'name' => 'شارژ ترب', 'billing' => 'monthly', 'period_months' => 1, 'renewable' => true, 'category' => 'بازاریابی', 'default_selected' => true ),
			array( 'name' => 'دامنه', 'billing' => 'yearly', 'period_months' => 12, 'renewable' => true, 'category' => 'زیرساخت', 'default_selected' => false ),
			array( 'name' => 'SSL', 'billing' => 'yearly', 'period_months' => 12, 'renewable' => true, 'category' => 'زیرساخت', 'default_selected' => false ),
			array( 'name' => 'درگاه پرداخت', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'category' => 'راه‌اندازی', 'default_selected' => false ),
			array( 'name' => 'پشتیبانی', 'billing' => 'monthly', 'period_months' => 1, 'renewable' => true, 'category' => 'عملیات', 'default_selected' => false ),
		);

		$out = array();
		$i   = 0;
		foreach ( $rows as $row ) {
			++$i;
			$out[] = array(
				'id'               => 'svc_' . $i,
				'name'             => $row['name'],
				'billing'          => $row['billing'],
				'period_months'    => (int) $row['period_months'],
				'renewable'        => ! empty( $row['renewable'] ),
				'amount'           => 0,
				'active'           => true,
				'default_selected' => ! empty( $row['default_selected'] ),
				'category'         => $row['category'],
				'description'      => '',
				'sort_order'       => $i,
			);
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get() {
		$all = WebinoCRM_Settings_Handler::get_all_settings();
		$raw = isset( $all[ self::SETTINGS_KEY ] ) && is_array( $all[ self::SETTINGS_KEY ] )
			? $all[ self::SETTINGS_KEY ]
			: array();
		$merged = self::merge_defaults( $raw );
		return $merged;
	}

	/**
	 * @param array<string,mixed> $payload Partial settings.
	 * @return array<string,mixed> Saved settings.
	 */
	public static function save( array $payload ) {
		$current = self::get();
		$next    = self::sanitize( array_merge( $current, $payload ) );

		$all = WebinoCRM_Settings_Handler::get_all_settings();
		$all[ self::SETTINGS_KEY ] = $next;
		WebinoCRM_Settings_Handler::update_settings( $all );

		return $next;
	}

	/**
	 * @param array<string,mixed> $raw Raw.
	 * @return array<string,mixed>
	 */
	private static function merge_defaults( array $raw ) {
		$defaults = self::defaults();
		$out      = array_merge( $defaults, $raw );

		if ( empty( $out['catalog'] ) || ! is_array( $out['catalog'] ) ) {
			$out['catalog'] = $defaults['catalog'];
		}
		if ( empty( $out['sales_definition'] ) || ! is_array( $out['sales_definition'] ) ) {
			$out['sales_definition'] = $defaults['sales_definition'];
		} else {
			$out['sales_definition'] = array_merge( $defaults['sales_definition'], $out['sales_definition'] );
		}
		if ( empty( $out['review'] ) || ! is_array( $out['review'] ) ) {
			$out['review'] = $defaults['review'];
		} else {
			$out['review'] = array_merge( $defaults['review'], $out['review'] );
		}

		return self::sanitize( $out );
	}

	/**
	 * @param array<string,mixed> $data Data.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $data ) {
		$defaults = self::defaults();

		$catalog = array();
		if ( ! empty( $data['catalog'] ) && is_array( $data['catalog'] ) ) {
			$order = 0;
			foreach ( $data['catalog'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				++$order;
				$id = sanitize_key( (string) ( $row['id'] ?? '' ) );
				if ( '' === $id ) {
					$id = 'svc_' . wp_generate_password( 8, false, false );
				}
				$billing = sanitize_key( (string) ( $row['billing'] ?? 'once' ) );
				if ( ! in_array( $billing, array( 'once', 'monthly', 'yearly', 'custom' ), true ) ) {
					$billing = 'once';
				}
				$catalog[] = array(
					'id'               => $id,
					'name'             => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
					'billing'          => $billing,
					'period_months'    => max( 1, (int) ( $row['period_months'] ?? 1 ) ),
					'renewable'        => ! empty( $row['renewable'] ),
					'amount'           => max( 0.0, (float) ( $row['amount'] ?? 0 ) ),
					'active'           => ! isset( $row['active'] ) || ! empty( $row['active'] ),
					'default_selected' => ! empty( $row['default_selected'] ),
					'category'         => sanitize_text_field( (string) ( $row['category'] ?? '' ) ),
					'description'      => sanitize_textarea_field( (string) ( $row['description'] ?? '' ) ),
					'sort_order'       => isset( $row['sort_order'] ) ? (int) $row['sort_order'] : $order,
				);
			}
			usort(
				$catalog,
				static function ( $a, $b ) {
					return (int) $a['sort_order'] <=> (int) $b['sort_order'];
				}
			);
		}

		$sales_def = $defaults['sales_definition'];
		if ( ! empty( $data['sales_definition'] ) && is_array( $data['sales_definition'] ) ) {
			foreach ( array( 'G', 'R', 'D', 'X' ) as $key ) {
				if ( empty( $data['sales_definition'][ $key ] ) || ! is_array( $data['sales_definition'][ $key ] ) ) {
					continue;
				}
				$sales_def[ $key ] = array(
					'enabled' => ! empty( $data['sales_definition'][ $key ]['enabled'] ),
					'label'   => sanitize_text_field( (string) ( $data['sales_definition'][ $key ]['label'] ?? $sales_def[ $key ]['label'] ) ),
				);
			}
		}

		$review = $defaults['review'];
		if ( ! empty( $data['review'] ) && is_array( $data['review'] ) ) {
			$review = array(
				'enabled'            => ! empty( $data['review']['enabled'] ),
				'deviation_percent'  => max( 0.0, (float) ( $data['review']['deviation_percent'] ?? 25 ) ),
				'consecutive_months' => max( 1, (int) ( $data['review']['consecutive_months'] ?? 3 ) ),
			);
		}

		$clause = isset( $data['clause_template'] )
			? sanitize_textarea_field( (string) $data['clause_template'] )
			: $defaults['clause_template'];
		if ( '' === trim( $clause ) ) {
			$clause = $defaults['clause_template'];
		}

		$p_min = max( 0.0, min( 1.0, (float) ( $data['p_min'] ?? $defaults['p_min'] ) ) );
		$p_max = max( $p_min, min( 1.0, (float) ( $data['p_max'] ?? $defaults['p_max'] ) ) );
		$p_def = max( $p_min, min( $p_max, (float) ( $data['p_default'] ?? $defaults['p_default'] ) ) );

		return array(
			'T'               => max( 1, (int) ( $data['T'] ?? $defaults['T'] ) ),
			'm'               => max( 0.0, (float) ( $data['m'] ?? $defaults['m'] ) ),
			'k'               => max( 0.0, min( 1.0, (float) ( $data['k'] ?? $defaults['k'] ) ) ),
			'p_min'           => $p_min,
			'p_max'           => $p_max,
			'p_default'       => $p_def,
			's_hat_default'   => max( 0.0, (float) ( $data['s_hat_default'] ?? $defaults['s_hat_default'] ) ),
			'clause_template' => $clause,
			'review'          => $review,
			'sales_definition'=> $sales_def,
			'catalog'         => $catalog ? $catalog : $defaults['catalog'],
		);
	}

	/**
	 * Active catalog items keyed by id.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function catalog_map() {
		$settings = self::get();
		$map      = array();
		foreach ( (array) $settings['catalog'] as $row ) {
			if ( empty( $row['active'] ) ) {
				continue;
			}
			$map[ (string) $row['id'] ] = $row;
		}
		return $map;
	}

	/**
	 * Resolve selected item ids to full catalog rows (snapshot-friendly).
	 *
	 * @param array<int,string>                $selected_ids Selected ids.
	 * @param array<int,array<string,mixed>>|null $override Optional override rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function resolve_items( array $selected_ids, $override = null ) {
		$map = array();
		if ( is_array( $override ) ) {
			foreach ( $override as $row ) {
				if ( is_array( $row ) && ! empty( $row['id'] ) ) {
					$map[ (string) $row['id'] ] = $row;
				}
			}
		} else {
			$map = self::catalog_map();
			// Also include inactive for historical quotes.
			foreach ( (array) self::get()['catalog'] as $row ) {
				$map[ (string) $row['id'] ] = $row;
			}
		}

		$items = array();
		foreach ( $selected_ids as $id ) {
			$id = (string) $id;
			if ( isset( $map[ $id ] ) ) {
				$items[] = $map[ $id ];
			}
		}
		return $items;
	}
}
