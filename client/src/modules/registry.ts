/**
 * ERP module registry — single source for routes, redirects, and navigation helpers.
 */

export type ErpMenuItemDef = {
  /** Stable menu / capability id (webinocrm_route_{id}). */
  id: string
  /** Path relative to dashboard basename (no leading slash). */
  path: string
  icon: string
  /** i18n key under nav.erp.* */
  titleKey: string
  /** Nested sidebar items (level 3). */
  children?: ErpMenuItemDef[]
}

export type ErpSubModuleDef = {
  id: string
  settingsKey: string
  parentModuleId: string
  marketplaceSlug?: string
  defaultEnabled: boolean
  menu: ErpMenuItemDef
}

export type ErpModuleDef = {
  id: string
  settingsKey: string
  /** Older settings keys still honored when checking enabled state. */
  legacySettingsKeys?: string[]
  basePath: string
  sidebarCategoryKey: string
  defaultEnabled: boolean
  menuItems: ErpMenuItemDef[]
  legacyRedirects: Array<{ from: string; to: string }>
}

/** Shared dashboard home items (not tied to a toggle except dashboard module). */
export const ERP_DASHBOARD_ITEMS: ErpMenuItemDef[] = [
  { id: 'dashboard', path: '', titleKey: 'nav.erp.dashboard', icon: 'ri-home-4-line' },
  { id: 'reports', path: 'reports', titleKey: 'nav.erp.reports', icon: 'ri-bar-chart-box-line' },
]

const MODIRPAYAMAK_CHILDREN: ErpMenuItemDef[] = [
  { id: 'modirpayamak-send', path: 'admin/integrations/modirpayamak/send', titleKey: 'nav.erp.admin.mpSend', icon: 'ri-send-plane-line' },
  { id: 'modirpayamak-reports', path: 'admin/integrations/modirpayamak/reports', titleKey: 'nav.erp.admin.mpReports', icon: 'ri-file-list-line' },
  { id: 'modirpayamak-customers', path: 'admin/integrations/modirpayamak/customers', titleKey: 'nav.erp.admin.mpCustomers', icon: 'ri-group-line' },
  { id: 'modirpayamak-packages', path: 'admin/integrations/modirpayamak/packages', titleKey: 'nav.erp.admin.mpPackages', icon: 'ri-wallet-line' },
  { id: 'modirpayamak-tariffs', path: 'admin/integrations/modirpayamak/tariffs', titleKey: 'nav.erp.admin.mpTariffs', icon: 'ri-price-tag-3-line' },
  { id: 'modirpayamak-orders', path: 'admin/integrations/modirpayamak/orders', titleKey: 'nav.erp.admin.mpOrders', icon: 'ri-shopping-cart-line' },
  { id: 'modirpayamak-patterns', path: 'admin/integrations/modirpayamak/patterns', titleKey: 'nav.erp.admin.mpPatterns', icon: 'ri-code-box-line' },
  { id: 'modirpayamak-secretaries', path: 'admin/integrations/modirpayamak/secretaries', titleKey: 'nav.erp.admin.mpSecretaries', icon: 'ri-robot-line' },
  { id: 'modirpayamak-phonebooks', path: 'admin/integrations/modirpayamak/phonebooks', titleKey: 'nav.erp.admin.mpPhonebooks', icon: 'ri-contacts-book-line' },
  { id: 'modirpayamak-numbers', path: 'admin/integrations/modirpayamak/numbers', titleKey: 'nav.erp.admin.mpNumbers', icon: 'ri-phone-line' },
  { id: 'modirpayamak-users', path: 'admin/integrations/modirpayamak/users', titleKey: 'nav.erp.admin.mpUsers', icon: 'ri-user-settings-line' },
  { id: 'modirpayamak-tickets', path: 'admin/integrations/modirpayamak/tickets', titleKey: 'nav.erp.admin.mpTickets', icon: 'ri-customer-service-line' },
  { id: 'modirpayamak-drafts', path: 'admin/integrations/modirpayamak/drafts', titleKey: 'nav.erp.admin.mpDrafts', icon: 'ri-draft-line' },
  { id: 'modirpayamak-settings', path: 'admin/integrations/modirpayamak/settings', titleKey: 'nav.erp.admin.mpSettings', icon: 'ri-settings-3-line' },
]

