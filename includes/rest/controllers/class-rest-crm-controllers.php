<?php
/** CRM REST controllers (service-backed). */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WebinoCRM_REST_Appointments_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/appointments', array( 'WebinoCRM_Appointments_Service', 'list' ), 'appointments' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/appointments/(?P<id>\d+)', array( 'WebinoCRM_Appointments_Service', 'get' ), 'appointments' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/appointments/calendar', array( 'WebinoCRM_Appointments_Service', 'calendar' ), 'appointments' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/appointments', array( 'WebinoCRM_Appointments_Service', 'save' ), 'appointments' );
		WebinoCRM_REST_Registry::register_service_route( 'PATCH', '/appointments/(?P<id>\d+)/datetime', array( 'WebinoCRM_Appointments_Service', 'reschedule' ), 'appointments' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/appointments/(?P<id>\d+)', array( 'WebinoCRM_Appointments_Service', 'delete' ), 'appointments' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/appointments/meta/customers', array( 'WebinoCRM_Appointments_Service', 'customers' ), 'appointments' );
	}
}

class WebinoCRM_REST_Campaigns_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/campaigns', array( 'WebinoCRM_Campaigns_Service', 'list' ), 'campaigns' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/campaigns', array( 'WebinoCRM_Campaigns_Service', 'save' ), 'campaigns' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/campaigns/(?P<id>\d+)', array( 'WebinoCRM_Campaigns_Service', 'delete' ), 'campaigns' );
	}
}

class WebinoCRM_REST_Consultations_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/consultations', array( 'WebinoCRM_Consultations_Service', 'list' ), 'consultations' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/consultations', array( 'WebinoCRM_Consultations_Service', 'save' ), 'consultations' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/consultations/(?P<id>\d+)/convert-project', array( 'WebinoCRM_Consultations_Service', 'convert_project' ), 'consultations' );
	}
}

class WebinoCRM_REST_Contracts_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/contracts', array( 'WebinoCRM_Contract_Service', 'list' ), 'contracts' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/contracts/(?P<id>\d+)', array( 'WebinoCRM_Contract_Service', 'get' ), 'contracts' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/contracts', array( 'WebinoCRM_Contract_Service', 'create_or_update' ), 'contracts' );
		WebinoCRM_REST_Registry::register_service_route( 'PATCH', '/contracts/(?P<id>\d+)', array( 'WebinoCRM_Contract_Service', 'create_or_update' ), 'contracts' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/contracts/(?P<id>\d+)', array( 'WebinoCRM_Contract_Service', 'delete' ), 'contracts' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/contracts/(?P<id>\d+)/cancel', array( 'WebinoCRM_Contract_Service', 'cancel' ), 'contracts' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/contracts/(?P<id>\d+)/projects', array( 'WebinoCRM_Contract_Service', 'add_project' ), 'contracts' );
	}
}

class WebinoCRM_REST_Customers_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/customers', array( 'WebinoCRM_Customers_Service', 'list' ), 'customers' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/customers', array( 'WebinoCRM_Customers_Service', 'save' ), 'customers' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/customers/(?P<id>\d+)', array( 'WebinoCRM_Customers_Service', 'delete' ), 'customers' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/customers/org-positions', array( 'WebinoCRM_Customers_Service', 'org_position_save' ), 'customers' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/customers/org-positions/(?P<id>\d+)', array( 'WebinoCRM_Customers_Service', 'org_position_delete' ), 'customers' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/customers/sms', array( 'WebinoCRM_Customers_Service', 'sms' ), 'customers' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/customers/bale', array( 'WebinoCRM_Customers_Service', 'bale' ), 'customers' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/customers/bale/bulk', array( 'WebinoCRM_Customers_Service', 'bale_bulk' ), 'customers' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/customers/(?P<customer_id>\d+)/projects', array( 'WebinoCRM_Invoices_Service', 'customer_projects' ), 'invoices' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/customers/(?P<id>\d+)/360', array( 'WebinoCRM_Customers_Service', 'customer_360' ), 'customers' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/leads/export', array( 'WebinoCRM_Import_Export_Service', 'export_leads' ), 'leads' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/leads/import', array( 'WebinoCRM_Import_Export_Service', 'import_leads' ), 'leads' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/customers/export', array( 'WebinoCRM_Import_Export_Service', 'export_customers' ), 'customers' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/customers/import', array( 'WebinoCRM_Import_Export_Service', 'import_customers' ), 'customers' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/tickets/meta/canned-responses', array( 'WebinoCRM_Tickets_Service', 'canned_responses' ), 'tickets' );
	}
}

