/**
 * Accounting module API – fiscal years, chart of accounts, journals, reports.
 */

import { crmPost } from "./client"

const acc = (suffix: string) => `accounting/${suffix.replace(/_/g, "-")}`

export interface FiscalYear {
  id: number
  name: string
  start_date: string
  end_date: string
  is_active: number
  created_at: string
  updated_at: string
}

export interface ChartAccount {
  id: number
  code: string
  title: string
  level: number
  parent_id: number
  account_type: string
  fiscal_year_id: number
  is_system: number
  sort_order: number
}

export interface JournalEntry {
  id: number
  fiscal_year_id: number
  voucher_no: string
  voucher_date: string
  description: string | null
  reference_type: string | null
  reference_id: number | null
  created_by: number
  created_at: string
  updated_at: string
  status: string
}

export interface JournalLine {
  id: number
  journal_entry_id: number
  account_id: number
  debit: number
  credit: number
  description: string | null
  sort_order: number
}

export async function accountingFiscalYears() {
  return crmPost<{ items: FiscalYear[] }>(acc("fiscal_years"), {})
}

export async function accountingChartList(fiscalYearId?: number) {
  const params: Record<string, string | number> = {}
  if (fiscalYearId) params.fiscal_year_id = fiscalYearId
  return crmPost<{ items: ChartAccount[] }>(acc("chart_list"), params)
}

export async function accountingJournalList(params: {
  fiscal_year_id?: number
  status?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}) {
  const p: Record<string, string | number> = {}
  Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== "") p[k] = v })
  return crmPost<{ items: JournalEntry[]; total: number }>(acc("journal_list"), p)
}

export async function accountingJournalGet(id: number) {
  return crmPost<{ entry: JournalEntry; lines: JournalLine[] }>(acc("journal_get"), { id })
}

export type JournalLineInput = {
  account_id: number
  debit: number
  credit: number
  description?: string
}

export async function accountingFiscalYearSave(params: {
  id?: number
  name: string
  start_date: string
  end_date: string
  is_active?: number | boolean
}) {
  const p: Record<string, string | number> = {
    name: params.name,
    start_date: params.start_date,
    end_date: params.end_date,
    is_active: params.is_active ? 1 : 0,
  }
  if (params.id) p.id = params.id
  return crmPost<{ id?: number; updated?: boolean }>(acc("fiscal_year_save"), p)
}

export async function accountingFiscalYearDelete(id: number) {
  return crmPost(acc("fiscal_year_delete"), { id })
}

export async function accountingChartSave(params: {
  id?: number
  code: string
  title: string
  level: number
  parent_id: number
  account_type: string
  fiscal_year_id: number
  sort_order?: number
}) {
  const p: Record<string, string | number> = {
    code: params.code,
    title: params.title,
    level: params.level,
    parent_id: params.parent_id,
    account_type: params.account_type,
    fiscal_year_id: params.fiscal_year_id,
  }
  if (params.id) p.id = params.id
  if (params.sort_order !== undefined) p.sort_order = params.sort_order
  return crmPost<{ id?: number; updated?: boolean }>(acc("chart_save"), p)
}

export async function accountingChartDelete(id: number) {
  return crmPost(acc("chart_delete"), { id })
}

export async function accountingJournalSave(params: {
  id?: number
  fiscal_year_id: number
  voucher_date: string
  description?: string
  lines: JournalLineInput[]
}) {
  const p: Record<string, string | number> = {
    fiscal_year_id: params.fiscal_year_id,
    voucher_date: params.voucher_date,
    description: params.description ?? "",
    lines: JSON.stringify(params.lines),
  }
  if (params.id) p.id = params.id
  return crmPost<{ id?: number; updated?: boolean }>(acc("journal_save"), p)
}

export async function accountingJournalPost(id: number) {
  return crmPost(acc("journal_post"), { id })
}

export async function accountingJournalDelete(id: number) {
  return crmPost(acc("journal_delete"), { id })
}

export async function accountingReportTrialBalance(fiscalYearId: number, dateFrom?: string, dateTo?: string) {
  return crmPost(acc("report_trial_balance"), {
    fiscal_year_id: fiscalYearId,
    date_from: dateFrom,
    date_to: dateTo,
  })
}

export async function accountingReportBalanceSheet(fiscalYearId: number, asOfDate?: string) {
  return crmPost(acc("report_balance_sheet"), { fiscal_year_id: fiscalYearId, as_of_date: asOfDate })
}

