<?php
/**
 * GapGPT-oriented token cost estimates (Toman).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rates, per-entity token heuristics, and job cost.
 */
final class WebinoCRM_AI_Pricing {

	const PRICING_URL = 'https://gapgpt.app/platform-v2/pricing';
	const TOK_PER_WORD = 2.2;
	const VARIANCE     = 0.35;
	const DEFAULT_USD_TO_TOMAN = 100000;

	/**
	 * OpenAI-family USD per 1M tokens (GapGPT resells these; convert with usd_to_toman).
	 * Snapshot 2026-08-23 after the 30 Jul 2026 Terra/Luna cut.
	 *
	 * @return array<string,array{in:float,out:float}>
	 */
	public static function bundled_usd() {
		return array(
			'gpt-5.6-sol'       => array( 'in' => 5.0, 'out' => 30.0 ),
			'gpt-5.6-terra'     => array( 'in' => 2.0, 'out' => 12.0 ),
			'gpt-5.6-luna'      => array( 'in' => 0.2, 'out' => 1.2 ),
			'gpt-5.5'           => array( 'in' => 5.0, 'out' => 30.0 ),
			'gpt-5.4'           => array( 'in' => 1.25, 'out' => 10.0 ),
			'gpt-5.4-mini'      => array( 'in' => 0.25, 'out' => 2.0 ),
			'gpt-4o'            => array( 'in' => 2.5, 'out' => 10.0 ),
			'gpt-4o-mini'       => array( 'in' => 0.15, 'out' => 0.6 ),
			'gpt-4.1'           => array( 'in' => 2.0, 'out' => 8.0 ),
			'gpt-4.1-mini'      => array( 'in' => 0.4, 'out' => 1.6 ),
			'gpt-4.1-nano'      => array( 'in' => 0.1, 'out' => 0.4 ),
			'o3'                => array( 'in' => 2.0, 'out' => 8.0 ),
			'o4-mini'           => array( 'in' => 1.1, 'out' => 4.4 ),
			'gemini-2.0-flash'  => array( 'in' => 0.1, 'out' => 0.4 ),
			'gemini-2.5-flash'  => array( 'in' => 0.15, 'out' => 0.6 ),
			'gemini-2.5-pro'    => array( 'in' => 1.25, 'out' => 10.0 ),
			'claude-sonnet-4'   => array( 'in' => 3.0, 'out' => 15.0 ),
			'claude-3-5-sonnet' => array( 'in' => 3.0, 'out' => 15.0 ),
			'grok-2-latest'     => array( 'in' => 2.0, 'out' => 10.0 ),
			'grok-3'            => array( 'in' => 3.0, 'out' => 15.0 ),
		);
	}

	/**
	 * @param array<string,mixed>|null $settings Settings.
	 * @return int
	 */
	public static function usd_to_toman( $settings = null ) {
		if ( ! is_array( $settings ) ) {
			$settings = WebinoCRM_AI_Content_Settings::get();
		}
		$n = (int) ( $settings['usd_to_toman'] ?? self::DEFAULT_USD_TO_TOMAN );
		return max( 1000, min( 1000000, $n ) );
	}

	/**
	 * Model currently configured for a provider.
	 *
	 * @param string                   $provider Provider.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return string
	 */
	public static function model_for_provider( $provider, $settings = null ) {
		if ( ! is_array( $settings ) ) {
			$settings = WebinoCRM_AI_Content_Settings::get();
		}
		$map = array(
			'gapgpt' => 'gapgpt_model',
			'openai' => 'openai_model',
			'gemini' => 'gemini_model',
			'grok'   => 'grok_model',
		);
		$key = $map[ sanitize_key( $provider ) ] ?? '';
		if ( '' === $key ) {
			return '';
		}
		return sanitize_text_field( (string) ( $settings[ $key ] ?? '' ) );
	}

