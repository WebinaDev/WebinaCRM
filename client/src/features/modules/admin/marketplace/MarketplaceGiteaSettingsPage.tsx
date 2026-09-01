import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useRef, useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  getGiteaSettings,
  saveGiteaSettings,
  testGiteaConnection,
  type GiteaConnectionDiagnosticsResult,
  type GiteaIpScheme,
} from "@/api/marketplace"
import { marketplaceError, translateMarketplaceMessage } from "./marketplace-messages"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { GiteaConnectionDiagnostics } from "@/features/modules/admin/marketplace/GiteaConnectionDiagnostics"
import { Loader2 } from "lucide-react"

export function MarketplaceGiteaSettingsPage() {
  const { t } = useLocale()
  const { layoutProps, setError, setSuccess, applyResponse } = useCrmFeedback()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [form, setForm] = useState({
    gitea_base_url: "https://package.webina.dev",
    gitea_org: "webina",
    gitea_ip_override: "",
    gitea_ip_scheme: "auto" as GiteaIpScheme,
    gitea_api_token: "",
  })
  const [hasToken, setHasToken] = useState(false)
  const [diagnostics, setDiagnostics] = useState<GiteaConnectionDiagnosticsResult | null>(null)
  const autoTestDone = useRef(false)

  const buildTestPayload = useCallback(() => {
    const payload: Parameters<typeof testGiteaConnection>[0] = {
      gitea_base_url: form.gitea_base_url.trim(),
      gitea_org: form.gitea_org.trim(),
      gitea_ip_override: form.gitea_ip_override.trim(),
      gitea_ip_scheme: form.gitea_ip_scheme,
    }
    if (form.gitea_api_token.trim()) {
      payload.gitea_api_token = form.gitea_api_token.trim()
    }
    return payload
  }, [form])

  const runTest = useCallback(
    async (silent = false) => {
      setTesting(true)
      if (!silent) {
        setError(null)
        setSuccess(null)
      }
      const res = await testGiteaConnection(buildTestPayload())
      setTesting(false)
      if (res.success && res.data) {
        setDiagnostics(res.data)
        if (!silent) {
          if (res.data.ok) {
            setSuccess(
              res.data.user
                ? t("pages.marketplace.gitea.testOkUser", { user: res.data.user })
                : t("pages.marketplace.gitea.testOk"),
            )
          } else {
            setError(
              translateMarketplaceMessage(
                t,
                res.data.message,
                "pages.marketplace.gitea.testFail",
              ),
            )
          }
        }
        return res.data
      }
      if (!silent) {
        setError(marketplaceError(t, res, "pages.marketplace.gitea.testFail"))
      }
      return null
    },
    [buildTestPayload, setError, setSuccess, t],
  )

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    const res = await getGiteaSettings()
    if (res.success && res.data?.settings) {
      const s = res.data.settings
      setForm((p) => ({
        ...p,
        gitea_base_url: s.gitea_base_url || p.gitea_base_url,
        gitea_org: s.gitea_org || p.gitea_org,
        gitea_ip_override: s.gitea_ip_override || "",
        gitea_ip_scheme: s.gitea_ip_scheme || "auto",
        gitea_api_token: "",
      }))
      setHasToken(s.has_token)
    } else {
      setError(marketplaceError(t, res, "pages.marketplace.gitea.loadError"))
    }
    setLoading(false)
  }, [setError, t])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (loading || autoTestDone.current || !hasToken) return
    autoTestDone.current = true
    void runTest(true)
  }, [hasToken, loading, runTest])

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    setSuccess(null)
    const payload: Parameters<typeof saveGiteaSettings>[0] = {
      gitea_base_url: form.gitea_base_url.trim(),
      gitea_org: form.gitea_org.trim(),
      gitea_ip_override: form.gitea_ip_override.trim(),
      gitea_ip_scheme: form.gitea_ip_scheme,
    }
    if (form.gitea_api_token.trim()) {
      payload.gitea_api_token = form.gitea_api_token.trim()
    }
    const res = await saveGiteaSettings(payload)
    setSaving(false)
    if (
      applyResponse(res, {
        successMessage: t("pages.marketplace.api.giteaSettingsSaved"),
        errorFallback: t("pages.marketplace.gitea.saveError"),
      })
    ) {
      setForm((p) => ({ ...p, gitea_api_token: "" }))
      void load()
    }
  }

  const handleTest = async () => {
    await runTest(false)
  }

  return (
    <CrmPageLayout
      title={t("pages.marketplace.gitea.title")}
      description={t("pages.marketplace.gitea.desc")}
      {...layoutProps}
    >
      <Card>
        <CardHeader>
          <CardTitle>{t("pages.marketplace.gitea.connection")}</CardTitle>
          <CardDescription>{t("pages.marketplace.gitea.connectionDesc")}</CardDescription>
        </CardHeader>
        <CardContent>
          {loading ? (
            <TableListSkeleton rows={8} columns={5} />
          ) : (
            <form onSubmit={handleSave} className="grid max-w-xl gap-4">
              <div className="space-y-2">
                <Label>{t("pages.marketplace.gitea.baseUrl")}</Label>
                <Input
                  value={form.gitea_base_url}
                  onChange={(e) => setForm((p) => ({ ...p, gitea_base_url: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.marketplace.gitea.org")}</Label>
                <Input
                  value={form.gitea_org}
                  onChange={(e) => setForm((p) => ({ ...p, gitea_org: e.target.value }))}
                />
                <p className="text-xs text-muted-foreground">{t("pages.marketplace.gitea.orgHint")}</p>
              </div>
              <div className="space-y-2">
                <Label>{t("pages.marketplace.gitea.ipOverride")}</Label>
                <Input
                  value={form.gitea_ip_override}
                  onChange={(e) => setForm((p) => ({ ...p, gitea_ip_override: e.target.value }))}
                  placeholder="185.164.73.225"
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.marketplace.gitea.ipScheme")}</Label>
                <Select
                  value={form.gitea_ip_scheme}
                  onValueChange={(v) =>
                    setForm((p) => ({ ...p, gitea_ip_scheme: v as GiteaIpScheme }))
                  }
                >
                  <SelectTrigger className="w-full max-w-xs">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="auto">{t("pages.marketplace.gitea.ipSchemeAuto")}</SelectItem>
                    <SelectItem value="http">{t("pages.marketplace.gitea.ipSchemeHttp")}</SelectItem>
                    <SelectItem value="https">{t("pages.marketplace.gitea.ipSchemeHttps")}</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  {t("pages.marketplace.gitea.ipSchemeDesc")}
                </p>
              </div>
              <div className="space-y-2">
                <Label>{t("pages.marketplace.gitea.apiToken")}</Label>
                <Input
                  type="password"
                  value={form.gitea_api_token}
                  onChange={(e) => setForm((p) => ({ ...p, gitea_api_token: e.target.value }))}
                  placeholder={hasToken ? t("pages.marketplace.gitea.tokenPlaceholder") : ""}
                />
              </div>
              <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={saving}>
                  {saving ? <Loader2 className="me-2 h-4 w-4 animate-spin" /> : null}
                  {t("common.save")}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  disabled={testing}
                  onClick={() => void handleTest()}
                >
                  {testing ? <Loader2 className="me-2 h-4 w-4 animate-spin" /> : null}
                  {t("pages.marketplace.gitea.test")}
                </Button>
              </div>
            </form>
          )}
        </CardContent>
      </Card>

      {diagnostics || testing ? (
        <div className="mt-4">
          <GiteaConnectionDiagnostics result={diagnostics} loading={testing && !diagnostics} />
        </div>
      ) : null}
    </CrmPageLayout>
  )
}