export async function accountingReportProfitLoss(fiscalYearId: number, dateFrom?: string, dateTo?: string) {
  return crmPost(acc("report_profit_loss"), {
    fiscal_year_id: fiscalYearId,
    date_from: dateFrom,
    date_to: dateTo,
  })
}

export async function accountingLedger(accountId: number, fiscalYearId: number, dateFrom?: string, dateTo?: string) {
  return crmPost(acc("ledger"), {
    account_id: accountId,
    fiscal_year_id: fiscalYearId,
    date_from: dateFrom,
    date_to: dateTo,
  })
}

export async function accountingSettingsGet() {
  return crmPost<{ currency?: string; fiscal_year_id?: number }>(acc("settings_get"))
}

export async function accountingSettingsSave(settings: { currency?: string; fiscal_year_id?: number }) {
  const p: Record<string, string | number> = {}
  if (settings.currency !== undefined) p.currency = settings.currency
  if (settings.fiscal_year_id !== undefined) p.fiscal_year_id = settings.fiscal_year_id
  return crmPost(acc("settings_save"), p)
}

export async function accountingSeedChart(fiscalYearId?: number) {
  return crmPost<{ count?: number }>(acc("seed_chart"), fiscalYearId ? { fiscal_year_id: fiscalYearId } : {})
}

// --- Phase 1: Persons (اشخاص) ---
export interface PersonCategory {
  id: number
  name: string
  parent_id: number
  sort_order: number
}
export interface Person {
  id: number
  code: string | null
  name: string
  category_id: number | null
  credit_limit: number | null
  national_id: string | null
  economic_code: string | null
  registration_no: string | null
  phone: string | null
  mobile: string | null
  extra_phones: string | null
  address: string | null
  person_type: "customer" | "supplier" | "both"
  group_id: number | null
  image_url: string | null
  note: string | null
  default_price_list_id: number | null
  is_active: number
  created_at: string
  updated_at: string
}

export function accountingPersonCategories(tree?: boolean) {
  return crmPost<{ items: PersonCategory[] }>(acc("person_categories"), tree ? { tree: 1 } : {})
}

export function accountingPersonCategorySave(params: { id?: number; name: string; parent_id?: number; sort_order?: number }) {
  const p: Record<string, string | number> = { name: params.name }
  if (params.id) p.id = params.id
  if (params.parent_id != null) p.parent_id = params.parent_id
  if (params.sort_order != null) p.sort_order = params.sort_order
  return crmPost<{ id?: number; updated?: boolean }>(acc("person_category_save"), p)
}

export function accountingPersonCategoryDelete(id: number) {
  return crmPost(acc("person_category_delete"), { id })
}

export function accountingPersonsList(params: {
  category_id?: number
  person_type?: string
  search?: string
  is_active?: number
  page?: number
  per_page?: number
  orderby?: string
  order?: string
}) {
  const p: Record<string, string | number> = {}
  Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== "") (p as Record<string, string | number>)[k] = v })
  return crmPost<{ items: Person[]; total: number }>(acc("persons_list"), p)
}

export function accountingPersonGet(id: number) {
  return crmPost<{ person: Person }>(acc("person_get"), { id })
}

export function accountingPersonSave(params: Partial<Person> & { name: string }) {
  const p: Record<string, string | number | undefined> = { name: params.name }
  if (params.id) p.id = params.id
  if (params.code !== undefined) p.code = params.code ?? ""
  if (params.category_id !== undefined) p.category_id = params.category_id ?? 0
  if (params.credit_limit !== undefined) p.credit_limit = params.credit_limit ?? 0
  if (params.national_id !== undefined) p.national_id = params.national_id ?? ""
  if (params.economic_code !== undefined) p.economic_code = params.economic_code ?? ""
  if (params.registration_no !== undefined) p.registration_no = params.registration_no ?? ""
  if (params.phone !== undefined) p.phone = params.phone ?? ""
  if (params.mobile !== undefined) p.mobile = params.mobile ?? ""
  if (params.extra_phones !== undefined) p.extra_phones = params.extra_phones ?? ""
  if (params.address !== undefined) p.address = params.address ?? ""
  if (params.person_type !== undefined) p.person_type = params.person_type
  if (params.group_id !== undefined) p.group_id = params.group_id ?? 0
  if (params.image_url !== undefined) p.image_url = params.image_url ?? ""
  if (params.note !== undefined) p.note = params.note ?? ""
  if (params.default_price_list_id !== undefined) p.default_price_list_id = params.default_price_list_id ?? 0
  if (params.is_active !== undefined) p.is_active = params.is_active
  return crmPost<{ id?: number; updated?: boolean }>(acc("person_save"), p as Record<string, string | number>)
}