	/**
	 * Resolved Toman rates for one model (overrides, live catalog, bundled USD).
	 *
	 * @param string                   $model Model id.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return array{in_per_1m:float,out_per_1m:float,source:string}
	 */
	public static function rates_for_model( $model, $settings = null ) {
		if ( ! is_array( $settings ) ) {
			$settings = WebinoCRM_AI_Content_Settings::get();
		}
		$model = self::normalize_model_id( $model );
		if ( '' === $model ) {
			return array(
				'in_per_1m'  => 0.0,
				'out_per_1m' => 0.0,
				'source'     => 'none',
			);
		}

		$overrides = isset( $settings['gapgpt_rates'] ) && is_array( $settings['gapgpt_rates'] ) ? $settings['gapgpt_rates'] : array();
		if ( isset( $overrides[ $model ] ) && is_array( $overrides[ $model ] ) ) {
			$parsed = self::parse_rate_row( $overrides[ $model ] );
			if ( $parsed ) {
				$parsed['source'] = 'override';
				return $parsed;
			}
		}

		$live = self::live_rate_map();
		if ( isset( $live[ $model ] ) ) {
			$row = $live[ $model ];
			$row['source'] = 'models_api';
			return $row;
		}

		$usd  = self::bundled_usd();
		$fx   = self::usd_to_toman( $settings );
		$hit  = self::match_bundled_key( $model, $usd );
		if ( $hit && isset( $usd[ $hit ] ) ) {
			return array(
				'in_per_1m'  => (float) $usd[ $hit ]['in'] * $fx,
				'out_per_1m' => (float) $usd[ $hit ]['out'] * $fx,
				'source'     => 'bundled',
			);
		}

		if ( isset( $live[ $hit ] ) ) {
			$row = $live[ $hit ];
			$row['source'] = 'models_api';
			return $row;
		}

		return array(
			'in_per_1m'  => 0.0,
			'out_per_1m' => 0.0,
			'source'     => 'unknown',
		);
	}

	/**
	 * @param string $provider Provider.
	 * @param string $model Model.
	 * @param int    $tokens_in Input tokens.
	 * @param int    $tokens_out Output tokens.
	 * @return float
	 */
	public static function cost_toman( $provider, $model, $tokens_in, $tokens_out ) {
		$tokens_in  = max( 0, (int) $tokens_in );
		$tokens_out = max( 0, (int) $tokens_out );
		if ( $tokens_in < 1 && $tokens_out < 1 ) {
			return 0.0;
		}
		$model = self::normalize_model_id( $model );
		if ( '' === $model ) {
			$model = self::model_for_provider( $provider );
		}
		$rates = self::rates_for_model( $model );
		$cost  = ( $tokens_in / 1000000.0 ) * (float) $rates['in_per_1m']
			+ ( $tokens_out / 1000000.0 ) * (float) $rates['out_per_1m'];
		return round( max( 0, $cost ), 4 );
	}

	/**
	 * Full estimate payload for REST/UI.
	 *
	 * @param array<string,mixed> $draft Optional unsaved settings overlay.
	 * @return array<string,mixed>
	 */
	public static function estimate( $draft = array() ) {
		$settings = self::merge_draft( WebinoCRM_AI_Content_Settings::get(), is_array( $draft ) ? $draft : array() );
		$settings['coffee_module'] = WebinoCRM_AI_Content_Settings::coffee_module_active();
		$provider = sanitize_key( (string) ( $settings['default_provider'] ?? 'gapgpt' ) );
		$model    = self::model_for_provider( $provider, $settings );
		$current  = self::rates_for_model( $model, $settings );
		$entities = array();
		foreach ( array( 'product', 'product_brand', 'product_cat', 'blog', 'blog_cat' ) as $entity ) {
			$entities[ $entity ] = self::estimate_entity( $entity, $settings, $current );
		}

		$models = self::comparison_models( $settings, $entities );

		return array(
			'currency'          => 'IRT',
			'usd_to_toman'      => self::usd_to_toman( $settings ),
			'pricing_url'       => self::PRICING_URL,
			'rates_updated_at'  => (string) ( $settings['gapgpt_rates_updated_at'] ?? '2026-08-23' ),
			'current'           => array(
				'provider'   => $provider,
				'model'      => $model,
				'in_per_1m'  => $current['in_per_1m'],
				'out_per_1m' => $current['out_per_1m'],
				'source'     => $current['source'],
			),
			'entities'          => $entities,
			'models'            => $models,
			'batch'             => self::batch_counts( $entities ),
		);
	}

