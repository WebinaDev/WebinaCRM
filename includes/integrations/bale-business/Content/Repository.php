<?php

namespace WebinaBaleBusiness\Content;

use WebinaBaleBusiness\Core\Plugin;

class Repository {

	/**
	 * @return array<int,array<string,string>>
	 */
	public static function get_business_types(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'wbb_business_type_tax',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			$out = array();
			foreach ( $terms as $term ) {
				if ( ! is_object( $term ) ) {
					continue;
				}
				$out[] = array(
					'key'   => sanitize_key( (string) $term->slug ),
					'title' => (string) $term->name,
				);
			}
			return $out;
		}
		return array(
			array( 'key' => 'store', 'title' => 'فروشگاهی' ),
			array( 'key' => 'service', 'title' => 'خدماتی' ),
			array( 'key' => 'education', 'title' => 'آموزشی' ),
		);
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	public static function get_business_activities_by_type( string $type_key ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'wbb_business_activity_tax',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		$out = array();
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! is_object( $term ) ) {
					continue;
				}
				$link_key = sanitize_key( (string) get_term_meta( (int) $term->term_id, 'wbb_parent_type_term_slug', true ) );
				if ( $link_key !== '' && $link_key !== sanitize_key( $type_key ) ) {
					continue;
				}
				$out[] = array(
					'key'   => sanitize_key( (string) $term->slug ),
					'title' => (string) $term->name,
				);
			}
			if ( ! empty( $out ) ) {
				return $out;
			}
		}
		return array(
			array( 'key' => 'general', 'title' => 'عمومی' ),
			array( 'key' => 'online-sales', 'title' => 'فروش آنلاین' ),
			array( 'key' => 'consulting', 'title' => 'مشاوره و خدمات' ),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_feature_docs(): array {
		$settings = Plugin::get_settings();
		$docs = isset( $settings['feature_docs'] ) && is_array( $settings['feature_docs'] ) ? $settings['feature_docs'] : array();
		if ( empty( $docs ) && isset( $settings['catalog_items'] ) && is_array( $settings['catalog_items'] ) ) {
			foreach ( $settings['catalog_items'] as $idx => $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$title = (string) ( $item['title'] ?? '' );
				if ( $title === '' ) {
					continue;
				}
				$docs[] = array(
					'key'             => \sanitize_key( (string) ( $item['key'] ?? ( 'legacy_' . $idx ) ) ),
					'title'           => $title,
					'short'           => (string) ( $item['description'] ?? '' ),
					'details'         => (string) ( $item['description'] ?? '' ),
					'steps'           => array(),
					'limitations'     => array(),
					'related_actions' => array(),
					'tags'            => array( 'legacy' ),
					'sort'            => \absint( $item['sort'] ?? ( $idx * 10 ) ),
				);
			}
		}
		usort(
			$docs,
			static function ( array $a, array $b ): int {
				return (int) ( $a['sort'] ?? 0 ) <=> (int) ( $b['sort'] ?? 0 );
			}
		);
		return $docs;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get_feature_doc( string $key ): ?array {
		$normalized = \sanitize_key( $key );
		foreach ( self::get_feature_docs() as $doc ) {
			if ( ! is_array( $doc ) ) {
				continue;
			}
			if ( \sanitize_key( (string) ( $doc['key'] ?? '' ) ) === $normalized ) {
				return $doc;
			}
		}
		return null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_support_items(): array {
		$s = Plugin::get_settings();
		$items = isset( $s['support_items'] ) && is_array( $s['support_items'] ) ? $s['support_items'] : array();
		usort(
			$items,
			static function ( array $a, array $b ): int {
				return (int) ( $a['sort'] ?? 0 ) <=> (int) ( $b['sort'] ?? 0 );
			}
		);
		return $items;
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	public static function get_faq_items(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'wbb_faq',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);
		if ( ! empty( $posts ) ) {
			$items = array();
			foreach ( $posts as $post ) {
				$items[] = array(
					'question' => $post->post_title,
					'answer'   => wp_strip_all_tags( $post->post_content ),
				);
			}
			return $items;
		}
		$settings = Plugin::get_settings();
		return is_array( $settings['faq_items'] ?? null ) ? $settings['faq_items'] : array();
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	public static function get_plan_items(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'wbb_plan',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);
		if ( ! empty( $posts ) ) {
			$items = array();
			foreach ( $posts as $post ) {
				$items[] = array(
					'title'       => $post->post_title,
					'description' => wp_strip_all_tags( $post->post_content ),
				);
			}
			return $items;
		}
		return array(
			array(
				'title'       => 'پلن شروع سریع 🚀',
				'description' => 'شروع سریع فروش در بله برای کسب وکارهای کوچک و متوسط.',
			),
			array(
				'title'       => 'پلن حرفه ای 💎',
				'description' => 'فلوهای کامل تر فروش و شخصی سازی پیشرفته برای رشد بیشتر.',
			),
		);
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	public static function get_sales_blocks(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'wbb_sales_block',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);

		if ( ! empty( $posts ) ) {
			$items = array();
			foreach ( $posts as $post ) {
				$items[] = array(
					'title'       => $post->post_title,
					'description' => wp_strip_all_tags( $post->post_content ),
					'cta'         => (string) get_post_meta( $post->ID, '_wbb_block_cta', true ),
				);
			}
			return $items;
		}
		return array(
			array(
				'title'       => 'فروش همیشه روشن در بله 🟣',
				'description' => 'دایرکت شما این بار در بله: پاسخگویی سریع، گرفتن لید و تبدیل مخاطب به مشتری.',
				'cta'         => 'درخواست ساخت ربات',
			),
		);
	}
}
