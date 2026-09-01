import { useEffect, useMemo, useState } from "react"
import { useSearchParams } from "react-router-dom"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { FormSettingsSkeleton } from "@/components/skeletons"
import { adminModirPayamakSend } from "@/api/modirpayamak"
import { edgeField, type EdgeRow } from "@/api/modirpayamak-edge"
import { getAjaxMessage } from "@/api/client"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { ModirPayamakLineSelect } from "./components/ModirPayamakLineSelect"
import { ModirPayamakNotConfigured } from "./components/ModirPayamakNotConfigured"
import { ModirPayamakPatternSelect } from "./components/ModirPayamakPatternSelect"
import { ModirPayamakStatusBadge } from "./components/ModirPayamakStatusBadge"
import { useModirPayamakConfigured } from "./hooks/useModirPayamakConfigured"
import { Loader2 } from "lucide-react"
import { toast } from "sonner"

export function ModirPayamakSendPage() {
  const { t } = useLocale()
  const [searchParams] = useSearchParams()
  const { layoutProps, setError, setSuccess } = useCrmFeedback()
  const { configured, loading: configLoading } = useModirPayamakConfigured()

  const initialMode = searchParams.get("mode") === "pattern" ? "pattern" : "simple"
  const [mode, setMode] = useState<"simple" | "pattern">(initialMode)
  const [from, setFrom] = useState("")
  const [phone, setPhone] = useState("")
  const [message, setMessage] = useState(searchParams.get("message") ?? "")
  const [patternCode, setPatternCode] = useState(searchParams.get("pattern") ?? "")
  const [patternParams, setPatternParams] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(false)
  const [lastResult, setLastResult] = useState<{ id: string; cost: string; status: string } | null>(null)

  useEffect(() => {
    const msg = searchParams.get("message")
    if (msg) setMessage(msg)
    const pat = searchParams.get("pattern")
    if (pat) {
      setPatternCode(pat)
      setMode("pattern")
    }
  }, [searchParams])

  const recipientCount = useMemo(
    () => phone.split(/[\s,;\n]+/).filter(Boolean).length,
    [phone],
  )

  const onPatternSelect = (row: EdgeRow | null) => {
    if (!row) {
      setPatternParams({})
      return
    }
    const message = edgeField(row, "pattern_message", "message", "text", "body")
    const vars = [...new Set((message.match(/%([a-zA-Z0-9_]+)%/g) ?? []).map((m) => m.slice(1, -1)))]
    const next: Record<string, string> = {}
    for (const key of vars) next[key] = ""
    // Prefer named %var% fields; never dump full body into param1.
    setPatternParams(next)
  }

  const send = async () => {
    setLoading(true)
    setError(null)
    setSuccess(null)
    setLastResult(null)
    const recipients = phone.split(/[\s,;\n]+/).filter(Boolean)
    const payload =
      mode === "pattern"
        ? {
            sending_type: "pattern",
            from_number: from,
            code: patternCode,
            recipients,
            params: patternParams,
          }
        : {
            sending_type: "webservice",
            from_number: from,
            message,
            params: { recipients },
          }
    const res = await adminModirPayamakSend(payload)
    setLoading(false)
    if (res.success) {
      const data = (res.data?.data ?? res.data) as Record<string, unknown> | undefined
      setLastResult({
        id: data ? String(data.message_id ?? data.id ?? "—") : "—",
        cost: data ? String(data.cost ?? data.price ?? "—") : "—",
        status: data ? String(data.status ?? "sent") : "sent",
      })
      setSuccess(t("pages.modirpayamak.sendSuccess"))
      toast.success(t("pages.modirpayamak.sendSuccess"))
    } else {
      setError(getAjaxMessage(res) ?? t("pages.modirpayamak.sendFailed"))
    }
  }

  return (
    <CrmPageLayout title={t("pages.modirpayamak.sendTitle")} {...layoutProps}>
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.sendTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />

      {configLoading ? (
        <FormSettingsSkeleton cards={1} fieldsPerCard={5} />
      ) : (
        <Card className="max-w-2xl">
          <CardHeader>
            <CardTitle>{t("pages.modirpayamak.sendTitle")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-wrap gap-2">
              <Button type="button" variant={mode === "simple" ? "default" : "outline"} onClick={() => setMode("simple")}>
                {t("pages.modirpayamak.simpleSend")}
              </Button>
              <Button type="button" variant={mode === "pattern" ? "default" : "outline"} onClick={() => setMode("pattern")}>
                {t("pages.modirpayamak.patternSend")}
              </Button>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.fromNumber")}</Label>
              <ModirPayamakLineSelect value={from} onChange={setFrom} disabled={loading} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.recipients")}</Label>
              <Textarea
                dir="ltr"
                rows={3}
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="+98912..."
              />
              <p className="text-xs text-muted-foreground">{t("pages.modirpayamak.recipientCount", { count: recipientCount })}</p>
            </div>
            {mode === "pattern" ? (
              <>
                <div className="space-y-2">
                  <Label>{t("pages.modirpayamak.patternCode")}</Label>
                  <ModirPayamakPatternSelect value={patternCode} onChange={setPatternCode} onSelectRow={onPatternSelect} disabled={loading} />
                </div>
                {Object.keys(patternParams).length === 0 ? (
                  <p className="text-xs text-muted-foreground">{t("pages.modirpayamak.noPatternVars")}</p>
                ) : (
                  Object.entries(patternParams).map(([key, val]) => (
                    <div key={key} className="space-y-2">
                      <Label className="font-mono text-xs" dir="ltr">
                        %{key}%
                      </Label>
                      <Input value={val} onChange={(e) => setPatternParams((p) => ({ ...p, [key]: e.target.value }))} />
                    </div>
                  ))
                )}
              </>
            ) : (
              <div className="space-y-2">
                <Label>{t("pages.modirpayamak.message")}</Label>
                <Textarea value={message} onChange={(e) => setMessage(e.target.value)} rows={4} />
              </div>
            )}
            <Button type="button" onClick={() => void send()} disabled={loading || !from || recipientCount === 0}>
              {loading ? <Loader2 className="me-2 h-4 w-4 animate-spin" /> : null}
              {t("pages.modirpayamak.send")}
            </Button>
            {lastResult ? (
              <Card className="border-success/30 bg-success/5">
                <CardContent className="pt-4 space-y-2 text-sm">
                  <p className="font-medium">{t("pages.modirpayamak.sendSuccess")}</p>
                  <div className="flex flex-wrap gap-4">
                    <span>{t("pages.modirpayamak.messageId")}: {lastResult.id}</span>
                    <span>{t("pages.modirpayamak.cost")}: {lastResult.cost}</span>
                    <ModirPayamakStatusBadge status={lastResult.status} />
                  </div>
                </CardContent>
              </Card>
            ) : null}
          </CardContent>
        </Card>
      )}
    </CrmPageLayout>
  )
}
