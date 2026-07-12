export type ReportInterval = 'day' | 'week' | 'month'

export type ReportPreset =
  | 'today'
  | 'yesterday'
  | 'last7'
  | 'last30'
  | 'thisWeek'
  | 'lastWeek'
  | 'thisMonth'
  | 'lastMonth'
  | 'thisYear'
  | 'custom'

export type OrderReportSummary = {
  revenue: number
  net_revenue: number
  refunds: number
  refund_count: number
  order_count: number
  avg_order_value: number
  items_sold: number
  discount_total: number
  shipping_total: number
  tax_total: number
  cogs: number
  gross_profit: number
  gross_margin_pct: number
  items_missing_cost: number
  wfcp_enabled: boolean
}

export type OrderReportSeriesPoint = {
  key: string
  label: string
  revenue: number
  orders: number
  items: number
  cogs: number
  profit: number
}

export type OrderReportStatusRow = {
  status: string
  label: string
  count: number
  revenue: number
}

export type OrderReportPaymentRow = {
  method: string
  title: string
  count: number
  revenue: number
}

export type OrderReportSourceRow = {
  source: string
  count: number
  revenue: number
}

export type OrderReportHourRow = {
  hour: number
  orders: number
}

export type OrderReportPriceTierRow = {
  tier: string
  label: string
  count: number
  revenue: number
  cogs: number
  profit: number
  margin_pct: number
}

export type OrderReportHeatmapCell = {
  dow: number
  hour: number
  orders: number
  revenue: number
}

export type OrderReportProductRow = {
  product_id: number
  name: string
  quantity: number
  revenue: number
}

export type OrderReportProductProfitRow = {
  product_id: number
  name: string
  quantity: number
  revenue: number
  cogs: number
  profit: number
  margin_pct: number
  missing_cost: number
}

export type OrderReportCategoryRow = {
  term_id: number
  name: string
  quantity: number
  revenue: number
}

export type OrderReportCustomerRow = {
  customer_id: number
  name: string
  email: string
  orders: number
  revenue: number
}

export type OrderReportCouponRow = {
  code: string
  count: number
  revenue: number
}

export type OrderReportCompare = {
  from: number
  to: number
  from_date: string
  to_date: string
  summary: OrderReportSummary
  series: OrderReportSeriesPoint[]
}

export type OrderReportResponse = {
  currency: string
  from: number
  to: number
  from_date: string
  to_date: string
  interval: ReportInterval
  statuses: string[]
  summary: OrderReportSummary
  series: OrderReportSeriesPoint[]
  by_status: OrderReportStatusRow[]
  by_payment: OrderReportPaymentRow[]
  by_source: OrderReportSourceRow[]
  by_hour: OrderReportHourRow[]
  by_price_tier: OrderReportPriceTierRow[]
  heatmap: OrderReportHeatmapCell[]
  top_products: OrderReportProductRow[]
  top_products_profit: OrderReportProductProfitRow[]
  top_categories: OrderReportCategoryRow[]
  top_customers: OrderReportCustomerRow[]
  top_coupons: OrderReportCouponRow[]
  compare?: OrderReportCompare
}

export type OrderReportFilters = {
  preset: ReportPreset
  from: Date
  to: Date
  interval: ReportInterval
  compare: boolean
  statuses: string[]
}
