import { crmDelete, crmGet, crmPost } from "./client"

export type RahnBilling = "once" | "monthly" | "yearly" | "custom"

export interface RahnCatalogItem {
  id: string
  name: string
  billing: RahnBilling
  period_months: number
  renewable: boolean
  amount: number
  active: boolean
  default_selected: boolean
  category: string
  description: string
  sort_order: number
}

export interface RahnSettings {
  T: number
  m: number
  k: number
  p_min: number
  p_max: number
  p_default: number
  s_hat_default: number
  clause_template: string
  review: {
    enabled: boolean
    deviation_percent: number
    consecutive_months: number
  }
  sales_definition: Record<
    "G" | "R" | "D" | "X",
    { enabled: boolean; label: string }
  >
  catalog: RahnCatalogItem[]
}

export interface RahnLockResult {
  C: number
  V_star: number
  F_min: number
  alpha: number
  F: number
  p: number
  p_percent: number
  V_hat: number
  S_BE: number | null
  S_hat: number
  T: number
  m: number
  k: number
  p_min: number
  p_max: number
  breakdown: Array<{
    id: string
    name: string
    billing: string
    amount: number
    U: number
    M: number
    monthly_share: number
  }>
  mode: string
}

export interface RahnPublicPayload {
  F: number
  p: number
  p_percent: number
  V_hat: number
  S_hat: number
  alpha: number
  T: number
  p_min: number
  p_max: number
  F_min: number
  services: Array<{
    id: string
    name: string
    billing: string
    period_months: number
    renewable: boolean
    description: string
  }>
  clause: string
}

export interface RahnCalcResponse {
  selected_ids: string[]
  items: RahnCatalogItem[]
  clause: string
  public: RahnPublicPayload
  lock: RahnLockResult | null
  internal?: {
    C: number
    V_star: number
    F_min: number
    S_BE: number | null
    m: number
    k: number
    breakdown: RahnLockResult["breakdown"]
  }
}

export interface RahnQuote {
  id: number
  token: string
  title: string
  status: string
  customer_id: number
  lead_id: number
  contract_id: number
  F: number
  p: number
  p_percent: number
  s_hat: number
  duration: number
  locked_at: string | null
  clause: string
  share_url: string
  created_at: string
  updated_at: string
  selected_ids: string[]
}

export interface RahnContract {
  id: number
  title: string
  customer_id: number
  customer_name: string
  F: number
  p: number
  p_percent: number
  clause: string
  duration: number
  s_hat: number
  locked_at: string
}

export interface RahnBill {
  G: number
  R: number
  D: number
  X: number
  S: number
  F: number
  p: number
  p_share: number
  V: number
  C?: number
  Pi?: number
}

export async function getRahnSettings() {
  return crmGet<{ settings: RahnSettings }>("/rahn/settings")
}

export async function saveRahnSettings(settings: Partial<RahnSettings>) {
  const payload: Record<string, string | number | boolean> = {
    T: settings.T ?? 6,
    m: settings.m ?? 0.6,
    k: settings.k ?? 0.7,
    p_min: settings.p_min ?? 0.05,
    p_max: settings.p_max ?? 0.25,
    p_default: settings.p_default ?? 0.1,
    s_hat_default: settings.s_hat_default ?? 0,
    clause_template: settings.clause_template ?? "",
    catalog: JSON.stringify(settings.catalog ?? []),
    review: JSON.stringify(settings.review ?? {}),
    sales_definition: JSON.stringify(settings.sales_definition ?? {}),
  }
  return crmPost<{ settings: RahnSettings; message?: string }>("/rahn/settings", payload)
}

export async function calculateRahn(body: Record<string, unknown>) {
  return crmPost<RahnCalcResponse>("/rahn/calculate", flattenRahnBody(body))
}

function flattenRahnBody(body: Record<string, unknown>): Record<string, string | number | boolean> {
  const out: Record<string, string | number | boolean> = {}
  for (const [k, v] of Object.entries(body)) {
    if (v == null) continue
    if (typeof v === "string" || typeof v === "number" || typeof v === "boolean") {
      out[k] = v
    } else {
      out[k] = JSON.stringify(v)
    }
  }
  return out
}

export async function listRahnQuotes(params?: { paged?: number; status?: string }) {
  return crmGet<{
    quotes: RahnQuote[]
    total_pages: number
    current_page: number
    total: number
  }>("/rahn/quotes", params)
}

export async function saveRahnQuote(body: Record<string, unknown>) {
  return crmPost<{ quote: RahnQuote; calculation: RahnCalcResponse; message?: string }>(
    "/rahn/quotes",
    flattenRahnBody(body),
  )
}

export async function lockRahnQuote(id: number, body: Record<string, unknown> = {}) {
  return crmPost<{ quote: RahnQuote; calculation: RahnCalcResponse; message?: string }>(
    `/rahn/quotes/${id}/lock`,
    flattenRahnBody(body),
  )
}

export async function contractFromRahnQuote(id: number, body: Record<string, unknown>) {
  return crmPost<{ quote: RahnQuote; contract_id: number; message?: string }>(
    `/rahn/quotes/${id}/contract`,
    flattenRahnBody(body),
  )
}

export async function deleteRahnQuote(id: number) {
  return crmDelete<{ message?: string }>(`/rahn/quotes/${id}`)
}

export async function listRahnContracts() {
  return crmGet<{ contracts: RahnContract[] }>("/rahn/contracts")
}

export async function calculateRahnStatement(body: Record<string, unknown>) {
  return crmPost<{
    bill: RahnBill
    year_month: string
    contract_id: number
    clause: string
    review_alert: { message: string } | null
    sales_definition: RahnSettings["sales_definition"]
  }>("/rahn/statements/calculate", flattenRahnBody(body))
}

export async function saveRahnStatement(body: Record<string, unknown>) {
  return crmPost<{
    statement_id: number
    invoice_id: number
    bill: RahnBill
    year_month: string
    review_alert: { message: string } | null
    message?: string
  }>("/rahn/statements", flattenRahnBody(body))
}

export async function listRahnStatements(contractId: number) {
  return crmGet<{ statements: Record<string, unknown>[] }>("/rahn/statements", {
    contract_id: contractId,
  })
}