export function accountingPersonDelete(id: number) {
  return crmPost(acc("person_delete"), { id })
}

// --- Phase 1: Product categories, Units, Price lists, Products ---
export interface ProductCategory {
  id: number
  name: string
  parent_id: number
  sort_order: number
}
export interface Unit {
  id: number
  name: string
  symbol: string | null
  is_main: number
  base_unit_id: number | null
  ratio_to_base: number
}
export interface PriceList {
  id: number
  name: string
  description: string | null
  is_default: number
  valid_from: string | null
  valid_to: string | null
}
export interface Product {
  id: number
  code: string
  name: string
  category_id: number | null
  main_unit_id: number
  sub_unit_id: number | null
  sub_unit_ratio: number
  purchase_price: number | null
  barcode: string | null
  inventory_controlled: number
  reorder_point: number | null
  tax_rate_sales: number | null
  tax_rate_purchase: number | null
  image_url: string | null
  note: string | null
  is_active: number
  created_at: string
  updated_at: string
}

export function accountingProductCategories(tree?: boolean) {
  return crmPost<{ items: ProductCategory[] }>(acc("product_categories"), tree ? { tree: 1 } : {})
}

export function accountingProductCategorySave(params: { id?: number; name: string; parent_id?: number; sort_order?: number }) {
  const p: Record<string, string | number> = { name: params.name }
  if (params.id) p.id = params.id
  if (params.parent_id != null) p.parent_id = params.parent_id
  if (params.sort_order != null) p.sort_order = params.sort_order
  return crmPost<{ id?: number; updated?: boolean }>(acc("product_category_save"), p)
}

export function accountingProductCategoryDelete(id: number) {
  return crmPost(acc("product_category_delete"), { id })
}

export function accountingUnitsList() {
  return crmPost<{ items: Unit[] }>(acc("units_list"), {})
}

export function accountingUnitSave(params: { id?: number; name: string; symbol?: string; is_main?: number; base_unit_id?: number; ratio_to_base?: number }) {
  const p: Record<string, string | number> = { name: params.name }
  if (params.id) p.id = params.id
  if (params.symbol !== undefined) p.symbol = params.symbol ?? ""
  if (params.is_main !== undefined) p.is_main = params.is_main
  if (params.base_unit_id !== undefined) p.base_unit_id = params.base_unit_id ?? 0
  if (params.ratio_to_base !== undefined) p.ratio_to_base = params.ratio_to_base
  return crmPost<{ id?: number; updated?: boolean }>(acc("unit_save"), p)
}

export function accountingUnitDelete(id: number) {
  return crmPost(acc("unit_delete"), { id })
}

export function accountingPriceLists() {
  return crmPost<{ items: PriceList[] }>(acc("price_lists"), {})
}

export function accountingPriceListGet(id: number) {
  return crmPost<{ price_list: PriceList; items: Record<string, { product_id: number; price: number; min_quantity: number }> }>(acc("price_list_get"), { id })
}

export function accountingPriceListSave(params: { id?: number; name: string; description?: string; is_default?: number; valid_from?: string; valid_to?: string }) {
  const p: Record<string, string | number> = { name: params.name }
  if (params.id) p.id = params.id
  if (params.description !== undefined) p.description = params.description ?? ""
  if (params.is_default !== undefined) p.is_default = params.is_default
  if (params.valid_from !== undefined) p.valid_from = params.valid_from ?? ""
  if (params.valid_to !== undefined) p.valid_to = params.valid_to ?? ""
  return crmPost<{ id?: number; updated?: boolean }>(acc("price_list_save"), p)
}

export function accountingPriceListDelete(id: number) {
  return crmPost(acc("price_list_delete"), { id })
}

export function accountingPriceListItems(priceListId: number) {
  return crmPost<{ items: Record<string, { product_id: number; price: number; min_quantity: number }> }>(acc("price_list_items"), { price_list_id: priceListId })
}

export function accountingPriceListItemsSave(priceListId: number, items: { product_id: number; price: number; min_quantity?: number }[]) {
  return crmPost(acc("price_list_items_save"), { price_list_id: priceListId, items: JSON.stringify(items) })
}

