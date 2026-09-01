import { useCallback, useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import { getMyProfileView, getMyRequests, submitHrmRequest, type HrmRequest, type StaffProfile } from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"

export function MyProfilePage() {
  const { t, isRtl } = useLocale()
  const [profile, setProfile] = useState<StaffProfile | null>(null)
  const [requests, setRequests] = useState<HrmRequest[]>([])
  const [mobile, setMobile] = useState("")
  const [address, setAddress] = useState("")
  const [iban, setIban] = useState("")
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [pRes, rRes] = await Promise.all([getMyProfileView(), getMyRequests({ status: "pending_hr" })])
      if (pRes.success && pRes.data?.profile) {
        setProfile(pRes.data.profile)
        const contact = pRes.data.profile.sections?.contact_info?.fields
        setMobile(contact?.mobile_phone?.value ?? "")
        setAddress(contact?.address?.value ?? "")
        setIban(pRes.data.profile.sections?.financial_info?.fields?.iban?.value ?? "")
      }
      if (rRes.success && rRes.data?.requests) setRequests(rRes.data.requests.filter((x) => x.type === "profile_change"))
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const submitChange = async () => {
    const res = await submitHrmRequest({
      type: "profile_change",
      payload: { mobile_phone: mobile, address, iban },
    })
    if (res.success) {
      setSuccess(res.data?.message ?? t("pages.hrm.portal.profileChangeSubmitted"))
      void load()
    } else {
      setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.hrm.portal.myProfile")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
    >
      {loading ? (
        <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
      ) : (
        <div className="space-y-4 max-w-xl text-start" dir={isRtl ? "rtl" : "ltr"}>
          <Card>
            <CardContent className="pt-6 space-y-3">
              <div className="text-sm text-muted-foreground">{t("pages.hrm.portal.profileReadOnlyHint")}</div>
              <div className="font-medium">{profile?.first_name} {profile?.last_name}</div>
              <div className="text-sm">{profile?.email}</div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6 grid gap-3">
              <label className="text-sm font-medium">{t("pages.hrm.portal.mobile")}</label>
              <Input dir="ltr" value={mobile} onChange={(e) => setMobile(e.target.value)} />
              <label className="text-sm font-medium">{t("pages.hrm.portal.address")}</label>
              <Textarea value={address} onChange={(e) => setAddress(e.target.value)} />
              <label className="text-sm font-medium">{t("pages.hrm.portal.iban")}</label>
              <Input dir="ltr" value={iban} onChange={(e) => setIban(e.target.value)} />
              <Button onClick={() => void submitChange()}>{t("pages.hrm.portal.submitChange")}</Button>
            </CardContent>
          </Card>
          {requests.length > 0 && (
            <Card>
              <CardContent className="pt-6 space-y-2 text-sm">
                <div className="font-medium">{t("pages.hrm.portal.pendingChanges")}</div>
                {requests.map((r) => (
                  <div key={r.id}>{statusLabel(r.status)} · {r.created_at}</div>
                ))}
              </CardContent>
            </Card>
          )}
        </div>
      )}
    </CrmPageLayout>
  )

  function statusLabel(s: string) {
    return t(`pages.hrm.portal.status.${s}`, s)
  }
}
