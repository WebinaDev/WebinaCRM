import { useCallback, useEffect, useState } from "react"
import { Link, useParams } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Card, CardContent } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import {
  getStaffProfile,
  saveStaffProfile,
  type ProfileFieldDef,
  type StaffProfile,
} from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"
import { ArrowLeft, Loader2, Save } from "lucide-react"
import { cn } from "@/lib/utils"

function ProfileFieldInput({
  fieldKey,
  field,
  value,
  onChange,
}: {
  fieldKey: string
  field: ProfileFieldDef
  value: string
  onChange: (key: string, v: string) => void
}) {
  if (field.type === "textarea") {
    return (
      <Textarea
        value={value}
        onChange={(e) => onChange(fieldKey, e.target.value)}
        rows={3}
      />
    )
  }
  if (field.type === "select" && field.options) {
    return (
      <Select value={value || "_empty"} onValueChange={(v) => onChange(fieldKey, v === "_empty" ? "" : v)}>
        <SelectTrigger>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {Object.entries(field.options).map(([k, label]) => (
            <SelectItem key={k || "_empty"} value={k || "_empty"}>
              {label || "—"}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    )
  }
  return (
    <Input
      type={field.type === "number" ? "number" : field.type === "tel" ? "tel" : "text"}
      dir={field.type === "tel" ? "ltr" : undefined}
      className={field.type === "tel" ? "text-start" : undefined}
      value={value}
      onChange={(e) => onChange(fieldKey, e.target.value)}
    />
  )
}

export function StaffDetailPage() {
  const { id } = useParams<{ id: string }>()
  const { t, isRtl } = useLocale()
  const userId = id ? parseInt(id, 10) : 0
  const [profile, setProfile] = useState<StaffProfile | null>(null)
  const [form, setForm] = useState<Record<string, Record<string, string>>>({})
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const load = useCallback(async () => {
    if (!userId || Number.isNaN(userId)) return
    setLoading(true)
    setError(null)
    try {
      const res = await getStaffProfile(userId)
      if (res.success && res.data) {
        setProfile(res.data)
        const sections: Record<string, Record<string, string>> = {}
        for (const [sk, sec] of Object.entries(res.data.sections ?? {})) {
          sections[sk] = {}
          for (const [fk, f] of Object.entries(sec.fields ?? {})) {
            sections[sk][fk] = f.value ?? ""
          }
        }
        setForm(sections)
      } else {
        setError(getAjaxMessage(res) ?? t("pages.hrm.loadError"))
      }
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [userId, t])

  useEffect(() => {
    void load()
  }, [load])

  const handleSave = async () => {
    if (!userId) return
    setSubmitting(true)
    setError(null)
    setSuccess(null)
    try {
      const res = await saveStaffProfile(userId, { sections: form })
      if (res.success) {
        setSuccess(res.data?.message ?? t("common.saved"))
        if (res.data?.profile) {
          setProfile(res.data.profile)
        } else {
          void load()
        }
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } catch {
      setError(t("common.errors.saveFailed"))
    } finally {
      setSubmitting(false)
    }
  }

  const displayName =
    profile &&
    (`${profile.first_name ?? ""} ${profile.last_name ?? ""}`.trim() || profile.email)

  const sectionKeys = profile?.sections ? Object.keys(profile.sections) : []

  return (
    <CrmPageLayout
      title={displayName ?? t("pages.hrm.profile")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
      actions={
        <div className={cn("flex gap-2", isRtl && "flex-row-reverse")}>
          <Button variant="outline" size="sm" asChild>
            <Link to="/hrm/staff">
              <ArrowLeft className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
              {t("pages.hrm.backToStaff")}
            </Link>
          </Button>
          <Button size="sm" onClick={() => void handleSave()} disabled={submitting || loading}>
            {submitting && <Loader2 className={cn("h-4 w-4 animate-spin", isRtl ? "ms-2" : "me-2")} />}
            <Save className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
            {t("common.save")}
          </Button>
        </div>
      }
    >
      {loading ? (
        <div className="py-12 text-muted-foreground">{t("common.loading")}</div>
      ) : !profile ? (
        <div className="py-12 text-muted-foreground">{t("pages.hrm.notFound")}</div>
      ) : (
        <Tabs defaultValue={sectionKeys[0] ?? "identity_info"} dir={isRtl ? "rtl" : "ltr"} className="text-start">
          <TabsList className="flex flex-wrap h-auto text-start">
            {sectionKeys.map((sk) => (
              <TabsTrigger key={sk} value={sk}>
                {profile.sections[sk]?.title ?? sk}
              </TabsTrigger>
            ))}
          </TabsList>
          {sectionKeys.map((sk) => {
            const sec = profile.sections[sk]
            if (!sec) return null
            return (
              <TabsContent key={sk} value={sk} className="mt-4">
                <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
                  <CardContent className="pt-6 grid gap-4 sm:grid-cols-2 text-start">
                    {Object.entries(sec.fields).map(([fk, field]) => (
                      <div key={fk} className={field.type === "textarea" ? "sm:col-span-2 space-y-2" : "space-y-2"}>
                        <label className="text-sm font-medium">{field.label}</label>
                        <ProfileFieldInput
                          fieldKey={fk}
                          field={field}
                          value={form[sk]?.[fk] ?? field.value ?? ""}
                          onChange={(key, v) =>
                            setForm((prev) => ({
                              ...prev,
                              [sk]: { ...prev[sk], [key]: v },
                            }))
                          }
                        />
                      </div>
                    ))}
                  </CardContent>
                </Card>
              </TabsContent>
            )
          })}
        </Tabs>
      )}
    </CrmPageLayout>
  )
}
