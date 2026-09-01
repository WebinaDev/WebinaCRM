import type { ComponentType, LazyExoticComponent } from 'react'

import { erpCapability } from '@/modules/registry'
import { lazyNamedPage } from '@/routes/lazyPage'

export type DashboardRouteDef = {
  path: string
  capability: string
  headerTitleKey?: string
  headerParamKeys?: Record<string, string>
  Component: LazyExoticComponent<ComponentType>
}

type RouteEntry = {
  paths: string[]
  menuId: string
  load: () => Promise<{ [key: string]: ComponentType }>
  exportName: string
}

function routesFromEntries(entries: RouteEntry[]): DashboardRouteDef[] {
  const out: DashboardRouteDef[] = []
  for (const e of entries) {
    const Component = lazyNamedPage(e.load, e.exportName)
    for (const path of e.paths) {
      out.push({ path, capability: erpCapability(e.menuId), Component })
    }
  }
  return out
}

const ROUTE_ENTRIES: RouteEntry[] = [
  { paths: ['hrm/staff', 'staff'], menuId: 'staff', load: () => import('@/pages/crm/staff-page'), exportName: 'StaffPage' },
  { paths: ['hrm/staff/:id', 'staff/:id'], menuId: 'staff', load: () => import('@/pages/crm/staff-detail-page'), exportName: 'StaffDetailPage' },
  { paths: ['hrm/attendance', 'hrm/attendance/*'], menuId: 'hrm-attendance', load: () => import('@/pages/crm/hrm-attendance-page'), exportName: 'AttendancePage' },
  { paths: ['hrm/leave', 'hrm/leave/*'], menuId: 'hrm-leave', load: () => import('@/pages/crm/hrm-leave-page'), exportName: 'LeavePage' },
  { paths: ['hrm/payroll/:id'], menuId: 'hrm-payroll', load: () => import('@/pages/crm/hrm-payroll-run-page'), exportName: 'PayrollRunDetailPage' },
  { paths: ['hrm/payroll/decrees'], menuId: 'hrm-payroll', load: () => import('@/pages/crm/hrm-payroll-decrees-page'), exportName: 'PayrollDecreesPage' },
  { paths: ['hrm/my-payroll'], menuId: 'hrm-my-payroll', load: () => import('@/pages/crm/hrm-my-payroll-page'), exportName: 'MyPayrollPage' },
  { paths: ['hrm/me'], menuId: 'hrm-me', load: () => import('@/pages/crm/hrm-me-page'), exportName: 'MyPortalPage' },
  { paths: ['hrm/my-time'], menuId: 'hrm-my-time', load: () => import('@/pages/crm/hrm-my-time-page'), exportName: 'MyTimePage' },
  { paths: ['hrm/my-docs'], menuId: 'hrm-my-docs', load: () => import('@/pages/crm/hrm-my-docs-page'), exportName: 'MyDocsPage' },
  { paths: ['hrm/my-insurance'], menuId: 'hrm-my-insurance', load: () => import('@/pages/crm/hrm-my-insurance-page'), exportName: 'MyInsurancePage' },
  { paths: ['hrm/my-org'], menuId: 'hrm-my-org', load: () => import('@/pages/crm/hrm-my-org-page'), exportName: 'MyOrgPage' },
  { paths: ['hrm/my-profile'], menuId: 'hrm-my-profile', load: () => import('@/pages/crm/hrm-my-profile-page'), exportName: 'MyProfilePage' },
  { paths: ['hrm/cartable'], menuId: 'hrm-cartable', load: () => import('@/pages/crm/hrm-cartable-page'), exportName: 'HrmCartablePage' },
  { paths: ['hrm/payroll'], menuId: 'hrm-payroll', load: () => import('@/pages/crm/hrm-payroll-page'), exportName: 'PayrollRunsPage' },
  { paths: ['hrm/recruitment', 'hrm/recruitment/*'], menuId: 'hrm-recruitment', load: () => import('@/pages/crm/hrm-recruitment-page'), exportName: 'RecruitmentPage' },
  { paths: ['hrm/performance', 'hrm/performance/*'], menuId: 'hrm-performance', load: () => import('@/pages/crm/hrm-performance-page'), exportName: 'PerformancePage' },
  { paths: ['hrm/training', 'hrm/training/*'], menuId: 'hrm-training', load: () => import('@/pages/crm/hrm-training-page'), exportName: 'TrainingPage' },
  { paths: ['profile'], menuId: 'profile', load: () => import('@/pages/crm/profile-page'), exportName: 'ProfilePage' },
  { paths: ['crm/leads', 'leads'], menuId: 'leads', load: () => import('@/pages/crm/leads-page'), exportName: 'LeadsPage' },
  { paths: ['crm/customers', 'customers'], menuId: 'customers', load: () => import('@/pages/crm/customers-page'), exportName: 'CustomersPage' },
  { paths: ['crm/tickets', 'crm/tickets/*', 'tickets', 'tickets/*'], menuId: 'tickets', load: () => import('@/pages/crm/tickets-page'), exportName: 'TicketsPage' },
  { paths: ['crm/consultations', 'consultations'], menuId: 'consultations', load: () => import('@/pages/crm/consultations-page'), exportName: 'ConsultationsPage' },
  { paths: ['pm/projects', 'pm/projects/*', 'projects', 'projects/*'], menuId: 'projects', load: () => import('@/pages/crm/projects-page'), exportName: 'ProjectsPage' },
  { paths: ['pm/tasks', 'pm/tasks/*', 'tasks', 'tasks/*'], menuId: 'tasks', load: () => import('@/pages/crm/tasks-page'), exportName: 'TasksPage' },
  { paths: ['pm/chat', 'chat'], menuId: 'chat', load: () => import('@/pages/crm/chat-page'), exportName: 'ChatPage' },
  { paths: ['pm/time-tracking', 'time-tracking'], menuId: 'time-tracking', load: () => import('@/pages/crm/time-tracking-page'), exportName: 'TimeTrackingPage' },
  { paths: ['pm/appointments', 'pm/appointments/*', 'appointments', 'appointments/*'], menuId: 'appointments', load: () => import('@/pages/crm/appointments-page'), exportName: 'AppointmentsPage' },
  { paths: ['docs/contracts', 'docs/contracts/*', 'contracts', 'contracts/*'], menuId: 'contracts', load: () => import('@/pages/crm/contracts-page'), exportName: 'ContractsPage' },
  { paths: ['docs/files', 'documents'], menuId: 'documents', load: () => import('@/pages/crm/documents-page'), exportName: 'DocumentsPage' },
  { paths: ['sales/invoices', 'sales/invoices/*', 'invoices', 'invoices/*'], menuId: 'invoices', load: () => import('@/pages/crm/invoices-page'), exportName: 'InvoicesPage' },
  { paths: ['sales/catalog', 'sales/catalog/*', 'services', 'services/*'], menuId: 'services', load: () => import('@/pages/crm/services-page'), exportName: 'ServicesPage' },
  { paths: ['sales/campaigns', 'sales/campaigns/*', 'campaigns', 'campaigns/*'], menuId: 'campaigns', load: () => import('@/pages/crm/campaigns-page'), exportName: 'CampaignsPage' },
  { paths: ['reports', 'reports/*'], menuId: 'reports', load: () => import('@/pages/crm/reports-page'), exportName: 'ReportsPage' },
  { paths: ['mfg', 'mfg/'], menuId: 'mfg-overview', load: () => import('@/pages/crm/mfg-overview-page'), exportName: 'MfgOverviewPage' },
  { paths: ['finance', 'accounting'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/AccountingDashboardPage'), exportName: 'AccountingDashboardPage' },
  { paths: ['finance/chart', 'accounting/chart'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/ChartOfAccountsPage'), exportName: 'ChartOfAccountsPage' },
  { paths: ['finance/journals', 'accounting/journals'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/JournalsPage'), exportName: 'JournalsPage' },
  { paths: ['finance/ledger', 'accounting/ledger'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/LedgerPage'), exportName: 'LedgerPage' },
  { paths: ['finance/reports', 'accounting/reports'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/AccountingReportsPage'), exportName: 'AccountingReportsPage' },
  { paths: ['finance/fiscal-year', 'accounting/fiscal-year'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/FiscalYearPage'), exportName: 'FiscalYearPage' },
  { paths: ['finance/settings', 'accounting/settings'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/AccountingSettingsPage'), exportName: 'AccountingSettingsPage' },
  { paths: ['finance/persons', 'accounting/persons'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/PersonsPage'), exportName: 'PersonsPage' },
  { paths: ['finance/products', 'accounting/products'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/ProductsPage'), exportName: 'ProductsPage' },
  { paths: ['finance/invoices', 'accounting/invoices'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/InvoicesPage'), exportName: 'InvoicesPage' },
  { paths: ['finance/cash-accounts', 'accounting/cash-accounts'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/CashAccountsPage'), exportName: 'CashAccountsPage' },
  { paths: ['finance/receipts', 'accounting/receipts'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/ReceiptsPage'), exportName: 'ReceiptsPage' },
  { paths: ['finance/checks', 'accounting/checks'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/ChecksPage'), exportName: 'ChecksPage' },
  { paths: ['scm/warehouses', 'accounting/warehouses'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/WarehousesPage'), exportName: 'WarehousesPage' },
  { paths: ['scm/stock', 'accounting/warehouse-stock'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/WarehouseStockPage'), exportName: 'WarehouseStockPage' },
  { paths: ['scm/inbound', 'accounting/warehouse-inbound'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/WarehouseInboundPage'), exportName: 'WarehouseInboundPage' },
  { paths: ['scm/outbound', 'accounting/warehouse-outbound'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/WarehouseOutboundPage'), exportName: 'WarehouseOutboundPage' },
  { paths: ['scm/audit', 'accounting/warehouse-audit'], menuId: 'accounting', load: () => import('@/pages/crm/accounting/WarehouseAuditPage'), exportName: 'WarehouseAuditPage' },
  { paths: ['admin/licenses', 'licenses'], menuId: 'licenses', load: () => import('@/pages/crm/licenses-page'), exportName: 'LicensesPage' },
  { paths: ['admin/marketplace/categories', 'marketplace/categories'], menuId: 'marketplace-categories', load: () => import('@/pages/crm/marketplace-categories-page'), exportName: 'MarketplaceCategoriesPage' },
  { paths: ['admin/marketplace/products', 'marketplace/products'], menuId: 'marketplace-products', load: () => import('@/pages/crm/marketplace-products-page'), exportName: 'MarketplaceProductsPage' },
  { paths: ['admin/marketplace/modules/new', 'marketplace/modules/new'], menuId: 'marketplace-products', load: () => import('@/pages/crm/marketplace-module-detail-page'), exportName: 'MarketplaceModuleDetailPage' },
  { paths: ['admin/marketplace/modules/:id', 'marketplace/modules/:id'], menuId: 'marketplace-products', load: () => import('@/pages/crm/marketplace-module-detail-page'), exportName: 'MarketplaceModuleDetailPage' },
  { paths: ['admin/marketplace/gitea', 'marketplace/gitea'], menuId: 'marketplace-products', load: () => import('@/pages/crm/marketplace-gitea-page'), exportName: 'MarketplaceGiteaSettingsPage' },
  { paths: ['admin/marketplace/orders', 'marketplace/orders'], menuId: 'marketplace-orders', load: () => import('@/pages/crm/marketplace-orders-page'), exportName: 'MarketplaceOrdersPage' },
  { paths: ['admin/logs', 'logs'], menuId: 'logs', load: () => import('@/pages/crm/logs-page'), exportName: 'LogsPage' },
  { paths: ['admin/analytics/visitors', 'visitor-statistics'], menuId: 'visitor-statistics', load: () => import('@/pages/crm/visitor-statistics-page'), exportName: 'VisitorStatisticsPage' },
  { paths: ['admin/settings', 'settings'], menuId: 'settings', load: () => import('@/pages/crm/settings-page'), exportName: 'SettingsPage' },
  { paths: ['admin/settings/general/:tab?', 'settings/general/:tab?'], menuId: 'settings', load: () => import('@/pages/crm/settings/settings-general-page'), exportName: 'SettingsGeneralPage' },
  { paths: ['admin/settings/projects/:tab?', 'settings/projects/:tab?'], menuId: 'settings', load: () => import('@/pages/crm/settings/settings-projects-page'), exportName: 'SettingsProjectsPage' },
  { paths: ['admin/settings/crm/:tab?', 'settings/crm/:tab?'], menuId: 'settings', load: () => import('@/pages/crm/settings/settings-crm-page'), exportName: 'SettingsCrmPage' },
  { paths: ['admin/settings/bots', 'settings/bots'], menuId: 'settings', load: () => import('@/pages/crm/settings/settings-bots-page'), exportName: 'SettingsBotsPage' },
  { paths: ['admin/settings/accounting/:tab?', 'settings/accounting/:tab?'], menuId: 'settings', load: () => import('@/pages/crm/settings/settings-accounting-page'), exportName: 'SettingsAccountingPage' },
  { paths: ['admin/integrations/bale', 'bale-business'], menuId: 'bale-business', load: () => import('@/pages/crm/bale-business-page'), exportName: 'BaleBusinessPage' },
  { paths: ['admin/integrations/modirpayamak', 'modirpayamak'], menuId: 'modirpayamak', load: () => import('@/pages/crm/modirpayamak-dashboard-page'), exportName: 'ModirPayamakDashboardPage' },
  { paths: ['admin/integrations/modirpayamak/send', 'modirpayamak/send'], menuId: 'modirpayamak-send', load: () => import('@/pages/crm/modirpayamak-send-page'), exportName: 'ModirPayamakSendPage' },
  { paths: ['admin/integrations/modirpayamak/reports', 'modirpayamak/reports'], menuId: 'modirpayamak-reports', load: () => import('@/pages/crm/modirpayamak-reports-page'), exportName: 'ModirPayamakReportsPage' },
  { paths: ['admin/integrations/modirpayamak/customers', 'modirpayamak/customers'], menuId: 'modirpayamak-customers', load: () => import('@/pages/crm/modirpayamak-customers-page'), exportName: 'ModirPayamakCustomersPage' },
  { paths: ['admin/integrations/modirpayamak/packages', 'modirpayamak/packages'], menuId: 'modirpayamak-packages', load: () => import('@/pages/crm/modirpayamak-packages-page'), exportName: 'ModirPayamakPackagesPage' },
  { paths: ['admin/integrations/modirpayamak/tariffs', 'modirpayamak/tariffs'], menuId: 'modirpayamak-tariffs', load: () => import('@/pages/crm/modirpayamak-tariffs-page'), exportName: 'ModirPayamakTariffsPage' },
  { paths: ['admin/integrations/modirpayamak/orders', 'modirpayamak/orders'], menuId: 'modirpayamak-orders', load: () => import('@/pages/crm/modirpayamak-orders-page'), exportName: 'ModirPayamakOrdersPage' },
  { paths: ['admin/integrations/modirpayamak/patterns', 'modirpayamak/patterns'], menuId: 'modirpayamak-patterns', load: () => import('@/pages/crm/modirpayamak-patterns-page'), exportName: 'ModirPayamakPatternsPage' },
  { paths: ['admin/integrations/modirpayamak/secretaries', 'modirpayamak/secretaries'], menuId: 'modirpayamak-secretaries', load: () => import('@/pages/crm/modirpayamak-secretaries-page'), exportName: 'ModirPayamakSecretariesPage' },
  { paths: ['admin/integrations/modirpayamak/phonebooks', 'modirpayamak/phonebooks'], menuId: 'modirpayamak-phonebooks', load: () => import('@/pages/crm/modirpayamak-phonebooks-page'), exportName: 'ModirPayamakPhonebooksPage' },
  { paths: ['admin/integrations/modirpayamak/numbers', 'modirpayamak/numbers'], menuId: 'modirpayamak-numbers', load: () => import('@/pages/crm/modirpayamak-numbers-page'), exportName: 'ModirPayamakNumbersPage' },
  { paths: ['admin/integrations/modirpayamak/users', 'modirpayamak/users'], menuId: 'modirpayamak-users', load: () => import('@/pages/crm/modirpayamak-users-page'), exportName: 'ModirPayamakUsersPage' },
  { paths: ['admin/integrations/modirpayamak/tickets', 'modirpayamak/tickets'], menuId: 'modirpayamak-tickets', load: () => import('@/pages/crm/modirpayamak-tickets-page'), exportName: 'ModirPayamakTicketsPage' },
  { paths: ['admin/integrations/modirpayamak/drafts', 'modirpayamak/drafts'], menuId: 'modirpayamak-drafts', load: () => import('@/pages/crm/modirpayamak-drafts-page'), exportName: 'ModirPayamakDraftsPage' },
  { paths: ['admin/integrations/modirpayamak/settings', 'modirpayamak/settings'], menuId: 'modirpayamak-settings', load: () => import('@/pages/crm/modirpayamak-settings-page'), exportName: 'ModirPayamakSettingsPage' },
  { paths: ['ai-content'], menuId: 'ai-content', load: () => import('@/pages/crm/ai-content-overview-page'), exportName: 'AiOverviewPage' },
  { paths: ['ai-content/jobs'], menuId: 'ai-content-jobs', load: () => import('@/pages/crm/ai-content-jobs-page'), exportName: 'AiJobsPage' },
  { paths: ['ai-content/calendar'], menuId: 'ai-content-calendar', load: () => import('@/pages/crm/ai-content-calendar-page'), exportName: 'AiCalendarPage' },
  { paths: ['ai-content/products'], menuId: 'ai-content-products', load: () => import('@/pages/crm/ai-content-products-page'), exportName: 'AiProductsPage' },
  { paths: ['ai-content/titles'], menuId: 'ai-content-titles', load: () => import('@/pages/crm/ai-content-titles-page'), exportName: 'AiTitlesPage' },
  { paths: ['ai-content/pages'], menuId: 'ai-content-pages', load: () => import('@/pages/crm/ai-content-pages-page'), exportName: 'AiPagesPage' },
  { paths: ['ai-content/taxonomies'], menuId: 'ai-content-taxonomies', load: () => import('@/pages/crm/ai-content-taxonomies-page'), exportName: 'AiTaxonomiesPage' },
  { paths: ['ai-content/attributes'], menuId: 'ai-content-attributes', load: () => import('@/pages/crm/ai-content-attributes-page'), exportName: 'AiAttributesPage' },
  { paths: ['ai-content/settings'], menuId: 'ai-content-settings', load: () => import('@/pages/crm/ai-content-settings-page'), exportName: 'AiSettingsPage' },
  { paths: ['pages', 'pages/new', 'pages/:pageId'], menuId: 'ai-content-cms-pages', load: () => import('@/pages/crm/cms-pages-page'), exportName: 'CmsPagesPage' },
]

export const dashboardRoutes: DashboardRouteDef[] = routesFromEntries(ROUTE_ENTRIES)
