import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { DatePicker } from "@/components/ui/date-picker"
import { Label } from "@/components/ui/label"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import { getPayrollRuns, savePayrollRun, type PayrollRun } from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"
import { Plus, Loader2, Eye } from "lucide-react"
import { cn } from "@/lib/utils"

export function PayrollRunsPage() {
  const { t, isRtl, formatNumber, formatDate } = useLocale()
  const [runs, setRuns] = useState<PayrollRun[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const now = new Date()
  const [form, setForm] = useState({
    title: "",
    period_date: now.toISOString().slice(0, 10),
  })

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getPayrollRuns()
      if (res.success && res.data?.runs) {
        setRuns(res.data.runs)
      } else {
        setError(getAjaxMessage(res) ?? t("pages.hrm.loadError"))
      }
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const handleCreate = async () => {
    setSubmitting(true)
    try {
      const periodDate = form.period_date || now.toISOString().slice(0, 10)
      const [period_year, period_month] = periodDate.split("-").map((part) => parseInt(part, 10))
      const res = await savePayrollRun({
        title: form.title.trim() || undefined,
        period_year,
        period_month,
      })
      if (res.success) {
        setDialogOpen(false)
        setSuccess(getAjaxMessage(res) ?? t("common.saved"))
        void load()
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.hrm.payroll.title")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
      actions={
        <Button size="sm" onClick={() => setDialogOpen(true)}>
          <Plus className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
          {t("pages.hrm.payroll.newRun")}
        </Button>
      }
    >
      <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
        <CardContent className="pt-6">
          {loading ? (
            <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
          ) : runs.length === 0 ? (
            <div className="py-8 text-muted-foreground">{t("pages.hrm.empty")}</div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("common.name")}</TableHead>
                  <TableHead>{t("pages.hrm.payroll.period")}</TableHead>
                  <TableHead>{t("common.status")}</TableHead>
                  <TableHead>{t("pages.hrm.payroll.net")}</TableHead>
                  <TableHead className="w-[80px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {runs.map((run) => (
                  <TableRow key={run.id}>
                    <TableCell className="font-medium">
                      <Link to={`/hrm/payroll/${run.id}`} className="hover:underline">
                        {run.title}
                      </Link>
                    </TableCell>
                    <TableCell className="text-start">
                      {formatDate(`${run.period_year}-${String(run.period_month).padStart(2, "0")}-01`.slice(0, 10))}
                    </TableCell>
                    <TableCell>{run.status}</TableCell>
                    <TableCell>{formatNumber(run.total_net)}</TableCell>
                    <TableCell>
                      <Button variant="ghost" size="icon" asChild>
                        <Link to={`/hrm/payroll/${run.id}`}>
                          <Eye className="h-4 w-4" />
                        </Link>
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>{t("pages.hrm.payroll.newRun")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <Input
              placeholder={t("common.name")}
              value={form.title}
              onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
            />
            <div className="space-y-2">
              <Label>{t("pages.hrm.payroll.period")}</Label>
              <DatePicker
                value={form.period_date}
                onChange={(v) => setForm((f) => ({ ...f, period_date: v }))}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>{t("common.cancel")}</Button>
            <Button onClick={() => void handleCreate()} disabled={submitting}>
              {submitting && <Loader2 className="h-4 w-4 animate-spin me-2" />}
              {t("common.add")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  )
}