	/**
	 * @param array<string,mixed> $job Job row.
	 * @return array<string,mixed>
	 */
	public static function present_job( $job ) {
		if ( ! is_array( $job ) ) {
			return array();
		}
		$job['model']            = isset( $job['model'] ) ? (string) $job['model'] : '';
		$job['cost_toman']       = isset( $job['cost_toman'] ) ? (float) $job['cost_toman'] : 0.0;
		$job['cost_estimated']   = false;
		$job['tokens_in']        = (int) ( $job['tokens_in'] ?? 0 );
		$job['tokens_out']       = (int) ( $job['tokens_out'] ?? 0 );
		if ( $job['cost_toman'] <= 0 && ( $job['tokens_in'] + $job['tokens_out'] ) > 0 ) {
			$model = $job['model'];
			if ( '' === $model ) {
				$model = self::model_for_provider( (string) ( $job['provider'] ?? '' ) );
			}
			$job['cost_toman']     = self::cost_toman( (string) ( $job['provider'] ?? '' ), $model, $job['tokens_in'], $job['tokens_out'] );
			$job['cost_estimated'] = true;
			if ( '' === $job['model'] ) {
				$job['model'] = $model;
			}
		}
		$job['target_title'] = self::job_target_title( $job );
		return $job;
	}

	/**
	 * Human title for the job target (product, post, term, calendar topic).
	 *
	 * @param array<string,mixed> $job Job row.
	 * @return string
	 */
	public static function job_target_title( $job ) {
		$type = sanitize_key( (string) ( $job['target_type'] ?? '' ) );
		$id   = (int) ( $job['target_id'] ?? 0 );

		if ( 'product' === $type && $id > 0 ) {
			if ( function_exists( 'wc_get_product' ) ) {
				$p = wc_get_product( $id );
				if ( $p ) {
					$name = trim( (string) $p->get_name() );
					if ( '' !== $name ) {
						return $name;
					}
				}
			}
			$title = trim( (string) get_the_title( $id ) );
			if ( '' !== $title ) {
				return $title;
			}
		}

		if ( 'post' === $type && $id > 0 ) {
			$title = trim( (string) get_the_title( $id ) );
			if ( '' !== $title ) {
				return $title;
			}
		}

		if ( in_array( $type, array( 'product_cat', 'product_brand', 'category' ), true ) && $id > 0 ) {
			$term = get_term( $id );
			if ( $term && ! is_wp_error( $term ) && '' !== (string) $term->name ) {
				return (string) $term->name;
			}
		}

		if ( 'calendar' === $type ) {
			$payload = $job['payload'] ?? array();
			if ( is_string( $payload ) ) {
				$decoded = json_decode( $payload, true );
				$payload = is_array( $decoded ) ? $decoded : array();
			}
			if ( is_array( $payload ) ) {
				foreach ( array( 'topic', 'title', 'focus_keyword' ) as $key ) {
					$val = isset( $payload[ $key ] ) ? trim( sanitize_text_field( (string) $payload[ $key ] ) ) : '';
					if ( '' !== $val ) {
						return $val;
					}
				}
			}
		}

		if ( $id > 0 ) {
			return $type . ' #' . $id;
		}
		return $type;
	}

	/**
	 * @param array<string,mixed> $row Rate row.
	 * @return array{in_per_1m:float,out_per_1m:float}|null
	 */
	public static function parse_rate_row( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$in  = isset( $row['in_per_1m'] ) ? (float) $row['in_per_1m'] : ( isset( $row['in'] ) ? (float) $row['in'] : -1 );
		$out = isset( $row['out_per_1m'] ) ? (float) $row['out_per_1m'] : ( isset( $row['out'] ) ? (float) $row['out'] : -1 );
		if ( $in <= 0 || $out <= 0 ) {
			return null;
		}
		return array(
			'in_per_1m'  => $in,
			'out_per_1m' => $out,
		);
	}

