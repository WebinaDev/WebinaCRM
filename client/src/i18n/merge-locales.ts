/** Flatten nested locale JSON into dot-notation keys for i18next. */
export function flattenLocaleTree(
  obj: Record<string, unknown>,
  prefix = '',
): Record<string, string> {
  const out: Record<string, string> = {}
  for (const [key, value] of Object.entries(obj)) {
    const fullKey = prefix ? `${prefix}.${key}` : key
    if (typeof value === 'string') {
      out[fullKey] = value
    } else if (value && typeof value === 'object' && !Array.isArray(value)) {
      Object.assign(out, flattenLocaleTree(value as Record<string, unknown>, fullKey))
    }
  }
  return out
}

/**
 * Sidebar module ids from PHP (kebab-case) → layout.* keys when naming differs.
 * Ensures nav.module.{phpId} resolves in English without PHP title fallback.
 */
export const NAV_MODULE_LAYOUT_ALIASES: Record<string, string> = {
  'modirpayamak-send': 'modirpayamakSend',
  'modirpayamak-reports': 'modirpayamakReports',
  'modirpayamak-customers': 'modirpayamakCustomers',
  'modirpayamak-packages': 'modirpayamakPackages',
  'modirpayamak-tariffs': 'modirpayamakTariffs',
  'modirpayamak-orders': 'modirpayamakOrders',
  'modirpayamak-patterns': 'modirpayamakPatterns',
  'modirpayamak-phonebooks': 'modirpayamakPhonebooks',
  'modirpayamak-numbers': 'modirpayamakNumbers',
  'modirpayamak-settings': 'modirpayamakSettings',
  'accounting-persons': 'accountingPersons',
  'accounting-products': 'accountingProducts',
  'accounting-invoices': 'accountingInvoices',
  'accounting-cash-accounts': 'accountingCashAccounts',
  'accounting-receipts': 'accountingReceipts',
  'accounting-checks': 'accountingChecks',
  'accounting-chart': 'accountingChart',
  'accounting-journals': 'accountingJournals',
  'accounting-ledger': 'accountingLedger',
  'accounting-reports': 'accountingReports',
  'accounting-fiscal-year': 'accountingFiscalYear',
  'accounting-settings': 'accountingSettings',
  'accounting-warehouses': 'accountingWarehouses',
  'accounting-warehouse-stock': 'accountingWarehouseStock',
  'accounting-warehouse-inbound': 'accountingWarehouseInbound',
  'accounting-warehouse-outbound': 'accountingWarehouseOutbound',
  'accounting-warehouse-audit': 'accountingWarehouseAudit',
  'bale-business': 'baleBusiness',
  'visitor-statistics': 'visitorStatistics',
}

/** Map PHP sidebar module id (often kebab-case) to layout.* key in locales-crm. */
export function resolveLayoutNavKey(moduleId: string): string {
  return NAV_MODULE_LAYOUT_ALIASES[moduleId] ?? moduleId
}

/** Merge shell + CRM bundles; layout.* aliases to nav.module.* */
export function mergeDashboardLocaleBundles(
  shell: Record<string, string>,
  crm: Record<string, unknown>,
  pages: Record<string, unknown>,
): Record<string, string> {
  const flatCrm = flattenLocaleTree(crm)
  const flatPages = flattenLocaleTree(pages, 'pages')
  const merged: Record<string, string> = { ...shell, ...flatCrm, ...flatPages }

  for (const [key, value] of Object.entries(flatCrm)) {
    if (key.startsWith('layout.')) {
      merged[`nav.module.${key.slice('layout.'.length)}`] = value
    }
  }

  for (const [moduleId, layoutKey] of Object.entries(NAV_MODULE_LAYOUT_ALIASES)) {
    const value = flatCrm[`layout.${layoutKey}`]
    if (value) {
      merged[`nav.module.${moduleId}`] = value
      merged[`layout.${moduleId}`] = value
    }
  }

  return merged
}