class WebinoCRM_REST_Dashboard_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/dashboard/summary', array( 'WebinoCRM_Dashboard_Service', 'summary' ), '' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/dashboard/full', array( 'WebinoCRM_Dashboard_Service', 'full' ), '' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/dashboard/team-stats', array( 'WebinoCRM_Dashboard_Service', 'team_stats' ), '' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/dashboard/client-stats', array( 'WebinoCRM_Dashboard_Service', 'client_stats' ), '' );
	}
}

class WebinoCRM_REST_Invoices_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/invoices', array( 'WebinoCRM_Invoices_Service', 'list' ), 'invoices' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/invoices/(?P<id>\d+)', array( 'WebinoCRM_Invoices_Service', 'get' ), 'invoices' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/invoices', array( 'WebinoCRM_Invoices_Service', 'save' ), 'invoices' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/invoices/(?P<id>\d+)/pdf', array( 'WebinoCRM_Invoices_Service', 'generate_pdf' ), 'invoices' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/invoices/(?P<id>\d+)/email', array( 'WebinoCRM_Invoices_Service', 'send_email' ), 'invoices' );
	}
}

class WebinoCRM_REST_Leads_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/leads', array( 'WebinoCRM_Leads_Service', 'list' ), 'leads' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/leads', array( 'WebinoCRM_Leads_Service', 'create' ), 'leads' );
		WebinoCRM_REST_Registry::register_service_route( 'PATCH', '/leads/(?P<id>\d+)', array( 'WebinoCRM_Leads_Service', 'update' ), 'leads' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/leads/(?P<id>\d+)', array( 'WebinoCRM_Leads_Service', 'delete' ), 'leads' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/leads/(?P<id>\d+)/assign', array( 'WebinoCRM_Leads_Service', 'assign' ), 'leads' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/leads/(?P<id>\d+)/status', array( 'WebinoCRM_Leads_Service', 'change_status' ), 'leads' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/leads/meta/assignees', array( 'WebinoCRM_Leads_Service', 'assignees' ), 'leads' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/leads/(?P<id>\d+)/for-contract', array( 'WebinoCRM_Leads_Service', 'for_contract' ), 'leads' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/leads/(?P<id>\d+)/create-customer', array( 'WebinoCRM_Leads_Service', 'create_customer' ), 'leads' );
	}
}

class WebinoCRM_REST_Licenses_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/licenses', array( 'WebinoCRM_Licenses_Service', 'list' ), 'licenses' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/licenses', array( 'WebinoCRM_Licenses_Service', 'create' ), 'licenses' );
		WebinoCRM_REST_Registry::register_service_route( 'PATCH', '/licenses/(?P<id>\d+)', array( 'WebinoCRM_Licenses_Service', 'update' ), 'licenses' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/licenses/(?P<id>\d+)/renew', array( 'WebinoCRM_Licenses_Service', 'renew' ), 'licenses' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/licenses/(?P<id>\d+)/cancel', array( 'WebinoCRM_Licenses_Service', 'cancel' ), 'licenses' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/licenses/(?P<id>\d+)', array( 'WebinoCRM_Licenses_Service', 'delete' ), 'licenses' );
	}
}

