<?php

namespace WebinaBaleBusiness\Admin;

class Menu {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register' ) );
	}

	public static function register(): void {
		add_menu_page(
			'ربات کسب و کار وبینا',
			'ربات کسب و کار وبینا',
			'manage_options',
			'wbb-dashboard',
			array( DashboardPage::class, 'render' ),
			'dashicons-store',
			56
		);

		add_submenu_page(
			'wbb-dashboard',
			'تنظیمات',
			'تنظیمات',
			'manage_options',
			'wbb-settings',
			array( SettingsPage::class, 'render' )
		);

		add_submenu_page(
			'wbb-dashboard',
			'عیب یابی و لاگ',
			'عیب یابی و لاگ',
			'manage_options',
			'wbb-diagnostics',
			array( DiagnosticsPage::class, 'render' )
		);
	}
}
