import type { ComponentType } from "react"
import {
  BarChart3,
  DollarSign,
  ListTodo,
  MessageSquare,
  Rocket,
  TrendingUp,
  UserCog,
  Users,
} from "lucide-react"

export const REPORT_TAB_IDS = [
  "overview",
  "sales",
  "team",
  "customers",
  "finance",
  "tasks",
  "tickets",
  "agile",
] as const

export type ReportTabId = (typeof REPORT_TAB_IDS)[number]

export type ReportTabDef = {
  id: ReportTabId
  labelKey: string
  icon: ComponentType<{ className?: string }>
}

export const REPORT_TAB_DEFS: ReportTabDef[] = [
  { id: "overview", labelKey: "pages.reports.نمای_کلی", icon: BarChart3 },
  { id: "sales", labelKey: "pages.reports.tab_sales", icon: TrendingUp },
  { id: "team", labelKey: "pages.reports.tab_team", icon: UserCog },
  { id: "customers", labelKey: "pages.reports.tab_customers", icon: Users },
  { id: "finance", labelKey: "pages.reports.گزارش_مالی", icon: DollarSign },
  { id: "tasks", labelKey: "pages.reports.گزارش_وظایف", icon: ListTodo },
  { id: "tickets", labelKey: "pages.reports.گزارش_تیکتها", icon: MessageSquare },
  { id: "agile", labelKey: "pages.reports.گزارش_اَجایل", icon: Rocket },
]
