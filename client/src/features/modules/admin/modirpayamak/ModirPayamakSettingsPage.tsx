import { FormSettingsSkeleton } from "@/components/skeletons"
import { useCallback, useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Checkbox } from "@/components/ui/checkbox"
import { edgeField, edgeMyCredit } from "@/api/modirpayamak-edge"
import { getSettings, saveSettings } from "@/api/settings"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { Loader2 } from "lucide-react"

export function ModirPayamakSettingsPage() {
  const { t, formatNumber } = useLocale()
  const { layoutProps, applyResponse, setSuccess } = useCrmFeedback()
  const [form, setForm] = useState({
    modirpayamak_api_key: "",
    modirpayamak_default_from: "",
    modirpayamak_enabled: false,
    modirpayamak_sms_price_per_unit: 500,
    modirpayamak_sms_tax_percent: 10,
    modirpayamak_sms_surcharge_rial: 40,
    modirpayamak_reseller_credit_alert: 100000,
  })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [creditBalance, setCreditBalance] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    const res = await getSettings("modirpayamak")
    if (res.success && res.data) {
      const d = res.data as Record<string, unknown>
      setForm({
        modirpayamak_api_key: String(d.modirpayamak_api_key ?? ""),
        modirpayamak_default_from: String(d.modirpayamak_default_from ?? ""),
        modirpayamak_enabled: Boolean(d.modirpayamak_enabled),
        modirpayamak_sms_price_per_unit: Number(d.modirpayamak_sms_price_per_unit ?? 500),
        modirpayamak_sms_tax_percent: Number(d.modirpayamak_sms_tax_percent ?? 10),
        modirpayamak_sms_surcharge_rial: Number(d.modirpayamak_sms_surcharge_rial ?? 40),
        modirpayamak_reseller_credit_alert: Number(d.modirpayamak_reseller_credit_alert ?? 100000),
      })
    }
    setLoading(false)
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const save = async () => {
    setSaving(true)
    const res = await saveSettings("modirpayamak", {
      modirpayamak_api_key: form.modirpayamak_api_key,
      modirpayamak_default_from: form.modirpayamak_default_from,
      modirpayamak_enabled: form.modirpayamak_enabled ? "1" : "0",
      modirpayamak_sms_price_per_unit: form.modirpayamak_sms_price_per_unit,
      modirpayamak_sms_tax_percent: form.modirpayamak_sms_tax_percent,
      modirpayamak_sms_surcharge_rial: form.modirpayamak_sms_surcharge_rial,
      modirpayamak_reseller_credit_alert: form.modirpayamak_reseller_credit_alert,
    })
    setSaving(false)
    applyResponse(res, {
      successMessage: res.message ?? t("common.saved"),
      errorFallback: t("pages.modirpayamak.saveError"),
    })
  }

  const testConnection = async () => {
    setTesting(true)
    setCreditBalance(null)
    const res = await edgeMyCredit()
    setTesting(false)
    if (res.ok) {
      const item = (res.data ?? {}) as Record<string, unknown>
      const balance = edgeField(item, "balance", "credit", "amount")
      setCreditBalance(balance)
      setSuccess(t("pages.modirpayamak.connectionOk"))
    } else {
      applyResponse({ success: false, message: res.message }, { errorFallback: t("pages.modirpayamak.connectionFailed") })
    }
  }

  return (
    <CrmPageLayout title={t("pages.modirpayamak.settingsTitle")} {...layoutProps}>
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.settingsTitle")} />

      <Card className="max-w-xl">
        <CardHeader>
          <CardTitle>{t("pages.modirpayamak.settingsTitle")}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <FormSettingsSkeleton cards={1} fieldsPerCard={5} />
          ) : (
            <>
              <label className="flex items-center gap-2">
                <Checkbox
                  checked={form.modirpayamak_enabled}
                  onCheckedChange={(v) => setForm((f) => ({ ...f, modirpayamak_enabled: v === true }))}
                />
                <span>{t("pages.modirpayamak.enabled")}</span>
              </label>
              <div className="space-y-2">
                <Label>{t("pages.modirpayamak.apiKey")}</Label>
                <Input
                  type="password"
                  className="font-mono"
                  value={form.modirpayamak_api_key}
                  onChange={(e) => setForm((f) => ({ ...f, modirpayamak_api_key: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.modirpayamak.fromNumber")}</Label>
                <Input
                  dir="ltr"
                  value={form.modirpayamak_default_from}
                  onChange={(e) => setForm((f) => ({ ...f, modirpayamak_default_from: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.modirpayamak.pricePerUnitFallback")}</Label>
                <Input
                  type="number"
                  value={form.modirpayamak_sms_price_per_unit}
                  onChange={(e) =>
                    setForm((f) => ({
                      ...f,
                      modirpayamak_sms_price_per_unit: parseFloat(e.target.value) || 0,
                    }))
                  }
                />
                <p className="text-xs text-muted-foreground">{t("pages.modirpayamak.pricePerUnitFallbackHint")}</p>
              </div>
              <div className="space-y-2">
                <Label>{t("pages.modirpayamak.taxPercent")}</Label>
                <Input
                  type="number"
                  value={form.modirpayamak_sms_tax_percent}
                  onChange={(e) =>
                    setForm((f) => ({
                      ...f,
                      modirpayamak_sms_tax_percent: parseFloat(e.target.value) || 0,
                    }))
                  }
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.modirpayamak.surchargeRial")}</Label>
                <Input
                  type="number"
                  value={form.modirpayamak_sms_surcharge_rial}
                  onChange={(e) =>
                    setForm((f) => ({
                      ...f,
                      modirpayamak_sms_surcharge_rial: parseFloat(e.target.value) || 0,
                    }))
                  }
                />
                <p className="text-xs text-muted-foreground">{t("pages.modirpayamak.surchargeHint")}</p>
              </div>
              <div className="space-y-2">
                <Label>{t("pages.modirpayamak.creditAlert")}</Label>
                <Input
                  type="number"
                  value={form.modirpayamak_reseller_credit_alert}
                  onChange={(e) =>
                    setForm((f) => ({
                      ...f,
                      modirpayamak_reseller_credit_alert: parseFloat(e.target.value) || 0,
                    }))
                  }
                />
              </div>
              <div className="flex flex-wrap gap-2">
                <Button type="button" onClick={() => void save()} disabled={saving}>
                  {saving ? <Loader2 className="me-2 h-4 w-4 animate-spin" /> : null}
                  {t("common.save")}
                </Button>
                <Button type="button" variant="outline" onClick={() => void testConnection()} disabled={testing}>
                  {testing ? <Loader2 className="me-2 h-4 w-4 animate-spin" /> : null}
                  {t("pages.modirpayamak.testConnection")}
                </Button>
              </div>
              {creditBalance ? (
                <p className="text-sm text-muted-foreground">
                  {t("pages.modirpayamak.resellerCredit")}: {formatNumber(Number(creditBalance) || 0)}
                </p>
              ) : null}
            </>
          )}
        </CardContent>
      </Card>
    </CrmPageLayout>
  )
}
