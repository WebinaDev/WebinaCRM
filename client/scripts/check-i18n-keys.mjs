/**
 * Verify CRM i18n key parity (fa/en) and that t() keys exist in merged bundles.
 * Usage: node scripts/check-i18n-keys.mjs
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import {
  ERP_NAV_CATEGORY_IDS,
  ERP_NAV_MODULE_IDS,
} from './erp-nav-module-ids.mjs'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const clientDir = path.resolve(__dirname, '..')
const srcDir = path.join(clientDir, 'src')

function flattenLocaleTree(obj, prefix = '') {
  const out = {}
  for (const [key, value] of Object.entries(obj)) {
    const fullKey = prefix ? `${prefix}.${key}` : key
    if (typeof value === 'string') {
      out[fullKey] = value
    } else if (value && typeof value === 'object' && !Array.isArray(value)) {
      Object.assign(out, flattenLocaleTree(value, fullKey))
    }
  }
  return out
}

/** @type {Record<string, string>} */
const NAV_MODULE_LAYOUT_ALIASES = {
  'modirpayamak-send': 'modirpayamakSend',
  'modirpayamak-reports': 'modirpayamakReports',
  'modirpayamak-customers': 'modirpayamakCustomers',
  'modirpayamak-packages': 'modirpayamakPackages',
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

function resolveLayoutNavKey(moduleId) {
  return NAV_MODULE_LAYOUT_ALIASES[moduleId] ?? moduleId
}

function mergeBundles(shell, crm, pages) {
  const flatCrm = flattenLocaleTree(crm)
  const flatPages = flattenLocaleTree(pages, 'pages')
  const merged = { ...shell, ...flatCrm, ...flatPages }
  for (const [key, value] of Object.entries(flatCrm)) {
    if (key.startsWith('layout.')) {
      merged[`nav.module.${key.slice(7)}`] = value
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

function loadMerged(lng) {
  const shell = JSON.parse(
    fs.readFileSync(path.join(srcDir, 'i18n/locales', `${lng}.json`), 'utf8'),
  )
  const crm = JSON.parse(fs.readFileSync(path.join(srcDir, 'locales-crm', `${lng}.json`), 'utf8'))
  const pagesFile = lng === 'fa' ? 'pages-fa.json' : 'pages-en.json'
  const pages = JSON.parse(fs.readFileSync(path.join(srcDir, 'locales-crm', pagesFile), 'utf8'))
  return mergeBundles(shell, crm, pages)
}

function walkTsx(dir, files = []) {
  if (!fs.existsSync(dir)) return files
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name)
    if (ent.isDirectory()) walkTsx(p, files)
    else if (ent.name.endsWith('.tsx') || ent.name.endsWith('.ts')) files.push(p)
  }
  return files
}

function extractTKeys(content) {
  const keys = new Set()
  const re = /\bt\(\s*['"]([^'"]+)['"]/g
  let m
  while ((m = re.exec(content))) {
    const k = m[1].trim()
    if (
      k &&
      !k.endsWith('.') &&
      !k.includes('/') &&
      !k.startsWith('http') &&
      !k.includes('${')
    ) {
      keys.add(k)
    }
  }
  return keys
}

const scanDirs = [
  path.join(srcDir, 'pages/crm'),
  path.join(srcDir, 'components/tasks'),
  path.join(srcDir, 'layouts'),
]

const used = new Set()
for (const dir of scanDirs) {
  for (const file of walkTsx(dir)) {
    for (const k of extractTKeys(fs.readFileSync(file, 'utf8'))) used.add(k)
  }
}

const bundleFa = loadMerged('fa')
const bundleEn = loadMerged('en')

const faKeys = new Set(Object.keys(bundleFa))
const enKeys = new Set(Object.keys(bundleEn))

const missingInEn = [...faKeys].filter((k) => !enKeys.has(k))
const missingInFa = [...enKeys].filter((k) => !faKeys.has(k))
const missingFa = [...used].filter((k) => !faKeys.has(k))
const missingEn = [...used].filter((k) => !enKeys.has(k))

let failed = false

if (missingInEn.length) {
  failed = true
  console.error(`[i18n] ${missingInEn.length} keys in fa bundle missing from en`)
  console.error(missingInEn.slice(0, 20).join('\n'))
}

if (missingInFa.length) {
  failed = true
  console.error(`[i18n] ${missingInFa.length} keys in en bundle missing from fa`)
  console.error(missingInFa.slice(0, 20).join('\n'))
}

if (missingFa.length) {
  failed = true
  console.error(`[i18n] ${missingFa.length} t() keys missing from fa bundle`)
  console.error(missingFa.slice(0, 30).join('\n'))
}

if (missingEn.length) {
  failed = true
  console.error(`[i18n] ${missingEn.length} t() keys missing from en bundle`)
  console.error(missingEn.slice(0, 30).join('\n'))
}

const erpSidebarIds = [...ERP_NAV_CATEGORY_IDS, ...ERP_NAV_MODULE_IDS]

const missingLayoutFa = []
const missingLayoutEn = []
for (const moduleId of erpSidebarIds) {
  const layoutKey = resolveLayoutNavKey(moduleId)
  const key = `layout.${layoutKey}`
  if (!faKeys.has(key)) missingLayoutFa.push(key)
  if (!enKeys.has(key)) missingLayoutEn.push(key)
}

if (missingLayoutFa.length) {
  failed = true
  console.error(`[i18n] ${missingLayoutFa.length} ERP sidebar layout keys missing from fa`)
  console.error(missingLayoutFa.join('\n'))
}

if (missingLayoutEn.length) {
  failed = true
  console.error(`[i18n] ${missingLayoutEn.length} ERP sidebar layout keys missing from en`)
  console.error(missingLayoutEn.join('\n'))
}

if (!failed) {
  console.log(
    `[i18n] OK — ${used.size} t() keys, fa=${faKeys.size} en=${enKeys.size} bundle keys, ${erpSidebarIds.length} ERP sidebar layout keys`,
  )
  process.exit(0)
}

process.exit(1)