class WebinoCRM_REST_Logs_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/logs', array( 'WebinoCRM_Logs_Service', 'list' ), 'logs' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/logs/system', array( 'WebinoCRM_Logs_Service', 'system' ), 'logs' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/logs/system', array( 'WebinoCRM_Logs_Service', 'delete_system' ), 'logs' );
	}
}

class WebinoCRM_REST_Marketplace_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/marketplace/categories', array( 'WebinoCRM_Marketplace_Service', 'categories' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/marketplace/categories', array( 'WebinoCRM_Marketplace_Service', 'category_save' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/marketplace/categories/(?P<id>\d+)', array( 'WebinoCRM_Marketplace_Service', 'category_delete' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/marketplace/modules', array( 'WebinoCRM_Marketplace_Service', 'modules' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/marketplace/modules/(?P<id>\d+)', array( 'WebinoCRM_Marketplace_Service', 'module_get' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/marketplace/modules', array( 'WebinoCRM_Marketplace_Service', 'module_save' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/marketplace/modules/(?P<id>\d+)', array( 'WebinoCRM_Marketplace_Service', 'module_delete' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/marketplace/modules/(?P<id>\d+)/repo', array( 'WebinoCRM_Marketplace_Service', 'module_create_repo' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/marketplace/modules/(?P<id>\d+)/repo/sync', array( 'WebinoCRM_Marketplace_Service', 'module_repo_sync' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'PATCH', '/marketplace/modules/(?P<id>\d+)/repo', array( 'WebinoCRM_Marketplace_Service', 'module_repo_visibility' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/marketplace/modules/(?P<id>\d+)/readme/sync', array( 'WebinoCRM_Marketplace_Service', 'module_readme_sync' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/marketplace/modules/(?P<id>\d+)/releases', array( 'WebinoCRM_Marketplace_Service', 'module_releases' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/marketplace/modules/(?P<id>\d+)/releases', array( 'WebinoCRM_Marketplace_Service', 'release_save' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/marketplace/releases/(?P<release_id>\d+)/publish', array( 'WebinoCRM_Marketplace_Service', 'release_publish' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/marketplace/releases/(?P<release_id>\d+)', array( 'WebinoCRM_Marketplace_Service', 'release_delete' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/marketplace/orders', array( 'WebinoCRM_Marketplace_Service', 'orders' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/marketplace/gitea/settings', array( 'WebinoCRM_Marketplace_Service', 'gitea_settings_get' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/marketplace/gitea/settings', array( 'WebinoCRM_Marketplace_Service', 'gitea_settings_save' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/marketplace/gitea/test', array( 'WebinoCRM_Marketplace_Service', 'gitea_test' ), 'marketplace' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/marketplace/gitea/test', array( 'WebinoCRM_Marketplace_Service', 'gitea_test' ), 'marketplace' );
	}
}

class WebinoCRM_REST_Core_Update_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/core/update-status', array( 'WebinoCRM_Core_Update_Service', 'status' ), 'settings' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/core/update', array( 'WebinoCRM_Core_Update_Service', 'run' ), 'settings' );
	}
}

class WebinoCRM_REST_Modirpayamak_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/modirpayamak/admin/dashboard', array( 'WebinoCRM_Modirpayamak_Service', 'dashboard' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/modirpayamak/admin/proxy', array( 'WebinoCRM_Modirpayamak_Service', 'proxy' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/modirpayamak/admin/customers', array( 'WebinoCRM_Modirpayamak_Service', 'customers' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/modirpayamak/admin/customers/balance', array( 'WebinoCRM_Modirpayamak_Service', 'adjust_balance' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/modirpayamak/admin/customers/(?P<domain>[a-zA-Z0-9._-]+)/ledger', array( 'WebinoCRM_Modirpayamak_Service', 'customer_ledger' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/modirpayamak/admin/customers/ledger', array( 'WebinoCRM_Modirpayamak_Service', 'customer_ledger' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/modirpayamak/admin/customers/ensure', array( 'WebinoCRM_Modirpayamak_Service', 'ensure_customers_from_licenses' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/modirpayamak/admin/numbers/attach', array( 'WebinoCRM_Modirpayamak_Service', 'attach_number' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/modirpayamak/admin/numbers/detach', array( 'WebinoCRM_Modirpayamak_Service', 'detach_number' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/modirpayamak/admin/patterns/attach', array( 'WebinoCRM_Modirpayamak_Service', 'attach_pattern' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/modirpayamak/admin/patterns/detach', array( 'WebinoCRM_Modirpayamak_Service', 'detach_pattern' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/modirpayamak/admin/patterns/registry', array( 'WebinoCRM_Modirpayamak_Service', 'domain_pattern_registry' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/modirpayamak/admin/secretaries', array( 'WebinoCRM_Modirpayamak_Service', 'domain_secretaries' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/modirpayamak/admin/secretaries', array( 'WebinoCRM_Modirpayamak_Service', 'save_domain_secretary' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/modirpayamak/admin/secretaries/delete', array( 'WebinoCRM_Modirpayamak_Service', 'delete_domain_secretary' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/modirpayamak/admin/packages', array( 'WebinoCRM_Modirpayamak_Service', 'packages' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/modirpayamak/admin/packages', array( 'WebinoCRM_Modirpayamak_Service', 'package_save' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/modirpayamak/admin/packages/(?P<id>\d+)', array( 'WebinoCRM_Modirpayamak_Service', 'package_delete' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/modirpayamak/admin/tariffs', array( 'WebinoCRM_Modirpayamak_Service', 'tariffs' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/modirpayamak/admin/tariffs', array( 'WebinoCRM_Modirpayamak_Service', 'tariff_save' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/modirpayamak/admin/tariffs/(?P<id>\d+)', array( 'WebinoCRM_Modirpayamak_Service', 'tariff_delete' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/modirpayamak/admin/orders', array( 'WebinoCRM_Modirpayamak_Service', 'orders' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/modirpayamak/admin/send', array( 'WebinoCRM_Modirpayamak_Service', 'send' ), 'modirpayamak' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/modirpayamak/admin/messages', array( 'WebinoCRM_Modirpayamak_Service', 'messages' ), 'modirpayamak' );
	}
}

class WebinoCRM_REST_Projects_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/projects', array( 'WebinoCRM_Project_Service', 'list' ), 'projects' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/projects/(?P<id>\d+)', array( 'WebinoCRM_Project_Service', 'get' ), 'projects' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/projects', array( 'WebinoCRM_Project_Service', 'create_or_update' ), 'projects' );
		WebinoCRM_REST_Registry::register_service_route( 'PATCH', '/projects/(?P<id>\d+)', array( 'WebinoCRM_Project_Service', 'create_or_update' ), 'projects' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/projects/(?P<id>\d+)', array( 'WebinoCRM_Project_Service', 'delete' ), 'projects' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/projects/meta/assignees', array( 'WebinoCRM_Project_Service', 'assignees' ), 'projects' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/project-templates', array( 'WebinoCRM_Project_Service', 'templates' ), 'projects' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/products/projects-preview', array( 'WebinoCRM_Project_Service', 'product_preview' ), 'projects' );
	}
}

class WebinoCRM_REST_Profile_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/profile', array( 'WebinoCRM_Profile_Service', 'update' ), '' );
	}
}

class WebinoCRM_REST_Reports_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/reports', array( 'WebinoCRM_Reports_Service', 'get' ), 'reports' );
		WebinoCRM_REST_Registry::register_stream_route( 'GET', '/reports/export', array( 'WebinoCRM_Reports_Service', 'export' ), 'reports' );
		WebinoCRM_REST_Registry::register_stream_route( 'GET', '/reports/analytics/export', array( 'WebinoCRM_Reports_Service', 'export_analytics' ), 'reports' );
	}
}

class WebinoCRM_REST_Services_Module_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/services/subscriptions', array( 'WebinoCRM_Crm_Services_Module_Service', 'subscriptions' ), 'services' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/services/products', array( 'WebinoCRM_Crm_Services_Module_Service', 'products' ), 'services' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/services/subscriptions/(?P<id>\d+)/convert-contract', array( 'WebinoCRM_Crm_Services_Module_Service', 'convert_subscription' ), 'services' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/services/products/(?P<id>\d+)/task-template', array( 'WebinoCRM_Crm_Services_Module_Service', 'product_task_template' ), 'services' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/services/task-templates', array( 'WebinoCRM_Crm_Services_Module_Service', 'task_templates' ), 'services' );
	}
}

class WebinoCRM_REST_Settings_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/settings/crm', array( 'WebinoCRM_Settings_Crm_Service', 'get' ), 'settings' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/settings/crm', array( 'WebinoCRM_Settings_Crm_Service', 'save' ), 'settings' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/settings/crm/auth', array( 'WebinoCRM_Settings_Crm_Service', 'save_auth' ), 'settings' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/settings/hub', array( 'WebinoCRM_Settings_Crm_Service', 'hub_get' ), 'settings' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/settings/hub/toggle', array( 'WebinoCRM_Settings_Crm_Service', 'hub_toggle' ), 'settings' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/settings/canned-responses', array( 'WebinoCRM_Settings_Crm_Service', 'canned_save' ), 'settings' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/settings/canned-responses/(?P<id>\d+)', array( 'WebinoCRM_Settings_Crm_Service', 'canned_delete' ), 'settings' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/settings/positions', array( 'WebinoCRM_Settings_Crm_Service', 'position_save' ), 'settings' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/settings/positions/(?P<id>\d+)', array( 'WebinoCRM_Settings_Crm_Service', 'position_delete' ), 'settings' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/settings/task-categories', array( 'WebinoCRM_Settings_Crm_Service', 'task_category_save' ), 'settings' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/settings/task-categories/(?P<id>\d+)', array( 'WebinoCRM_Settings_Crm_Service', 'task_category_delete' ), 'settings' );
	}
}

class WebinoCRM_REST_Tasks_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/tasks', array( 'WebinoCRM_Tasks_Service', 'list' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/tasks/(?P<id>\d+)', array( 'WebinoCRM_Tasks_Service', 'get' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/tasks/(?P<id>\d+)/attachments', array( 'WebinoCRM_Tasks_Service', 'upload_attachment' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/tasks/(?P<id>\d+)/attachments/(?P<attachment_id>\d+)', array( 'WebinoCRM_Tasks_Service', 'delete_attachment' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/tasks', array( 'WebinoCRM_Tasks_Service', 'create' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'PATCH', '/tasks/(?P<id>\d+)/status', array( 'WebinoCRM_Tasks_Service', 'update_status' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/tasks/(?P<id>\d+)', array( 'WebinoCRM_Tasks_Service', 'delete' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/tasks/calendar', array( 'WebinoCRM_Tasks_Service', 'calendar' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/tasks/gantt', array( 'WebinoCRM_Tasks_Service', 'gantt' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/tasks/views', array( 'WebinoCRM_Tasks_Service', 'views' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/tasks/extended', array( 'WebinoCRM_Tasks_Service', 'create_extended' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'PATCH', '/tasks/(?P<id>\d+)/content', array( 'WebinoCRM_Tasks_Service', 'save_content' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/tasks/(?P<id>\d+)/comments', array( 'WebinoCRM_Tasks_Service', 'add_comment' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/tasks/(?P<id>\d+)/checklist', array( 'WebinoCRM_Tasks_Service', 'manage_checklist' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/tasks/(?P<id>\d+)/time-log', array( 'WebinoCRM_Tasks_Service', 'log_time' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'PATCH', '/tasks/(?P<id>\d+)/quick-edit', array( 'WebinoCRM_Tasks_Service', 'quick_edit' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'PATCH', '/tasks/(?P<id>\d+)/assignee', array( 'WebinoCRM_Tasks_Service', 'update_assignee' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/tasks/search', array( 'WebinoCRM_Tasks_Service', 'search_for_linking' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/tasks/links', array( 'WebinoCRM_Tasks_Service', 'add_link' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/tasks/links', array( 'WebinoCRM_Tasks_Service', 'remove_link' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/tasks/bulk', array( 'WebinoCRM_Tasks_Service', 'bulk_edit' ), 'tasks' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/tasks/templates', array( 'WebinoCRM_Tasks_Service', 'save_as_template' ), 'tasks' );
	}
}

class WebinoCRM_REST_Tickets_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/tickets', array( 'WebinoCRM_Tickets_Service', 'list' ), 'tickets' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/tickets/(?P<id>\d+)', array( 'WebinoCRM_Tickets_Service', 'get' ), 'tickets' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/tickets', array( 'WebinoCRM_Tickets_Service', 'create' ), 'tickets' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/tickets/(?P<id>\d+)/replies', array( 'WebinoCRM_Tickets_Service', 'reply' ), 'tickets' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/tickets/(?P<id>\d+)/convert-task', array( 'WebinoCRM_Tickets_Service', 'convert_task' ), 'tickets' );
	}
}

class WebinoCRM_REST_Visitor_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/visitor-statistics', array( 'WebinoCRM_Visitor_Service', 'get' ), 'visitor-statistics' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/visitor-statistics/settings', array( 'WebinoCRM_Visitor_Service', 'save_settings' ), 'visitor-statistics' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/visitor-statistics/track', array( 'WebinoCRM_Visitor_Service', 'track' ), '' );
	}
}

class WebinoCRM_REST_Chat_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/chat/channels', array( 'WebinoCRM_Chat_Service', 'list_channels' ), 'chat' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/chat/channels', array( 'WebinoCRM_Chat_Service', 'create_channel' ), 'chat' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/chat/direct', array( 'WebinoCRM_Chat_Service', 'list_direct' ), 'chat' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/chat/messages', array( 'WebinoCRM_Chat_Service', 'list_messages' ), 'chat' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/chat/messages', array( 'WebinoCRM_Chat_Service', 'send_message' ), 'chat' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/chat/read', array( 'WebinoCRM_Chat_Service', 'mark_read' ), 'chat' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/chat/unread-count', array( 'WebinoCRM_Chat_Service', 'unread_count' ), 'chat' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/chat/search', array( 'WebinoCRM_Chat_Service', 'search' ), 'chat' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/chat/messages/(?P<id>\d+)', array( 'WebinoCRM_Chat_Service', 'delete_message' ), 'chat' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/chat/ws-token', array( 'WebinoCRM_Realtime_Service', 'ws_token' ), 'chat' );
	}
}

