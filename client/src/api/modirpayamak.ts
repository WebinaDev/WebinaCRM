import { crmDelete, crmGet, crmPost } from "./client"

export interface ModirPayamakAccount {
  id: number
  domain: string
  balance: number
  default_from?: string
  status: string
  expires_at?: string | null
  numbers?: ModirPayamakDomainNumber[]
}

export interface ModirPayamakDomainNumber {
  id?: number
  domain?: string
  number: string
  role: "service" | "personal" | "marketing" | string
  label?: string
  is_default?: boolean
}

export interface ModirPayamakLedgerRow {
  id: number
  type: string
  amount: number
  balance_after: number
  note?: string
  created_at?: string
}

export interface ModirPayamakPackage {
  id: number
  name: string
  amount: number
  bonus: number
  sort: number
  status: string
}

export interface ModirPayamakOrder {
  id: number
  domain: string
  package_id?: number
  amount: number
  credit_amount?: number
  status: string
  authority?: string
  ref_id?: string
  created_at?: string
}

export interface ModirPayamakStats {
  total_customers: number
  sent_today: number
  pending_orders: number
  reseller_credit: unknown
  price_per_unit: number
  configured: boolean
}

export async function getModirPayamakDashboard() {
  return crmGet<{ stats: ModirPayamakStats }>("modirpayamak/admin/dashboard")
}

export async function modirpayamakProxy(method: string, path: string, body?: unknown, query?: unknown) {
  return crmPost<{ ok: boolean; data?: unknown; meta?: unknown; message?: string }>(
    "modirpayamak/admin/proxy",
    {
      method,
      path,
      body: body ? JSON.stringify(body) : "",
      query: query ? JSON.stringify(query) : "",
    },
  )
}

export async function getModirPayamakCustomers() {
  return crmGet<{ accounts: ModirPayamakAccount[] }>("modirpayamak/admin/customers")
}

export async function adjustModirPayamakBalance(domain: string, amount: number, note?: string) {
  return crmPost<{ account: ModirPayamakAccount; message?: string }>("modirpayamak/admin/customers/balance", {
    domain,
    amount: String(amount),
    note: note ?? "",
  })
}

export async function getModirPayamakCustomerLedger(domain: string, page = 1) {
  return crmGet<{ ledger: ModirPayamakLedgerRow[]; account?: ModirPayamakAccount }>(
    "modirpayamak/admin/customers/ledger",
    { domain, page: String(page) },
  )
}

export async function ensureModirPayamakCustomersFromLicenses() {
  return crmPost<{ accounts: ModirPayamakAccount[]; created?: number; total?: number; message?: string }>(
    "modirpayamak/admin/customers/ensure",
    {},
  )
}

export async function attachModirPayamakNumber(data: {
  domain: string
  number: string
  role: "service" | "personal" | "marketing"
  label?: string
}) {
  return crmPost<{
    number: ModirPayamakDomainNumber
    account?: ModirPayamakAccount
    attachments?: ModirPayamakDomainNumber[]
    message?: string
  }>("modirpayamak/admin/numbers/attach", {
    domain: data.domain,
    number: data.number,
    role: data.role === "marketing" ? "personal" : data.role,
    label: data.label ?? "",
  })
}

export async function detachModirPayamakNumber(
  domain: string,
  role: "service" | "personal" | "marketing",
  number?: string,
) {
  return crmPost<{ account?: ModirPayamakAccount; message?: string }>("modirpayamak/admin/numbers/detach", {
    domain,
    role: role === "marketing" ? "personal" : role,
    number: number ?? "",
  })
}

export type ModirPayamakPatternScope = "order_customer" | "order_admin" | "site"

export interface ModirPayamakRegistryRow {
  domain?: string
  scope?: string
  event_key?: string
  ippanel_code?: string
  sync_status?: string
  last_error?: string
  param_map?: Record<string, string>
}

export async function attachModirPayamakPattern(data: {
  domain: string
  scope: ModirPayamakPatternScope
  event_key: string
  pattern_code: string
  param_map?: Record<string, string>
}) {
  return crmPost<{
    message?: string
    result?: unknown
    registry?: ModirPayamakRegistryRow[]
  }>("modirpayamak/admin/patterns/attach", {
    domain: data.domain,
    scope: data.scope,
    event_key: data.event_key,
    pattern_code: data.pattern_code,
    param_map: data.param_map ? JSON.stringify(data.param_map) : "",
  })
}