	/**
	 * Extract Toman rates from a GapGPT /v1/models item.
	 *
	 * @param array<string,mixed> $item Model object.
	 * @param int                 $usd_to_toman FX.
	 * @return array{in_per_1m:float,out_per_1m:float}|null
	 */
	public static function rates_from_model_item( $item, $usd_to_toman ) {
		if ( ! is_array( $item ) ) {
			return null;
		}
		$in  = self::first_numeric(
			$item,
			array(
				'input_price_toman',
				'prompt_price_toman',
				'input_price',
				'prompt_price',
				'price_input',
				'in_per_1m',
			)
		);
		$out = self::first_numeric(
			$item,
			array(
				'output_price_toman',
				'completion_price_toman',
				'output_price',
				'completion_price',
				'price_output',
				'out_per_1m',
			)
		);
		if ( null === $in || null === $out ) {
			$pricing = isset( $item['pricing'] ) && is_array( $item['pricing'] ) ? $item['pricing'] : array();
			if ( $pricing ) {
				$in  = self::first_numeric( $pricing, array( 'input', 'prompt', 'in' ) );
				$out = self::first_numeric( $pricing, array( 'output', 'completion', 'out' ) );
			}
		}
		if ( null === $in || null === $out || $in <= 0 || $out <= 0 ) {
			return null;
		}
		$fx = max( 1000, (int) $usd_to_toman );
		// Values under 1000 look like USD per 1M tokens.
		if ( $in < 1000 && $out < 1000 ) {
			$in  = $in * $fx;
			$out = $out * $fx;
		}
		return array(
			'in_per_1m'  => (float) $in,
			'out_per_1m' => (float) $out,
		);
	}

	/**
	 * @param string $model Model id.
	 * @return string
	 */
	public static function normalize_model_id( $model ) {
		return sanitize_text_field( strtolower( trim( (string) $model ) ) );
	}

	/**
	 * @param array<string,mixed> $base Saved settings.
	 * @param array<string,mixed> $draft Overlay.
	 * @return array<string,mixed>
	 */
	private static function merge_draft( $base, $draft ) {
		foreach ( array( 'default_provider', 'gapgpt_model', 'openai_model', 'gemini_model', 'grok_model', 'language', 'prompt_system', 'usd_to_toman', 'do_coffee', 'do_product', 'gapgpt_rates_updated_at' ) as $key ) {
			if ( array_key_exists( $key, $draft ) ) {
				$base[ $key ] = $draft[ $key ];
			}
		}
		if ( isset( $draft['fields'] ) && is_array( $draft['fields'] ) && isset( $base['fields'] ) && is_array( $base['fields'] ) ) {
			$base['fields'] = WebinoCRM_AI_Content_Settings::merge_fields_public( $base['fields'], $draft['fields'] );
		}
		if ( isset( $draft['gapgpt_rates'] ) && is_array( $draft['gapgpt_rates'] ) ) {
			$base['gapgpt_rates'] = $draft['gapgpt_rates'];
		}
		foreach ( array_keys( WebinoCRM_AI_Content_Settings::default_prompts() ) as $pk ) {
			if ( array_key_exists( $pk, $draft ) ) {
				$base[ $pk ] = $draft[ $pk ];
			}
		}
		return $base;
	}