class WebinoCRM_REST_Realtime_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		register_rest_route(
			'webinocrm/v1',
			'/ws-broadcast',
			array(
				'methods'             => 'POST',
				'callback'            => array( 'WebinoCRM_Realtime_Service', 'rest_broadcast' ),
				'permission_callback' => '__return_true',
			)
		);
	}
}

class WebinoCRM_REST_Documents_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/documents', array( 'WebinoCRM_Documents_Service', 'list' ), 'documents' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/documents', array( 'WebinoCRM_Documents_Service', 'upload' ), 'documents' );
		WebinoCRM_REST_Registry::register_stream_route( 'GET', '/documents/(?P<id>\d+)/download', array( 'WebinoCRM_Documents_Service', 'download' ), 'documents' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/documents/(?P<id>\d+)', array( 'WebinoCRM_Documents_Service', 'delete' ), 'documents' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/documents/folders', array( 'WebinoCRM_Documents_Service', 'create_folder' ), 'documents' );
		WebinoCRM_REST_Registry::register_service_route( 'PATCH', '/documents/(?P<id>\d+)', array( 'WebinoCRM_Documents_Service', 'update' ), 'documents' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/documents/(?P<id>\d+)/share', array( 'WebinoCRM_Documents_Service', 'share' ), 'documents' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/documents/(?P<id>\d+)/versions', array( 'WebinoCRM_Documents_Service', 'versions' ), 'documents' );
	}
}

