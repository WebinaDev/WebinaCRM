<?php

namespace WebinaBaleBusiness\Admin;

class ContentTypes {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register(): void {
		register_post_type(
			'wbb_plan',
			array(
				'label'        => 'پلن های فروش ربات',
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'tools.php',
				'supports'     => array( 'title', 'editor', 'custom-fields' ),
			)
		);

		register_post_type(
			'wbb_faq',
			array(
				'label'        => 'FAQ ربات',
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'tools.php',
				'supports'     => array( 'title', 'editor', 'custom-fields' ),
			)
		);

		register_post_type(
			'wbb_sales_block',
			array(
				'label'        => 'بلاک های فروش ربات',
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'tools.php',
				'supports'     => array( 'title', 'editor', 'page-attributes', 'custom-fields' ),
			)
		);

		register_post_type(
			'wbb_catalog_stub',
			array(
				'label'        => 'دسته بندی کسب و کار',
				'public'       => false,
				'show_ui'      => false,
				'show_in_menu' => false,
				'supports'     => array( 'title' ),
			)
		);

		register_post_type(
			'wbb_business_submission',
			array(
				'label'        => 'درخواست های کسب و کار',
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'tools.php',
				'supports'     => array( 'title', 'custom-fields' ),
			)
		);

		register_taxonomy(
			'wbb_business_type_tax',
			array( 'wbb_catalog_stub' ),
			array(
				'labels'       => array(
					'name'          => 'انواع کسب و کار',
					'singular_name' => 'نوع کسب و کار',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'tools.php',
				'hierarchical' => true,
			)
		);

		register_taxonomy(
			'wbb_business_activity_tax',
			array( 'wbb_catalog_stub' ),
			array(
				'labels'       => array(
					'name'          => 'حوزه های فعالیت',
					'singular_name' => 'حوزه فعالیت',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'tools.php',
				'hierarchical' => true,
			)
		);
	}
}
