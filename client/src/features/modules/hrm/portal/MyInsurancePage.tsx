import { useCallback, useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import { getHrmMe, getMyDependents, submitHrmRequest, type HrmDependent, type HrmMeResponse } from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"

export function MyInsurancePage() {
  const { t, isRtl } = useLocale()
  const [me, setMe] = useState<HrmMeResponse | null>(null)
  const [dependents, setDependents] = useState<HrmDependent[]>([])
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [meRes, depRes] = await Promise.all([getHrmMe(), getMyDependents()])
      if (meRes.success && meRes.data) setMe(meRes.data)
      if (depRes.success && depRes.data?.dependents) setDependents(depRes.data.dependents)
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const requestReferral = async () => {
    const res = await submitHrmRequest({
      type: "profile_change",
      payload: { request_type: "insurance_referral", note: t("pages.hrm.portal.insuranceReferral") },
    })
    if (res.success) {
      setSuccess(res.data?.message ?? t("common.saved"))
    } else {
      setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
    }
  }

  const id = me?.identity

  return (
    <CrmPageLayout
      title={t("pages.hrm.portal.myInsurance")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
    >
      {loading ? (
        <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
      ) : (
        <div className="space-y-4 text-start" dir={isRtl ? "rtl" : "ltr"}>
          <Card>
            <CardHeader><CardTitle className="text-base">{t("pages.hrm.portal.insuranceStatus")}</CardTitle></CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div>{t("pages.hrm.portal.insuranceNumber")}: {id?.insurance_number || "—"}</div>
              <div>{t("pages.hrm.portal.workshop")}: {id?.workshop?.name || "—"}</div>
              <Button variant="outline" onClick={() => void requestReferral()}>{t("pages.hrm.portal.requestReferral")}</Button>
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{t("pages.hrm.portal.dependents")}</CardTitle></CardHeader>
            <CardContent className="space-y-2 text-sm">
              {dependents.length === 0 ? (
                <div className="text-muted-foreground">{t("pages.hrm.empty")}</div>
              ) : (
                dependents.map((d) => (
                  <div key={d.id} className="rounded border px-3 py-2">
                    {d.full_name} · {d.relation} · {d.national_id}
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