class WebinoCRM_REST_Time_Tracking_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/time-tracking/active', array( 'WebinoCRM_Time_Tracking_Service', 'active_timer' ), 'time-tracking' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/time-tracking/start', array( 'WebinoCRM_Time_Tracking_Service', 'start' ), 'time-tracking' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/time-tracking/(?P<id>\d+)/stop', array( 'WebinoCRM_Time_Tracking_Service', 'stop' ), 'time-tracking' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/time-tracking/(?P<id>\d+)/pause', array( 'WebinoCRM_Time_Tracking_Service', 'pause' ), 'time-tracking' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/time-tracking/(?P<id>\d+)/resume', array( 'WebinoCRM_Time_Tracking_Service', 'resume' ), 'time-tracking' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/time-tracking/entries', array( 'WebinoCRM_Time_Tracking_Service', 'entries' ), 'time-tracking' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/time-tracking/entries', array( 'WebinoCRM_Time_Tracking_Service', 'manual_entry' ), 'time-tracking' );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/time-tracking/entries/(?P<id>\d+)', array( 'WebinoCRM_Time_Tracking_Service', 'delete_entry' ), 'time-tracking' );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/time-tracking/report', array( 'WebinoCRM_Time_Tracking_Service', 'report' ), 'time-tracking' );
	}
}