export function accountingProductsList(params: {
  category_id?: number
  search?: string
  is_active?: number
  page?: number
  per_page?: number
  orderby?: string
  order?: string
}) {
  const p: Record<string, string | number> = {}
  Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== "") (p as Record<string, string | number>)[k] = v })
  return crmPost<{ items: Product[]; total: number }>(acc("products_list"), p)
}

export function accountingProductGet(id: number) {
  return crmPost<{ product: Product }>(acc("product_get"), { id })
}

export function accountingProductSave(params: Partial<Product> & { code: string; name: string }) {
  const p: Record<string, string | number> = { code: params.code, name: params.name }
  if (params.id) p.id = params.id
  if (params.category_id !== undefined) p.category_id = params.category_id ?? 0
  if (params.main_unit_id !== undefined) p.main_unit_id = params.main_unit_id
  if (params.sub_unit_id !== undefined) p.sub_unit_id = params.sub_unit_id ?? 0
  if (params.sub_unit_ratio !== undefined) p.sub_unit_ratio = params.sub_unit_ratio
  if (params.purchase_price !== undefined) p.purchase_price = params.purchase_price ?? 0
  if (params.barcode !== undefined) p.barcode = params.barcode ?? ""
  if (params.inventory_controlled !== undefined) p.inventory_controlled = params.inventory_controlled ? 1 : 0
  if (params.reorder_point !== undefined) p.reorder_point = params.reorder_point ?? 0
  if (params.tax_rate_sales !== undefined) p.tax_rate_sales = params.tax_rate_sales ?? 0
  if (params.tax_rate_purchase !== undefined) p.tax_rate_purchase = params.tax_rate_purchase ?? 0
  if (params.image_url !== undefined) p.image_url = params.image_url ?? ""
  if (params.note !== undefined) p.note = params.note ?? ""
  if (params.is_active !== undefined) p.is_active = params.is_active ? 1 : 0
  return crmPost<{ id?: number; updated?: boolean }>(acc("product_save"), p)
}

export function accountingProductDelete(id: number) {
  return crmPost(acc("product_delete"), { id })
}

export function accountingUserDefaultsGet() {
  return crmPost<{ defaults: { default_invoice_person_id: number | null; default_price_list_id: number | null } }>(acc("user_defaults_get"), {})
}

export function accountingUserDefaultsSave(params: { default_invoice_person_id?: number | null; default_price_list_id?: number | null }) {
  const p: Record<string, number | string> = {}
  if (params.default_invoice_person_id !== undefined) p.default_invoice_person_id = params.default_invoice_person_id ?? 0
  if (params.default_price_list_id !== undefined) p.default_price_list_id = params.default_price_list_id ?? 0
  return crmPost(acc("user_defaults_save"), p)
}

// --- Phase 2: Invoices (فاکتورها) ---
export interface AccountingInvoice {
  id: number
  fiscal_year_id: number
  invoice_no: string
  invoice_type: "proforma" | "sales" | "purchase"
  person_id: number
  invoice_date: string
  due_date: string | null
  status: "draft" | "confirmed" | "returned"
  seller_id: number | null
  project_id: number | null
  shipping_cost: number | null
  extra_additions: number | null
  extra_deductions: number | null
  reference_type: string | null
  reference_id: number | null
  created_by: number
  created_at: string
  updated_at: string
}
export interface AccountingInvoiceLine {
  id?: number
  invoice_id?: number
  product_id: number
  quantity: number
  unit_id: number | null
  unit_price: number
  discount_percent: number | null
  discount_amount: number | null
  tax_percent: number | null
  tax_amount: number | null
  description: string | null
  sort_order?: number
}

export function accountingInvoiceList(params: {
  fiscal_year_id?: number
  person_id?: number
  invoice_type?: string
  status?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}) {
  const p: Record<string, string | number> = {}
  Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== "") (p as Record<string, string | number>)[k] = v })
  return crmPost<{ items: AccountingInvoice[]; total: number }>(acc("invoice_list"), p)
}

export function accountingInvoiceGet(id: number) {
  return crmPost<{ invoice: AccountingInvoice; lines: AccountingInvoiceLine[] }>(acc("invoice_get"), { id })
}

