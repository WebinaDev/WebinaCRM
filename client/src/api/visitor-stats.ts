import { crmGet, crmPost } from "./client"

export interface VisitorStatsResponse {
  total_visits?: number
  unique_visitors?: number
  page_views?: number
  tracking_enabled?: boolean
  live_kpis_global?: boolean
  overall?: {
    total_visits?: number
    unique_visitors?: number
    today_visits?: number
    online_now?: number
  }
  daily?: Array<{ date: string; visits: number }>
  top_pages?: Array<{ page_url?: string; visit_count?: number; path?: string; views?: number }>
  browsers?: Array<{ name?: string; count?: number }>
  os?: Array<{ name?: string; count?: number }>
  devices?: Array<{ name?: string; count?: number }>
  referrers?: Array<{ name?: string; count?: number }>
  online?: Array<{ page_url?: string; visit_date?: string }>
  recent?: Array<{
    page_url?: string
    visit_date?: string
    ip_address?: string
    browser?: string
    device_type?: string
  }>
  visits_by_day?: Array<{ date: string; count: number }>
}

export async function getVisitorStats(params?: { date_from?: string; date_to?: string }) {
  return crmGet<VisitorStatsResponse>("visitor-statistics", {
    date_from: params?.date_from,
    date_to: params?.date_to,
  })
}

export async function saveVisitorSettings(settings: Record<string, string | number | boolean>) {
  return crmPost<{ message?: string; tracking_enabled?: boolean }>(
    "visitor-statistics/settings",
    settings,
  )
}