class WebinoCRM_REST_Warehouse_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		$cap = 'accounting';
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/warehouses', array( 'WebinoCRM_Warehouse_Service', 'list' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/warehouses/create', array( 'WebinoCRM_Warehouse_Service', 'create' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/warehouses/update', array( 'WebinoCRM_Warehouse_Service', 'update' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/warehouses/delete', array( 'WebinoCRM_Warehouse_Service', 'delete' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/products', array( 'WebinoCRM_Warehouse_Service', 'products' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/warehouse/stock', array( 'WebinoCRM_Warehouse_Service', 'stock_list' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/warehouse/stock/(?P<warehouse_id>\d+)/(?P<product_id>\d+)', array( 'WebinoCRM_Warehouse_Service', 'stock_get' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/warehouse/inbound', array( 'WebinoCRM_Warehouse_Service', 'inbound_list' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/warehouse/inbound/(?P<id>\d+)', array( 'WebinoCRM_Warehouse_Service', 'inbound_get' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/warehouse/inbound/create', array( 'WebinoCRM_Warehouse_Service', 'inbound_create' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/warehouse/inbound/post', array( 'WebinoCRM_Warehouse_Service', 'inbound_post' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/warehouse/outbound', array( 'WebinoCRM_Warehouse_Service', 'outbound_list' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/warehouse/outbound/(?P<id>\d+)', array( 'WebinoCRM_Warehouse_Service', 'outbound_get' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/warehouse/outbound/create', array( 'WebinoCRM_Warehouse_Service', 'outbound_create' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/warehouse/outbound/post', array( 'WebinoCRM_Warehouse_Service', 'outbound_post' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/warehouse/audit', array( 'WebinoCRM_Warehouse_Service', 'audit_list' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/warehouse/audit/(?P<id>\d+)', array( 'WebinoCRM_Warehouse_Service', 'audit_get' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/warehouse/audit/create', array( 'WebinoCRM_Warehouse_Service', 'audit_create' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/warehouse/audit/record', array( 'WebinoCRM_Warehouse_Service', 'audit_record' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/warehouse/audit/complete', array( 'WebinoCRM_Warehouse_Service', 'audit_complete' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/warehouse/audit/post', array( 'WebinoCRM_Warehouse_Service', 'audit_post' ), $cap );
	}
}

class WebinoCRM_REST_Rahn_Controller extends WebinoCRM_REST_Controller_Base {
	public static function register_routes() {
		$cap = 'rahn-percent';
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/rahn/settings', array( 'WebinoCRM_Rahn_Service', 'settings_get' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/rahn/settings', array( 'WebinoCRM_Rahn_Service', 'settings_save' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/rahn/calculate', array( 'WebinoCRM_Rahn_Service', 'calculate' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/rahn/quotes', array( 'WebinoCRM_Rahn_Service', 'quotes_list' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/rahn/quotes', array( 'WebinoCRM_Rahn_Service', 'quote_save' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/rahn/quotes/(?P<id>\d+)/lock', array( 'WebinoCRM_Rahn_Service', 'quote_lock' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/rahn/quotes/(?P<id>\d+)/contract', array( 'WebinoCRM_Rahn_Service', 'quote_to_contract' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'DELETE', '/rahn/quotes/(?P<id>\d+)', array( 'WebinoCRM_Rahn_Service', 'quote_delete' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/rahn/contracts', array( 'WebinoCRM_Rahn_Service', 'contracts_list' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/rahn/statements/calculate', array( 'WebinoCRM_Rahn_Service', 'statement_calculate' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/rahn/statements', array( 'WebinoCRM_Rahn_Service', 'statement_save' ), $cap );
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/rahn/statements', array( 'WebinoCRM_Rahn_Service', 'statements_list' ), $cap );

		// Public share endpoints (no auth).
		WebinoCRM_REST_Registry::register_service_route( 'GET', '/rahn/public/(?P<token>[a-zA-Z0-9]+)', array( 'WebinoCRM_Rahn_Service', 'public_get' ), 'public' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/rahn/public/(?P<token>[a-zA-Z0-9]+)/calculate', array( 'WebinoCRM_Rahn_Service', 'public_calculate' ), 'public' );
		WebinoCRM_REST_Registry::register_service_route( 'POST', '/rahn/public/(?P<token>[a-zA-Z0-9]+)/submit', array( 'WebinoCRM_Rahn_Service', 'public_submit' ), 'public' );
	}
}

class WebinoCRM_REST_Crm_Controllers {
	public static function register_all() {
		WebinoCRM_REST_Appointments_Controller::register_routes();
		WebinoCRM_REST_Campaigns_Controller::register_routes();
		WebinoCRM_REST_Chat_Controller::register_routes();
		WebinoCRM_REST_Realtime_Controller::register_routes();
		WebinoCRM_REST_Consultations_Controller::register_routes();
		WebinoCRM_REST_Contracts_Controller::register_routes();
		WebinoCRM_REST_Customers_Controller::register_routes();
		WebinoCRM_REST_Dashboard_Controller::register_routes();
		WebinoCRM_REST_Documents_Controller::register_routes();
		WebinoCRM_REST_Invoices_Controller::register_routes();
		WebinoCRM_REST_Leads_Controller::register_routes();
		WebinoCRM_REST_Licenses_Controller::register_routes();
		WebinoCRM_REST_Logs_Controller::register_routes();
		WebinoCRM_REST_Marketplace_Controller::register_routes();
		WebinoCRM_REST_Core_Update_Controller::register_routes();
		WebinoCRM_REST_Modirpayamak_Controller::register_routes();
		WebinoCRM_REST_Projects_Controller::register_routes();
		WebinoCRM_REST_Profile_Controller::register_routes();
		WebinoCRM_REST_Reports_Controller::register_routes();
		WebinoCRM_REST_Services_Module_Controller::register_routes();
		WebinoCRM_REST_Settings_Controller::register_routes();
		WebinoCRM_REST_Tasks_Controller::register_routes();
		WebinoCRM_REST_Tickets_Controller::register_routes();
		WebinoCRM_REST_Time_Tracking_Controller::register_routes();
		WebinoCRM_REST_Visitor_Controller::register_routes();
		WebinoCRM_REST_Warehouse_Controller::register_routes();
		WebinoCRM_REST_Hrm_Controller::register_routes();
		WebinoCRM_REST_Rahn_Controller::register_routes();
	}
}