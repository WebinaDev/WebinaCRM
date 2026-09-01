import { useCallback, useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import {
  getMyDecrees,
  getMyNotices,
  printCertificateHtml,
  printDecreeHtml,
  submitHrmRequest,
  type HrmDecreeSummary,
  type HrmNotice,
} from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"

export function MyDocsPage() {
  const { t, isRtl } = useLocale()
  const [decrees, setDecrees] = useState<HrmDecreeSummary[]>([])
  const [notices, setNotices] = useState<HrmNotice[]>([])
  const [deductionAmount, setDeductionAmount] = useState("")
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [dRes, nRes] = await Promise.all([getMyDecrees(), getMyNotices()])
      if (dRes.success && dRes.data?.decrees) setDecrees(dRes.data.decrees)
      if (nRes.success && nRes.data?.notices) setNotices(nRes.data.notices)
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const printDecree = async (id: number) => {
    const res = await printDecreeHtml(id)
    if (res.success && res.data?.html) {
      const w = window.open("", "_blank")
      if (w) {
        w.document.write(res.data.html)
        w.document.close()
      }
    }
  }

  const requestCert = async (type: "certificate_employment" | "certificate_deduction") => {
    const payload = type === "certificate_deduction" ? { amount: Number(deductionAmount) || 0 } : {}
    const res = await submitHrmRequest({ type, payload })
    if (res.success) {
      setSuccess(res.data?.message ?? t("common.saved"))
    } else {
      setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
    }
  }

  const previewCert = async (type: string) => {
    const res = await printCertificateHtml(type)
    if (res.success && res.data?.html) {
      const w = window.open("", "_blank")
      if (w) {
        w.document.write(res.data.html)
        w.document.close()
      }
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.hrm.portal.myDocs")}
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
            <CardHeader><CardTitle className="text-base">{t("pages.hrm.portal.decrees")}</CardTitle></CardHeader>
            <CardContent className="space-y-2 text-sm">
              {decrees.map((d) => (
                <div key={d.id} className="flex items-center justify-between rounded border px-3 py-2">
                  <span>{d.decree_no} · {d.effective_from}</span>
                  <Button size="sm" variant="outline" onClick={() => void printDecree(d.id)}>{t("pages.hrm.payroll.print")}</Button>
                </div>
              ))}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{t("pages.hrm.portal.certificates")}</CardTitle></CardHeader>
            <CardContent className="flex flex-wrap gap-2">
              <Button variant="outline" onClick={() => void requestCert("certificate_employment")}>{t("pages.hrm.portal.requestEmployment")}</Button>
              <Button variant="outline" onClick={() => void previewCert("certificate_employment")}>{t("pages.hrm.portal.preview")}</Button>
              <Input type="number" className="max-w-[160px]" value={deductionAmount} onChange={(e) => setDeductionAmount(e.target.value)} placeholder={t("pages.hrm.portal.amount")} />
              <Button variant="outline" onClick={() => void requestCert("certificate_deduction")}>{t("pages.hrm.portal.requestDeduction")}</Button>
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{t("pages.hrm.portal.notices")}</CardTitle></CardHeader>
            <CardContent className="space-y-3 text-sm">
              {notices.length === 0 ? (
                <div className="text-muted-foreground">{t("pages.hrm.empty")}</div>
              ) : (
                notices.map((n) => (
                  <div key={n.id} className="rounded border p-3">
                    <div className="font-medium">{n.title}</div>
                    <div className="text-muted-foreground whitespace-pre-wrap">{n.body}</div>
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