export const ERP_SUBMODULES: ErpSubModuleDef[] = [
  {
    id: 'modirpayamak',
    settingsKey: 'modirpayamak',
    parentModuleId: 'sales',
    marketplaceSlug: 'modirpayamak-module',
    defaultEnabled: true,
    menu: {
      id: 'modirpayamak',
      path: 'admin/integrations/modirpayamak',
      titleKey: 'nav.erp.sales.modirpayamak',
      icon: 'ri-message-2-line',
      children: MODIRPAYAMAK_CHILDREN,
    },
  },
  {
    id: 'bale-business',
    settingsKey: 'bale_business',
    parentModuleId: 'sales',
    marketplaceSlug: 'bale-business-module',
    defaultEnabled: true,
    menu: {
      id: 'bale-business',
      path: 'admin/integrations/bale',
      titleKey: 'nav.erp.sales.bale',
      icon: 'ri-robot-2-line',
    },
  },
]

export const ERP_MODULES: ErpModuleDef[] = [
  {
    id: 'hrm',
    settingsKey: 'hrm',
    basePath: '/hrm',
    sidebarCategoryKey: 'nav.module.hrm',
    defaultEnabled: true,
    menuItems: [
      {
        id: 'hrm-menu',
        path: 'hrm/staff',
        titleKey: 'nav.module.hrm',
        icon: 'ri-user-star-line',
        children: [
          { id: 'staff', path: 'hrm/staff', titleKey: 'nav.erp.hrm.staff', icon: 'ri-user-star-line' },
          { id: 'hrm-attendance', path: 'hrm/attendance', titleKey: 'nav.erp.hrm.attendance', icon: 'ri-calendar-check-line' },
          { id: 'hrm-leave', path: 'hrm/leave', titleKey: 'nav.erp.hrm.leave', icon: 'ri-calendar-event-line' },
          { id: 'hrm-payroll', path: 'hrm/payroll', titleKey: 'nav.erp.hrm.payroll', icon: 'ri-money-dollar-circle-line' },
          { id: 'hrm-payroll-decrees', path: 'hrm/payroll/decrees', titleKey: 'nav.erp.hrm.decrees', icon: 'ri-file-list-3-line' },
          { id: 'hrm-my-payroll', path: 'hrm/my-payroll', titleKey: 'nav.erp.hrm.myPayroll', icon: 'ri-wallet-3-line' },
          { id: 'hrm-me', path: 'hrm/me', titleKey: 'nav.erp.hrm.portal', icon: 'ri-dashboard-line' },
          { id: 'hrm-my-time', path: 'hrm/my-time', titleKey: 'nav.erp.hrm.myTime', icon: 'ri-time-line' },
          { id: 'hrm-my-docs', path: 'hrm/my-docs', titleKey: 'nav.erp.hrm.myDocs', icon: 'ri-file-text-line' },
          { id: 'hrm-my-insurance', path: 'hrm/my-insurance', titleKey: 'nav.erp.hrm.myInsurance', icon: 'ri-shield-user-line' },
          { id: 'hrm-my-org', path: 'hrm/my-org', titleKey: 'nav.erp.hrm.myOrg', icon: 'ri-building-line' },
          { id: 'hrm-my-profile', path: 'hrm/my-profile', titleKey: 'nav.erp.hrm.myProfile', icon: 'ri-user-settings-line' },
          { id: 'hrm-cartable', path: 'hrm/cartable', titleKey: 'nav.erp.hrm.cartable', icon: 'ri-inbox-line' },
          { id: 'hrm-recruitment', path: 'hrm/recruitment', titleKey: 'nav.erp.hrm.recruitment', icon: 'ri-user-add-line' },
          { id: 'hrm-performance', path: 'hrm/performance', titleKey: 'nav.erp.hrm.performance', icon: 'ri-bar-chart-box-line' },
          { id: 'hrm-training', path: 'hrm/training', titleKey: 'nav.erp.hrm.training', icon: 'ri-graduation-cap-line' },
        ],
      },
    ],
    legacyRedirects: [{ from: 'staff', to: 'hrm/staff' }, { from: 'staff/*', to: 'hrm/staff' }],
  },
  {
    id: 'finance',
    settingsKey: 'finance',
    legacySettingsKeys: ['accounting'],
    basePath: '/finance',
    sidebarCategoryKey: 'nav.module.finance',
    defaultEnabled: true,
    menuItems: [
      {
        id: 'finance-menu',
        path: 'finance',
        titleKey: 'nav.module.finance',
        icon: 'ri-calculator-line',
        children: [
          { id: 'accounting', path: 'finance', titleKey: 'nav.erp.finance.dashboard', icon: 'ri-calculator-line' },
          { id: 'accounting-persons', path: 'finance/persons', titleKey: 'nav.erp.finance.persons', icon: 'ri-user-line' },
          { id: 'accounting-products', path: 'finance/products', titleKey: 'nav.erp.finance.products', icon: 'ri-box-3-line' },
          { id: 'accounting-invoices', path: 'finance/invoices', titleKey: 'nav.erp.finance.invoices', icon: 'ri-file-list-3-line' },
          { id: 'accounting-cash-accounts', path: 'finance/cash-accounts', titleKey: 'nav.erp.finance.cashAccounts', icon: 'ri-bank-line' },
          { id: 'accounting-receipts', path: 'finance/receipts', titleKey: 'nav.erp.finance.receipts', icon: 'ri-exchange-dollar-line' },
          { id: 'accounting-checks', path: 'finance/checks', titleKey: 'nav.erp.finance.checks', icon: 'ri-bank-card-line' },
          { id: 'accounting-chart', path: 'finance/chart', titleKey: 'nav.erp.finance.chart', icon: 'ri-book-open-line' },
          { id: 'accounting-journals', path: 'finance/journals', titleKey: 'nav.erp.finance.journals', icon: 'ri-file-list-3-line' },
          { id: 'accounting-ledger', path: 'finance/ledger', titleKey: 'nav.erp.finance.ledger', icon: 'ri-book-2-line' },
          { id: 'accounting-reports', path: 'finance/reports', titleKey: 'nav.erp.finance.reports', icon: 'ri-bar-chart-2-line' },
          { id: 'accounting-fiscal-year', path: 'finance/fiscal-year', titleKey: 'nav.erp.finance.fiscalYear', icon: 'ri-calendar-line' },
          { id: 'accounting-settings', path: 'finance/settings', titleKey: 'nav.erp.finance.settings', icon: 'ri-settings-3-line' },
        ],
      },
    ],
    legacyRedirects: [
      { from: 'accounting', to: 'finance' },
      { from: 'accounting/*', to: 'finance' },
      { from: 'accounting/chart', to: 'finance/chart' },
      { from: 'accounting/journals', to: 'finance/journals' },
      { from: 'accounting/ledger', to: 'finance/ledger' },
      { from: 'accounting/reports', to: 'finance/reports' },
      { from: 'accounting/fiscal-year', to: 'finance/fiscal-year' },
      { from: 'accounting/settings', to: 'finance/settings' },
      { from: 'accounting/persons', to: 'finance/persons' },
      { from: 'accounting/products', to: 'finance/products' },
      { from: 'accounting/invoices', to: 'finance/invoices' },
      { from: 'accounting/cash-accounts', to: 'finance/cash-accounts' },
      { from: 'accounting/receipts', to: 'finance/receipts' },
      { from: 'accounting/checks', to: 'finance/checks' },
      { from: 'accounting/warehouses', to: 'scm/warehouses' },
      { from: 'accounting/warehouse-stock', to: 'scm/stock' },
      { from: 'accounting/warehouse-inbound', to: 'scm/inbound' },
      { from: 'accounting/warehouse-outbound', to: 'scm/outbound' },
      { from: 'accounting/warehouse-audit', to: 'scm/audit' },
      { from: 'settings/accounting/:tab?', to: 'admin/settings/accounting/:tab?' },
      { from: 'settings/payment', to: 'admin/settings/accounting/payment' },
    ],
  },
  {
    id: 'crm',
    settingsKey: 'crm',
    basePath: '/crm',
    sidebarCategoryKey: 'nav.module.crm',
    defaultEnabled: true,
    menuItems: [
      {
        id: 'crm-menu',
        path: 'crm/leads',
        titleKey: 'nav.module.crm',
        icon: 'ri-user-add-line',
        children: [
          { id: 'leads', path: 'crm/leads', titleKey: 'nav.erp.crm.leads', icon: 'ri-user-add-line' },
          { id: 'customers', path: 'crm/customers', titleKey: 'nav.erp.crm.customers', icon: 'ri-group-line' },
          { id: 'tickets', path: 'crm/tickets', titleKey: 'nav.erp.crm.tickets', icon: 'ri-customer-service-2-line' },
          { id: 'consultations', path: 'crm/consultations', titleKey: 'nav.erp.crm.consultations', icon: 'ri-discuss-line' },
        ],
      },
    ],
    legacyRedirects: [
      { from: 'leads', to: 'crm/leads' },
      { from: 'customers', to: 'crm/customers' },
      { from: 'tickets', to: 'crm/tickets' },
      { from: 'tickets/*', to: 'crm/tickets' },
      { from: 'consultations', to: 'crm/consultations' },
    ],
  },
  {
    id: 'pm',
    settingsKey: 'pm',
    legacySettingsKeys: ['projects'],
    basePath: '/pm',
    sidebarCategoryKey: 'nav.module.pm',
    defaultEnabled: true,
    menuItems: [
      {
        id: 'pm-menu',
        path: 'pm/projects',
        titleKey: 'nav.erp.pm.group',
        icon: 'ri-folder-2-line',
        children: [
          { id: 'projects', path: 'pm/projects', titleKey: 'nav.erp.pm.projects', icon: 'ri-folder-2-line' },
          { id: 'tasks', path: 'pm/tasks', titleKey: 'nav.erp.pm.tasks', icon: 'ri-task-line' },
          { id: 'chat', path: 'pm/chat', titleKey: 'nav.erp.pm.chat', icon: 'ri-message-2-line' },
          { id: 'time-tracking', path: 'pm/time-tracking', titleKey: 'nav.erp.pm.timeTracking', icon: 'ri-time-line' },
          { id: 'appointments', path: 'pm/appointments', titleKey: 'nav.erp.pm.appointments', icon: 'ri-calendar-check-line' },
        ],
      },
    ],
    legacyRedirects: [
      { from: 'projects', to: 'pm/projects' },
      { from: 'projects/*', to: 'pm/projects' },
      { from: 'tasks', to: 'pm/tasks' },
      { from: 'tasks/*', to: 'pm/tasks' },
      { from: 'chat', to: 'pm/chat' },
      { from: 'time-tracking', to: 'pm/time-tracking' },
      { from: 'appointments', to: 'pm/appointments' },
      { from: 'appointments/*', to: 'pm/appointments' },
    ],
  },
  {
    id: 'scm',
    settingsKey: 'scm',
    basePath: '/scm',
    sidebarCategoryKey: 'nav.module.scm',
    defaultEnabled: true,
    menuItems: [
      {
        id: 'scm-menu',
        path: 'scm/warehouses',
        titleKey: 'nav.erp.scm.group',
        icon: 'ri-building-4-line',
        children: [
          { id: 'accounting-warehouses', path: 'scm/warehouses', titleKey: 'nav.erp.scm.warehouses', icon: 'ri-building-4-line' },
          { id: 'accounting-warehouse-stock', path: 'scm/stock', titleKey: 'nav.erp.scm.stock', icon: 'ri-stack-line' },
          { id: 'accounting-warehouse-inbound', path: 'scm/inbound', titleKey: 'nav.erp.scm.inbound', icon: 'ri-inbox-archive-line' },
          { id: 'accounting-warehouse-outbound', path: 'scm/outbound', titleKey: 'nav.erp.scm.outbound', icon: 'ri-inbox-unarchive-line' },
          { id: 'accounting-warehouse-audit', path: 'scm/audit', titleKey: 'nav.erp.scm.audit', icon: 'ri-file-search-line' },
        ],
      },
    ],
    legacyRedirects: [],
  },
  {
    id: 'sales',
    settingsKey: 'sales',
    basePath: '/sales',
    sidebarCategoryKey: 'nav.module.sales',
    defaultEnabled: true,
    menuItems: [
      {
        id: 'sales-menu',
        path: 'sales/invoices',
        titleKey: 'nav.erp.sales.group',
        icon: 'ri-file-list-3-line',
        children: [
          { id: 'invoices', path: 'sales/invoices', titleKey: 'nav.erp.sales.invoices', icon: 'ri-file-list-3-line' },
          { id: 'services', path: 'sales/catalog', titleKey: 'nav.erp.sales.catalog', icon: 'ri-service-line' },
          { id: 'rahn-percent', path: 'sales/rahn-percent', titleKey: 'nav.erp.sales.rahnPercent', icon: 'ri-percent-line' },
          { id: 'campaigns', path: 'sales/campaigns', titleKey: 'nav.erp.sales.campaigns', icon: 'ri-megaphone-line' },
        ],
      },
    ],
    legacyRedirects: [
      { from: 'invoices', to: 'sales/invoices' },
      { from: 'invoices/*', to: 'sales/invoices' },
      { from: 'services', to: 'sales/catalog' },
      { from: 'services/*', to: 'sales/catalog' },
      { from: 'campaigns', to: 'sales/campaigns' },
      { from: 'campaigns/*', to: 'sales/campaigns' },
    ],
  },
  {
    id: 'mfg',
    settingsKey: 'mfg',
    basePath: '/mfg',
    sidebarCategoryKey: 'nav.module.mfg',
    defaultEnabled: false,
    menuItems: [{ id: 'mfg-overview', path: 'mfg', titleKey: 'nav.erp.mfg.overview', icon: 'ri-settings-5-line' }],
    legacyRedirects: [],
  },
  {
    id: 'docs',
    settingsKey: 'docs',
    basePath: '/docs',
    sidebarCategoryKey: 'nav.module.docs',
    defaultEnabled: true,
    menuItems: [
      {
        id: 'docs-menu',
        path: 'docs/contracts',
        titleKey: 'nav.erp.docs.group',
        icon: 'ri-file-text-line',
        children: [
          { id: 'contracts', path: 'docs/contracts', titleKey: 'nav.erp.docs.contracts', icon: 'ri-file-text-line' },
          { id: 'documents', path: 'docs/files', titleKey: 'nav.erp.docs.files', icon: 'ri-folder-line' },
        ],
      },
    ],
    legacyRedirects: [
      { from: 'contracts', to: 'docs/contracts' },
      { from: 'contracts/*', to: 'docs/contracts' },
      { from: 'documents', to: 'docs/files' },
    ],
  },
  {
    id: 'distribution',
    settingsKey: 'distribution',
    basePath: '/admin/marketplace',
    sidebarCategoryKey: 'nav.module.distribution',
    defaultEnabled: true,
    menuItems: [
      {
        id: 'distribution-menu',
        path: 'admin/marketplace/products',
        titleKey: 'nav.erp.distribution.group',
        icon: 'ri-store-2-line',
        children: [
          { id: 'marketplace-products', path: 'admin/marketplace/products', titleKey: 'nav.erp.distribution.marketplaceProducts', icon: 'ri-store-2-line' },
          { id: 'marketplace-gitea', path: 'admin/marketplace/gitea', titleKey: 'nav.erp.distribution.marketplaceGitea', icon: 'ri-git-branch-line' },
          { id: 'marketplace-categories', path: 'admin/marketplace/categories', titleKey: 'nav.erp.distribution.marketplaceCategories', icon: 'ri-folder-line' },
          { id: 'marketplace-orders', path: 'admin/marketplace/orders', titleKey: 'nav.erp.distribution.marketplaceOrders', icon: 'ri-shopping-bag-line' },
          { id: 'licenses', path: 'admin/licenses', titleKey: 'nav.erp.distribution.licenses', icon: 'ri-key-2-line' },
        ],
      },
    ],
    legacyRedirects: [
      { from: 'marketplace/*', to: 'admin/marketplace' },
      { from: 'marketplace/products', to: 'admin/marketplace/products' },
      { from: 'marketplace/categories', to: 'admin/marketplace/categories' },
      { from: 'marketplace/gitea', to: 'admin/marketplace/gitea' },
      { from: 'marketplace/orders', to: 'admin/marketplace/orders' },
      { from: 'marketplace/modules/:id', to: 'admin/marketplace/modules/:id' },
      { from: 'licenses', to: 'admin/licenses' },
    ],
  },
  {
    id: 'ai_content',
    settingsKey: 'ai_content',
    basePath: '/ai-content',
    sidebarCategoryKey: 'nav.module.ai_content',
    defaultEnabled: true,
    menuItems: [
      {
        id: 'ai-content-menu',
        path: 'ai-content',
        titleKey: 'nav.erp.aiContent.group',
        icon: 'ri-sparkling-line',
        children: [
          { id: 'ai-content', path: 'ai-content', titleKey: 'nav.erp.aiContent.overview', icon: 'ri-dashboard-line' },
          { id: 'ai-content-jobs', path: 'ai-content/jobs', titleKey: 'nav.erp.aiContent.jobs', icon: 'ri-list-check-2' },
          { id: 'ai-content-calendar', path: 'ai-content/calendar', titleKey: 'nav.erp.aiContent.calendar', icon: 'ri-calendar-line' },
          { id: 'ai-content-products', path: 'ai-content/products', titleKey: 'nav.erp.aiContent.products', icon: 'ri-shopping-bag-line' },
          { id: 'ai-content-titles', path: 'ai-content/titles', titleKey: 'nav.erp.aiContent.titles', icon: 'ri-text' },
          { id: 'ai-content-pages', path: 'ai-content/pages', titleKey: 'nav.erp.aiContent.pages', icon: 'ri-file-list-3-line' },
          { id: 'ai-content-taxonomies', path: 'ai-content/taxonomies', titleKey: 'nav.erp.aiContent.taxonomies', icon: 'ri-folder-line' },
          { id: 'ai-content-attributes', path: 'ai-content/attributes', titleKey: 'nav.erp.aiContent.attributes', icon: 'ri-price-tag-3-line' },
          { id: 'ai-content-cms-pages', path: 'pages', titleKey: 'nav.erp.aiContent.cmsPages', icon: 'ri-pages-line' },
          { id: 'ai-content-settings', path: 'ai-content/settings', titleKey: 'nav.erp.aiContent.settings', icon: 'ri-settings-3-line' },
        ],
      },
    ],
    legacyRedirects: [],
  },
  {
    id: 'admin',
    settingsKey: 'admin',
    legacySettingsKeys: ['general', 'bots'],
    basePath: '/admin',
    sidebarCategoryKey: 'nav.module.admin',
    defaultEnabled: true,
    menuItems: [
      {
        id: 'admin-menu',
        path: 'admin/settings',
        titleKey: 'nav.erp.admin.group',
        icon: 'ri-settings-3-line',
        children: [
          { id: 'logs', path: 'admin/logs', titleKey: 'nav.erp.admin.logs', icon: 'ri-file-list-2-line' },
          { id: 'visitor-statistics', path: 'admin/analytics/visitors', titleKey: 'nav.erp.admin.visitors', icon: 'ri-line-chart-line' },
          { id: 'settings', path: 'admin/settings', titleKey: 'nav.erp.admin.settings', icon: 'ri-settings-3-line' },
        ],
      },
    ],
    legacyRedirects: [
      { from: 'bale-business', to: 'admin/integrations/bale' },
      { from: 'bots/business', to: 'admin/integrations/bale' },
      { from: 'modirpayamak', to: 'admin/integrations/modirpayamak' },
      { from: 'modirpayamak/*', to: 'admin/integrations/modirpayamak' },
      { from: 'logs', to: 'admin/logs' },
      { from: 'visitor-statistics', to: 'admin/analytics/visitors' },
      { from: 'settings', to: 'admin/settings' },
      { from: 'settings/*', to: 'admin/settings' },
    ],
  },
]

export function getErpSubmodule(id: string): ErpSubModuleDef | undefined {
  return ERP_SUBMODULES.find((s) => s.id === id || s.settingsKey === id)
}

export function submoduleKeyForMenuId(menuId: string): string {
  if (menuId === 'bale-business') return 'bale_business'
  if (menuId === 'modirpayamak' || menuId.startsWith('modirpayamak-')) return 'modirpayamak'
  return ''
}

export function getErpModule(id: string): ErpModuleDef | undefined {
  return ERP_MODULES.find((m) => m.id === id)
}

export function erpCapability(menuId: string): string {
  return 'webinocrm_route_' + (menuId || 'home')
}

/** All legacy redirect pairs (flattened, longest paths should be registered first in App). */
export function getAllLegacyRedirects(): Array<{ from: string; to: string }> {
  const out: Array<{ from: string; to: string }> = []
  for (const mod of ERP_MODULES) {
    out.push(...mod.legacyRedirects)
  }
  return out
}

export function modulePath(moduleId: string, segment = ''): string {
  const mod = getErpModule(moduleId)
  if (!mod) return '/' + segment
  const base = mod.basePath.replace(/^\//, '')
  if (!segment) return '/' + base
  return '/' + base + '/' + segment.replace(/^\//, '')
}
