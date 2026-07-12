import { ReportsSkeleton } from "@/components/skeletons"
import { useCallback, useEffect, useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DatePicker } from "@/components/ui/date-picker"
import { Link } from "react-router-dom"
import { getVisitorStats, type VisitorStatsResponse } from "@/api/visitor-stats"
import { Button } from "@/components/ui/button"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { SimpleBarChart } from "@/features/shared/charts/SimpleBarChart"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Label } from "@/components/ui/label"
import { LineChart, Users, Eye, Activity } from "lucide-react"

export function VisitorStatisticsPage() {
  const { t, isRtl, formatNumber, formatDateTime } = useLocale()
  const formatVisitDate = (s: string) => formatDateTime(s.replace(/-/g, "/").slice(0, 16))
      const [dateFrom, setDateFrom] = useState(() => {
    const d = new Date()
    d.setDate(d.getDate() - 30)
    return d.toISOString().slice(0, 10)
  })
  const [dateTo, setDateTo] = useState(() => new Date().toISOString().slice(0, 10))
  const [data, setData] = useState<VisitorStatsResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [trackingEnabled, setTrackingEnabled] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getVisitorStats({ date_from: dateFrom, date_to: dateTo })
      if (res.success && res.data) {
        setData(res.data)
        if (typeof res.data.tracking_enabled === "boolean") {
          setTrackingEnabled(res.data.tracking_enabled)
        }
      } else {
        setError(res.message ?? t("pages.visitorstatistics.خطا_در_بارگذاری_آمار"))
        setData(null)
      }
    } catch {
      setError(t("pages.visitorstatistics.خطا_در_بارگذاری_آمار"))
      setData(null)
    } finally {
      setLoading(false)
    }
  }, [dateFrom, dateTo])

  useEffect(() => {
    load()
  }, [load])

  const noData = t("pages.visitorstatistics.بدون_داده")
  const globalKpiHint = data?.live_kpis_global ? t("pages.visitorstatistics.kpi_global_hint") : ""
  const overall = data?.overall
  const daily = data?.daily ?? []

  const statsTable = (
    rows: Array<{ name?: string; page_url?: string; count?: number; visit_count?: number }>,
    nameKey: "name" | "page_url",
    countKey: "count" | "visit_count",
    colName: string,
    colCount: string,
  ) => (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="text-start">{colName}</TableHead>
          <TableHead className="text-start">{colCount}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {rows.length === 0 ? (
          <TableRow>
            <TableCell colSpan={2} className="text-start text-muted-foreground py-4">
              {noData}
            </TableCell>
          </TableRow>
        ) : (
          rows.map((r, i) => (
            <TableRow key={i}>
              <TableCell className="truncate max-w-[200px] text-start" title={String(r[nameKey] ?? "")}>
                {r[nameKey] || "–"}
              </TableCell>
              <TableCell className="text-start" dir="ltr">{formatNumber(Number(r[countKey] ?? 0))}</TableCell>
            </TableRow>
          ))
        )}
      </TableBody>
    </Table>
  )

  return (
    <CrmPageLayout
      title={t("pages.visitorstatistics.آمار_بازدیدکنندگان")}
      description={
        !trackingEnabled
          ? `${t("pages.visitorstatistics.tracking_disabled_hint")} ${t("pages.settings.hub.tabs.visitor_tracking")}`
          : undefined
      }
      error={error}
      onDismissError={() => setError(null)}
      actions={
        !trackingEnabled ? (
          <Button variant="outline" size="sm" asChild>
            <Link to="/settings/general/visitor-tracking">{t("pages.settings.hub.tabs.visitor_tracking")}</Link>
          </Button>
        ) : undefined
      }
    >
      <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} onApply={() => void load()} isRtl={isRtl}>
        <div className="space-y-1">
          <Label>{t("pages.visitorstatistics.date_from")}</Label>
          <DatePicker value={dateFrom} onChange={setDateFrom} className="w-[160px]" />
        </div>
        <div className="space-y-1">
          <Label>{t("pages.visitorstatistics.date_to")}</Label>
          <DatePicker value={dateTo} onChange={setDateTo} className="w-[160px]" />
        </div>
      </PmFilterBar>

      {loading ? <ReportsSkeleton showPageHeader={false} /> : null}

      {!loading && data && (
        <>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between pb-2 text-start">
                <CardTitle className="text-sm font-medium">{t("pages.visitorstatistics.کل_بازدیدها")}</CardTitle>
                <Eye className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">
                  {formatNumber(Number(overall?.total_visits ?? 0))}
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between pb-2 text-start">
                <CardTitle className="text-sm font-medium">{t("pages.visitorstatistics.بازدید_یکتا")}</CardTitle>
                <Users className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">
                  {formatNumber(Number(overall?.unique_visitors ?? 0))}
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between pb-2 text-start">
                <CardTitle className="text-sm font-medium">
                  {t("pages.visitorstatistics.امروز")}
                  {globalKpiHint ? (
                    <span className="block text-xs font-normal text-muted-foreground">{globalKpiHint}</span>
                  ) : null}
                </CardTitle>
                <LineChart className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">
                  {formatNumber(Number(overall?.today_visits ?? 0))}
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between pb-2 text-start">
                <CardTitle className="text-sm font-medium">
                  {t("pages.visitorstatistics.آنلاین_۵_دقیقه")}
                  {globalKpiHint ? (
                    <span className="block text-xs font-normal text-muted-foreground">{globalKpiHint}</span>
                  ) : null}
                </CardTitle>
                <Activity className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">
                  {formatNumber(Number(overall?.online_now ?? 0))}
                </div>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>{t("pages.visitorstatistics.بازدید_روزانه")}</CardTitle>
            </CardHeader>
            <CardContent>
              <SimpleBarChart
                data={daily.map((d) => d.visits)}
                labels={daily.map((d) => d.date)}
              />
            </CardContent>
          </Card>

          <div className="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>{t("pages.visitorstatistics.پربازدیدترین_صفحات")}</CardTitle>
              </CardHeader>
              <CardContent className="overflow-auto max-h-[280px]">
                {statsTable(
                  data.top_pages || [],
                  "page_url",
                  "visit_count",
                  t("pages.visitorstatistics.صفحه"),
                  t("pages.visitorstatistics.بازدید"),
                )}
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>{t("pages.visitorstatistics.مرورگرها")}</CardTitle>
              </CardHeader>
              <CardContent className="overflow-auto max-h-[280px]">
                {statsTable(
                  data.browsers || [],
                  "name",
                  "count",
                  t("pages.visitorstatistics.مرورگر"),
                  t("pages.accounting.invoices.تعداد"),
                )}
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>{t("pages.visitorstatistics.سیستمعامل")}</CardTitle>
              </CardHeader>
              <CardContent className="overflow-auto max-h-[280px]">
                {statsTable(
                  data.os || [],
                  "name",
                  "count",
                  t("pages.visitorstatistics.سیستمعامل"),
                  t("pages.accounting.invoices.تعداد"),
                )}
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>{t("pages.visitorstatistics.نوع_دستگاه")}</CardTitle>
              </CardHeader>
              <CardContent className="overflow-auto max-h-[280px]">
                {statsTable(
                  data.devices || [],
                  "name",
                  "count",
                  t("pages.visitorstatistics.دستگاه"),
                  t("pages.accounting.invoices.تعداد"),
                )}
              </CardContent>
            </Card>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>{t("pages.visitorstatistics.referrers")}</CardTitle>
              </CardHeader>
              <CardContent className="overflow-auto max-h-[280px]">
                {statsTable(
                  data.referrers || [],
                  "name",
                  "count",
                  t("pages.visitorstatistics.referrer"),
                  t("pages.accounting.invoices.تعداد"),
                )}
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>{t("pages.visitorstatistics.online_now")}</CardTitle>
              </CardHeader>
              <CardContent className="overflow-auto max-h-[280px]">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="text-start">{t("pages.visitorstatistics.صفحه")}</TableHead>
                      <TableHead className="text-start">{t("pages.balebusiness.زمان")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {(data.online || []).length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={2} className="text-start text-muted-foreground py-4">
                          {noData}
                        </TableCell>
                      </TableRow>
                    ) : (
                      (data.online || []).map((r, i) => (
                        <TableRow key={i}>
                          <TableCell className="truncate max-w-[200px] text-start" title={r.page_url}>
                            {r.page_url || "–"}
                          </TableCell>
                          <TableCell className="text-start" dir="ltr">{formatVisitDate(r.visit_date ?? "")}</TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>{t("pages.visitorstatistics.آخرین_بازدیدها")}</CardTitle>
            </CardHeader>
            <CardContent className="overflow-auto max-h-[300px]">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="text-start">{t("pages.balebusiness.زمان")}</TableHead>
                    <TableHead className="text-start">{t("pages.visitorstatistics.صفحه")}</TableHead>
                    <TableHead className="text-start">{t("pages.visitorstatistics.ip")}</TableHead>
                    <TableHead className="text-start">{t("pages.visitorstatistics.مرورگر_دستگاه")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(data.recent || []).length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={4} className="text-start text-muted-foreground py-4">
                        {noData}
                      </TableCell>
                    </TableRow>
                  ) : (
                    (data.recent || []).map((r, i) => (
                      <TableRow key={i}>
                        <TableCell className="text-start" dir="ltr">{formatVisitDate(r.visit_date ?? "")}</TableCell>
                        <TableCell className="truncate max-w-[180px] text-start" title={r.page_url}>
                          {r.page_url || "–"}
                        </TableCell>
                        <TableCell className="text-start" dir="ltr">{r.ip_address || "–"}</TableCell>
                        <TableCell className="text-start">
                          {(r.browser || "") + (r.device_type ? " / " + r.device_type : "") || "–"}
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </>
      )}

      {!loading && !data && !error && (
        <PmEmptyState message={t("pages.visitorstatistics.بدون_داده")} />
      )}
    </CrmPageLayout>
  )
}