export function accountingInvoiceSave(params: {
  id?: number
  fiscal_year_id?: number
  invoice_no?: string
  invoice_type?: string
  person_id: number
  invoice_date: string
  due_date?: string | null
  seller_id?: number | null
  project_id?: number | null
  shipping_cost?: number | null
  extra_additions?: number | null
  extra_deductions?: number | null
  lines?: AccountingInvoiceLine[]
}) {
  const p: Record<string, string | number | undefined> = {
    person_id: params.person_id,
    invoice_date: params.invoice_date,
  }
  if (params.id) p.id = params.id
  if (params.fiscal_year_id) p.fiscal_year_id = params.fiscal_year_id
  if (params.invoice_no !== undefined) p.invoice_no = params.invoice_no
  if (params.invoice_type !== undefined) p.invoice_type = params.invoice_type
  if (params.due_date !== undefined) p.due_date = params.due_date ?? ""
  if (params.seller_id !== undefined) p.seller_id = params.seller_id ?? 0
  if (params.project_id !== undefined) p.project_id = params.project_id ?? 0
  if (params.shipping_cost !== undefined) p.shipping_cost = params.shipping_cost ?? 0
  if (params.extra_additions !== undefined) p.extra_additions = params.extra_additions ?? 0
  if (params.extra_deductions !== undefined) p.extra_deductions = params.extra_deductions ?? 0
  if (params.lines?.length) p.lines = JSON.stringify(params.lines)
  return crmPost<{ id?: number; updated?: boolean }>(acc("invoice_save"), p as Record<string, string | number>)
}

export function accountingInvoiceDelete(id: number) {
  return crmPost(acc("invoice_delete"), { id })
}

export function accountingInvoiceNextNumber(fiscalYearId?: number, invoiceType?: string) {
  const p: Record<string, string | number> = {}
  if (fiscalYearId) p.fiscal_year_id = fiscalYearId
  if (invoiceType) p.invoice_type = invoiceType
  return crmPost<{ invoice_no: string }>(acc("invoice_next_number"), p)
}

/** Confirm (post) a draft invoice. Phase 2. */
export function accountingInvoiceConfirm(id: number) {
  return crmPost(acc("invoice_confirm"), { id })
}

// --- Phase 3: Cash accounts (صندوق/بانک/تنخواه) and Receipt/Payment ---
export interface CashAccount {
  id: number
  name: string
  type: "bank" | "cash" | "petty"
  code: string | null
  description: string | null
  card_no: string | null
  sheba: string | null
  chart_account_id: number | null
  is_active: number
  sort_order: number
  created_at: string
  updated_at: string
}

export function accountingCashAccountsList(params?: { type?: string; is_active?: number }) {
  const p: Record<string, string | number> = {}
  if (params?.type) p.type = params.type
  if (params?.is_active !== undefined) p.is_active = params.is_active
  return crmPost<{ items: CashAccount[] }>(acc("cash_accounts_list"), p)
}

export function accountingCashAccountGet(id: number) {
  return crmPost<{ cash_account: CashAccount }>(acc("cash_account_get"), { id })
}

export function accountingCashAccountSave(params: Partial<CashAccount> & { name: string }) {
  const p: Record<string, string | number> = { name: params.name }
  if (params.id) p.id = params.id
  if (params.type) p.type = params.type
  if (params.code !== undefined) p.code = params.code ?? ""
  if (params.description !== undefined) p.description = params.description ?? ""
  if (params.card_no !== undefined) p.card_no = params.card_no ?? ""
  if (params.sheba !== undefined) p.sheba = params.sheba ?? ""
  if (params.chart_account_id !== undefined) p.chart_account_id = params.chart_account_id ?? 0
  if (params.is_active !== undefined) p.is_active = params.is_active
  if (params.sort_order !== undefined) p.sort_order = params.sort_order
  return crmPost<{ id?: number; updated?: boolean }>(acc("cash_account_save"), p)
}

export function accountingCashAccountDelete(id: number) {
  return crmPost(acc("cash_account_delete"), { id })
}

export interface ReceiptVoucher {
  id: number
  fiscal_year_id: number
  voucher_no: string
  voucher_date: string
  type: "receipt" | "payment" | "transfer"
  cash_account_id: number
  transfer_to_cash_account_id: number | null
  person_id: number | null
  amount: number
  description: string | null
  invoice_id: number | null
  project_id: number | null
  bank_fee: number | null
  journal_entry_id: number | null
  created_by: number
  status: "draft" | "posted"
  created_at: string
  updated_at: string
}

export function accountingReceiptVoucherList(params: {
  fiscal_year_id?: number
  type?: string
  cash_account_id?: number
  person_id?: number
  status?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}) {
  const p: Record<string, string | number> = {}
  Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== "") (p as Record<string, string | number>)[k] = v })
  return crmPost<{ items: ReceiptVoucher[]; total: number }>(acc("receipt_voucher_list"), p)
}

