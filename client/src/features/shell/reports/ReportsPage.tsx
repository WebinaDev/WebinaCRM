import { ReportsSkeleton } from "@/components/skeletons"
import { useCallback, useEffect, useMemo, useState } from "react"
import { renderIcon } from "@/lib/react-icon"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/ui/date-picker"
import {
  getReports,
  getReportsAnalyticsExportUrl,
  getReportsExportUrl,
  type ReportsResponseData,
} from "@/api/reports"
import { getAjaxMessage } from "@/api/client"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { ReportsTabPanel } from "./ReportsTabPanel"
import { REPORT_TAB_DEFS } from "./constants"
import { FileDown, FileSpreadsheet } from "lucide-react"

export function ReportsPage() {
  const { t, isRtl, formatNumber } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const cols = useMemo(
    () => ({
      month: t("pages.reports.columns.month"),
      count: t("pages.reports.columns.count"),
      total: t("pages.reports.columns.total"),
      customer: t("pages.reports.columns.customer"),
      contract: t("pages.reports.columns.contract"),
      value: t("pages.reports.columns.value"),
      member: t("pages.reports.columns.member"),
      all: t("pages.reports.columns.all"),
      completed: t("pages.reports.columns.completed"),
      score: t("pages.reports.columns.score"),
      minutes: t("pages.reports.columns.minutes"),
      billable: t("pages.reports.columns.billable"),
      revenue: t("pages.reports.columns.revenue"),
      entry_count: t("pages.reports.columns.entry_count"),
      status: t("pages.reports.columns.status"),
    }),
    [t],
  )

  const tabs = useMemo(
    () => REPORT_TAB_DEFS.map((tb) => ({ id: tb.id, label: t(tb.labelKey), icon: tb.icon })),
    [t],
  )

  const [tab, setTab] = useState<string>("overview")
  const [dateFrom, setDateFrom] = useState(() => {
    const d = new Date()
    d.setDate(1)
    return d.toISOString().slice(0, 10)
  })
  const [dateTo, setDateTo] = useState(() => new Date().toISOString().slice(0, 10))
  const [payload, setPayload] = useState<ReportsResponseData | null>(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getReports({
        tab,
        date_from: dateFrom,
        date_to: dateTo,
      })
      if (res.success && res.data) {
        setPayload(res.data)
      } else {
        setError(getAjaxMessage(res) ?? t("pages.reports.خطا_در_بارگذاری_گزارش"))
        setPayload(null)
      }
    } catch {
      setError(t("pages.reports.خطا_در_بارگذاری_گزارش"))
      setPayload(null)
    } finally {
      setLoading(false)
    }
  }, [tab, dateFrom, dateTo, setError, t])

  useEffect(() => {
    void load()
  }, [load])

  const stats = payload?.stats

  const setPresetThisMonth = () => {
    const d = new Date()
    setDateFrom(new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10))
    setDateTo(new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10))
  }

  const setPresetLastMonth = () => {
    const d = new Date()
    setDateFrom(new Date(d.getFullYear(), d.getMonth() - 1, 1).toISOString().slice(0, 10))
    setDateTo(new Date(d.getFullYear(), d.getMonth(), 0).toISOString().slice(0, 10))
  }

  const handleExportCsv = (type: "contracts" | "tasks") => {
    const url = getReportsExportUrl(type, dateFrom, dateTo)
    if (url) window.open(url, "_blank")
  }

  const handleAnalyticsExport = () => {
    const url = getReportsAnalyticsExportUrl(tab, dateFrom, dateTo, "csv")
    if (url) window.open(url, "_blank")
  }

  const handleExportPdf = () => {
    const serverUrl = getReportsAnalyticsExportUrl(tab, dateFrom, dateTo, "pdf")
    if (serverUrl) {
      window.open(serverUrl, "_blank")
      return
    }
    if (!stats) return
    import("jspdf").then(({ jsPDF }) => {
      const doc = new jsPDF("p", "mm", "a4")
      const pageW = (doc as unknown as { internal: { pageSize: { width: number } } }).internal.pageSize
        .width
      let y = 20
      doc.setFontSize(18)
      doc.text(t("pages.reports.pdf_title"), pageW / 2, y, { align: "center" })
      y += 12
      doc.setFontSize(11)
      doc.text(
        t("pages.reports.pdf_range", { from: dateFrom, to: dateTo, tab }),
        pageW / 2,
        y,
        { align: "center" },
      )
      y += 15
      doc.setFontSize(10)
      Object.entries(stats).forEach(([key, value]) => {
        if (Array.isArray(value) || typeof value === "object") return
        if (y > 270) {
          doc.addPage()
          y = 20
        }
        doc.text(`${key}: ${value != null ? String(value) : "-"}`, 20, y)
        y += 8
      })
      doc.save(`report-${tab}-${dateFrom}-${dateTo}.pdf`)
    })
  }

  return (
    <CrmPageLayout
      title={t("pages.reports.گزارشات")}
      {...layoutProps}
      actions={
        <div className="flex flex-wrap items-center gap-2">
          <Button variant="outline" size="sm" onClick={handleAnalyticsExport} className="gap-2">
            <FileSpreadsheet className="h-4 w-4" />
            {t("pages.reports.analytics_csv")}
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => handleExportCsv("contracts")}
            className="gap-2"
          >
            <FileSpreadsheet className="h-4 w-4" />
            {t("pages.reports.export_contracts_csv")}
          </Button>
          <Button variant="outline" size="sm" onClick={() => handleExportCsv("tasks")} className="gap-2">
            <FileSpreadsheet className="h-4 w-4" />
            {t("pages.reports.export_tasks_csv")}
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={handleExportPdf}
            disabled={!payload}
            className="gap-2"
          >
            <FileDown className="h-4 w-4" />
            {t("pages.reports.export_pdf")}
          </Button>
        </div>
      }
    >
      <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} onApply={() => void load()} isRtl={isRtl}>
        <div className="space-y-1">
          <label className="text-sm text-muted-foreground">{t("common.dateFrom")}</label>
          <DatePicker value={dateFrom} onChange={setDateFrom} className="w-[160px]" />
        </div>
        <div className="space-y-1">
          <label className="text-sm text-muted-foreground">{t("common.dateTo")}</label>
          <DatePicker value={dateTo} onChange={setDateTo} className="w-[160px]" />
        </div>
        <Button variant="outline" type="button" onClick={setPresetThisMonth}>
          {t("pages.reports.preset_this_month")}
        </Button>
        <Button variant="outline" type="button" onClick={setPresetLastMonth}>
          {t("pages.reports.preset_last_month")}
        </Button>
      </PmFilterBar>

      <Card>
        <CardContent className="pt-6">
          <div className="mb-4 flex flex-wrap gap-2 border-b pb-2">
            {tabs.map((tb) => (
                <Button
                  key={tb.id}
                  variant={tab === tb.id ? "default" : "ghost"}
                  size="sm"
                  className="gap-2"
                  onClick={() => setTab(tb.id)}
                >
                  {renderIcon(tb.icon, "h-4 w-4")}
                  {tb.label}
                </Button>
              ))}
          </div>

          {loading ? (
            <ReportsSkeleton showPageHeader={false} />
          ) : payload ? (
            <ReportsTabPanel tab={tab} payload={payload} cols={cols} t={t} formatNumber={formatNumber} />
          ) : (
            !layoutProps.error && (
              <PmEmptyState message={t("pages.reports.دادهای_برای_این_بازه_موجود_نیست")} />
            )
          )}
        </CardContent>
      </Card>
    </CrmPageLayout>
  )
}