	/**
	 * @param string              $entity Entity.
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $rates Current model rates.
	 * @return array<string,mixed>
	 */
	private static function estimate_entity( $entity, $settings, $rates ) {
		$from_jobs = self::median_job_tokens( $entity );
		if ( $from_jobs ) {
			$tokens_in  = $from_jobs['in'];
			$tokens_out = $from_jobs['out'];
			$source     = 'jobs';
		} else {
			$tokens_in  = self::heuristic_input_tokens( $entity, $settings );
			$tokens_out = self::heuristic_output_tokens( $entity, $settings );
			$source     = 'heuristic';
		}

		$lo_in  = (int) max( 1, round( $tokens_in * ( 1 - self::VARIANCE ) ) );
		$hi_in  = (int) max( $lo_in, round( $tokens_in * ( 1 + self::VARIANCE ) ) );
		$lo_out = (int) max( 1, round( $tokens_out * ( 1 - self::VARIANCE ) ) );
		$hi_out = (int) max( $lo_out, round( $tokens_out * ( 1 + self::VARIANCE ) ) );
		$mid_in = (int) round( $tokens_in );
		$mid_out = (int) round( $tokens_out );

		$cost = static function ( $tin, $tout ) use ( $rates ) {
			return round( ( $tin / 1000000.0 ) * (float) $rates['in_per_1m'] + ( $tout / 1000000.0 ) * (float) $rates['out_per_1m'], 2 );
		};

		return array(
			'source'     => $source,
			'tokens_in'  => array(
				'lo'  => $lo_in,
				'mid' => $mid_in,
				'hi'  => $hi_in,
			),
			'tokens_out' => array(
				'lo'  => $lo_out,
				'mid' => $mid_out,
				'hi'  => $hi_out,
			),
			'cost_toman' => array(
				'lo'  => $cost( $lo_in, $lo_out ),
				'mid' => $cost( $mid_in, $mid_out ),
				'hi'  => $cost( $hi_in, $hi_out ),
			),
		);
	}

	/**
	 * @param string              $entity Entity.
	 * @param array<string,mixed> $settings Settings.
	 * @return float
	 */
	private static function heuristic_output_tokens( $entity, $settings ) {
		$words  = self::enabled_field_words( $entity, $settings );
		if ( 'product' === $entity && ! empty( $settings['do_coffee'] ) && ! empty( $settings['coffee_module'] ) ) {
			$words += 80;
		}
		if ( $words < 40 ) {
			$words = 80;
		}
		return $words * self::TOK_PER_WORD;
	}

	/**
	 * @param string              $entity Entity.
	 * @param array<string,mixed> $settings Settings.
	 * @return float
	 */
	private static function heuristic_input_tokens( $entity, $settings ) {
		$sys = WebinoCRM_AI_Content_Settings::interpolate_prompt( (string) ( $settings['prompt_system'] ?? '' ), $settings );
		$pk  = 'prompt_' . $entity;
		$ent = (string) ( $settings[ $pk ] ?? '' );
		$chars = strlen( $sys ) + strlen( $ent ) + 1800;
		if ( 'product' === $entity ) {
			$chars += 2800;
		} elseif ( 'blog' === $entity ) {
			$chars += 900;
		} else {
			$chars += 500;
		}
		return max( 800, $chars / 3.4 );
	}

	/**
	 * @param string              $entity Entity.
	 * @param array<string,mixed> $settings Settings.
	 * @return int
	 */
	private static function enabled_field_words( $entity, $settings ) {
		$rows = isset( $settings['fields'][ $entity ] ) && is_array( $settings['fields'][ $entity ] ) ? $settings['fields'][ $entity ] : array();
		$words = 0;
		foreach ( $rows as $field => $spec ) {
			if ( ! is_array( $spec ) || empty( $spec['enabled'] ) ) {
				continue;
			}
			$len  = max( 0, (int) ( $spec['length'] ?? 0 ) );
			$unit = sanitize_key( (string) ( $spec['unit'] ?? 'words' ) );
			if ( $len > 0 ) {
				if ( 'paragraphs' === $unit ) {
					$words += $len * 80;
				} elseif ( 'count' === $unit ) {
					$per = ( 'faqs' === $field ) ? 140 : ( ( 'custom_labels' === $field ) ? 6 : 40 );
					$words += $len * $per;
				} else {
					$words += $len;
				}
				continue;
			}
			$fixed = array(
				'seo'         => 70,
				'name'        => 12,
				'slug'        => 8,
				'title'       => 12,
				'english_name'=> 10,
				'tags'        => 18,
				'attributes'  => 90,
			);
			if ( isset( $fixed[ $field ] ) ) {
				$words += $fixed[ $field ];
			} else {
				$words += 12;
			}
		}
		return $words;
	}

