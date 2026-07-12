import { crmGet, getConfigOrNull } from "./client"

export interface ReportsDailyPoint {
  date: string
  projects?: number
  tasks?: number
  tickets?: number
  contracts?: number
  total?: number
}

export interface ReportsMonthlyPoint {
  month: string
  projects?: number
  tasks?: number
  tickets?: number
  contracts?: number
  total?: number
}

export interface ReportsGrowth {
  current?: Record<string, unknown>
  previous?: Record<string, unknown>
  total_projects_growth?: number
  total_tasks_growth?: number
  total_revenue_growth?: number
  completed_tasks_growth?: number
}

export interface ReportsResponseData {
  tab: string
  date_from: string
  date_to: string
  stats: Record<string, unknown>
  charts?: {
    daily?: ReportsDailyPoint[]
    monthly?: ReportsMonthlyPoint[]
    status_distribution?: { label: string; count: number }[]
    growth?: ReportsGrowth
  }
  tables?: Record<string, unknown[]>
  analytics?: Record<string, unknown>
}

export async function getReports(params: {
  tab?: string
  date_from?: string
  date_to?: string
}) {
  return crmGet<ReportsResponseData>("reports", {
    tab: params.tab ?? "overview",
    date_from: params.date_from,
    date_to: params.date_to,
  })
}

export function getReportsExportUrl(type: "contracts" | "tasks", dateFrom: string, dateTo: string): string | null {
  const c = getConfigOrNull()
  if (!c?.restUrl || !c?.nonce) return null
  const base = c.restUrl.replace(/\/$/, "")
  const params = new URLSearchParams({
    _wpnonce: c.nonce,
    type,
    date_from: dateFrom,
    date_to: dateTo,
  })
  return `${base}/reports/export?${params.toString()}`
}

export function getReportsAnalyticsExportUrl(
  tab: string,
  dateFrom: string,
  dateTo: string,
  format: "csv" | "pdf" = "csv",
): string | null {
  const c = getConfigOrNull()
  if (!c?.restUrl || !c?.nonce) return null
  const base = c.restUrl.replace(/\/$/, "")
  const type = tab === "customers" ? "customer" : tab
  const params = new URLSearchParams({
    _wpnonce: c.nonce,
    type,
    tab,
    format,
    date_from: dateFrom,
    date_to: dateTo,
  })
  return `${base}/reports/analytics/export?${params.toString()}`
}
