import { useCallback, useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Textarea } from "@/components/ui/textarea"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import { getCartableInbox, hrRequestAction, managerRequestAction, type HrmRequest } from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"

export function HrmCartablePage() {
  const { t, isRtl } = useLocale()
  const [items, setItems] = useState<HrmRequest[]>([])
  const [notes, setNotes] = useState<Record<number, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await getCartableInbox()
      if (res.success && res.data?.requests) {
        setItems(res.data.requests)
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

  const act = async (req: HrmRequest, action: "approve" | "reject", asHr: boolean) => {
    const fn = asHr ? hrRequestAction : managerRequestAction
    const res = await fn(req.id, action, notes[req.id])
    if (res.success) {
      setSuccess(res.data?.message ?? t("common.saved"))
      void load()
    } else {
      setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.hrm.portal.cartable")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
    >
      {loading ? (
        <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
      ) : items.length === 0 ? (
        <div className="py-8 text-muted-foreground">{t("pages.hrm.empty")}</div>
      ) : (
        <div className="space-y-3 text-start" dir={isRtl ? "rtl" : "ltr"}>
          {items.map((r) => (
            <Card key={r.id}>
              <CardContent className="pt-6 space-y-2 text-sm">
                <div className="flex flex-wrap justify-between gap-2">
                  <span className="font-medium">{r.user_name} · {r.type}</span>
                  <span className="text-muted-foreground">{r.status}</span>
                </div>
                <Textarea
                  value={notes[r.id] ?? ""}
                  onChange={(e) => setNotes((prev) => ({ ...prev, [r.id]: e.target.value }))}
                  placeholder={t("pages.hrm.portal.notes")}
                  rows={2}
                />
                <div className="flex flex-wrap gap-2">
                  {r.status === "pending_manager" && (
                    <>
                      <Button size="sm" onClick={() => void act(r, "approve", false)}>{t("pages.hrm.portal.approve")}</Button>
                      <Button size="sm" variant="outline" onClick={() => void act(r, "reject", false)}>{t("pages.hrm.portal.reject")}</Button>
                    </>
                  )}
                  {(r.status === "pending_hr" || r.status === "pending_manager") && (
                    <>
                      <Button size="sm" onClick={() => void act(r, "approve", true)}>{t("pages.hrm.portal.hrApprove")}</Button>
                      <Button size="sm" variant="outline" onClick={() => void act(r, "reject", true)}>{t("pages.hrm.portal.reject")}</Button>
                    </>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </CrmPageLayout>
  )
}