export function accountingReceiptVoucherGet(id: number) {
  return crmPost<{ voucher: ReceiptVoucher }>(acc("receipt_voucher_get"), { id })
}

export function accountingReceiptVoucherSave(params: {
  id?: number
  fiscal_year_id?: number
  voucher_no?: string
  voucher_date: string
  type: string
  cash_account_id: number
  transfer_to_cash_account_id?: number | null
  person_id?: number | null
  amount: number
  description?: string | null
  invoice_id?: number | null
  project_id?: number | null
  bank_fee?: number | null
}) {
  const p: Record<string, string | number | undefined> = {
    voucher_date: params.voucher_date,
    type: params.type,
    cash_account_id: params.cash_account_id,
    amount: params.amount,
  }
  if (params.id) p.id = params.id
  if (params.fiscal_year_id) p.fiscal_year_id = params.fiscal_year_id
  if (params.voucher_no !== undefined) p.voucher_no = params.voucher_no
  if (params.transfer_to_cash_account_id !== undefined) p.transfer_to_cash_account_id = params.transfer_to_cash_account_id ?? 0
  if (params.person_id !== undefined) p.person_id = params.person_id ?? 0
  if (params.description !== undefined) p.description = params.description ?? ""
  if (params.invoice_id !== undefined) p.invoice_id = params.invoice_id ?? 0
  if (params.project_id !== undefined) p.project_id = params.project_id ?? 0
  if (params.bank_fee !== undefined) p.bank_fee = params.bank_fee ?? 0
  return crmPost<{ id?: number; updated?: boolean }>(acc("receipt_voucher_save"), p as Record<string, string | number>)
}

export function accountingReceiptVoucherPost(id: number) {
  return crmPost(acc("receipt_voucher_post"), { id })
}

export function accountingReceiptVoucherDelete(id: number) {
  return crmPost(acc("receipt_voucher_delete"), { id })
}

export function accountingReceiptVoucherNextNumber(fiscalYearId?: number) {
  return crmPost<{ voucher_no: string }>(acc("receipt_voucher_next_number"), fiscalYearId ? { fiscal_year_id: fiscalYearId } : {})
}

// --- Phase 4: Cheques (چک دریافتی و پرداختی) ---
export interface AccountingCheck {
  id: number
  type: "receivable" | "payable"
  check_no: string
  check_date: string | null
  amount: number
  cash_account_id: number
  person_id: number
  due_date: string
  status: "in_safe" | "collected" | "returned" | "spent"
  receipt_voucher_id: number | null
  description: string | null
  journal_entry_id: number | null
  created_by: number
  created_at: string
  updated_at: string
}

export function accountingCheckList(params: {
  type?: string
  person_id?: number
  cash_account_id?: number
  status?: string
  due_from?: string
  due_to?: string
  check_no?: string
  page?: number
  per_page?: number
}) {
  const p: Record<string, string | number> = {}
  Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== "") (p as Record<string, string | number>)[k] = v })
  return crmPost<{ items: AccountingCheck[]; total: number }>(acc("check_list"), p)
}

export function accountingCheckGet(id: number) {
  return crmPost<{ check: AccountingCheck }>(acc("check_get"), { id })
}

export function accountingCheckSave(params: {
  id?: number
  type: string
  check_no: string
  check_date?: string | null
  amount: number
  cash_account_id: number
  person_id: number
  due_date: string
  description?: string | null
  receipt_voucher_id?: number | null
}) {
  const p: Record<string, string | number> = {
    type: params.type,
    check_no: params.check_no,
    amount: params.amount,
    cash_account_id: params.cash_account_id,
    person_id: params.person_id,
    due_date: params.due_date,
  }
  if (params.id) p.id = params.id
  if (params.check_date !== undefined) p.check_date = params.check_date ?? ""
  if (params.description !== undefined) p.description = params.description ?? ""
  if (params.receipt_voucher_id !== undefined) p.receipt_voucher_id = params.receipt_voucher_id ?? 0
  return crmPost<{ id?: number; updated?: boolean }>(acc("check_save"), p)
}

export function accountingCheckDelete(id: number) {
  return crmPost(acc("check_delete"), { id })
}

export function accountingCheckSetStatus(id: number, status: "in_safe" | "collected" | "returned" | "spent") {
  return crmPost(acc("check_set_status"), { id, status })
}
