import { useCallback, useEffect, useState } from "react"
import { Link, useParams } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import {
  approvePayrollRun,
  calculatePayrollRun,
  exportTaminDsk,
  getPayrollRun,
  getPayslips,
  printPayslipHtml,
  type PayrollRun,
  type Payslip,
} from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"
import { ArrowLeft, Calculator, Check, Loader2 } from "lucide-react"
import { cn } from "@/lib/utils"

export function PayrollRunDetailPage() {
  const { id } = useParams<{ id: string }>()
  const runId = id ? parseInt(id, 10) : 0
  const { t, isRtl, formatNumber } = useLocale()
  const [run, setRun] = useState<PayrollRun | null>(null)
  const [payslips, setPayslips] = useState<Payslip[]>([])
  const [loading, setLoading] = useState(true)
  const [acting, setActing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const load = useCallback(async () => {
    if (!runId || Number.isNaN(runId)) return
    setLoading(true)
    setError(null)
    try {
      const [runRes, slipRes] = await Promise.all([
        getPayrollRun(runId),
        getPayslips(runId),
      ])
      if (runRes.success && runRes.data?.run) {
        setRun(runRes.data.run)
      } else {
        setError(getAjaxMessage(runRes) ?? t("pages.hrm.loadError"))
      }
      if (slipRes.success && slipRes.data?.payslips) {
        setPayslips(slipRes.data.payslips)
      }
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [runId, t])

  useEffect(() => {
    void load()
  }, [load])

  const handleCalculate = async () => {
    if (!runId) return
    setActing(true)
    try {
      const res = await calculatePayrollRun(runId)
      if (res.success) {
        setSuccess(getAjaxMessage(res) ?? t("pages.hrm.payroll.calculated"))
        void load()
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } finally {
      setActing(false)
    }
  }

  const handleApprove = async () => {
    if (!runId) return
    setActing(true)
    try {
      const res = await approvePayrollRun(runId)
      if (res.success) {
        setSuccess(getAjaxMessage(res) ?? t("pages.hrm.payroll.approved"))
        void load()
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } finally {
      setActing(false)
    }
  }

  const handleTamin = async () => {
    if (!runId) return
    setActing(true)
    try {
      const res = await exportTaminDsk(runId)
      if (res.success && res.data?.url) {
        window.open(res.data.url, "_blank", "noopener,noreferrer")
        setSuccess(t("pages.hrm.payroll.taminExported"))
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } finally {
      setActing(false)
    }
  }

  const handlePrintSlip = async (id: number) => {
    try {
      const res = await printPayslipHtml(id)
      if (res.success && res.data?.html) {
        const w = window.open("", "_blank")
        if (w) {
          w.document.write(res.data.html)
          w.document.close()
        }
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } catch {
      setError(t("common.errors.saveFailed"))
    }
  }

  return (
    <CrmPageLayout
      title={run?.title ?? t("pages.hrm.payroll.runDetail")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
      actions={
        <div className={cn("flex flex-wrap gap-2", isRtl && "flex-row-reverse")}>
          <Button variant="outline" size="sm" asChild>
            <Link to="/hrm/payroll">
              <ArrowLeft className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
              {t("pages.hrm.payroll.back")}
            </Link>
          </Button>
          {run && run.status === "draft" && (
            <Button size="sm" onClick={() => void handleCalculate()} disabled={acting}>
              <Calculator className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
              {t("pages.hrm.payroll.calculate")}
            </Button>
          )}
          {run && run.status === "calculated" && (
            <Button size="sm" onClick={() => void handleApprove()} disabled={acting}>
              {acting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />}
              {t("pages.hrm.payroll.approve")}
            </Button>
          )}
          {run ? (
            <Button size="sm" variant="secondary" onClick={() => void handleTamin()} disabled={acting}>
              {t("pages.hrm.payroll.exportTamin")}
            </Button>
          ) : null}
        </div>
      }
    >
      {loading ? (
        <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
      ) : run ? (
        <div className="space-y-4">
          <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
            <CardContent className="pt-6 grid gap-2 sm:grid-cols-3 text-sm">
              <div>
                <span className="text-muted-foreground">{t("common.status")}: </span>
                {run.status}
              </div>
              <div>
                <span className="text-muted-foreground">{t("pages.hrm.payroll.gross")}: </span>
                {formatNumber(run.total_gross)}
              </div>
              <div>
                <span className="text-muted-foreground">{t("pages.hrm.payroll.net")}: </span>
                {formatNumber(run.total_net)}
              </div>
            </CardContent>
          </Card>
          <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
            <CardContent className="pt-6">
              <h3 className="font-medium mb-4">{t("pages.hrm.payroll.payslips")}</h3>
              {payslips.length === 0 ? (
                <div className="py-6 text-muted-foreground">{t("pages.hrm.empty")}</div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("common.name")}</TableHead>
                      <TableHead>{t("pages.hrm.payroll.gross")}</TableHead>
                      <TableHead>{t("pages.hrm.payroll.deductions")}</TableHead>
                      <TableHead>{t("pages.hrm.payroll.net")}</TableHead>
                      <TableHead>{t("common.status")}</TableHead>
                      <TableHead />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {payslips.map((ps) => (
                      <TableRow key={ps.id}>
                        <TableCell>{ps.user_name}</TableCell>
                        <TableCell>{formatNumber(ps.gross)}</TableCell>
                        <TableCell>{formatNumber(ps.deductions)}</TableCell>
                        <TableCell>{formatNumber(ps.net)}</TableCell>
                        <TableCell>{ps.status}</TableCell>
                        <TableCell>
                          <Button size="sm" variant="outline" onClick={() => void handlePrintSlip(ps.id)}>
                            {t("pages.hrm.payroll.print")}
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </div>
      ) : (
        <div className="py-8 text-muted-foreground">{t("pages.hrm.notFound")}</div>
      )}
    </CrmPageLayout>
  )
}
