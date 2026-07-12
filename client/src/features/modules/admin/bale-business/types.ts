export type BaleSettings = Record<string, unknown>

export interface BaleLogRow {
  id: string
  level: string
  log_type: string
  context: string
  created_at: string
}

export interface BaleBotStats {
  total_events: number
  total_logs: number
  total_users: number
  total_businesses: number
  started_users: number
}

export interface BaleKpi {
  start_to_lead_rate: number
  lead_to_customer_rate: number
  first_response_minutes: number
  retention_rate: number
  campaign_revenue_impact: number
  funnel_dropoff: Record<string, number>
  campaign_metrics: Record<string, number>
}

export type BaleTab = "settings" | "webhook" | "logs"

export const BALE_TABS: BaleTab[] = ["settings", "webhook", "logs"]

export interface BaleLoadResult {
  settings: BaleSettings | null
  webhookUrl: string
  webhookNote: string
  logs: BaleLogRow[]
  stats: { support_opened: number; support_item_clicked: number } | null
  botStats: BaleBotStats | null
  kpi: BaleKpi | null
  campaigns: Array<Record<string, unknown>>
}
