import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { DatePicker } from "@/components/ui/date-picker"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { useLocale } from "@/hooks/use-locale"
import {
  deleteTimeEntry,
  getActiveTimer,
  getTimeEntries,
  getTimeReport,
  pauseTimer,
  resumeTimer,
  startTimer,
  stopTimer,
  type TimeEntry,
} from "@/api/time-tracking"
import { getAjaxMessage } from "@/api/client"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { Clock, Pause, Play, Square } from "lucide-react"

export function TimeTrackingPage() {
  const { t, isRtl, formatDateTime, formatNumber } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const [tab, setTab] = useState<"entries" | "report">("entries")
  const [timer, setTimer] = useState<TimeEntry | null>(null)
  const [entries, setEntries] = useState<TimeEntry[]>([])
  const [report, setReport] = useState<Record<string, unknown>[]>([])
  const [loading, setLoading] = useState(true)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleting, setDeleting] = useState(false)
  const [dateFrom, setDateFrom] = useState(() => {
    const d = new Date()
    d.setDate(1)
    return d.toISOString().slice(0, 10)
  })
  const [dateTo, setDateTo] = useState(() => new Date().toISOString().slice(0, 10))
  const [description, setDescription] = useState("")

  const loadTimer = useCallback(async () => {
    const res = await getActiveTimer()
    if (res.success && res.data) {
      setTimer(res.data.timer ?? null)
    }
  }, [])

  const loadEntries = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getTimeEntries({ date_from: dateFrom, date_to: dateTo, limit: 100 })
      if (res.success && res.data?.entries) {
        setEntries(res.data.entries)
      } else {
        setError(getAjaxMessage(res) ?? t("pages.time.loadError"))
      }
    } catch {
      setError(t("pages.time.loadError"))
    } finally {
      setLoading(false)
    }
  }, [dateFrom, dateTo, t, setError])

  const loadReport = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getTimeReport({ date_from: dateFrom, date_to: dateTo, group_by: "day" })
      if (res.success && res.data?.report) {
        const r = res.data.report
        setReport(Array.isArray(r) ? (r as Record<string, unknown>[]) : [])
      } else {
        setError(getAjaxMessage(res) ?? t("pages.time.loadError"))
      }
    } catch {
      setError(t("pages.time.loadError"))
    } finally {
      setLoading(false)
    }
  }, [dateFrom, dateTo, t, setError])

  useEffect(() => {
    void loadTimer()
    const id = window.setInterval(() => void loadTimer(), 15000)
    return () => window.clearInterval(id)
  }, [loadTimer])

  useEffect(() => {
    if (tab === "entries") void loadEntries()
    else void loadReport()
  }, [tab, loadEntries, loadReport])

  const refreshCurrentTab = () => {
    if (tab === "entries") void loadEntries()
    else void loadReport()
  }

  const handleStart = async () => {
    setError(null)
    const res = await startTimer({ description })
    if (res.success) {
      setDescription("")
      await loadTimer()
      if (tab === "entries") await loadEntries()
    } else {
      setError(getAjaxMessage(res) ?? t("pages.time.loadError"))
    }
  }

  const handleStop = async () => {
    if (!timer?.id) return
    const res = await stopTimer(timer.id)
    if (res.success) {
      setTimer(null)
      refreshCurrentTab()
    } else {
      setError(getAjaxMessage(res) ?? t("pages.time.loadError"))
    }
  }

  const handlePause = async () => {
    if (!timer?.id) return
    const res = await pauseTimer(timer.id)
    if (res.success) await loadTimer()
    else setError(getAjaxMessage(res) ?? t("pages.time.loadError"))
  }

  const handleResume = async () => {
    if (!timer?.id) return
    const res = await resumeTimer(timer.id)
    if (res.success) await loadTimer()
    else setError(getAjaxMessage(res) ?? t("pages.time.loadError"))
  }

  const confirmDelete = async () => {
    if (deleteId == null) return
    setDeleting(true)
    const res = await deleteTimeEntry(deleteId)
    setDeleting(false)
    if (res.success) {
      setEntries((prev) => prev.filter((e) => e.id !== deleteId))
      setDeleteId(null)
    } else {
      setError(getAjaxMessage(res) ?? t("pages.time.loadError"))
    }
  }

  return (
    <CrmPageLayout title={t("pages.time.title")} {...layoutProps}>
      <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
        <CardHeader className="text-start">
          <CardTitle className="text-base flex items-center gap-2 text-start">
            <Clock className="h-4 w-4" />
            {t("pages.time.active")}
          </CardTitle>
        </CardHeader>
        <CardContent className="flex flex-wrap items-end gap-3">
          {timer ? (
            <>
              <div className="text-sm">
                <span className="text-muted-foreground">{timer.entity_type}</span>
                {timer.entity_id > 0 && <span> #{timer.entity_id}</span>}
                {timer.description && <p className="mt-1">{timer.description}</p>}
                <p className="text-xs text-muted-foreground mt-1">
                  {formatDateTime(timer.start_time)} — {timer.status}
                </p>
              </div>
              {timer.status === "running" && (
                <Button type="button" variant="outline" size="sm" onClick={() => void handlePause()}>
                  <Pause className="h-4 w-4 me-1" />
                  {t("pages.time.pause")}
                </Button>
              )}
              {timer.status === "paused" && (
                <Button type="button" variant="outline" size="sm" onClick={() => void handleResume()}>
                  <Play className="h-4 w-4 me-1" />
                  {t("pages.time.resume")}
                </Button>
              )}
              <Button type="button" variant="destructive" size="sm" onClick={() => void handleStop()}>
                <Square className="h-4 w-4 me-1" />
                {t("pages.time.stop")}
              </Button>
            </>
          ) : (
            <>
              <Input
                className="max-w-md"
                placeholder={t("pages.time.active")}
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              />
              <Button type="button" onClick={() => void handleStart()}>
                <Play className="h-4 w-4 me-1" />
                {t("pages.time.start")}
              </Button>
              <span className="text-sm text-muted-foreground">{t("pages.time.noTimer")}</span>
            </>
          )}
        </CardContent>
      </Card>

      <PmFilterBar
        isRtl={isRtl}
        applyLabel={t("common.filter")}
        onApply={refreshCurrentTab}
      >
        <DatePicker value={dateFrom} onChange={setDateFrom} />
        <DatePicker value={dateTo} onChange={setDateTo} />
      </PmFilterBar>

      <Tabs
        value={tab}
        onValueChange={(v) => setTab(v as "entries" | "report")}
        dir={isRtl ? "rtl" : "ltr"}
        className="text-start"
      >
        <TabsList className="text-start">
          <TabsTrigger value="entries">{t("pages.time.entries")}</TabsTrigger>
          <TabsTrigger value="report">{t("pages.time.report")}</TabsTrigger>
        </TabsList>
        <TabsContent value="entries" className="mt-4 text-start">
          <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
            <CardContent className="pt-6 text-start">
              {loading ? (

                <TableListSkeleton rows={8} columns={5} />

              ) : entries.length === 0 ? (
                <PmEmptyState icon={Clock} message={t("common.noResults")} />
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.time.col_user")}</TableHead>
                      <TableHead>{t("pages.time.col_entity")}</TableHead>
                      <TableHead>{t("pages.time.col_minutes")}</TableHead>
                      <TableHead>{t("pages.time.col_start")}</TableHead>
                      <TableHead>{t("common.status")}</TableHead>
                      <TableHead />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {entries.map((e) => (
                      <TableRow key={e.id}>
                        <TableCell>{e.user_name ?? e.user_id}</TableCell>
                        <TableCell>
                          {e.entity_type}
                          {e.entity_id > 0 ? ` #${e.entity_id}` : ""}
                        </TableCell>
                        <TableCell>
                          {e.duration_minutes != null
                            ? formatNumber(Number(e.duration_minutes))
                            : "—"}
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {formatDateTime(e.start_time)}
                        </TableCell>
                        <TableCell>{e.status}</TableCell>
                        <TableCell>
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="text-destructive"
                            onClick={() => setDeleteId(e.id)}
                          >
                            {t("common.delete")}
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
        <TabsContent value="report" className="mt-4 text-start">
          <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
            <CardContent className="pt-6 text-start">
              {loading ? (

                <TableListSkeleton rows={8} columns={5} />

              ) : report.length === 0 ? (
                <PmEmptyState icon={Clock} message={t("common.noResults")} />
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.time.col_period")}</TableHead>
                      <TableHead>{t("pages.time.col_minutes")}</TableHead>
                      <TableHead>{t("pages.time.col_count")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {report.map((row, i) => (
                      <TableRow key={i}>
                        <TableCell>
                          {String(row.group_label ?? row.label ?? row.period ?? row.day ?? i)}
                        </TableCell>
                        <TableCell>
                          {row.total_minutes != null
                            ? formatNumber(Number(row.total_minutes))
                            : "—"}
                        </TableCell>
                        <TableCell>
                          {row.entry_count != null ? formatNumber(Number(row.entry_count)) : "—"}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <PmConfirmDialog
        open={deleteId != null}
        onOpenChange={(open) => !open && setDeleteId(null)}
        title={t("common.delete")}
        description={t("common.delete")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDelete}
        loading={deleting}
        isRtl={isRtl}
      />
    </CrmPageLayout>
  )
}
