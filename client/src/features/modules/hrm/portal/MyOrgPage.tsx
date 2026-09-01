import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import {
  getMyNotices,
  getMyRequests,
  getOrgChart,
  getReviews,
  getEnrollments,
  type HrmNotice,
  type HrmRequest,
  type OrgChartResponse,
} from "@/api/hrm"

export function MyOrgPage() {
  const { t, isRtl } = useLocale()
  const [requests, setRequests] = useState<HrmRequest[]>([])
  const [notices, setNotices] = useState<HrmNotice[]>([])
  const [org, setOrg] = useState<OrgChartResponse | null>(null)
  const [trainingCount, setTrainingCount] = useState(0)
  const [reviewCount, setReviewCount] = useState(0)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [reqRes, nRes, orgRes, enRes, revRes] = await Promise.all([
        getMyRequests(),
        getMyNotices(),
        getOrgChart(),
        getEnrollments(),
        getReviews(),
      ])
      if (reqRes.success && reqRes.data?.requests) setRequests(reqRes.data.requests)
      if (nRes.success && nRes.data?.notices) setNotices(nRes.data.notices)
      if (orgRes.success && orgRes.data) setOrg(orgRes.data)
      if (enRes.success && enRes.data?.enrollments) setTrainingCount(enRes.data.enrollments.length)
      if (revRes.success && revRes.data?.reviews) setReviewCount(revRes.data.reviews.length)
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const statusLabel = (s: string) => t(`pages.hrm.portal.status.${s}`, s)

  return (
    <CrmPageLayout title={t("pages.hrm.portal.myOrg")} error={error} onDismissError={() => setError(null)}>
      {loading ? (
        <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
      ) : (
        <div className="space-y-4 text-start" dir={isRtl ? "rtl" : "ltr"}>
          <Card>
            <CardHeader><CardTitle className="text-base">{t("pages.hrm.portal.myRequests")}</CardTitle></CardHeader>
            <CardContent className="space-y-2 text-sm">
              {requests.length === 0 ? (
                <div className="text-muted-foreground">{t("pages.hrm.empty")}</div>
              ) : (
                requests.map((r) => (
                  <div key={r.id} className="flex justify-between rounded border px-3 py-2">
                    <span>{r.type}</span>
                    <span className="text-muted-foreground">{statusLabel(r.status)}</span>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{t("pages.hrm.portal.notices")}</CardTitle></CardHeader>
            <CardContent className="space-y-2 text-sm">
              {notices.slice(0, 5).map((n) => (
                <div key={n.id} className="rounded border px-3 py-2">{n.title}</div>
              ))}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{t("pages.hrm.portal.orgChart")}</CardTitle></CardHeader>
            <CardContent className="space-y-1 text-sm">
              {(org?.departments ?? []).map((d) => (
                <div key={d.id} className="font-medium">{d.name}</div>
              ))}
              {(org?.positions ?? []).map((p) => (
                <div key={p.id} className="ps-4 text-muted-foreground">↳ {p.name}</div>
              ))}
            </CardContent>
          </Card>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" asChild>
              <Link to="/hrm/training">{t("pages.hrm.portal.myTraining")} ({trainingCount})</Link>
            </Button>
            <Button variant="outline" asChild>
              <Link to="/hrm/performance">{t("pages.hrm.portal.myReviews")} ({reviewCount})</Link>
            </Button>
            <Button variant="outline" asChild>
              <Link to="/crm/tickets">{t("nav.erp.crm.tickets")}</Link>
            </Button>
          </div>
        </div>
      )}
    </CrmPageLayout>
  )
}
