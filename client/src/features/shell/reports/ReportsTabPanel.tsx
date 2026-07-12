import type { ComponentType } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import type { ReportsResponseData } from "@/api/reports"
import { SimpleBarChart } from "@/features/shared/charts/SimpleBarChart"
import { ReportsStatCard } from "./components/ReportsStatCard"
import { ReportsDataTable } from "./components/ReportsDataTable"
import type { ReportColumnLabels } from "./types"
import type { ReportTabId } from "./constants"
import {
  DollarSign,
  FileText,
  FolderOpen,
  ListTodo,
  MessageSquare,
  Ticket,
  TrendingUp,
  UserPlus,
  Users,
} from "lucide-react"

type Props = {
  tab: string
  payload: ReportsResponseData
  cols: ReportColumnLabels
  t: (key: string, options?: Record<string, unknown>) => string
  formatNumber: (value: number) => string
}

export function ReportsTabPanel({ tab, payload, cols, t, formatNumber }: Props) {
  const stats = payload.stats ?? {}
  const charts = payload.charts
  const tables = payload.tables ?? {}

  const renderStatGrid = (
    keys: { key: string; label: string; icon: ComponentType<{ className?: string }> }[],
  ) => (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {keys.map(({ key, label, icon }) =>
        key in stats ? (
          <ReportsStatCard
            key={key}
            label={label}
            value={
              typeof stats[key] === "number"
                ? formatNumber(Number(stats[key]))
                : String(stats[key] ?? 0)
            }
            icon={icon}
          />
        ) : null,
      )}
    </div>
  )

  switch (tab as ReportTabId) {
    case "sales":
      return (
        <div className="space-y-4">
          <ReportsDataTable
            title={t("pages.reports.sales_by_month")}
            rows={(tables.sales_by_month as Record<string, unknown>[]) ?? []}
            columns={[
              { key: "month", label: cols.month },
              { key: "count", label: cols.count },
              { key: "total", label: cols.total },
            ]}
          />
          <ReportsDataTable
            title={t("pages.reports.top_customers")}
            rows={(tables.top_customers as Record<string, unknown>[]) ?? []}
            columns={[
              { key: "customer_name", label: cols.customer },
              { key: "contract_count", label: cols.contract },
              { key: "total_value", label: cols.value },
            ]}
          />
        </div>
      )

    case "team":
      return (
        <div className="space-y-4">
          <ReportsDataTable
            title={t("pages.reports.tasks_by_member")}
            rows={(tables.tasks_by_member as Record<string, unknown>[]) ?? []}
            columns={[
              { key: "user_name", label: cols.member },
              { key: "total_tasks", label: cols.all },
              { key: "completed_tasks", label: cols.completed },
              { key: "activity_score", label: cols.score },
            ]}
          />
          <ReportsDataTable
            title={t("pages.reports.time_by_member")}
            rows={(tables.time_by_member as Record<string, unknown>[]) ?? []}
            columns={[
              { key: "user_name", label: cols.member },
              { key: "total_minutes", label: cols.minutes },
              { key: "billable_minutes", label: cols.billable },
              { key: "total_revenue", label: cols.revenue },
            ]}
          />
        </div>
      )

    case "customers":
      return (
        <div className="space-y-4">
          {renderStatGrid([
            { key: "new_customers", label: t("pages.reports.new_customers"), icon: UserPlus },
            { key: "retention_rate", label: t("pages.reports.retention"), icon: Users },
            { key: "avg_customer_value", label: t("pages.reports.avg_ltv"), icon: DollarSign },
          ])}
          <ReportsDataTable
            title={t("pages.reports.ltv_table")}
            rows={(tables.customer_ltv as Record<string, unknown>[]) ?? []}
            columns={[
              { key: "customer_name", label: cols.customer },
              { key: "total_contracts", label: cols.contract },
              { key: "lifetime_value", label: cols.value },
            ]}
          />
        </div>
      )

    case "finance":
      return renderStatGrid([
        { key: "total_revenue", label: t("pages.dashboard.کل_درآمد"), icon: DollarSign },
        { key: "total_contracts", label: t("pages.dashboard.قراردادها"), icon: FileText },
      ])

    case "tasks":
      return (
        <div className="space-y-4">
          {renderStatGrid([
            { key: "total_tasks", label: t("pages.dashboard.وظایف"), icon: ListTodo },
            { key: "completed_tasks", label: t("pages.reports.وظایف_تکمیلشده"), icon: ListTodo },
            { key: "task_completion_rate", label: t("pages.reports.completion_rate"), icon: ListTodo },
            { key: "total_time_hours", label: t("pages.reports.total_hours"), icon: ListTodo },
          ])}
          <ReportsDataTable
            title={t("pages.reports.time_by_member")}
            rows={(tables.time_by_member as Record<string, unknown>[]) ?? []}
            columns={[
              { key: "user_name", label: cols.member },
              { key: "total_minutes", label: cols.minutes },
              { key: "entry_count", label: cols.entry_count },
            ]}
          />
        </div>
      )

    case "tickets":
      return renderStatGrid([
        { key: "total_tickets", label: t("pages.reports.تیکتها"), icon: Ticket },
        { key: "avg_response_time", label: t("pages.reports.avg_response"), icon: MessageSquare },
      ])

    case "agile":
      return (
        <div className="space-y-4">
          {renderStatGrid([
            { key: "total_projects", label: t("pages.projects.پروژهها"), icon: FolderOpen },
            { key: "active_projects", label: t("pages.reports.active_projects"), icon: FolderOpen },
            { key: "task_completion_rate", label: t("pages.reports.completion_rate"), icon: ListTodo },
          ])}
          {charts?.monthly && charts.monthly.length > 0 ? (
            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-base">{t("pages.reports.chart_monthly")}</CardTitle>
              </CardHeader>
              <CardContent>
                <SimpleBarChart
                  data={charts.monthly.map((m) => m.total ?? 0)}
                  labels={charts.monthly.map((m) => m.month?.slice(5) ?? "")}
                />
              </CardContent>
            </Card>
          ) : null}
        </div>
      )

    case "overview":
    default:
      return (
        <div className="space-y-6">
          {renderStatGrid([
            { key: "total_projects", label: t("pages.projects.پروژهها"), icon: FolderOpen },
            { key: "total_tasks", label: t("pages.dashboard.وظایف"), icon: ListTodo },
            { key: "total_leads", label: t("pages.leads.سرنخها"), icon: UserPlus },
            { key: "conversion_rate", label: t("pages.reports.conversion_rate"), icon: TrendingUp },
            { key: "total_revenue", label: t("pages.dashboard.کل_درآمد"), icon: DollarSign },
            { key: "total_tickets", label: t("pages.reports.تیکتها"), icon: Ticket },
          ])}

          {charts?.daily && charts.daily.length > 0 ? (
            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-base">{t("pages.reports.chart_daily")}</CardTitle>
              </CardHeader>
              <CardContent>
                <SimpleBarChart
                  data={charts.daily.map((d) => d.total ?? 0)}
                  labels={charts.daily.map((d) => d.date?.slice(5) ?? "")}
                />
              </CardContent>
            </Card>
          ) : null}

          {charts?.growth ? (
            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-base">{t("pages.reports.growth")}</CardTitle>
              </CardHeader>
              <CardContent className="grid gap-2 text-sm sm:grid-cols-2">
                <p>
                  {t("pages.reports.growth_projects")}:{" "}
                  {formatNumber(charts.growth.total_projects_growth ?? 0)}%
                </p>
                <p>
                  {t("pages.reports.growth_tasks")}: {formatNumber(charts.growth.total_tasks_growth ?? 0)}%
                </p>
                <p>
                  {t("pages.reports.growth_revenue")}:{" "}
                  {formatNumber(charts.growth.total_revenue_growth ?? 0)}%
                </p>
              </CardContent>
            </Card>
          ) : null}

          <ReportsDataTable
            title={t("pages.reports.leads_by_status")}
            rows={(tables.leads_by_status as Record<string, unknown>[]) ?? []}
            columns={[
              { key: "status", label: cols.status },
              { key: "count", label: cols.count },
            ]}
          />

          {Array.isArray(stats.project_by_status) &&
          (stats.project_by_status as { name: string; count: number }[]).length > 0 ? (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {(stats.project_by_status as { name: string; count: number }[]).map((item, i) => (
                <ReportsStatCard
                  key={i}
                  label={t("pages.reports.project_status", { name: item.name })}
                  value={String(item.count)}
                  icon={FolderOpen}
                />
              ))}
            </div>
          ) : null}
        </div>
      )
  }
}
