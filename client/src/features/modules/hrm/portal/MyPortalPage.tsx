import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import { getHrmMe, type HrmMeResponse } from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"

export function MyPortalPage() {
  const { t, isRtl, formatNumber, formatDate } = useLocale()
  const [data, setData] = useState<HrmMeResponse | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await getHrmMe()
      if (res.success && res.data) {
        setData(res.data)
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

  const id = data?.identity

  return (
    <CrmPageLayout title={t("pages.hrm.portal.title")} error={error} onDismissError={() => setError(null)}>
      {loading ? (
        <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
      ) : !id ? (
        <div className="py-8 text-muted-foreground">{t("pages.hrm.empty")}</div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 text-start" dir={isRtl ? "rtl" : "ltr"}>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("pages.hrm.portal.identity")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div>{id.display_name}</div>
              <div>{id.job_title || "—"} · {id.department || "—"}</div>
              <div>{t("pages.hrm.portal.personnelCode")}: {id.personnel_code || "—"}</div>
              <div>{t("pages.hrm.portal.nationalId")}: {id.national_id || "—"}</div>
              <div>{t("pages.hrm.portal.manager")}: {id.direct_manager?.name || "—"}</div>
              <div>{t("pages.hrm.portal.hireDate")}: {id.hire_date ? formatDate(id.hire_date) : "—"}</div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("pages.hrm.portal.leaveBalance")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              {(data.leave_balances ?? []).length === 0 ? (
                <div className="text-muted-foreground">{t("pages.hrm.empty")}</div>
              ) : (
                data.leave_balances?.map((b) => (
                  <div key={b.leave_type_id} className="flex justify-between">
                    <span>{b.type_name}</span>
                    <span>{formatNumber(b.balance)} {t("pages.hrm.leave.days")}</span>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("pages.hrm.portal.latestPayslip")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              {data.latest_payslip ? (
                <>
                  <div>{data.latest_payslip.run_title || `#${data.latest_payslip.id}`}</div>
                  <div>{formatNumber(data.latest_payslip.net)} {t("pages.hrm.payroll.net")}</div>
                  <Button size="sm" variant="outline" asChild>
                    <Link to="/hrm/my-payroll">{t("pages.hrm.portal.viewPayslips")}</Link>
                  </Button>
                </>
              ) : (
                <div className="text-muted-foreground">{t("pages.hrm.empty")}</div>
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("pages.hrm.portal.openRequests")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div className="text-2xl font-semibold">{formatNumber(data.open_requests ?? 0)}</div>
              <Button size="sm" variant="outline" asChild>
                <Link to="/hrm/my-org">{t("pages.hrm.portal.viewRequests")}</Link>
              </Button>
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  )
}