	/**
	 * @param string $entity Entity.
	 * @return array{in:float,out:float}|null
	 */
	private static function median_job_tokens( $entity ) {
		global $wpdb;
		if ( ! class_exists( 'WebinoCRM_AI_Content_Db', false ) || ! WebinoCRM_AI_Content_Db::jobs_table_exists() ) {
			return null;
		}
		$map = array(
			'product'       => array( 'product_fill', 'product' ),
			'blog'          => array( 'blog_write', '' ),
			'product_cat'   => array( 'term_fill', 'product_cat' ),
			'product_brand' => array( 'term_fill', 'product_brand' ),
			'blog_cat'      => array( 'term_fill', 'category' ),
		);
		if ( ! isset( $map[ $entity ] ) ) {
			return null;
		}
		$table = WebinoCRM_AI_Content_Db::table( 'jobs' );
		$type  = $map[ $entity ][0];
		$target = $map[ $entity ][1];
		if ( '' !== $target ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tokens_in, tokens_out FROM {$table} WHERE status = %s AND job_type = %s AND target_type = %s AND tokens_out > 0 ORDER BY id DESC LIMIT 30",
					'done',
					$type,
					$target
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tokens_in, tokens_out FROM {$table} WHERE status = %s AND job_type = %s AND tokens_out > 0 ORDER BY id DESC LIMIT 30",
					'done',
					$type
				),
				ARRAY_A
			);
		}
		if ( ! is_array( $rows ) || count( $rows ) < 3 ) {
			return null;
		}
		$ins  = array();
		$outs = array();
		foreach ( $rows as $row ) {
			$ins[]  = (int) $row['tokens_in'];
			$outs[] = (int) $row['tokens_out'];
		}
		return array(
			'in'  => self::median( $ins ),
			'out' => self::median( $outs ),
		);
	}

	/**
	 * @param list<int> $nums Numbers.
	 * @return float
	 */
	private static function median( $nums ) {
		sort( $nums, SORT_NUMERIC );
		$n = count( $nums );
		if ( $n < 1 ) {
			return 0.0;
		}
		$mid = (int) floor( $n / 2 );
		if ( 0 === $n % 2 ) {
			return ( $nums[ $mid - 1 ] + $nums[ $mid ] ) / 2.0;
		}
		return (float) $nums[ $mid ];
	}

	/**
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $entities Entity estimates.
	 * @return list<array<string,mixed>>
	 */
	private static function comparison_models( $settings, $entities ) {
		$ids = array( 'gpt-5.6-luna', 'gpt-5.6-terra', 'gpt-5.6-sol' );
		$live = array();
		if ( class_exists( 'WebinoCRM_AI_Providers', false ) ) {
			$list = WebinoCRM_AI_Providers::list_gapgpt_models( false );
			if ( ! is_wp_error( $list ) && is_array( $list ) ) {
				$live = $list;
				foreach ( $list as $row ) {
					$id = self::normalize_model_id( $row['id'] ?? '' );
					if ( '' !== $id && ! in_array( $id, $ids, true ) ) {
						$ids[] = $id;
					}
				}
			}
		}
		$selected = self::normalize_model_id( (string) ( $settings['gapgpt_model'] ?? '' ) );
		if ( '' !== $selected && ! in_array( $selected, $ids, true ) ) {
			array_unshift( $ids, $selected );
		}

		$owned = array();
		foreach ( $live as $row ) {
			$owned[ self::normalize_model_id( $row['id'] ?? '' ) ] = (string) ( $row['owned_by'] ?? '' );
		}

		$out = array();
		foreach ( $ids as $id ) {
			$rates = self::rates_for_model( $id, $settings );
			$costs = array();
			foreach ( $entities as $ek => $est ) {
				$tin  = (int) ( $est['tokens_in']['mid'] ?? 0 );
				$tout = (int) ( $est['tokens_out']['mid'] ?? 0 );
				$costs[ $ek ] = round( ( $tin / 1000000.0 ) * (float) $rates['in_per_1m'] + ( $tout / 1000000.0 ) * (float) $rates['out_per_1m'], 2 );
			}
			$out[] = array(
				'id'         => $id,
				'owned_by'   => $owned[ $id ] ?? '',
				'in_per_1m'  => $rates['in_per_1m'],
				'out_per_1m' => $rates['out_per_1m'],
				'source'     => $rates['source'],
				'selected'   => $id === $selected,
				'costs'      => $costs,
			);
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $entities Entity estimates.
	 * @return array<string,mixed>
	 */
	private static function batch_counts( $entities ) {
		$product_n = 0;
		if ( class_exists( 'WebinoCRM_AI_Content', false ) ) {
			$inc       = WebinoCRM_AI_Content::incomplete_products( 80 );
			$product_n = (int) ( $inc['total'] ?? 0 );
		}
		$out = array(
			'product' => array(
				'count'      => $product_n,
				'cost_toman' => $product_n * (float) ( $entities['product']['cost_toman']['mid'] ?? 0 ),
			),
		);
		foreach ( array( 'product_cat' => 'product_cat', 'product_brand' => 'product_brand', 'blog_cat' => 'category' ) as $entity => $tax ) {
			$n = self::incomplete_term_count( $tax );
			$out[ $entity ] = array(
				'count'      => $n,
				'cost_toman' => $n * (float) ( $entities[ $entity ]['cost_toman']['mid'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * @param string $taxonomy Taxonomy.
	 * @return int
	 */
	private static function incomplete_term_count( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 80,
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return 0;
		}
		$n = 0;
		foreach ( $terms as $t ) {
			$desc = trim( wp_strip_all_tags( (string) $t->description ) );
			$seo  = (string) get_term_meta( (int) $t->term_id, 'rank_math_focus_keyword', true );
			if ( mb_strlen( $desc ) < 80 || '' === $seo ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Cached live rates parsed from GapGPT models list.
	 *
	 * @return array<string,array{in_per_1m:float,out_per_1m:float}>
	 */
	private static function live_rate_map() {
		$cached = get_transient( 'webinocrm_ai_gapgpt_models' );
		if ( ! is_array( $cached ) ) {
			return array();
		}
		$fx  = self::usd_to_toman();
		$out = array();
		foreach ( $cached as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = self::normalize_model_id( $row['id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$parsed = self::rates_from_model_item( $row, $fx );
			if ( $parsed ) {
				$out[ $id ] = $parsed;
			}
		}
		return $out;
	}

	/**
	 * @param string                         $model Model.
	 * @param array<string,array{in:float,out:float}> $usd Bundled.
	 * @return string
	 */
	private static function match_bundled_key( $model, $usd ) {
		if ( isset( $usd[ $model ] ) ) {
			return $model;
		}
		$best = '';
		$len  = 0;
		foreach ( array_keys( $usd ) as $key ) {
			if ( 0 === strpos( $model, $key ) && strlen( $key ) > $len ) {
				$best = $key;
				$len  = strlen( $key );
			}
		}
		if ( '' !== $best ) {
			return $best;
		}
		foreach ( array_keys( $usd ) as $key ) {
			if ( false !== strpos( $model, $key ) && strlen( $key ) > $len ) {
				$best = $key;
				$len  = strlen( $key );
			}
		}
		return $best;
	}

	/**
	 * @param array<string,mixed> $item Item.
	 * @param list<string>        $keys Keys.
	 * @return float|null
	 */
	private static function first_numeric( $item, $keys ) {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $item ) ) {
				continue;
			}
			$val = $item[ $key ];
			if ( is_array( $val ) ) {
				continue;
			}
			if ( is_numeric( $val ) ) {
				return (float) $val;
			}
		}
		return null;
	}
}