export async function detachModirPayamakPattern(data: {
  domain: string
  scope: ModirPayamakPatternScope
  event_key: string
}) {
  return crmPost<{ message?: string; registry?: ModirPayamakRegistryRow[] }>(
    "modirpayamak/admin/patterns/detach",
    data,
  )
}

export async function getModirPayamakDomainPatternRegistry(domain = "") {
  return crmGet<{ registry: ModirPayamakRegistryRow[]; events?: string[] }>(
    "modirpayamak/admin/patterns/registry",
    domain ? { domain } : {},
  )
}

export async function getModirPayamakMessages(domain = "", page = 1, limit = 20) {
  return crmGet<{ messages: Array<Record<string, unknown>> }>("modirpayamak/admin/messages", {
    domain,
    page: String(page),
    limit: String(limit),
  })
}

export async function getModirPayamakDomainSecretaries(domain: string) {
  return crmGet<{ secretaries: Array<Record<string, unknown>> }>("modirpayamak/admin/secretaries", {
    domain,
  })
}

export async function saveModirPayamakDomainSecretary(data: {
  domain: string
  type?: string
  name?: string
  keywords?: string
  reply_body?: string
  forward_to?: string
  enabled?: boolean
  id?: number
  [key: string]: string | number | boolean | undefined
}) {
  return crmPost<{ rule?: Record<string, unknown>; message?: string }>("modirpayamak/admin/secretaries", data)
}

export async function deleteModirPayamakDomainSecretary(domain: string, id: number) {
  return crmPost<{ ok?: boolean }>("modirpayamak/admin/secretaries/delete", { domain, id })
}

export async function getModirPayamakPackages() {
  return crmGet<{ packages: ModirPayamakPackage[] }>("modirpayamak/admin/packages")
}

export interface ModirPayamakTariff {
  id: number
  line_type: string
  operator: "mci" | "other" | string
  rate_fa: number
  rate_la: number
  sort: number
  status: string
}

export async function getModirPayamakTariffs() {
  return crmGet<{
    tariffs: ModirPayamakTariff[]
    tax_percent?: number
    surcharge_rial?: number
  }>("modirpayamak/admin/tariffs")
}

export async function saveModirPayamakTariff(data: Partial<ModirPayamakTariff>) {
  return crmPost<{ id: number; message?: string }>("modirpayamak/admin/tariffs", {
    id: data.id ? String(data.id) : "",
    line_type: data.line_type ?? "",
    operator: data.operator ?? "other",
    rate_fa: String(data.rate_fa ?? 0),
    rate_la: String(data.rate_la ?? 0),
    sort: String(data.sort ?? 0),
    status: data.status ?? "active",
  })
}

export async function deleteModirPayamakTariff(id: number) {
  return crmDelete<{ message?: string }>(`modirpayamak/admin/tariffs/${id}`, { id: String(id) })
}

export async function saveModirPayamakPackage(data: Partial<ModirPayamakPackage>) {
  return crmPost<{ id: number; message?: string }>("modirpayamak/admin/packages", {
    id: data.id ? String(data.id) : "",
    name: data.name ?? "",
    amount: String(data.amount ?? 0),
    bonus: String(data.bonus ?? 0),
    sort: String(data.sort ?? 0),
    status: data.status ?? "active",
  })
}

export async function deleteModirPayamakPackage(id: number) {
  return crmDelete<{ message?: string }>(`modirpayamak/admin/packages/${id}`, { id: String(id) })
}

export async function getModirPayamakOrders(page = 1) {
  return crmGet<{ orders: ModirPayamakOrder[] }>("modirpayamak/admin/orders", {
    page: String(page),
  })
}

export async function adminModirPayamakSend(payload: Record<string, unknown>) {
  return crmPost<{ ok: boolean; data?: unknown }>("modirpayamak/admin/send", {
    payload: JSON.stringify(payload),
  })
}
