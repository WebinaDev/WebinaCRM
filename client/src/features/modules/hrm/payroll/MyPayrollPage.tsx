import { useCallback, useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import { getHrmMe, getMyPayslips, printPayslipHtml, type MyPayslipRow } from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"

export function MyPayrollPage() {
  const { t, isRtl, formatNumber, formatDate } = useLocale()
  const [items, setItems] = useState<MyPayslipRow[]>([])
  const [decree, setDecree] = useState<Record<string, unknown> | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [psRes, meRes] = await Promise.all([getMyPayslips(), getHrmMe()])
      if (psRes.success && psRes.data?.payslips) {
        setItems(psRes.data.payslips as MyPayslipRow[])
      } else {
        setError(getAjaxMessage(psRes) ?? t("pages.hrm.loadError"))
      }
      if (meRes.success && meRes.data?.decree) {
        setDecree(meRes.data.decree)
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

  const print = async (id: number) => {
    const res = await printPayslipHtml(id)
    if (res.success && res.data?.html) {
      const w = window.open("", "_blank")
      if (w) {
        w.document.write(res.data.html)
        w.document.close()
      }
    }
  }

  return (
    <CrmPageLayout title={t("pages.hrm.payroll.myPayslips")} error={error} onDismissError={() => setError(null)}>
      {loading ? (
        <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
      ) : (
        <div className="space-y-4 text-start" dir={isRtl ? "rtl" : "ltr"}>
          {decree && (
            <Card>
              <CardContent className="pt-6 text-sm space-y-1">
                <div className="font-medium">{t("pages.hrm.portal.currentDecree")}</div>
                <div>{String(decree.decree_no ?? "")} · {String(decree.job_title ?? "")}</div>
                <div>{t("pages.hrm.portal.baseSalary")}: {formatNumber(Number(decree.base_salary ?? 0))}</div>
              </CardContent>
            </Card>
          )}
          <Card>
            <CardContent className="pt-6 space-y-3">
              {items.length === 0 ? (
                <div className="text-muted-foreground">{t("pages.hrm.empty")}</div>
              ) : (
                items.map((ps) => (
                  <div key={ps.id} className="rounded-md border px-3 py-3 text-sm space-y-2">
                    <div className="flex items-center justify-between">
                      <span className="font-medium">
                        {ps.run_title || `#${ps.id}`}
                        {ps.jalali_year ? ` · ${ps.jalali_year}/${ps.jalali_month}` : ""}
                      </span>
                      <Button size="sm" variant="outline" onClick={() => void print(ps.id)}>
                        {t("pages.hrm.payroll.print")}
                      </Button>
                    </div>
                    <div className="grid gap-1 sm:grid-cols-2 text-muted-foreground">
                      <span>{t("pages.hrm.payroll.gross")}: {formatNumber(ps.gross)}</span>
                      <span>{t("pages.hrm.payroll.net")}: {formatNumber(ps.net)}</span>
                      <span>{t("pages.hrm.portal.daysWorked")}: {formatNumber(ps.days_worked ?? 0)}</span>
                      <span>{t("pages.hrm.portal.overtime")}: {formatNumber(ps.overtime ?? 0)}</span>
                      <span>{t("pages.hrm.portal.insurance7")}: {formatNumber(ps.employee_insurance ?? 0)}</span>
                      <span>{t("pages.hrm.portal.insurance20")}: {formatNumber(ps.employer_insurance ?? 0)}</span>
                      <span>{t("pages.hrm.portal.tax")}: {formatNumber(ps.tax ?? 0)}</span>
                      <span>{t("pages.hrm.portal.loan")}: {formatNumber(ps.loan_deduction ?? 0)}</span>
                      <span>{t("pages.hrm.portal.advance")}: {formatNumber(ps.advance_deduction ?? 0)}</span>
                      <span>{t("pages.hrm.portal.iban")}: {ps.iban || "—"}</span>
                      <span>{t("pages.hrm.portal.depositDate")}: {ps.deposit_date ? formatDate(ps.deposit_date) : "—"}</span>
                    </div>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  )
}
