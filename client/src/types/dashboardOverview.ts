import type { CommentCounts, CommentRow } from '@/components/comments/types'
import type { OrderReportSeriesPoint, OrderReportSummary } from '@/types/orderReports'

export type DashboardOverviewProductStats = {
  total: number
  by_status: Record<string, number>
  by_stock: Record<string, number>
}

export type DashboardOverviewOrderRow = {
  id: number
  number: string
  status: string
  status_label: string
  total: string
  date: string
  customer_name: string
  item_count: number
}

export type DashboardOverviewProductRow = {
  id?: number
  product_id?: number
  name: string
  status?: string
  price?: string
  date?: string
  image_url: string
  views?: number
  quantity?: number
  revenue?: number
}

export type DashboardOverviewSales = {
  currency: string
  from: number
  to: number
  month_label: string
  summary: OrderReportSummary
  compare_summary: OrderReportSummary
  series: OrderReportSeriesPoint[]
  compare_series: OrderReportSeriesPoint[]
  recent_orders: DashboardOverviewOrderRow[]
  recent_products: DashboardOverviewProductRow[]
  top_products: DashboardOverviewProductRow[]
  top_products_by_views: DashboardOverviewProductRow[]
  top_categories: Array<{ term_id: number; name: string; quantity: number; revenue: number }>
  top_customers: Array<{ customer_id: number; name: string; email: string; orders: number; revenue: number }>
}

export type DashboardTrafficPeriod = {
  id: string
  from: string
  to: string
  visitors: number
  views: number
  visitors_change_pct: number | null
  views_change_pct: number | null
}

export type DashboardOverviewTraffic = {
  online: number
  highlight: {
    visitors: number
    views: number
    visitors_change_pct: number | null
    views_change_pct: number | null
  }
  periods: DashboardTrafficPeriod[]
  all_time: DashboardTrafficPeriod
  chart: { series: Array<{ day: string; visitors: number; views: number }> }
}

export type DashboardOverviewSmsPanel = {
  provider: string
  balance: number | null
  low_balance: boolean
  unavailable?: boolean
  status: string
  default_from: string
  price_per_unit: number
}

export type DashboardOverviewBotPanel = {
  provider: string
  sessions_24h: number
  users_linked: number
  webhook_configured: boolean
  token_configured: boolean
  last_error: string
}

export type DashboardOverviewPanels = {
  sms?: DashboardOverviewSmsPanel
  license: { active: boolean; demo: boolean; status: string }
  woocommerce: { active: boolean }
  analytics: { active: boolean; online: number }
  bots?: DashboardOverviewBotPanel[]
}

export type DashboardTaskOrdersBlock = {
  count: number
  preview: DashboardOverviewOrderRow[]
  href: string
}

export type DashboardOverviewTasks = {
  comments_hold: { count: number; href: string }
  orders_processing?: DashboardTaskOrdersBlock
  orders_on_hold?: DashboardTaskOrdersBlock
  products_outofstock?: { count: number; href: string }
}

export type DashboardOverviewAlert = {
  level: 'error' | 'warning' | 'info'
  source: string
  message: string
  at: string
}

export type DashboardOverviewResponse = {
  generated_at: number
  locale?: string
  sections: string[]
  products?: DashboardOverviewProductStats
  panels?: DashboardOverviewPanels
  sales?: DashboardOverviewSales
  traffic?: DashboardOverviewTraffic
  tasks?: DashboardOverviewTasks
  comments?: { items: CommentRow[]; counts: CommentCounts }
  alerts?: DashboardOverviewAlert[]
}
