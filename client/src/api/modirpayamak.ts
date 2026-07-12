import { crmDelete, crmGet, crmPost } from "./client"

export interface ModirPayamakAccount {
  id: number
  domain: string
  balance: number
  default_from?: string
  status: string
  expires_at?: string | null
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

export async function getModirPayamakPackages() {
  return crmGet<{ packages: ModirPayamakPackage[] }>("modirpayamak/admin/packages")
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

export async function getModirPayamakMessages(domain?: string, page = 1) {
  return crmGet<{ messages: Record<string, unknown>[] }>("modirpayamak/admin/messages", {
    domain: domain ?? "",
    page: String(page),
  })
}
